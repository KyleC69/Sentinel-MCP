<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Status admin tab.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

if (! class_exists('SentinelMCP\SENTINEL_Admin_Tab_Status')) {

	/**
	 * Renders the Status tab: MCP status, OAuth endpoints, detected integrations.
	 */
	class SENTINEL_Admin_Tab_Status extends SENTINEL_Admin_Tab
	{

		/**
		 * Constructor.
		 */
		public function __construct()
		{
			parent::__construct('status', __('Status', 'mcp-sentinel'));
		}

		/**
		 * Render the tab content.
		 *
		 * @return void
		 */
		public function render(): void
		{
			$has_mcp = class_exists('\WP\MCP\Core\McpAdapter');
			?>
			<!-- MCP Status -->
			<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">MCP Status</h2>
				<?php if ($has_mcp) : ?>
					<p style="color:green;font-weight:bold;">&#10003; MCP Adapter active</p>
					<p><strong>Endpoint:</strong><br>
						<code style="font-size:13px;"><?php echo esc_html(rest_url('mcp/mcp-adapter-default-server')); ?></code>
					</p>
				<?php else : ?>
					<p style="color:red;font-weight:bold;">&#10060; MCP Adapter not detected</p>
					<p>Install the dependencies:</p>
					<pre style="background:#f1f1f1;padding:10px;">composer require wordpress/abilities-api wordpress/mcp-adapter</pre>
				<?php endif; ?>
			</div>

			<?php if ($has_mcp) : ?>
				<!-- How to connect -->
				<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;border-left:4px solid #2271b1;">
					<h2 style="margin-top:0;">How to connect your AI assistant</h2>
					<p>To connect Cowork, Claude, or any MCP-compatible AI to your WordPress site, use this URL:</p>
					<div style="background:#f0f6fc;border:1px solid #2271b1;border-radius:4px;padding:12px;margin:10px 0;">
						<code style="font-size:14px;word-break:break-all;user-select:all;"><?php echo esc_html(rest_url('mcp/mcp-adapter-default-server')); ?></code>
					</div>
					<p style="margin-bottom:5px;"><strong>Steps:</strong></p>
					<ol style="margin-top:5px;">
						<li>Copy the URL above.</li>
						<li>In your AI tool (Cowork, Claude Code, etc.), go to <strong>Settings &gt; MCP Servers</strong> and add a new server.</li>
						<li>Paste the URL as the server endpoint.</li>
						<li>When you connect for the first time, your browser will open an OAuth authorization page &mdash; click <strong>Authorize</strong> to grant access.</li>
						<li>Done! Your AI assistant can now manage your WordPress site.</li>
					</ol>
					<p class="description">The OAuth 2.1 handshake happens automatically. You only need to authorize once per device.</p>
				</div>
			<?php endif; ?>

			<!-- OAuth 2.1 Status -->
			<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">OAuth 2.1</h2>
				<p style="color:green;font-weight:bold;">Active</p>
				<p class="description" style="margin-bottom:10px;">These are the technical OAuth endpoints. You do not need to use them directly &mdash; the AI tool handles the handshake automatically using the MCP endpoint URL above.</p>
				<table class="widefat striped" style="max-width:100%;">
					<thead>
						<tr>
							<th>Endpoint</th>
							<th>URL</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>Protected Resource</td>
							<td><code style="font-size:11px;word-break:break-all;"><?php echo esc_html(home_url('/.well-known/oauth-protected-resource')); ?></code></td>
						</tr>
						<tr>
							<td>Authorization Server</td>
							<td><code style="font-size:11px;word-break:break-all;"><?php echo esc_html(home_url('/.well-known/oauth-authorization-server')); ?></code></td>
						</tr>
						<tr>
							<td>Registration (DCR)</td>
							<td><code style="font-size:11px;word-break:break-all;"><?php echo esc_html(rest_url('sentinel-auth/v1/register')); ?></code></td>
						</tr>
						<tr>
							<td>Authorization</td>
							<td><code style="font-size:11px;word-break:break-all;"><?php echo esc_html(rest_url('sentinel-auth/v1/authorize')); ?></code></td>
						</tr>
						<tr>
							<td>Token</td>
							<td><code style="font-size:11px;word-break:break-all;"><?php echo esc_html(rest_url('sentinel-auth/v1/token')); ?></code></td>
						</tr>
						<tr>
							<td>Revocation</td>
							<td><code style="font-size:11px;word-break:break-all;"><?php echo esc_html(rest_url('sentinel-auth/v1/revoke')); ?></code></td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Detected integrations -->
			<?php $this->render_detected_integrations(); ?>
			<?php
		}

		/**
		 * Render "Detected on this site" cards for popular plugins.
		 *
		 * @return void
		 */
		private function render_detected_integrations(): void
		{
			$badges = [];

			if (class_exists('WooCommerce')) {
				$badges[] = [
					'label'   => __('WooCommerce detected', 'mcp-sentinel'),
					'message' => __('Premium adds 8 modules with 60+ abilities for products, orders, customers, coupons, shipping, taxes and subscriptions.', 'mcp-sentinel'),
					'cat'     => 'woocommerce',
				];
			}
			if (defined('WPSEO_VERSION')) {
				$badges[] = [
					'label'   => __('Yoast SEO detected', 'mcp-sentinel'),
					'message' => __('Premium adds full write SEO management across Yoast, Rank Math and AIOSEO.', 'mcp-sentinel'),
					'cat'     => 'seo',
				];
			}
			if (defined('RANK_MATH_VERSION')) {
				$badges[] = [
					'label'   => __('Rank Math detected', 'mcp-sentinel'),
					'message' => __('Premium adds full write SEO management for Rank Math.', 'mcp-sentinel'),
					'cat'     => 'seo',
				];
			}
			if (defined('POLYLANG_VERSION') || defined('ICL_SITEPRESS_VERSION') || defined('TRP_PLUGIN_VERSION')) {
				$badges[] = [
					'label'   => __('Multilingual plugin detected', 'mcp-sentinel'),
					'message' => __('Premium adds translation creation and per-language sync for Polylang, WPML and TranslatePress.', 'mcp-sentinel'),
					'cat'     => 'multilingual',
				];
			}
			if (class_exists('ACF') || function_exists('get_field')) {
				$badges[] = [
					'label'   => __('ACF detected', 'mcp-sentinel'),
					'message' => __('Premium adds universal field write across all ACF field types.', 'mcp-sentinel'),
					'cat'     => 'custom-fields',
				];
			}

			if (empty($badges)) {
				return;
			}
			?>
			<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
				<h3 style="margin-top:0;"><?php esc_html_e('Detected on this site', 'mcp-sentinel'); ?></h3>
				<?php foreach ($badges as $badge) : ?>
					<?php
					$cat_url = $this->tab_url(
						'premium',
						['cat' => $badge['cat']]
					);
					?>
					<div style="border-left:3px solid #2271b1;padding:8px 12px;margin-bottom:8px;background:#f6f7f7;">
						<strong><?php echo esc_html((string) $badge['label']); ?></strong><br>
						<span style="color:#50575e;"><?php echo esc_html((string) $badge['message']); ?></span><br>
						<a href="<?php echo esc_url($cat_url); ?>" style="font-size:13px;"><?php esc_html_e('See Premium features', 'mcp-sentinel'); ?> &rarr;</a>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		}
	}
}
