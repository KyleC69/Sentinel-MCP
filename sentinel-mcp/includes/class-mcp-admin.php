<?php

declare(strict_types=1);

namespace SentinelMCP;

use WordPress\AiClient\AiClient;

/**
 * Admin page orchestrator.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

if (! class_exists('SentinelMCP\SENTINEL_Admin')) {

	/**
	 * Admin settings page for Sentinel-MCP.
	 *
	 * Delegates tab rendering and POST handling to SENTINEL_Admin_Tab subclasses.
	 */
	class SENTINEL_Admin
	{

		/**
		 * Registered tab instances, keyed by slug.
		 *
		 * @var array<string, SENTINEL_Admin_Tab>
		 */
		private array $tabs = [];

		/**
		 * Constructor. Registers admin hooks and tab classes.
		 */
		public function __construct()
		{
			add_action('admin_menu', [$this, 'add_menu']);
			add_action('admin_init', [$this, 'register_settings']);
			add_action('admin_init', [$this, 'handle_post_actions']);

			mcpcomal_debug_log('SENTINEL_Admin instantiated');

			$this->register_tabs();
		}

		/**
		 * Register all tab subclasses.
		 *
		 * @return void
		 */
		private function register_tabs(): void
		{
			$tab_classes = [
				'\SentinelMCP\SENTINEL_Admin_Tab_GetStarted',
				'\SentinelMCP\SENTINEL_Admin_Tab_Status',
				'\SentinelMCP\SENTINEL_Admin_Tab_Providers',
				'\SentinelMCP\SENTINEL_Admin_Tab_Connect',
				'\SentinelMCP\SENTINEL_Admin_Tab_Prompts',
				'\SentinelMCP\SENTINEL_Admin_Tab_Settings',
				'\SentinelMCP\SENTINEL_Admin_Tab_OAuth',
				'\SentinelMCP\SENTINEL_Admin_Tab_Activity',
				'\SentinelMCP\SENTINEL_Admin_Tab_Info',
				'\SentinelMCP\SENTINEL_Admin_Tab_Premium',
			];

			foreach ($tab_classes as $class) {
				if (! class_exists($class)) {
					mcpcomal_debug_log('SENTINEL_Admin::register_tabs — class not found: ' . $class);
					continue;
				}
				/** @var SENTINEL_Admin_Tab $instance */
				$instance = new $class();
				$this->tabs[$instance->get_slug()] = $instance;
				mcpcomal_debug_log('SENTINEL_Admin::register_tabs — registered tab: ' . $instance->get_slug());
			}
		}

		/**
		 * Add the settings submenu page under Settings.
		 *
		 * @return void
		 */
		public function add_menu(): void
		{
			mcpcomal_debug_log('SENTINEL_Admin::add_menu called');

			add_options_page(
				'Sentinel-MCP',
				'Sentinel-MCP',
				'manage_options',
				'sentinel-settings',
				[$this, 'render_page']
			);

			// Chat AI page (hidden from menu, accessed via admin bar button).
			add_submenu_page(
				null,
				'Chat AI — Sentinel-MCP',
				'Chat AI',
				'manage_options',
				'sentinel-chat',
				['SentinelMCP\SENTINEL_Admin_Chat', 'render_page']
			);
		}

		/**
		 * Register plugin settings with the Settings API.
		 *
		 * @return void
		 */
		public function register_settings(): void
		{
			// No settings to register currently. Reserved for future use.
		}

		/**
		 * Get available tabs.
		 *
		 * @return array<string, string>
		 */
		private function get_tabs(): array
		{
			$labels = [];
			foreach ($this->tabs as $slug => $tab) {
				$labels[$slug] = $tab->get_label();
			}
			return $labels;
		}

		/**
		 * Get the current tab from the URL.
		 *
		 * @return string
		 */
		private function get_current_tab(): string
		{
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation parameter, not a state-changing action.
			$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'getstarted';
			if (! array_key_exists($tab, $this->tabs)) {
				$tab = 'getstarted';
			}
			return $tab;
		}

		/**
		 * Handle admin POST actions by delegating to the active tab.
		 *
		 * @return void
		 */
		public function handle_post_actions(): void
		{
			if (! current_user_can('manage_options')) {
				return;
			}

			$current_tab = $this->get_current_tab();
			if (isset($this->tabs[$current_tab])) {
				$handled = $this->tabs[$current_tab]->handle_post();
				if ($handled) {
					return;
				}
			}
		}

		/**
		 * Render the admin settings page.
		 *
		 * @return void
		 */
		public function render_page(): void
		{
			if (! current_user_can('manage_options')) {
				return;
			}

			$current_tab = $this->get_current_tab();
			$tabs        = $this->get_tabs();
			?>
			<div class="wrap">
				<h1>Sentinel-MCP</h1>

				<h2 class="nav-tab-wrapper">
					<?php foreach ($tabs as $slug => $label) : ?>
						<?php
						$tab_url = add_query_arg(
							[
								'page' => 'sentinel-settings',
								'tab'  => $slug,
							],
							admin_url('options-general.php')
						);
						?>
						<a href="<?php echo esc_url($tab_url); ?>"
							class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
							<?php echo esc_html($label); ?>
						</a>
					<?php endforeach; ?>
				</h2>

				<?php
				// Flash messages from POST redirects.
				$flash_key = 'mcpcomal_admin_notice_' . get_current_user_id();
				$flash     = get_transient($flash_key);
				if ($flash) {
					delete_transient($flash_key);
					printf(
						'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
						esc_attr($flash['type']),
						esc_html($flash['message'])
					);
				}

				if (isset($this->tabs[$current_tab])) {
					$this->tabs[$current_tab]->render();
				} else {
					$this->tabs['getstarted']->render();
				}
				?>
			</div>
			<?php
		}
	}
}
