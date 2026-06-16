<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Activity Log for MCP calls (Sprint 2.1).
 *
 * Records every MCP tool invocation: client_id, ability slug, status, duration,
 * error code, IP. Retention is fixed at 30 days. Diff/before-after, rollback,
 * full-text search and CSV export are reserved for the Premium edition.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Lightweight, append-only audit log for MCP ability invocations.
 */
class Activity_Log
{

	/**
	 * Schema version. Bumped to trigger dbDelta on plugin upgrade.
	 */
	const SCHEMA_VERSION = '1';

	/**
	 * Maximum days to retain entries.
	 */
	const RETENTION_DAYS = 30;

	/**
	 * Holds the start time of the in-flight tool call, keyed by tool name.
	 *
	 * @var array<string,float>
	 */
	protected static array $in_flight = array();

	/**
	 * Wire up filters and the daily purge cron.
	 */
	public static function init(): void
	{
		add_filter('mcp_adapter_pre_tool_call', array(__CLASS__, 'on_pre_call'), 50, 4);
		add_filter('mcp_adapter_tool_call_result', array(__CLASS__, 'on_call_result'), 50, 5);

		add_action('mcpcomal_activity_log_purge', array(__CLASS__, 'purge'));

		if (! wp_next_scheduled('mcpcomal_activity_log_purge')) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'mcpcomal_activity_log_purge');
		}
	}

	/**
	 * Get the table name (with prefix).
	 */
	public static function table_name(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'mcpcomal_activity_log';
	}

	/**
	 * Create or upgrade the activity_log table.
	 */
	public static function create_table(): void
	{
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				ts DATETIME NOT NULL,
				oauth_client_id VARCHAR(64) NULL,
				user_id BIGINT UNSIGNED NULL,
				ability_slug VARCHAR(191) NOT NULL,
				status VARCHAR(20) NOT NULL,
				duration_ms INT UNSIGNED NULL,
				error_code VARCHAR(64) NULL,
				ip VARCHAR(45) NULL,
				PRIMARY KEY (id),
				KEY ts (ts),
				KEY oauth_client_id (oauth_client_id),
				KEY ability_slug (ability_slug)
			) {$charset};";

		dbDelta($sql);
		update_option('mcpcomal_activity_log_schema', self::SCHEMA_VERSION, false);
	}

	/**
	 * Run dbDelta only if schema version mismatches.
	 */
	public static function maybe_upgrade(): void
	{
		if (get_option('mcpcomal_activity_log_schema') !== self::SCHEMA_VERSION) {
			self::create_table();
		}
	}

	/**
	 * Pre-call filter: record start time and apply allowlist + rate limit gates.
	 *
	 * @param array  $args      Tool args.
	 * @param string $tool_name Tool slug.
	 * @param mixed  $mcp_tool  Tool instance.
	 * @param mixed  $server    Server instance.
	 * @return array|\WP_Error
	 */
	public static function on_pre_call($args, $tool_name, $mcp_tool, $server)
	{
		self::$in_flight[(string) $tool_name] = microtime(true);
		return $args;
	}

	/**
	 * Post-call filter: compute duration and persist the entry.
	 *
	 * @param mixed  $result    Tool result (may be \WP_Error).
	 * @param array  $args      Tool args.
	 * @param string $tool_name Tool slug.
	 * @param mixed  $mcp_tool  Tool instance.
	 * @param mixed  $server    Server instance.
	 * @return mixed
	 */
	public static function on_call_result($result, $args, $tool_name, $mcp_tool, $server)
	{
		$started   = self::$in_flight[(string) $tool_name] ?? null;
		$duration  = null !== $started ? (int) round((microtime(true) - $started) * 1000) : null;
		unset(self::$in_flight[(string) $tool_name]);

		$status     = is_wp_error($result) ? 'error' : 'ok';
		$error_code = is_wp_error($result) ? (string) $result->get_error_code() : null;

		self::record((string) $tool_name, $status, $duration, $error_code);

		return $result;
	}

	/**
	 * Insert a row. Failures are silent: the audit log must never break the call.
	 *
	 * @param string      $ability   Ability slug.
	 * @param string      $status    Status (ok|error|denied|rate_limited).
	 * @param int|null    $duration  Duration in ms.
	 * @param string|null $error_code Optional error code.
	 */
	public static function record(string $ability, string $status, ?int $duration = null, ?string $error_code = null): void
	{
		global $wpdb;

		$client_id = OAuth_Interceptor::get_current_client_id();
		$user_id   = get_current_user_id();
		$ip        = self::current_ip();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::table_name(),
			array(
				'ts'              => current_time('mysql', true),
				'oauth_client_id' => $client_id ? $client_id : null,
				'user_id'         => $user_id ? $user_id : null,
				'ability_slug'    => $ability,
				'status'          => $status,
				'duration_ms'     => $duration,
				'error_code'      => $error_code,
				'ip'              => $ip,
			),
			array('%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s')
		);
	}

	/**
	 * Query log entries with optional filters and pagination.
	 *
	 * @param array $args {
	 *     @type int    $page      Page number (>=1).
	 *     @type int    $per_page  Page size (1-100).
	 *     @type string $client_id Optional client_id filter.
	 *     @type string $status    Optional status filter.
	 *     @type string $ability   Optional ability slug filter.
	 * }
	 * @return array{items:array<int,array<string,mixed>>, total:int, page:int, per_page:int}
	 */
	public static function query(array $args = array()): array
	{
		global $wpdb;

		$page     = isset($args['page']) ? max(1, (int) $args['page']) : 1;
		$per_page = isset($args['per_page']) ? max(1, min(100, (int) $args['per_page'])) : 50;
		$offset   = ($page - 1) * $per_page;

		$where  = array('1=1');
		$params = array();
		if (! empty($args['client_id'])) {
			$where[]  = 'oauth_client_id = %s';
			$params[] = (string) $args['client_id'];
		}
		if (! empty($args['status'])) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}
		if (! empty($args['ability'])) {
			$where[]  = 'ability_slug = %s';
			$params[] = (string) $args['ability'];
		}

		$table     = self::table_name();
		$where_sql = implode(' AND ', $where);

		$total_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) (empty($params)
			? $wpdb->get_var($total_sql) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			: $wpdb->get_var($wpdb->prepare($total_sql, $params))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		$rows_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;
		$rows     = (array) $wpdb->get_results($wpdb->prepare($rows_sql, $params), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items'    => $rows,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Delete entries older than RETENTION_DAYS.
	 */
	public static function purge(): void
	{
		global $wpdb;
		$cutoff = gmdate('Y-m-d H:i:s', time() - self::RETENTION_DAYS * DAY_IN_SECONDS);
		$wpdb->query($wpdb->prepare('DELETE FROM ' . self::table_name() . ' WHERE ts < %s', $cutoff)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get the request remote IP. Returns empty string when not available.
	 */
	protected static function current_ip(): string
	{
		$candidates = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
		foreach ($candidates as $key) {
			if (! empty($_SERVER[$key])) {
				$value = sanitize_text_field(wp_unslash((string) $_SERVER[$key]));
				if (false !== strpos($value, ',')) {
					$value = trim(strtok($value, ','));
				}
				return $value;
			}
		}
		return '';
	}
}
