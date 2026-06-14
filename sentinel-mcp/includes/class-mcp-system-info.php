<?php

namespace SentinelMCP;

/**
 * System Information for MCP Content Manager.
 *
 * Provides comprehensive server, PHP, database, and WordPress
 * diagnostics similar to WooCommerce System Status, but works
 * independently without requiring WooCommerce. When WooCommerce
 * is active, includes WC-specific diagnostics.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 */

defined('ABSPATH') || exit;

if (! class_exists('SentinelMCP\SENTINEL_System_Info')) {

	/**
	 * Provides comprehensive server, PHP, database, and WordPress diagnostics.
	 */
	class SENTINEL_System_Info
	{

		/**
		 * Get system information.
		 *
		 * @param array $input Ability input parameters.
		 * @return array
		 */
		public static function get_info(array $input): array
		{
			$all_sections = array('wordpress', 'server', 'php', 'database', 'theme', 'plugins', 'security', 'constants', 'woocommerce', 'post_type_counts', 'logging');
			$sections     = $input['sections'] ?? $all_sections;

			if (! is_array($sections)) {
				$sections = array($sections);
			}

			$result = array();

			foreach ($sections as $section) {
				$method = 'get_' . sanitize_key($section) . '_info';
				if (method_exists(__CLASS__, $method)) {
					$result[$section] = self::$method();
				}
			}

			return $result;
		}

		/**
		 * WordPress environment info.
		 *
		 * @return array
		 */
		private static function get_wordpress_info(): array
		{
			global $wp_version;

			$uploads_dir = wp_upload_dir();
			$permalink   = get_option('permalink_structure');

			$data = array(
				'version'             => $wp_version,
				'multisite'           => is_multisite(),
				'site_url'            => site_url(),
				'home_url'            => home_url(),
				'permalink_structure' => $permalink ? $permalink : 'Plain',
				'is_ssl'              => is_ssl(),
				'language'            => get_locale(),
				'timezone'            => wp_timezone_string(),
				'memory_limit'        => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'Not defined',
				'max_memory_limit'    => defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : 'Not defined',
				'debug_mode'          => defined('WP_DEBUG') && WP_DEBUG,
				'debug_log'           => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
				'debug_display'       => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
				'cron_enabled'        => ! (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
				'object_cache'        => wp_using_ext_object_cache() ? 'External (persistent)' : 'Default (non-persistent)',
				'uploads_dir'         => $uploads_dir['basedir'],
				'uploads_writable'    => wp_is_writable($uploads_dir['basedir']),
				'wp_content_writable' => wp_is_writable(dirname($uploads_dir['basedir'])),
				'environment_type'    => function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'Not available',
			);

			return $data;
		}

		/**
		 * Server environment info.
		 *
		 * @return array
		 */
		private static function get_server_info(): array
		{
			$data = array(
				'software'                  => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown',
				'os'                        => php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m'),
				'architecture'              => PHP_INT_SIZE === 8 ? '64-bit' : '32-bit',
				'hostname'                  => php_uname('n'),
				'document_root'             => isset($_SERVER['DOCUMENT_ROOT']) ? sanitize_text_field(wp_unslash($_SERVER['DOCUMENT_ROOT'])) : 'Unknown',
				'max_upload_size'           => size_format(wp_max_upload_size()),
				'max_post_size'             => ini_get('post_max_size'),
				'max_input_vars'            => (int) ini_get('max_input_vars'),
				'max_execution_time'        => (int) ini_get('max_execution_time'),
				'default_timezone'          => date_default_timezone_get(),
				'curl_version'              => function_exists('curl_version') ? curl_version()['version'] : 'Not available',
				'curl_ssl_version'          => function_exists('curl_version') ? curl_version()['ssl_version'] : 'Not available',
				'fsockopen_or_curl_enabled' => function_exists('fsockopen') || function_exists('curl_init'),
				'soapclient_enabled'        => class_exists('SoapClient'),
				'domdocument_enabled'       => class_exists('DOMDocument'),
				'gzip_enabled'              => is_callable('gzopen'),
				'mbstring_enabled'          => extension_loaded('mbstring'),
			);

			// Remote connectivity tests.
			$data['remote_post'] = self::test_remote_post();
			$data['remote_get']  = self::test_remote_get();

			return $data;
		}

		/**
		 * PHP environment info.
		 *
		 * @return array
		 */
		private static function get_php_info(): array
		{
			$important_extensions = array(
				'curl',
				'gd',
				'imagick',
				'intl',
				'mbstring',
				'openssl',
				'zip',
				'zlib',
				'json',
				'xml',
				'dom',
				'simplexml',
				'fileinfo',
				'exif',
				'sodium',
				'bcmath',
				'iconv',
				'soap',
			);

			$loaded   = get_loaded_extensions();
			$ext_info = array();
			foreach ($important_extensions as $ext) {
				$ext_info[$ext] = in_array($ext, $loaded, true);
			}

			return array(
				'version'             => phpversion(),
				'sapi'                => php_sapi_name(),
				'memory_limit'        => ini_get('memory_limit'),
				'upload_max_filesize' => ini_get('upload_max_filesize'),
				'opcache_enabled'     => self::is_opcache_enabled(),
				'suhosin_installed'   => extension_loaded('suhosin'),
				'extensions'          => $ext_info,
				'total_extensions'    => count($loaded),
			);
		}

		/**
		 * Database info.
		 *
		 * @return array
		 */
		private static function get_database_info(): array
		{
			global $wpdb;

			$server_info = $wpdb->db_server_info();
			$is_mariadb  = stripos($server_info, 'mariadb') !== false;

			// Total tables and DB size.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL command, cannot be prepared or cached.
			$tables       = $wpdb->get_results('SHOW TABLE STATUS', ARRAY_A);
			$total_size   = 0;
			$total_tables = 0;
			$table_list   = array();
			if (is_array($tables)) {
				$total_tables = count($tables);
				foreach ($tables as $table) {
					$data_len    = (int) ($table['Data_length'] ?? 0);
					$index_len   = (int) ($table['Index_length'] ?? 0);
					$total_size += $data_len + $index_len;

					$table_list[$table['Name']] = array(
						'data'   => round($data_len / 1048576, 2),
						'index'  => round($index_len / 1048576, 2),
						'rows'   => (int) ($table['Rows'] ?? 0),
						'engine' => $table['Engine'] ?? 'Unknown',
					);
				}
			}

			// Autoloaded options size (WP 6.6+ changed 'yes' to 'on'/'auto-on').
			$autoload_values = function_exists('wp_autoload_values_to_autoload')
				? wp_autoload_values_to_autoload()
				: array('yes', 'on', 'auto-on');
			$al_placeholders = implode(',', array_fill(0, count($autoload_values), '%s'));

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate query, no WP API equivalent.
			$autoloaded_size = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN() placeholders, all values passed through prepare().
					"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ({$al_placeholders})",
					...$autoload_values
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate query, no WP API equivalent.
			$autoloaded_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN() placeholders, all values passed through prepare().
					"SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload IN ({$al_placeholders})",
					...$autoload_values
				)
			);

			$result = array(
				'type'                     => $is_mariadb ? 'MariaDB' : 'MySQL',
				'version'                  => $wpdb->db_version(),
				'server_info'              => $server_info,
				'charset'                  => $wpdb->charset,
				'collation'                => $wpdb->collate,
				'prefix'                   => $wpdb->prefix,
				'total_tables'             => $total_tables,
				'total_db_size'            => size_format($total_size),
				'total_db_size_bytes'      => $total_size,
				'autoloaded_options_size'  => size_format($autoloaded_size),
				'autoloaded_options_count' => $autoloaded_count,
				'autoloaded_warning'       => $autoloaded_size > 1048576,
				'database_tables'          => $table_list,
			);

			// WooCommerce database version if available.
			if (class_exists('WooCommerce')) {
				$result['wc_database_version'] = get_option('woocommerce_db_version', '');
			}

			return $result;
		}

		/**
		 * Active theme info.
		 *
		 * @return array
		 */
		private static function get_theme_info(): array
		{
			$theme        = wp_get_theme();
			$parent_theme = $theme->parent();

			$data = array(
				'name'           => $theme->get('Name'),
				'version'        => $theme->get('Version'),
				'author'         => $theme->get('Author'),
				'author_url'     => $theme->get('AuthorURI'),
				'template'       => $theme->get_template(),
				'stylesheet'     => $theme->get_stylesheet(),
				'is_child_theme' => (bool) $parent_theme,
				'parent_theme'   => $parent_theme ? $parent_theme->get('Name') : null,
				'is_block_theme' => $theme->is_block_theme(),
			);

			// WooCommerce theme support.
			if (class_exists('WooCommerce')) {
				$data['has_woocommerce_support'] = current_theme_supports('woocommerce');
				$data['has_woocommerce_file']    = file_exists(get_template_directory() . '/woocommerce.php');
				$data['wc_template_overrides']   = self::get_wc_template_overrides();
			}

			// Parent theme details.
			if ($parent_theme) {
				$data['parent_version']    = $parent_theme->get('Version');
				$data['parent_author_url'] = $parent_theme->get('AuthorURI');
			}

			// Latest version available (from WordPress.org).
			$updates = get_site_transient('update_themes');
			if (isset($updates->response[$theme->get_stylesheet()])) {
				$data['version_latest']   = $updates->response[$theme->get_stylesheet()]['new_version'];
				$data['update_available'] = true;
			} else {
				$data['version_latest']   = $theme->get('Version');
				$data['update_available'] = false;
			}

			return $data;
		}

		/**
		 * Plugins info.
		 *
		 * @return array
		 */
		private static function get_plugins_info(): array
		{
			if (! function_exists('get_plugins')) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$all_plugins    = get_plugins();
			$active_plugins = get_option('active_plugins', array());
			$mu_plugins     = get_mu_plugins();
			$updates        = get_site_transient('update_plugins');

			$active_list   = array();
			$inactive_list = array();

			foreach ($all_plugins as $plugin_path => $p) {
				$info = array(
					'name'              => $p['Name'],
					'version'           => $p['Version'],
					'author'            => wp_strip_all_tags($p['Author']),
					'author_url'        => $p['AuthorURI'] ?? '',
					'plugin_url'        => $p['PluginURI'] ?? '',
					'network_activated' => is_multisite() && is_plugin_active_for_network($plugin_path),
				);

				// Check for available updates.
				if (isset($updates->response[$plugin_path])) {
					$info['version_latest']   = $updates->response[$plugin_path]->new_version;
					$info['update_available'] = true;
				} else {
					$info['version_latest']   = $p['Version'];
					$info['update_available'] = false;
				}

				if (in_array($plugin_path, $active_plugins, true)) {
					$active_list[] = $info;
				} else {
					$inactive_list[] = $info;
				}
			}

			$mu_list = array();
			foreach ($mu_plugins as $path => $p) {
				$mu_list[] = array(
					'name'    => $p['Name'],
					'version' => $p['Version'],
					'author'  => wp_strip_all_tags($p['Author'] ?? ''),
				);
			}

			// WordPress dropins.
			$dropins      = get_dropins();
			$dropin_list  = array();
			$dropin_descs = _get_dropins();
			foreach ($dropins as $file => $p) {
				$dropin_list[] = array(
					'file'        => $file,
					'name'        => ! empty($p['Name']) ? $p['Name'] : $file,
					'description' => isset($dropin_descs[$file]) ? $dropin_descs[$file][0] : '',
				);
			}

			return array(
				'active_count'     => count($active_list),
				'inactive_count'   => count($inactive_list),
				'must_use_count'   => count($mu_list),
				'dropin_count'     => count($dropin_list),
				'active_plugins'   => $active_list,
				'inactive_plugins' => $inactive_list,
				'mu_plugins'       => $mu_list,
				'dropins'          => $dropin_list,
			);
		}

		/**
		 * Security settings info.
		 *
		 * @return array
		 */
		private static function get_security_info(): array
		{
			return array(
				'secure_connection'     => is_ssl(),
				'hide_errors'           => ! (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY),
				'file_editing_disabled' => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT,
				'file_mods_disabled'    => defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS,
				'force_ssl_admin'       => defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN,
				'auth_keys_defined'     => defined('SECURE_AUTH_KEY') && '' !== SECURE_AUTH_KEY
					&& defined('AUTH_KEY') && '' !== AUTH_KEY
					&& defined('LOGGED_IN_KEY') && '' !== LOGGED_IN_KEY
					&& defined('NONCE_KEY') && '' !== NONCE_KEY,
			);
		}

		/**
		 * WordPress constants info.
		 *
		 * @return array
		 */
		private static function get_constants_info(): array
		{
			$constants = array(
				'WP_DEBUG',
				'WP_DEBUG_LOG',
				'WP_DEBUG_DISPLAY',
				'SCRIPT_DEBUG',
				'WP_CACHE',
				'DISABLE_WP_CRON',
				'WP_CRON_LOCK_TIMEOUT',
				'AUTOSAVE_INTERVAL',
				'WP_POST_REVISIONS',
				'EMPTY_TRASH_DAYS',
				'WP_MEMORY_LIMIT',
				'WP_MAX_MEMORY_LIMIT',
				'DISALLOW_FILE_EDIT',
				'DISALLOW_FILE_MODS',
				'FORCE_SSL_ADMIN',
				'WP_AUTO_UPDATE_CORE',
				'CONCATENATE_SCRIPTS',
				'WP_CONTENT_DIR',
				'WP_PLUGIN_DIR',
				'WPMU_PLUGIN_DIR',
				'ABSPATH',
			);

			$result = array();
			foreach ($constants as $const) {
				$result[$const] = defined($const) ? constant($const) : 'Not defined';
			}

			return $result;
		}

		/**
		 * WooCommerce-specific info. Only available when WooCommerce is active.
		 *
		 * @return array
		 */
		private static function get_woocommerce_info(): array
		{
			if (! class_exists('WooCommerce')) {
				return array(
					'active' => false,
					'note'   => 'WooCommerce is not installed or not active.',
				);
			}

			$data = array(
				'active'  => true,
				'version' => WC()->version,
			);

			// Store settings.
			$data['store'] = array(
				'currency'           => get_woocommerce_currency(),
				'currency_symbol'    => get_woocommerce_currency_symbol(),
				'currency_position'  => get_option('woocommerce_currency_pos', 'left'),
				'thousand_separator' => wc_get_price_thousand_separator(),
				'decimal_separator'  => wc_get_price_decimal_separator(),
				'number_of_decimals' => wc_get_price_decimals(),
				'store_address'      => get_option('woocommerce_store_address', ''),
				'store_city'         => get_option('woocommerce_store_city', ''),
				'default_country'    => get_option('woocommerce_default_country', ''),
				'store_postcode'     => get_option('woocommerce_store_postcode', ''),
				'tax_enabled'        => wc_tax_enabled(),
				'shipping_enabled'   => wc_shipping_enabled(),
				'coupons_enabled'    => wc_coupons_enabled(),
			);

			// HPOS (High-Performance Order Storage).
			if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
				$data['hpos_enabled'] = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			}

			// WC database version.
			$data['db_version'] = get_option('woocommerce_db_version', '');

			// Payment gateways.
			$gateways = WC()->payment_gateways()->payment_gateways();
			$gw_list  = array();
			foreach ($gateways as $gw) {
				if ('yes' === $gw->enabled) {
					$gw_list[] = array(
						'id'    => $gw->id,
						'title' => $gw->get_title(),
					);
				}
			}
			$data['active_payment_gateways'] = $gw_list;

			// Shipping methods.
			$shipping_methods = WC()->shipping()->get_shipping_methods();
			$sm_list          = array();
			foreach ($shipping_methods as $sm) {
				if ('yes' === ($sm->enabled ?? '') || (method_exists($sm, 'is_enabled') && $sm->is_enabled())) {
					$sm_list[] = array(
						'id'    => $sm->id,
						'title' => $sm->get_method_title(),
					);
				}
			}
			$data['active_shipping_methods'] = $sm_list;

			// WooCommerce pages.
			$wc_pages = array(
				'shop'      => array(
					'option' => 'woocommerce_shop_page_id',
					'label'  => 'Shop base',
				),
				'cart'      => array(
					'option' => 'woocommerce_cart_page_id',
					'label'  => 'Cart',
				),
				'checkout'  => array(
					'option' => 'woocommerce_checkout_page_id',
					'label'  => 'Checkout',
				),
				'myaccount' => array(
					'option' => 'woocommerce_myaccount_page_id',
					'label'  => 'My account',
				),
				'terms'     => array(
					'option' => 'woocommerce_terms_page_id',
					'label'  => 'Terms and conditions',
				),
			);

			$pages_data = array();
			foreach ($wc_pages as $key => $page_config) {
				$page_id   = (int) get_option($page_config['option'], 0);
				$page_info = array(
					'page_name'    => $page_config['label'],
					'page_id'      => $page_id,
					'page_set'     => $page_id > 0,
					'page_exists'  => false,
					'page_visible' => false,
				);

				if ($page_id > 0) {
					$page = get_post($page_id);
					if ($page) {
						$page_info['page_exists']  = true;
						$page_info['page_visible'] = 'publish' === $page->post_status;
					}
				}

				$pages_data[$key] = $page_info;
			}
			$data['pages'] = $pages_data;

			// Enabled features (WC 8.0+).
			if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
				$feature_list = array();
				$features     = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_features(true);
				foreach ($features as $slug => $feature) {
					$feature_list[$slug] = $feature['is_enabled'] ?? false;
				}
				$data['enabled_features'] = $feature_list;
			}

			// Geolocation.
			$data['geolocation_enabled'] = 'geolocation' === get_option('woocommerce_default_customer_address')
				|| 'geolocation_ajax' === get_option('woocommerce_default_customer_address');

			// Logging.
			$data['log_directory'] = WC_LOG_DIR;
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Read-only check for diagnostics, WP_Filesystem not needed.
			$data['log_directory_writable'] = is_writable(WC_LOG_DIR);

			return $data;
		}

		/**
		 * Post type counts.
		 *
		 * @return array
		 */
		private static function get_post_type_counts_info(): array
		{
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate count grouped by type, no WP API equivalent.
			$results = $wpdb->get_results(
				"SELECT post_type, COUNT(*) AS count FROM {$wpdb->posts} GROUP BY post_type ORDER BY count DESC",
				ARRAY_A
			);

			$counts = array();
			foreach ($results as $row) {
				$counts[$row['post_type']] = (int) $row['count'];
			}

			return $counts;
		}

		/**
		 * Logging info.
		 *
		 * @return array
		 */
		private static function get_logging_info(): array
		{
			$data = array(
				'wp_debug'     => defined('WP_DEBUG') && WP_DEBUG,
				'wp_debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
			);

			// WP debug log location.
			$content_base = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
			if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG)) {
				$data['wp_debug_log_path'] = WP_DEBUG_LOG;
			} elseif (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				$data['wp_debug_log_path'] = $content_base . '/debug.log';
			}

			// Debug log file size.
			$log_path = $data['wp_debug_log_path'] ?? ($content_base . '/debug.log');
			if (file_exists($log_path)) {
				$data['debug_log_size'] = size_format(filesize($log_path));
			}

			// WooCommerce logging.
			if (defined('WC_LOG_DIR') && is_dir(WC_LOG_DIR)) {
				$data['wc_log_directory'] = WC_LOG_DIR;
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Read-only check for diagnostics, WP_Filesystem not needed.
				$data['wc_log_writable'] = is_writable(WC_LOG_DIR);

				// Calculate log directory size.
				$log_size = 0;
				$files    = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator(WC_LOG_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
				);
				foreach ($files as $file) {
					if ($file->isFile()) {
						$log_size += $file->getSize();
					}
				}
				$data['wc_log_directory_size'] = size_format($log_size);
			}

			return $data;
		}

		/**
		 * Test remote POST to WordPress.org.
		 *
		 * @return array
		 */
		private static function test_remote_post(): array
		{
			$response = wp_safe_remote_post(
				'https://api.wordpress.org/core/version-check/1.7/',
				array(
					'timeout' => 10,
					'body'    => array(
						'version' => get_bloginfo('version'),
					),
				)
			);

			if (is_wp_error($response)) {
				return array(
					'successful' => false,
					'error'      => $response->get_error_message(),
				);
			}

			return array(
				'successful'    => true,
				'response_code' => wp_remote_retrieve_response_code($response),
			);
		}

		/**
		 * Test remote GET to WordPress.org.
		 *
		 * @return array
		 */
		private static function test_remote_get(): array
		{
			$response = wp_safe_remote_get(
				'https://api.wordpress.org/core/version-check/1.7/',
				array('timeout' => 10)
			);

			if (is_wp_error($response)) {
				return array(
					'successful' => false,
					'error'      => $response->get_error_message(),
				);
			}

			return array(
				'successful'    => true,
				'response_code' => wp_remote_retrieve_response_code($response),
			);
		}

		/**
		 * Get WooCommerce template overrides in the active theme.
		 *
		 * @return array
		 */
		private static function get_wc_template_overrides(): array
		{
			if (! function_exists('WC')) {
				return array();
			}

			$template_path = WC()->plugin_path() . '/templates/';
			$theme_root    = get_stylesheet_directory() . '/woocommerce/';
			$parent_root   = get_template_directory() . '/woocommerce/';
			$overrides     = array();
			$has_outdated  = false;

			// Check child theme overrides.
			if (is_dir($theme_root)) {
				$overrides = self::scan_template_overrides($theme_root, $template_path, 'child');
			}

			// Check parent theme overrides (only if different from child).
			if (is_child_theme() && is_dir($parent_root)) {
				$parent_overrides = self::scan_template_overrides($parent_root, $template_path, 'parent');
				$overrides        = array_merge($overrides, $parent_overrides);
			}

			foreach ($overrides as $override) {
				if (! empty($override['outdated'])) {
					$has_outdated = true;
					break;
				}
			}

			return array(
				'overrides'       => $overrides,
				'has_outdated'    => $has_outdated,
				'total_overrides' => count($overrides),
			);
		}

		/**
		 * Scan a directory for WooCommerce template overrides.
		 *
		 * @param string $theme_dir    Theme templates directory.
		 * @param string $template_dir WooCommerce templates directory.
		 * @param string $source       Source label (child/parent).
		 * @return array
		 */
		private static function scan_template_overrides(string $theme_dir, string $template_dir, string $source): array
		{
			$overrides = array();

			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($theme_dir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ($files as $file) {
				if (! $file->isFile() || 'php' !== pathinfo($file->getFilename(), PATHINFO_EXTENSION)) {
					continue;
				}

				$relative  = str_replace($theme_dir, '', $file->getPathname());
				$core_file = $template_dir . $relative;
				$override  = array(
					'file'   => 'woocommerce/' . $relative,
					'source' => $source,
				);

				// Compare versions if core file exists.
				if (file_exists($core_file)) {
					$theme_version = self::get_template_version($file->getPathname());
					$core_version  = self::get_template_version($core_file);

					$override['version']      = $theme_version;
					$override['core_version'] = $core_version;
					$override['outdated']     = $theme_version && $core_version && version_compare($theme_version, $core_version, '<');
				}

				$overrides[] = $override;
			}

			return $overrides;
		}

		/**
		 * Extract @version tag from a template file header.
		 *
		 * @param string $file File path.
		 * @return string Version string or empty.
		 */
		private static function get_template_version(string $file): string
		{
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading first 8KB of local file for version tag extraction.
			$content = file_get_contents($file, false, null, 0, 8192);
			if ($content && preg_match('/@version\s+(\d+\.\d+(\.\d+)?)/', $content, $matches)) {
				return $matches[1];
			}
			return '';
		}

		/**
		 * Check if OPcache is enabled without triggering warnings.
		 *
		 * @return bool
		 */
		private static function is_opcache_enabled(): bool
		{
			if (! function_exists('opcache_get_status')) {
				return false;
			}
			if (! ini_get('opcache.enable')) {
				return false;
			}
			$status = opcache_get_status(false);
			return is_array($status) && ! empty($status['opcache_enabled']);
		}
	}
}
