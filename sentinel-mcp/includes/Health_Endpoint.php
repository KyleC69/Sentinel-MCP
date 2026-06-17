<?php


declare(strict_types=1);

namespace SentinelMCP;

/**
 * Public /health REST endpoint (Sprint 3.3).
 *
 * Unauthenticated, read-only health probe for monitoring tools (Pingdom,
 * UptimeRobot, n8n, Make). Exposes only non-sensitive operational data.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Registers /sentinel/v1/health.
 */
class Health_Endpoint
{

	/**
	 * Wire up the rest_api_init hook.
	 */
	public static function init(): void
	{
		add_action('rest_api_init', array(__CLASS__, 'register_routes'));
	}

	/**
	 * Register the /health route.
	 */
	public static function register_routes(): void
	{
		register_rest_route(
			'sentinel/v1',
			'/health',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array(__CLASS__, 'handle'),
			)
		);
	}

	/**
	 * Build the health payload.
	 */
	public static function handle(): \WP_REST_Response
	{
		global $wp_version, $wpdb;

		$mcp_loaded = class_exists('\WP\MCP\Core\McpAdapter');

		$oauth_tables_present = false;
		if (isset($wpdb)) {
			$table = $wpdb->prefix . 'sentinel_oauth_clients';
			$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$oauth_tables_present = ($found === $table);
		}

		$abilities = 0;
		if (function_exists('wp_get_abilities')) {
			$all = wp_get_abilities();
			if (is_array($all)) {
				$abilities = count(
					array_filter(
						array_keys($all),
						function ($slug) {
							return 0 === strpos((string) $slug, 'sentinel/');
						}
					)
				);
			}
		}

		return new \WP_REST_Response(
			array(
				'status'               => 'ok',
				'plugin_version'       => defined('SENTINEL_VERSION') ? SENTINEL_VERSION : '',
				'wp_version'           => (string) $wp_version,
				'php_version'          => PHP_VERSION,
				'mcp_adapter_loaded'   => $mcp_loaded,
				'oauth_tables_present' => $oauth_tables_present,
				'abilities_registered' => $abilities,
				'timestamp'            => time(),
			)
		);
	}
}
