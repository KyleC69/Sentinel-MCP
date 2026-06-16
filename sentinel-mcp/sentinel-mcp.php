<?php

declare(strict_types=1);

/**
 * Plugin Name: Sentinel-MCP
 * Plugin URI:  https://wordpress.org/plugins/sentinel-mcp/
 * Description: Universal content manager via MCP. Auto-discovers all CPTs, taxonomies and custom fields. Create, edit, search and manage any content from Claude, ChatGPT, Copilot or any MCP client.
 * Version:     2.0.2
 * Author:      Kyle L Crowder
 * Author URI:  https://github.com/KyleC69
 * License:     GPL-2.0-or-later
 * Text Domain: sentinel-mcp
 * Requires at least: 7.0
 * Requires PHP: 8.3
 *
 * NOTE: Changes have been made to vendor HTTP Transport file to add SSE transport
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
 * Constants.
 */
if (! defined('SENTINEL_VERSION')) {
	define('SENTINEL_VERSION', '2.0.2');
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
	 * @param mixed $message Message to log.
	 * @return void
	 */
	function mcpcomal_debug_log($message): void
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
 * Load core classes.
 */
require_once SENTINEL_PATH . 'includes/helpers.php';

/**
 * Load admin tab base and tabs.
 */
require_once SENTINEL_PATH . 'includes/admin/class-mcp-admin-tab.php';
require_once SENTINEL_PATH . 'includes/admin/class-mcp-admin-tab-getstarted.php';
require_once SENTINEL_PATH . 'includes/admin/class-mcp-admin-tab-status.php';
require_once SENTINEL_PATH . 'includes/admin/class-mcp-admin-tab-providers.php';
require_once SENTINEL_PATH . 'includes/admin/class-mcp-admin-tab-connect.php';
require_once SENTINEL_PATH . 'includes/admin/class-mcp-admin-tab-prompts.php';
require_once SENTINEL_PATH . 'includes/admin/class-mcp-admin-tab-settings.php';
require_once SENTINEL_PATH . 'includes/admin/class-mcp-admin-tab-oauth.php';
require_once SENTINEL_PATH . 'includes/admin/class-mcp-admin-tab-activity.php';
require_once SENTINEL_PATH . 'includes/admin/class-mcp-admin-tab-info.php';

require_once SENTINEL_PATH . 'includes/Abilities/Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Registry.php';
require_once SENTINEL_PATH . 'includes/Abilities/Discovery/Site_Schema_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Discovery/Inspect_Post_Type_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Discovery/List_Terms_Ability.php';

require_once SENTINEL_PATH . 'includes/Abilities/Content/Create_Content_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Content/Read_Content_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Content/Update_Content_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Content/Search_Content_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Content/Delete_Content_Ability.php';

require_once SENTINEL_PATH . 'includes/Abilities/Comments/List_Comments_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Comments/Manage_Comment_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Discovery_Extended/List_Post_Types_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Discovery_Extended/List_Taxonomies_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Discovery_Extended/List_Post_Statuses_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Discovery_Extended/List_Shortcodes_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Discovery_Extended/Get_Permalink_Structure_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/FSE/List_Blocks_Registered_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/FSE/List_Block_Patterns_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/FSE/List_FSE_Templates_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Gutenberg/Gutenberg_Reference_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/I18n/Get_Post_In_Language_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/I18n/List_Languages_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/I18n/List_String_Translations_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/I18n/List_Translations_For_Post_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Images/Generate_Image_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Images/Set_Featured_From_Prompt_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Media/List_Media_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Media/Upload_Media_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Media/Set_Featured_Image_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Media/Delete_Media_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Menus_Widgets/List_Nav_Menus_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Menus_Widgets/List_Widgets_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Menus_Widgets/List_Sidebars_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Options/Get_Option_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Options/List_Options_By_Prefix_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Options/List_Registered_Settings_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Premium/List_Premium_Features_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Recovery/Clear_Recovery_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Recovery/Site_Health_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Recovery/Toggle_Plugin_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Recovery/List_Plugins_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/SEO/Read_SEO_Meta_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Stats/Get_Site_Stats_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Stats/Get_Media_Stats_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/System/List_Cron_Events_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/System/List_User_Roles_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/System_Info/System_Info_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Taxonomy/Create_Term_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Taxonomy/Update_Term_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Taxonomy/Delete_Term_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Theme/Get_Theme_Mod_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Users/List_Users_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Users/Read_User_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/Users/List_User_Meta_Keys_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/WooCommerce/WC_Get_Store_Info_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/WooCommerce/WC_List_Products_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/WooCommerce/WC_List_Recent_Orders_Ability.php';
require_once SENTINEL_PATH . 'includes/Abilities/WooCommerce/WC_List_Coupons_Ability.php';

require_once SENTINEL_PATH . 'includes/class-mcp-admin.php';
require_once SENTINEL_PATH . 'includes/abilities-discovery.php';
require_once SENTINEL_PATH . 'includes/HTML_To_Blocks_Converter.php';
require_once SENTINEL_PATH . 'includes/Logging/Logger.php';
require_once SENTINEL_PATH . 'includes/abilities-universal-crud.php';
require_once SENTINEL_PATH . 'includes/abilities-gutenberg-reference.php';
require_once SENTINEL_PATH . 'includes/class-mcp-file-manager.php';
require_once SENTINEL_PATH . 'includes/abilities-recovery.php';
require_once SENTINEL_PATH . 'includes/abilities-media.php';
require_once SENTINEL_PATH . 'includes/abilities-options.php';
require_once SENTINEL_PATH . 'includes/abilities-comments.php';
require_once SENTINEL_PATH . 'includes/abilities-users.php';
require_once SENTINEL_PATH . 'includes/abilities-taxonomy.php';
require_once SENTINEL_PATH . 'includes/abilities-theme-mods.php';
require_once SENTINEL_PATH . 'includes/abilities-system-info.php';
require_once SENTINEL_PATH . 'includes/abilities-system-extended.php';
require_once SENTINEL_PATH . 'includes/abilities-discovery-extended.php';
require_once SENTINEL_PATH . 'includes/abilities-i18n-read.php';
require_once SENTINEL_PATH . 'includes/abilities-image-generation.php';
require_once SENTINEL_PATH . 'includes/abilities-menus-widgets-read.php';
require_once SENTINEL_PATH . 'includes/abilities-fse-read.php';
require_once SENTINEL_PATH . 'includes/abilities-content-shortcuts.php';
require_once SENTINEL_PATH . 'includes/abilities-stats.php';
require_once SENTINEL_PATH . 'includes/abilities-seo-read.php';
require_once SENTINEL_PATH . 'includes/abilities-wc-read.php';
require_once SENTINEL_PATH . 'includes/abilities-premium-features.php';

require_once SENTINEL_PATH . 'includes/class-mcp-activity-log.php';
require_once SENTINEL_PATH . 'includes/class-mcp-comment-manager.php';
require_once SENTINEL_PATH . 'includes/class-mcp-config-exporter.php';
require_once SENTINEL_PATH . 'includes/class-mcp-health-endpoint.php';
require_once SENTINEL_PATH . 'includes/class-mcp-schema-inspector.php';
require_once SENTINEL_PATH . 'includes/class-mcp-image-generator.php';
require_once SENTINEL_PATH . 'includes/class-mcp-media-manager.php';
require_once SENTINEL_PATH . 'includes/class-mcp-options-manager.php';
require_once SENTINEL_PATH . 'includes/class-mcp-prompt-gallery.php';



/**
 * Chat AI.
 */
require_once SENTINEL_PATH . 'includes/chat/class-mcp-chat-db.php';
require_once SENTINEL_PATH . 'includes/chat/class-mcp-chat-provider-registry.php';
require_once SENTINEL_PATH . 'includes/chat/class-mcp-chat-engine.php';
require_once SENTINEL_PATH . 'includes/chat/class-mcp-admin-chat.php';
require_once SENTINEL_PATH . 'includes/chat/class-mcp-rest-chat.php';

\SentinelMCP\REST_Chat::init();
\SentinelMCP\Admin_Chat::init();

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
				array('back_link' => true)
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
		$ts = wp_next_scheduled('mcpcomal_activity_log_purge');
		if ($ts) {
			wp_unschedule_event($ts, 'mcpcomal_activity_log_purge');
		}
	}
);

add_action('plugins_loaded', array('SentinelMCP\OAuth_DB', 'maybe_upgrade'));
add_action('plugins_loaded', array('SentinelMCP\Chat_DB', 'maybe_upgrade'));
add_action('plugins_loaded', array('SentinelMCP\Activity_Log', 'maybe_upgrade'));

/**
 * Admin.
 */
if (is_admin()) {
	new \SentinelMCP\Admin();
}
