<?php

declare(strict_types=1);

/**
 * Plugin Name: Sentinel-MCP
 * Plugin URI:  https://github.com/kylec69/sentinel-mcp/
 * Description: Universal content manager via MCP. Provides a local AI chat window for admins to utilize AI to update sites. MCP server can be utiliized to update sites from any * compatible REST client.
 * Props: Code is adapted from orginal author: **[Get MCP Content Manager Premium](https://plugins.joseconti.com/en/product/sentinel-mcp/)**
 * Props: I have added additional functionality with modern clients and variations in MCP protcol registration. Many clients vary in their routines as standards evolve, SSE transport has been added and compatibility with VS Code and Visual Studio has been added. Overall architecture and design has been updated and streamlined for todays design practices
 * Version:     2.0.0
 * Author:      Kyle L Crowder, Jose Conti
 * Author URI:  https://github.com/KyleC69
 * License:     GPL-2.0-or-later
 * Text Domain: sentinel-mcp
 * Requires at least: 7.0
 * Requires PHP: 8.3
 *
 * NOTE: Changes have been made to vendor HTTP Transport file to add SSE transport for VS Code compatibility.
 *
 * Requires:
 *  - WordPress Abilities API (wordpress/abilities-api)
 *  - WordPress MCP Adapter  (wordpress/mcp-adapter)
 *
 * @package    SENTINEL
 * @author     Kyle Crowder, Jose Conti
 * @copyright  2026 Kyle L Crowder
 * @link       https://github.com/KyleC69/Sentinel-MCP
 * @version	   2.0.0
 */

defined('ABSPATH') || exit;

/**
 * Constants.
 */
if (! defined('SENTINEL_VERSION')) {
	define('SENTINEL_VERSION', '2.0.0');
}
if (! defined('SENTINEL_PATH')) {
	define('SENTINEL_PATH', plugin_dir_path(__FILE__));
}
if (! defined('SENTINEL_URL')) {
	define('SENTINEL_URL', plugin_dir_url(__FILE__));
}
if (! defined('SENTINEL_ITEM_NAME')) {
	define('SENTINEL_ITEM_NAME', 'sentinel-mcp');
}
if (! defined('SENTINEL_PREFIX')) {
	define('SENTINEL_PREFIX', 'sentinel');
}



if (! function_exists('sentinel_debug_log')) {
	/**
	 * Debug logging helper.
	 *
	 * @param mixed $message Message to log.
	 * @return void
	 */
	function sentinel_debug_log($message): void
	{
		if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			if (is_array($message) || is_object($message)) {
				$message = wp_json_encode($message, JSON_PRETTY_PRINT);
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log('[Sentinel-MCP] ' . (string) $message);
		}
	}
}

/**
 * Load vendor autoloader.
 */
if (file_exists(SENTINEL_PATH . 'vendor/autoload.php')) {
	require_once SENTINEL_PATH . 'vendor/autoload.php';
}

/**
 * Load plugin classes and procedural ability registrations.
 *
 * Composer autoload (PSR-4 + classmap + files) handles all class discovery and
 * executes the procedural ability registration files at plugin bootstrap.
 * No manual require_once chain is needed.
 */

/**
 * WordPress MCP Adapter.
 *
 * The adapter is a Composer dependency. Loading its main plugin class registers
 * the default MCP server REST route at /wp-json/mcp/mcp-adapter-default-server.
 */
if (class_exists('\WP\MCP\Plugin')) {
	\WP\MCP\Plugin::instance();
}

/**
 * Chat AI.
 */
\SentinelMCP\REST_Chat::init();
\SentinelMCP\Admin_Chat::init();

/**
 * OAuth 2.1 Server.
 */
\SentinelMCP\OAuth_Server::init();
\SentinelMCP\OAuth_Permissions::init();
\SentinelMCP\Activity_Log::init();
\SentinelMCP\Health_Endpoint::init();

/**
 * Activation.
 */
register_activation_hook(
	__FILE__,
	function () {
		if (version_compare(PHP_VERSION, '8.3', '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__('Sentinel-MCP requires PHP %1$s or higher. Your server is running PHP %2$s. Please upgrade PHP and try again.', 'mcp-sentinel'),
						'8.3',
						PHP_VERSION
					)
				),
				esc_html__('Plugin activation error', 'mcp-sentinel'),
				['back_link' => true]
			);
		}

		// Create OAuth tables.
		\SentinelMCP\OAuth_DB::create_tables();

		// Create Chat AI tables.
		\SentinelMCP\Chat_DB::create_tables();

		// Create Activity Log table.
		\SentinelMCP\Activity_Log::create_table();

		// Create backups directory.
		\SentinelMCP\File_Manager::ensure_backup_dir();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		$ts = wp_next_scheduled('sentinel_activity_log_purge');
		if ($ts) {
			wp_unschedule_event($ts, 'sentinel_activity_log_purge');
		}
	}
);

add_action('plugins_loaded', ['SentinelMCP\OAuth_DB', 'maybe_upgrade']);
add_action('plugins_loaded', ['SentinelMCP\Chat_DB', 'maybe_upgrade']);
add_action('plugins_loaded', ['SentinelMCP\Activity_Log', 'maybe_upgrade']);

/**
 * Admin.
 */
if (is_admin()) {
	new \SentinelMCP\Admin();
}
