<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Base class for Sentinel-MCP admin tabs.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

	/**
	 * Abstract base for every admin settings tab.
	 *
	 * Subclasses must implement render() and may override
	 * handle_post() to process tab-specific form submissions.
	 */
	abstract class Admin_Tab
	{

		/**
		 * Unique tab slug (e.g. 'settings').
		 *
		 * @var string
		 */
		protected string $slug;

		/**
		 * Human-readable tab label.
		 *
		 * @var string
		 */
		protected string $label;

		/**
		 * Constructor.
		 *
		 * @param string $slug  Tab slug.
		 * @param string $label Tab label.
		 */
		public function __construct(string $slug, string $label)
		{
			$this->slug  = $slug;
			$this->label = $label;
		}

		/**
		 * Get the tab slug.
		 *
		 * @return string
		 */
		public function get_slug(): string
		{
			return $this->slug;
		}

		/**
		 * Get the tab label.
		 *
		 * @return string
		 */
		public function get_label(): string
		{
			return $this->label;
		}

		/**
		 * Render the tab content.
		 *
		 * @return void
		 */
		abstract public function render(): void;

		/**
		 * Handle POST actions specific to this tab.
		 *
		 * Called from Admin::handle_oauth_actions() when the
		 * current tab matches this slug.  Return true to indicate that
		 * a redirect was already performed and no further processing
		 * should happen.
		 *
		 * @return bool True if a redirect was issued.
		 */
		public function handle_post(): bool
		{
			return false;
		}

		/**
		 * Helper: build a tab URL.
		 *
		 * @param string $tab   Target tab slug.
		 * @param array  $extra Additional query args.
		 * @return string
		 */
		protected function tab_url(string $tab, array $extra = []): string
		{
			$args = array_merge(
				[
					'page' => 'sentinel-settings',
					'tab'  => $tab,
				],
				$extra
			);
			return add_query_arg($args, admin_url('options-general.php'));
		}

		/**
		 * Helper: store a flash notice and redirect to a tab.
		 *
		 * @param string $tab     Target tab slug.
		 * @param string $type    Notice type (success, error, warning, info).
		 * @param string $message Notice message.
		 * @return void
		 */
		protected function redirect_with_notice(string $tab, string $type, string $message): void
		{
			set_transient(
				'mcpcomal_admin_notice_' . get_current_user_id(),
				[
					'type'    => $type,
					'message' => $message,
				],
				30
			);
			wp_safe_redirect($this->tab_url($tab));
			exit;
		}
	}

