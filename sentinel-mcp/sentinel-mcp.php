<?php

/**
 * Plugin Name: Sentinel-MCP
 * Plugin URI:  https://wordpress.org/plugins/sentinel-mcp/
 * Description: Universal content manager via MCP. Auto-discovers all CPTs, taxonomies and custom fields. Create, edit, search and manage any content from Claude, ChatGPT, Copilot or any MCP client.
 * Version:     1.1.0
 * Author:      Kyle L Crowder
 * Author URI:  https://github.com/KyleC69
 * License:     GPL-2.0-or-later
 * Text Domain: sentinel-mcp
 * Requires at least: 7.0
 * Requires PHP: 8.3
 *
 * Requires:
 *  - WordPress Abilities API (wordpress/abilities-api)
 *  - WordPress MCP Adapter  (wordpress/mcp-adapter)
 *
 * @package    SENTINEL
 * @author     Kyle Crowder
 * @copyright  2026 Kyle L Crowder
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Coexistence check: if Premium is active, Lite yields completely.
 *
 * WordPress loads plugins alphabetically, so Lite (mcp-sentinel)
 * loads BEFORE Premium (sentinel-mcp). At this point the
 * MCM_IS_PREMIUM constant won't be defined yet, so we check active_plugins directly.
 */
$mcpcomal_active_plugins = (array) get_option('active_plugins', array());
$mcpcomal_premium_files  = array(
	'sentinel-mcp/sentinel-mcp.php',
	'mcp-content-manager-for-wp/mcp-content-manager-for-wp.php',
);
$mcpcomal_premium_active = false;
foreach ($mcpcomal_premium_files as $mcpcomal_premium_file) {
	if (in_array($mcpcomal_premium_file, $mcpcomal_active_plugins, true)) {
		$mcpcomal_premium_active = true;
		break;
	}
}

if ($mcpcomal_premium_active || (defined('MCM_IS_PREMIUM') && MCM_IS_PREMIUM)) {
	unset($mcpcomal_active_plugins, $mcpcomal_premium_files, $mcpcomal_premium_active);
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-info is-dismissible" style="display:flex;align-items:center;gap:10px;padding:12px 16px;">'
					. '<span class="dashicons dashicons-info" style="font-size:24px;color:#2271b1;"></span>'
					. '<p style="margin:0;"><strong>Sentinel-MCP:</strong> %s</p>'
					. '</div>',
				esc_html__('The Premium version is active. You can safely deactivate the Lite version.', 'mcp-sentinel')
			);
		}
	);
}
unset($mcpcomal_active_plugins, $mcpcomal_premium_files, $mcpcomal_premium_active);

/**
 * Constants.
 */
if (! defined('SENTINEL_VERSION')) {
	define('SENTINEL_VERSION', '1.1.0');
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

/**
 * Load translations bundled in /languages/. WordPress.org will also serve
 * Translate.WordPress.org translations on top of these.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain(
			'mcp-sentinel',
			false,
			dirname(plugin_basename(__FILE__)) . '/languages/'
		);
	}
);

if (! function_exists('mcpcomal_debug_log')) {
	/**
	 * Debug logging helper.
	 *
	 * Only writes to error_log when debug logging is enabled in Settings > Sentinel-MCP > Settings.
	 * Uses a static cache to avoid calling get_option() on every invocation.
	 *
	 * @param string $message The debug message (will be prefixed with [SENTINEL-DEBUG]).
	 */
	function mcpcomal_debug_log(string $message): void
	{
		static $enabled = null;

		if (null === $enabled) {
			$enabled = (bool) get_option('mcpcomal_debug_logging', false);
		}

		if ($enabled) {
			error_log('[SENTINEL-DEBUG] ' . $message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

/**
 * Autoloader.
 */
if (file_exists(SENTINEL_PATH . 'vendor/autoload_packages.php')) {
	require_once SENTINEL_PATH . 'vendor/autoload_packages.php';
} elseif (file_exists(SENTINEL_PATH . 'vendor/autoload.php')) {
	require_once SENTINEL_PATH . 'vendor/autoload.php';
}

/**
 * MCP Adapter bootstrap.
 *
 * The plugin bundles WordPress MCP Adapter at vendor/wordpress/mcp-adapter/. The
 * bundled copy is loaded only when no other source has already defined
 * WP_MCP_DIR (e.g. another plugin that bundles the same adapter, an mu-plugin,
 * or the standalone "mcp-adapter" plugin already active). WordPress loads
 * active plugins alphabetically, so an active standalone "mcp-adapter" runs
 * before this file and will have defined WP_MCP_DIR by now; if the constant
 * isn't defined, no adapter is loaded yet and it's safe to load the bundled
 * copy regardless of whether the standalone exists on disk but is inactive.
 */

if (! defined('WP_MCP_DIR')) {
	$_mcp_bundled_adapter = SENTINEL_PATH . 'vendor/wordpress/mcp-adapter/mcp-adapter.php';
	if (file_exists($_mcp_bundled_adapter)) {
		// The bundled adapter ships as a Composer sub-package, so its WP\MCP\*
		// classes are already registered in this plugin's parent autoloader
		// (loaded above). Telling the adapter to skip its own autoloader
		// lookup avoids the "Composer autoloader was not found" admin notice
		// it would otherwise raise when looking for vendor/autoload.php
		// inside the sub-package directory (which doesn't exist).
		if (! defined('WP_MCP_AUTOLOAD')) {
			define('WP_MCP_AUTOLOAD', false);
		}
		require_once $_mcp_bundled_adapter;
	}
	unset($_mcp_bundled_adapter);
}

// Silent guard: if no source loaded the adapter, stop here without notices.
if (! defined('WP_MCP_DIR')) {
	return;
}

/**
 * Includes.
 */
require_once SENTINEL_PATH . 'includes/class-mcp-schema-inspector.php';
require_once SENTINEL_PATH . 'includes/class-mcp-admin.php';
require_once SENTINEL_PATH . 'includes/abilities-discovery.php';
require_once SENTINEL_PATH . 'includes/abilities-universal-crud.php';
require_once SENTINEL_PATH . 'includes/abilities-gutenberg-reference.php';
require_once SENTINEL_PATH . 'includes/class-mcp-file-manager.php';
require_once SENTINEL_PATH . 'includes/abilities-recovery.php';
require_once SENTINEL_PATH . 'includes/class-mcp-comment-manager.php';
require_once SENTINEL_PATH . 'includes/abilities-comments.php';
require_once SENTINEL_PATH . 'includes/class-mcp-system-info.php';
require_once SENTINEL_PATH . 'includes/abilities-system-info.php';
require_once SENTINEL_PATH . 'includes/class-mcp-media-manager.php';
require_once SENTINEL_PATH . 'includes/abilities-media.php';
require_once SENTINEL_PATH . 'includes/abilities-taxonomy.php';
require_once SENTINEL_PATH . 'includes/class-mcp-options-manager.php';
require_once SENTINEL_PATH . 'includes/abilities-options.php';
require_once SENTINEL_PATH . 'includes/abilities-theme-mods.php';
require_once SENTINEL_PATH . 'includes/class-mcp-user-manager.php';
require_once SENTINEL_PATH . 'includes/abilities-users.php';
require_once SENTINEL_PATH . 'includes/class-mcp-premium-hints.php';
require_once SENTINEL_PATH . 'includes/abilities-premium-features.php';

/**
 * Sprint 1.1 — Extended discovery (list post types, taxonomies, statuses, shortcodes, permalinks).
 */
require_once SENTINEL_PATH . 'includes/abilities-discovery-extended.php';

/**
 * Sprint 1.2 — Site stats (post/comment/user/media counts).
 */
require_once SENTINEL_PATH . 'includes/abilities-stats.php';

/**
 * Sprint 1.3 — Content shortcuts (recent/pending/scheduled/trashed/revisions).
 */
require_once SENTINEL_PATH . 'includes/abilities-content-shortcuts.php';

/**
 * Sprint 1.4 — FSE / blocks read-only (block types, patterns, templates metadata).
 */
require_once SENTINEL_PATH . 'includes/abilities-fse-read.php';

/**
 * Sprint 1.5 — Menus, widgets and sidebars (read-only).
 */
require_once SENTINEL_PATH . 'includes/abilities-menus-widgets-read.php';

/**
 * Sprint 1.6 — WooCommerce read-only.
 *
 * Loaded unconditionally; the file's own action callbacks check for the
 * WooCommerce class at the time `wp_abilities_api_init` fires, by which point
 * all plugins (including WooCommerce, which loads after this one alphabetically)
 * are available.
 */
require_once SENTINEL_PATH . 'includes/abilities-wc-read.php';

/**
 * Sprint 1.7 — SEO read universal (Yoast/RankMath/AIOSEO/TSF/SureRank/SEOPress/Slim/Squirrly).
 */
require_once SENTINEL_PATH . 'includes/seo/class-mcp-seo-adapter.php';
require_once SENTINEL_PATH . 'includes/abilities-seo-read.php';

/**
 * Sprint 1.8 — System extended (cron events, user roles).
 */
require_once SENTINEL_PATH . 'includes/abilities-system-extended.php';

/**
 * Sprint 4.1 — i18n read (Polylang, WPML, TranslatePress).
 */
require_once SENTINEL_PATH . 'includes/i18n/class-mcp-i18n-adapter.php';
require_once SENTINEL_PATH . 'includes/i18n/class-mcp-i18n-polylang.php';
require_once SENTINEL_PATH . 'includes/i18n/class-mcp-i18n-wpml.php';
require_once SENTINEL_PATH . 'includes/i18n/class-mcp-i18n-translatepress.php';
require_once SENTINEL_PATH . 'includes/abilities-i18n-read.php';

/**
 * Sprint 4.2 — Prompt gallery.
 */
require_once SENTINEL_PATH . 'includes/class-mcp-prompt-gallery.php';

/**
 * AI image generation (Gemini, Lite minimal).
 */
require_once SENTINEL_PATH . 'includes/class-mcp-image-generator.php';
require_once SENTINEL_PATH . 'includes/abilities-image-generation.php';

/**
 * WordPress 7.0 Connectors API.
 */
require_once SENTINEL_PATH . 'includes/class-mcp-connectors.php';

/**
 * Chat AI.
 */
require_once SENTINEL_PATH . 'includes/chat/class-mcp-chat-db.php';
require_once SENTINEL_PATH . 'includes/chat/class-mcp-chat-engine.php';
require_once SENTINEL_PATH . 'includes/chat/class-mcp-admin-chat.php';
require_once SENTINEL_PATH . 'includes/chat/class-mcp-rest-chat.php';

SENTINEL_REST_Chat::init();
SENTINEL_Admin_Chat::init();

/**
 * OAuth 2.1 Server.
 */
require_once SENTINEL_PATH . 'includes/oauth/class-mcp-oauth-db.php';
require_once SENTINEL_PATH . 'includes/oauth/class-mcp-oauth-server.php';
require_once SENTINEL_PATH . 'includes/oauth/class-mcp-oauth-authorize.php';
require_once SENTINEL_PATH . 'includes/oauth/class-mcp-oauth-token.php';
require_once SENTINEL_PATH . 'includes/oauth/class-mcp-oauth-interceptor.php';
require_once SENTINEL_PATH . 'includes/oauth/class-mcp-oauth-permissions.php';
// New streamlined OAuth manager.
require_once SENTINEL_PATH . 'includes/oauth/class-mcp-oauth-manager.php';

/**
 * Sprint 2.1 — Activity Log for MCP calls.
 */
require_once SENTINEL_PATH . 'includes/class-mcp-activity-log.php';

/**
 * Sprint 3 — Onboarding helpers (config exporter + public /health endpoint).
 */
require_once SENTINEL_PATH . 'includes/class-mcp-config-exporter.php';
require_once SENTINEL_PATH . 'includes/class-mcp-health-endpoint.php';

SENTINEL_OAuth_Server::init();
SENTINEL_OAuth_Permissions::init();
SENTINEL_Activity_Log::init();
SENTINEL_Health_Endpoint::init();

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
				array('back_link' => true)
			);
		}

		// Create OAuth tables.
		SENTINEL_OAuth_DB::create_tables();

		// Create Chat AI tables.
		SENTINEL_Chat_DB::create_tables();

		// Create Activity Log table.
		SENTINEL_Activity_Log::create_table();

		// Create backups directory.
		SENTINEL_File_Manager::ensure_backup_dir();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		$ts = wp_next_scheduled('mcpcomal_activity_log_purge');
		if ($ts) {
			wp_unschedule_event($ts, 'mcpcomal_activity_log_purge');
		}
	}
);

add_action('plugins_loaded', array('SENTINEL_OAuth_DB', 'maybe_upgrade'));
add_action('plugins_loaded', array('SENTINEL_Chat_DB', 'maybe_upgrade'));
add_action('plugins_loaded', array('SENTINEL_Activity_Log', 'maybe_upgrade'));

/**
 * Admin.
 */
if (is_admin()) {
	new SENTINEL_Admin();
}
