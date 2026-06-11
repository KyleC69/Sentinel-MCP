<?php
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

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * Do not delete shared data if the Premium version is still installed.
 * Both Lite and Premium share the same DB prefix (mcpcomal_), so removing
 * tables/options here would break Premium.
 */
$mcpcomal_premium_installed = file_exists( WP_PLUGIN_DIR . '/sentinel-mcp/sentinel-mcp.php' );

if ( $mcpcomal_premium_installed ) {
	return;
}

// OAuth 2.1 tables.
global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- DDL commands for uninstall, intentional schema removal.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcpcomal_oauth_tokens" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcpcomal_oauth_codes" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcpcomal_oauth_clients" );
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

// OAuth options.
delete_option( 'mcpcomal_oauth_db_version' );

// Plugin settings.
delete_option( 'mcpcomal_debug_logging' );

// Backups directory (stored in uploads/sentinel-backups).
$mcpcomal_upload_dir = wp_upload_dir();
$mcpcomal_backup_dir = $mcpcomal_upload_dir['basedir'] . '/sentinel-backups';
if ( is_dir( $mcpcomal_backup_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;

	if ( $wp_filesystem ) {
		$wp_filesystem->delete( $mcpcomal_backup_dir, true );
	}
}
