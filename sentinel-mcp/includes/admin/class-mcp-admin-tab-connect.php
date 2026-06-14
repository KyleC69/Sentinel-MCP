<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Connect admin tab.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

if (! class_exists('SentinelMCP\SENTINEL_Admin_Tab_Connect')) {

	/**
	 * Renders the Connect tab: client picker + config exporter + OAuth probe.
	 */
	class SENTINEL_Admin_Tab_Connect extends SENTINEL_Admin_Tab
	{

		/**
		 * Constructor.
		 */
		public function __construct()
		{
			parent::__construct('connect', __('Connect', 'mcp-sentinel'));
		}

		/**
		 * Render the tab content.
		 *
		 * @return void
		 */
		public function render(): void
		{
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
			$selected   = isset($_GET['client']) ? sanitize_key((string) $_GET['client']) : 'claude_desktop';
			$rest_nonce = wp_create_nonce('wp_rest');
			$clients    = SENTINEL_Config_Exporter::clients();
			if (! array_key_exists($selected, $clients)) {
				$selected = 'claude_desktop';
			}

			$config = SENTINEL_Config_Exporter::for_client($selected);
			?>
			<div class="card" style="max-width:1100px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Connect your AI assistant</h2>
				<p><strong>Endpoint URL:</strong></p>
				<div style="background:#f0f6fc;border:1px solid #2271b1;border-radius:4px;padding:10px;margin-bottom:15px;">
					<code style="font-size:14px;word-break:break-all;user-select:all;"><?php echo esc_html(SENTINEL_Config_Exporter::endpoint_url()); ?></code>
				</div>

				<form method="get">
					<input type="hidden" name="page" value="sentinel-settings">
					<input type="hidden" name="tab" value="connect">
					<label for="mcp-client-select"><strong>AI client:</strong></label>
					<select id="mcp-client-select" name="client" onchange="this.form.submit()">
						<?php foreach ($clients as $slug => $label) : ?>
							<option value="<?php echo esc_attr($slug); ?>" <?php selected($selected, $slug); ?>><?php echo esc_html($label); ?></option>
						<?php endforeach; ?>
					</select>
				</form>

				<h3 style="margin-top:20px;">Configuration (<?php echo esc_html($config['format']); ?>)</h3>
				<p><?php echo esc_html($config['instructions']); ?></p>
				<textarea readonly rows="10" style="width:100%;font-family:monospace;font-size:13px;" onclick="this.select()"><?php echo esc_textarea($config['content']); ?></textarea>
			</div>

			<div class="card" style="max-width:1100px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Quick OAuth Troubleshooter</h2>
				<p>Use this lightweight probe to send a manual request to the OAuth or MCP endpoint while you are debugging the auth flow. It does not modify anything on the server.</p>
				<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start;">
					<div>
						<label for="oauth-probe-endpoint"><strong>Endpoint URL</strong></label>
						<input id="oauth-probe-endpoint" type="text" value="<?php echo esc_attr(rest_url('sentinel-auth/v1/register')); ?>" style="width:100%;margin-top:6px;">
						<label for="oauth-probe-method" style="display:block;margin-top:10px;"><strong>Method</strong></label>
						<select id="oauth-probe-method" style="width:100%;margin-top:6px;">
							<option value="POST">POST</option>
							<option value="GET">GET</option>
						</select>
						<label for="oauth-probe-body" style="display:block;margin-top:10px;"><strong>Request body (JSON)</strong></label>
						<textarea id="oauth-probe-body" rows="8" style="width:100%;font-family:monospace;font-size:12px;">{"client_name":"debug-probe","redirect_uris":["https://example.com/callback"],"grant_types":["authorization_code","refresh_token"],"response_types":["code"],"token_endpoint_auth_method":"none"}</textarea>
						<button type="button" id="oauth-probe-run" class="button button-primary" style="margin-top:10px;">Run probe</button>
						<p id="oauth-probe-status" class="description" style="margin-top:8px;">Ready to send a manual request for troubleshooting.</p>
					</div>
					<div>
						<label for="oauth-probe-headers"><strong>Response headers</strong></label>
						<textarea id="oauth-probe-headers" readonly rows="8" style="width:100%;font-family:monospace;font-size:12px;">No response yet.</textarea>
						<label for="oauth-probe-response" style="display:block;margin-top:10px;"><strong>Response body</strong></label>
						<textarea id="oauth-probe-response" readonly rows="12" style="width:100%;font-family:monospace;font-size:12px;">No response yet.</textarea>
					</div>
				</div>
			</div>

			<script>
			(function() {
				const runButton = document.getElementById('oauth-probe-run');
				const endpointInput = document.getElementById('oauth-probe-endpoint');
				const methodSelect = document.getElementById('oauth-probe-method');
				const bodyInput = document.getElementById('oauth-probe-body');
				const statusBox = document.getElementById('oauth-probe-status');
				const headersBox = document.getElementById('oauth-probe-headers');
				const responseBox = document.getElementById('oauth-probe-response');
				if (!runButton || !endpointInput || !methodSelect || !bodyInput || !statusBox || !headersBox || !responseBox) {
					return;
				}
				runButton.addEventListener('click', function() {
					const endpoint = endpointInput.value.trim();
					if (!endpoint) {
						statusBox.textContent = 'Please enter an endpoint URL before running the probe.';
						return;
					}

					statusBox.textContent = 'Sending request…';
					headersBox.value = 'Loading…';
					responseBox.value = 'Loading…';

					fetch('<?php echo esc_js(rest_url('sentinel-auth/v1/debug-probe')); ?>', {
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': '<?php echo esc_js($rest_nonce); ?>'
						},
						body: JSON.stringify({
							endpoint: endpoint,
							method: methodSelect.value,
							body: bodyInput.value,
							headers: {
								'Content-Type': 'application/json',
								'Accept': '*/*'
							}
						})
					}).then(function(res) {
						return res.json().then(function(data) {
							return {
								ok: res.ok,
								status: res.status,
								data: data
							};
						});
					}).then(function(result) {
						if (!result.ok) {
							statusBox.textContent = 'Probe request failed: ' + (result.data.message || 'Unknown error');
							return;
						}

						statusBox.textContent = 'HTTP ' + result.data.status + ' received.';
						headersBox.value = JSON.stringify(result.data.headers || {}, null, 2);
						responseBox.value = result.data.body || '(empty body)';
					}).catch(function(error) {
						statusBox.textContent = 'Probe request failed: ' + error.message;
						headersBox.value = 'Error';
						responseBox.value = error.message;
					});
				});
			})();
			</script>
			<?php
		}
	}
}
