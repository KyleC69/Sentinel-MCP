<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Uninstall handler.
 *
 * Cleans up all plugin options from the database
 * when the plugin is deleted via the WordPress admin.
 *
 * @package    SENTINEL
 * @author     Kyle Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

/*
 * Do not delete shared data if the Premium version is still installed.
 * Both Lite and Premium share the same DB prefix (SENTINEL_), so removing
 * tables/options here would break Premium.
 */
$SENTINEL_premium_installed = file_exists(WP_PLUGIN_DIR . '/sentinel-mcp/sentinel-mcp.php');

if ($SENTINEL_premium_installed) {
	return;
}

// OAuth 2.1 tables.
global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- DDL commands for uninstall, intentional schema removal.
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sentinel_oauth_tokens");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sentinel_oauth_codes");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sentinel_oauth_clients");
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

// OAuth options.
delete_option('sentinel_oauth_db_version');

// Plugin settings.
delete_option('sentinel_debug_logging');

// Backups directory (stored in uploads/sentinel-backups).
$SENTINEL_upload_dir = wp_upload_dir();
$SENTINEL_backup_dir = $SENTINEL_upload_dir['basedir'] . '/sentinel-backups';
if (is_dir($SENTINEL_backup_dir)) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;

	if ($wp_filesystem) {
		$wp_filesystem->delete($SENTINEL_backup_dir, true);
	}
}
