<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Get Started admin tab.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

	/**
	 * Renders the Get Started onboarding tab.
	 */
	class Admin_Tab_GetStarted extends Admin_Tab
	{

		/**
		 * Constructor.
		 */
		public function __construct()
		{
			parent::__construct('getstarted', __('Get Started', 'mcp-sentinel'));
		}

		/**
		 * Render the tab content.
		 *
		 * @return void
		 */
		public function render(): void
		{
			$checks = [
				[
					'label' => 'PHP &ge; 8.3',
					'pass'  => version_compare(PHP_VERSION, '8.3', '>='),
					'value' => PHP_VERSION,
				],
				[
					'label' => 'WordPress &ge; 6.9',
					'pass'  => version_compare(get_bloginfo('version'), '6.9', '>='),
					'value' => (string) get_bloginfo('version'),
				],
				[
					'label' => 'Application Passwords available',
					'pass'  => class_exists('WP_Application_Passwords') && wp_is_application_passwords_available(),
					'value' => '',
				],
				[
					'label' => 'MCP Adapter loaded',
					'pass'  => class_exists('\WP\MCP\Core\McpAdapter'),
					'value' => '',
				],
				[
					'label' => 'OAuth tables present',
					'pass'  => self::oauth_tables_exist(),
					'value' => '',
				],
			];

			$oauth_url   = $this->tab_url('oauth');
			$connect_url = $this->tab_url('connect');
?>
			<div class="card" style="max-width:800px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Step 1 &mdash; Environment check</h2>
				<table class="widefat striped" style="margin-bottom:0;">
					<tbody>
						<?php foreach ($checks as $check) : ?>
							<tr>
								<td style="width:60%"><?php echo wp_kses_post($check['label']); ?></td>
								<td>
									<?php if ($check['pass']) : ?>
										<span style="color:green;font-weight:bold;">&#10003; OK</span>
									<?php else : ?>
										<span style="color:red;font-weight:bold;">&#10060; Missing</span>
									<?php endif; ?>
									<?php if ($check['value']) : ?>
										<span style="color:#666;">(<?php echo esc_html($check['value']); ?>)</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<td style="width:60%">
								<p>If a check does not pass, check your host requirements and deactivate the plugin and reactivate.</p>
							</td>
						</tr>
						<tr>
							<td style="width:60%">
								<h3>If a check does not pass, check your host requirements and deactivate the plugin and reactivate.</h3>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="card" style="max-width:800px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Step 2 &mdash; Create an OAuth client</h2>
				<p>Each AI client (Claude, Cursor, ChatGPT&hellip;) authenticates with its own OAuth client. Most clients register themselves automatically the first time they connect, so this step is optional.</p>
				<p><a class="button button-primary" href="<?php echo esc_url($oauth_url); ?>">Open Authentication tab</a></p>
			</div>

			<div class="card" style="max-width:800px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Step 3 &mdash; Pick your AI client</h2>
				<p>Use the Connect tab to copy the configuration snippet for your AI tool of choice (Claude Desktop, ChatGPT, Cursor, Windsurf, Continue or JetBrains AI).</p>
				<p><a class="button button-primary" href="<?php echo esc_url($connect_url); ?>">Open Connect tab</a></p>
			</div>

			<div class="card" style="max-width:800px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Step 4 &mdash; Test the connection</h2>
				<p>Once your AI client is connected, ask it: "List my registered MCP abilities" or "Show me my site structure". You should see the 56+ abilities provided by this plugin.</p>
				<p>If something fails, check the <a href="<?php echo esc_url(admin_url('options-general.php?page=sentinel-settings&tab=activity')); ?>">Activity Log</a> for the latest tool calls.</p>
			</div>
<?php
		}

		/**
		 * Quick check whether OAuth tables exist.
		 *
		 * @return bool
		 */
		protected static function oauth_tables_exist(): bool
		{
			global $wpdb;
			$table = $wpdb->prefix . 'sentinel_oauth_clients';
			$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $found === $table;
		}
	}

