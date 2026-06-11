<?php

/**
 * OAuth granular permissions and rate limiting (Sprint 2.2 + 2.3).
 *
 * Two gates that run before tool execution via `mcp_adapter_pre_tool_call`:
 *
 *   1. Allowlist: each OAuth client may have a JSON list of allowed ability slugs.
 *      NULL/empty list means "all abilities".
 *   2. Rate limit: hourly and daily counters per client_id, stored in transients.
 *      Limits configurable via constants and the `mcpcomal_rate_limit_*` filters.
 *
 * @package    SENTINEL
 * @author     José Conti <j.conti@joseconti.com>
 * @copyright  2026 José Conti
 * @license    GPL-2.0-or-later
 * @link       https://mcpwp.com/
 */

defined('ABSPATH') || exit;

if (! defined('MCPCOMAL_RATE_LIMIT_PER_HOUR')) {
	define('MCPCOMAL_RATE_LIMIT_PER_HOUR', 1000);
}
if (! defined('MCPCOMAL_RATE_LIMIT_PER_DAY')) {
	define('MCPCOMAL_RATE_LIMIT_PER_DAY', 10000);
}

if (! class_exists('SENTINEL_OAuth_Permissions')) {

	/**
	 * Pre-execution gates: per-client allowlist and rate limiting.
	 */
	class SENTINEL_OAuth_Permissions
	{

		/**
		 * Wire up the pre-call filter.
		 */
		public static function init(): void
		{
			add_filter('mcp_adapter_pre_tool_call', array(__CLASS__, 'gate'), 5, 4);
		}

		/**
		 * Gate every tool call by allowlist and rate limit.
		 *
		 * Runs at priority 5, before activity log (priority 50). Returning a WP_Error
		 * short-circuits execution per the contract documented in ToolsHandler.php.
		 *
		 * @param array  $args      Tool args.
		 * @param string $tool_name Tool slug.
		 * @param mixed  $mcp_tool  Tool instance.
		 * @param mixed  $server    Server instance.
		 * @return array|WP_Error
		 */
		public static function gate($args, $tool_name, $mcp_tool, $server)
		{
			$client_id = SENTINEL_OAuth_Interceptor::get_current_client_id();

			// No OAuth client (cookie auth, application password) → no allowlist or rate limit applied.
			if ('' === $client_id) {
				return $args;
			}

			// 1. Allowlist.
			if (! self::is_allowed($client_id, (string) $tool_name)) {
				if (class_exists('MCPCOMAL_Activity_Log')) {
					MCPCOMAL_Activity_Log::record((string) $tool_name, 'denied', 0, 'allowlist');
				}
				return new WP_Error(
					'mcpcomal_ability_not_allowed',
					sprintf(
						/* translators: %s: ability slug */
						__('This OAuth client is not authorized to call ability "%s".', 'mcp-sentinel'),
						(string) $tool_name
					),
					array('status' => 403)
				);
			}

			// 2. Rate limit.
			$rate = self::check_rate($client_id);
			if (! $rate['ok']) {
				if (class_exists('MCPCOMAL_Activity_Log')) {
					MCPCOMAL_Activity_Log::record((string) $tool_name, 'rate_limited', 0, $rate['scope']);
				}
				return new WP_Error(
					'rate_limit_exceeded',
					__('Rate limit exceeded.', 'mcp-sentinel'),
					array(
						'status'              => 429,
						'retry_after_seconds' => $rate['retry_after'],
						'scope'               => $rate['scope'],
					)
				);
			}

			return $args;
		}

		/**
		 * Whether the client is allowed to call this ability.
		 *
		 * @param string $client_id    OAuth client_id.
		 * @param string $ability_slug Ability slug (with namespace prefix).
		 */
		public static function is_allowed(string $client_id, string $ability_slug): bool
		{
			$allowed = self::get_allowed_abilities($client_id);
			if (null === $allowed) {
				return true;
			}
			return in_array($ability_slug, $allowed, true);
		}

		/**
		 * Get the allowed abilities list for a client. NULL means "all".
		 *
		 * @param string $client_id OAuth client_id.
		 * @return array<string>|null
		 */
		public static function get_allowed_abilities(string $client_id): ?array
		{
			$client = SENTINEL_OAuth_DB::get_client_by_id($client_id);
			if (! $client || empty($client['allowed_abilities'])) {
				return null;
			}
			$decoded = json_decode((string) $client['allowed_abilities'], true);
			if (! is_array($decoded)) {
				return null;
			}
			return array_values(array_filter(array_map('strval', $decoded)));
		}

		/**
		 * Persist the allowed abilities list for a client.
		 *
		 * @param string             $client_id OAuth client_id.
		 * @param array<string>|null $abilities NULL or empty = all abilities.
		 */
		public static function set_allowed_abilities(string $client_id, ?array $abilities): bool
		{
			global $wpdb;
			$value = (null === $abilities || array() === $abilities)
				? null
				: wp_json_encode(array_values(array_unique(array_map('strval', $abilities))));

			$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prefix . 'mcpcomal_oauth_clients',
				array('allowed_abilities' => $value),
				array('client_id' => $client_id),
				array('%s'),
				array('%s')
			);

			return false !== $result;
		}

		/**
		 * Check rate limit and atomically increment counters when allowed.
		 *
		 * @param string $client_id OAuth client_id.
		 * @return array{ok:bool, scope:string, retry_after:int}
		 */
		public static function check_rate(string $client_id): array
		{
			$per_hour = (int) apply_filters('mcpcomal_rate_limit_per_hour', MCPCOMAL_RATE_LIMIT_PER_HOUR, $client_id);
			$per_day  = (int) apply_filters('mcpcomal_rate_limit_per_day', MCPCOMAL_RATE_LIMIT_PER_DAY, $client_id);

			$key_h = 'mcpcomal_rl_h_' . md5($client_id);
			$key_d = 'mcpcomal_rl_d_' . md5($client_id);

			$count_h = (int) get_transient($key_h);
			$count_d = (int) get_transient($key_d);

			if ($per_hour > 0 && $count_h >= $per_hour) {
				return array(
					'ok'          => false,
					'scope'       => 'hour',
					'retry_after' => HOUR_IN_SECONDS,
				);
			}
			if ($per_day > 0 && $count_d >= $per_day) {
				return array(
					'ok'          => false,
					'scope'       => 'day',
					'retry_after' => DAY_IN_SECONDS,
				);
			}

			set_transient($key_h, $count_h + 1, HOUR_IN_SECONDS);
			set_transient($key_d, $count_d + 1, DAY_IN_SECONDS);

			return array(
				'ok'          => true,
				'scope'       => '',
				'retry_after' => 0,
			);
		}
	}
}
