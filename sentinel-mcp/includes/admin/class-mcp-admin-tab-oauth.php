<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * OAuth admin tab.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

if (! class_exists('SentinelMCP\SENTINEL_Admin_Tab_OAuth')) {

	/**
	 * Renders the Authentication (OAuth + Application Passwords) tab.
	 *
	 * Also handles its own POST actions.
	 */
	class SENTINEL_Admin_Tab_OAuth extends SENTINEL_Admin_Tab
	{

		/**
		 * Constructor.
		 */
		public function __construct()
		{
			parent::__construct('oauth', __('Authentication', 'mcp-sentinel'));
		}

		/**
		 * Render the tab content.
		 *
		 * @return void
		 */
		public function render(): void
		{
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
			$subview = isset($_GET['subview']) ? sanitize_key((string) $_GET['subview']) : '';
			if ('permissions' === $subview) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
				$client_id = isset($_GET['client']) ? sanitize_text_field(wp_unslash((string) $_GET['client'])) : '';
				$this->render_permissions_view($client_id);
				return;
			}

			$oauth_clients = SENTINEL_OAuth_DB::get_all_clients();
			$active_tokens = SENTINEL_OAuth_DB::get_active_tokens();
			$has_mcp       = class_exists('\WP\MCP\Core\McpAdapter');
			$mcp_url       = $has_mcp ? rest_url('mcp/mcp-adapter-default-server') : '';
			?>
			<?php if ($has_mcp) : ?>
				<!-- MCP Endpoint URL -->
				<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;border-left:4px solid #2271b1;">
					<h2 style="margin-top:0;">MCP Endpoint</h2>
					<p>Use this URL to connect any MCP-compatible AI assistant to your WordPress site:</p>
					<div style="background:#f0f6fc;border:1px solid #2271b1;border-radius:4px;padding:12px;margin:10px 0;">
						<code style="font-size:14px;word-break:break-all;user-select:all;"><?php echo esc_html($mcp_url); ?></code>
					</div>
					<p class="description">You can authenticate using <strong>OAuth 2.1</strong> (recommended) or <strong>Application Passwords</strong> (alternative). See details below.</p>
				</div>
			<?php endif; ?>

			<!-- Option 1: OAuth 2.1 (Recommended) -->
			<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;border-left:4px solid #00a32a;">
				<h2 style="margin-top:0;">Option 1: OAuth 2.1 <span style="background:#00a32a;color:#fff;font-size:11px;padding:2px 8px;border-radius:3px;margin-left:8px;vertical-align:middle;">Recommended</span></h2>
				<p>OAuth 2.1 with PKCE is the most secure method. Tokens rotate automatically and can be scoped. Used by <strong>Claude.ai</strong> and <strong>Cowork</strong>.</p>
				<?php if ($has_mcp) : ?>
					<p style="margin-bottom:5px;"><strong>Steps:</strong></p>
					<ol style="margin-top:5px;">
						<li>In your AI tool, go to <strong>Settings &gt; MCP Servers</strong> and add a new server.</li>
						<li>Paste the MCP Endpoint URL above.</li>
						<li>Your browser will open an authorization page &mdash; click <strong>Authorize</strong>.</li>
						<li>Done! You only need to authorize once per device.</li>
					</ol>
				<?php endif; ?>
			</div>

			<!-- Registered OAuth clients -->
			<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Registered OAuth clients</h2>
				<?php if (empty($oauth_clients)) : ?>
					<p>No registered clients.</p>
				<?php else : ?>
					<table class="widefat striped" style="max-width:100%;">
						<thead>
							<tr>
								<th>Name</th>
								<th>Client ID</th>
								<th>Registered</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($oauth_clients as $oc) : ?>
								<tr>
									<td><strong><?php echo esc_html($oc['client_name']); ?></strong></td>
									<td><code style="font-size:11px;"><?php echo esc_html(substr($oc['client_id'], 0, 16) . '...'); ?></code></td>
									<td><?php echo esc_html($oc['created_at']); ?></td>
									<td>
										<?php
										$perm_url = $this->tab_url(
											'oauth',
											[
												'subview' => 'permissions',
												'client'  => $oc['client_id'],
											]
										);
										?>
										<a class="button button-small" href="<?php echo esc_url($perm_url); ?>"><?php esc_html_e('Permissions', 'mcp-sentinel'); ?></a>
										<form method="post" style="display:inline;">
											<?php wp_nonce_field('mcpcomal_revoke_client_' . $oc['client_id']); ?>
											<input type="hidden" name="mcpcomal_revoke_client" value="<?php echo esc_attr($oc['client_id']); ?>" />
											<button type="submit" class="button button-small" onclick="return confirm('Revoke this client and all its tokens?');">Revoke</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Active OAuth tokens -->
			<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Active tokens</h2>
				<?php if (empty($active_tokens)) : ?>
					<p>No active tokens.</p>
				<?php else : ?>
					<table class="widefat striped" style="max-width:100%;">
						<thead>
							<tr>
								<th>Client</th>
								<th>User</th>
								<th>Scope</th>
								<th>Expires</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($active_tokens as $tk) : ?>
								<?php $token_user = get_userdata((int) $tk['user_id']); ?>
								<tr>
									<td><?php echo esc_html($tk['client_name'] ?? $tk['client_id']); ?></td>
									<td><?php echo esc_html($token_user ? $token_user->display_name : '#' . $tk['user_id']); ?></td>
									<td><code><?php echo esc_html($tk['scope']); ?></code></td>
									<td><?php echo esc_html($tk['access_expires_at']); ?></td>
									<td>
										<form method="post" style="display:inline;">
											<?php wp_nonce_field('mcpcomal_revoke_token_' . $tk['id']); ?>
											<input type="hidden" name="mcpcomal_revoke_token" value="<?php echo esc_attr($tk['id']); ?>" />
											<button type="submit" class="button button-small" onclick="return confirm('Revoke this token?');">Revoke</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Option 2: Application Passwords -->
			<?php $this->render_app_passwords_section($mcp_url); ?>
			<?php
		}

		/**
		 * Handle POST actions for the OAuth tab.
		 *
		 * @return bool True if a redirect was issued.
		 */
		public function handle_post(): bool
		{
			// Revoke OAuth client.
			if (isset($_POST['mcpcomal_revoke_client'])) {
				$client_id = sanitize_text_field(wp_unslash($_POST['mcpcomal_revoke_client']));
				check_admin_referer('mcpcomal_revoke_client_' . $client_id);
				SENTINEL_OAuth_DB::revoke_all_for_client($client_id);
				SENTINEL_OAuth_DB::delete_client($client_id);
				$this->redirect_with_notice('oauth', 'success', 'OAuth client revoked successfully.');
			}

			// Save per-client allowed_abilities allowlist.
			if (isset($_POST['mcpcomal_save_permissions'])) {
				$client_id = sanitize_text_field(wp_unslash((string) $_POST['mcpcomal_save_permissions']));
				check_admin_referer('mcpcomal_save_permissions_' . $client_id);

				$mode = isset($_POST['mcpcomal_perm_mode']) ? sanitize_key((string) $_POST['mcpcomal_perm_mode']) : 'all';
				if ('all' === $mode) {
					SENTINEL_OAuth_Permissions::set_allowed_abilities($client_id, null);
				} else {
					$selected = isset($_POST['mcpcomal_perm_abilities']) && is_array($_POST['mcpcomal_perm_abilities'])
						? array_map('sanitize_text_field', wp_unslash((array) $_POST['mcpcomal_perm_abilities']))
						: [];
					SENTINEL_OAuth_Permissions::set_allowed_abilities($client_id, $selected);
				}

				$this->redirect_with_notice('oauth', 'success', 'Permissions saved.');
			}

			// Revoke individual token.
			if (isset($_POST['mcpcomal_revoke_token'])) {
				$token_id = (int) $_POST['mcpcomal_revoke_token'];
				check_admin_referer('mcpcomal_revoke_token_' . $token_id);
				SENTINEL_OAuth_DB::revoke_token_by_id($token_id);
				$this->redirect_with_notice('oauth', 'success', 'Token revoked successfully.');
			}

			// Create Application Password for current user.
			if (isset($_POST['mcpcomal_create_app_password'])) {
				check_admin_referer('mcpcomal_create_app_password');
				$app_name = isset($_POST['mcpcomal_app_password_name'])
					? sanitize_text_field(wp_unslash($_POST['mcpcomal_app_password_name']))
					: '';
				if (empty($app_name)) {
					$this->redirect_with_notice('oauth', 'error', 'Application name is required.');
				}
				$user_id = get_current_user_id();
				$result  = WP_Application_Passwords::create_new_application_password(
					$user_id,
					['name' => $app_name]
				);
				if (is_wp_error($result)) {
					$this->redirect_with_notice('oauth', 'error', $result->get_error_message());
				}
				// $result[0] = unhashed password (only available now, never again).
				set_transient(
					'mcpcomal_new_app_password_' . $user_id,
					[
						'password' => $result[0],
						'name'     => $app_name,
					],
					60
				);
				$this->redirect_with_notice('oauth', 'success', 'Application Password created. Copy the connection URL below — the password will not be shown again.');
			}

			// Revoke Application Password for current user.
			if (isset($_POST['mcpcomal_revoke_app_password'])) {
				$uuid = sanitize_text_field(wp_unslash($_POST['mcpcomal_revoke_app_password']));
				check_admin_referer('mcpcomal_revoke_app_password_' . $uuid);
				$user_id = get_current_user_id();
				$deleted = WP_Application_Passwords::delete_application_password($user_id, $uuid);
				if (is_wp_error($deleted)) {
					$this->redirect_with_notice('oauth', 'error', $deleted->get_error_message());
				}
				$this->redirect_with_notice('oauth', 'success', 'Application Password revoked.');
			}

			return false;
		}

		/**
		 * Render the Application Passwords section.
		 *
		 * @param string $mcp_url The MCP endpoint URL.
		 * @return void
		 */
		private function render_app_passwords_section(string $mcp_url): void
		{
			$current_user = wp_get_current_user();
			$user_id      = $current_user->ID;

			if (! class_exists('WP_Application_Passwords')) {
				?>
				<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;border-left:4px solid #dba617;">
					<h2 style="margin-top:0;">Option 2: Application Passwords</h2>
					<p>Application Passwords require <strong>WordPress 5.6+</strong>. Please update WordPress to use this feature.</p>
				</div>
				<?php
				return;
			}

			$app_passwords = WP_Application_Passwords::get_user_application_passwords($user_id);

			$transient_key = 'mcpcomal_new_app_password_' . $user_id;
			$new_password  = get_transient($transient_key);
			if ($new_password) {
				delete_transient($transient_key);
			}

			$connection_url = '';
			if ($new_password && ! empty($mcp_url)) {
				$parsed = wp_parse_url($mcp_url);
				$scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'https';
				$host   = isset($parsed['host']) ? $parsed['host'] : '';
				$port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
				$path   = isset($parsed['path']) ? $parsed['path'] : '';

				$login = $current_user->user_login;
				if (preg_match('/[\s\x80-\xff]/', $login)) {
					$login = $current_user->user_email;
				}

				$connection_url = sprintf(
					'%s://%s:%s@%s%s%s',
					$scheme,
					rawurlencode($login),
					rawurlencode($new_password['password']),
					$host,
					$port,
					$path
				);
			}
			?>
			<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;border-left:4px solid #dba617;">
				<h2 style="margin-top:0;">Option 2: Application Passwords <span style="background:#dba617;color:#fff;font-size:11px;padding:2px 8px;border-radius:3px;margin-left:8px;vertical-align:middle;">Alternative</span></h2>
				<p>Use Application Passwords when your MCP client does not support OAuth (e.g., Claude Desktop, Cursor, ChatGPT, custom scripts). Credentials are sent via HTTP Basic Auth on every request.</p>
				<p class="description" style="margin-bottom:15px;">Less secure than OAuth (no token rotation, no PKCE). Use HTTPS. Each password can be individually revoked.</p>

				<?php if (! empty($connection_url)) : ?>
					<div style="background:#fcf9e8;border:2px solid #dba617;border-radius:4px;padding:15px;margin-bottom:15px;">
						<h3 style="margin-top:0;color:#826200;">Connection URL for &ldquo;<?php echo esc_html($new_password['name']); ?>&rdquo;</h3>
						<p><strong>Copy this URL and paste it in your MCP client as the server endpoint:</strong></p>
						<div style="background:#fff;border:1px solid #c3c4c7;border-radius:3px;padding:10px;margin:8px 0;position:relative;">
							<input type="text" readonly value="<?php echo esc_attr($connection_url); ?>"
								style="width:100%;font-family:monospace;font-size:13px;border:none;background:transparent;padding:0;"
								onclick="this.select();" id="sentinel-app-password-url" />
						</div>
						<p style="color:#b32d2e;font-weight:bold;margin-bottom:0;">This password will not be shown again. Copy the URL now.</p>
					</div>
				<?php endif; ?>

				<h3>Your Application Passwords</h3>
				<?php if (empty($app_passwords)) : ?>
					<p>No Application Passwords created yet.</p>
				<?php else : ?>
					<table class="widefat striped" style="max-width:100%;">
						<thead>
							<tr>
								<th>Name</th>
								<th>Created</th>
								<th>Last Used</th>
								<th>Last IP</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($app_passwords as $ap) : ?>
								<tr>
									<td><strong><?php echo esc_html($ap['name']); ?></strong></td>
									<td><?php echo esc_html(gmdate('Y-m-d', $ap['created'])); ?></td>
									<td><?php echo ! empty($ap['last_used']) ? esc_html(gmdate('Y-m-d', $ap['last_used'])) : 'Never'; ?></td>
									<td><?php echo ! empty($ap['last_ip']) ? esc_html($ap['last_ip']) : '&mdash;'; ?></td>
									<td>
										<form method="post" style="display:inline;">
											<?php wp_nonce_field('mcpcomal_revoke_app_password_' . $ap['uuid']); ?>
											<input type="hidden" name="mcpcomal_revoke_app_password" value="<?php echo esc_attr($ap['uuid']); ?>" />
											<button type="submit" class="button button-small" onclick="return confirm('Revoke this Application Password?');">Revoke</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<h3 style="margin-top:20px;">Create new Application Password</h3>
				<form method="post" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
					<?php wp_nonce_field('mcpcomal_create_app_password'); ?>
					<label for="mcpcomal_app_password_name" class="screen-reader-text">Application name</label>
					<input type="text" id="mcpcomal_app_password_name" name="mcpcomal_app_password_name"
						placeholder="e.g. Claude Code, Cursor, ChatGPT"
						style="min-width:280px;" required />
					<button type="submit" name="mcpcomal_create_app_password" value="1" class="button button-primary">Create Application Password</button>
				</form>
				<p class="description" style="margin-top:8px;">The password will be shown only once after creation. You will get a ready-to-use connection URL.</p>
			</div>
			<?php
		}

		/**
		 * Render the per-client OAuth permissions editor (subview of the OAuth tab).
		 *
		 * @param string $client_id OAuth client_id.
		 * @return void
		 */
		private function render_permissions_view(string $client_id): void
		{
			$client   = $client_id ? SENTINEL_OAuth_DB::get_client_by_id($client_id) : null;
			$back_url = $this->tab_url('oauth');

			if (! $client) {
				?>
				<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
					<p><?php esc_html_e('OAuth client not found.', 'mcp-sentinel'); ?></p>
					<p><a class="button" href="<?php echo esc_url($back_url); ?>">&laquo; <?php esc_html_e('Back to OAuth clients', 'mcp-sentinel'); ?></a></p>
				</div>
				<?php
				return;
			}

			$current   = SENTINEL_OAuth_Permissions::get_allowed_abilities($client_id);
			$mode      = (null === $current) ? 'all' : 'restricted';
			$selected  = is_array($current) ? $current : [];
			$abilities = $this->get_mcpcomal_abilities_grouped();
			?>
			<div class="card" style="max-width:900px;margin-bottom:20px;padding:15px;">
				<p><a href="<?php echo esc_url($back_url); ?>">&laquo; <?php esc_html_e('Back to OAuth clients', 'mcp-sentinel'); ?></a></p>
				<h2 style="margin-top:0;">
					<?php
					printf(
						/* translators: %s: OAuth client name */
						esc_html__('Permissions for %s', 'mcp-sentinel'),
						'<code>' . esc_html((string) $client['client_name']) . '</code>'
					);
					?>
				</h2>
				<p><?php esc_html_e('Restrict which abilities this OAuth client can call. By default a client may call every ability the WordPress user has capability for. Restricting here adds an extra allowlist on top of WordPress capabilities.', 'mcp-sentinel'); ?></p>

				<form method="post">
					<?php wp_nonce_field('mcpcomal_save_permissions_' . $client_id); ?>
					<input type="hidden" name="mcpcomal_save_permissions" value="<?php echo esc_attr($client_id); ?>" />

					<p>
						<label>
							<input type="radio" name="mcpcomal_perm_mode" value="all" <?php checked($mode, 'all'); ?> />
							<strong><?php esc_html_e('All abilities', 'mcp-sentinel'); ?></strong>
							<span style="color:#50575e;">&mdash; <?php esc_html_e('No allowlist (default).', 'mcp-sentinel'); ?></span>
						</label>
					</p>
					<p>
						<label>
							<input type="radio" name="mcpcomal_perm_mode" value="restricted" <?php checked($mode, 'restricted'); ?> />
							<strong><?php esc_html_e('Restricted to selected abilities', 'mcp-sentinel'); ?></strong>
						</label>
					</p>

					<div style="border:1px solid #dcdcde;border-radius:4px;padding:10px;max-height:500px;overflow:auto;background:#fafafa;">
						<?php if (empty($abilities)) : ?>
							<p><?php esc_html_e('No abilities registered yet. Open any abilities-using AI client first.', 'mcp-sentinel'); ?></p>
						<?php else : ?>
							<?php foreach ($abilities as $group_label => $group_slugs) : ?>
								<details open>
									<summary style="font-weight:bold;cursor:pointer;padding:6px 0;">
										<?php echo esc_html($group_label); ?>
										<span style="color:#50575e;font-weight:normal;">(<?php echo esc_html((string) count($group_slugs)); ?>)</span>
									</summary>
									<div style="padding:6px 0 12px 18px;">
										<?php foreach ($group_slugs as $slug => $label) : ?>
											<label style="display:block;margin:2px 0;">
												<input type="checkbox" name="mcpcomal_perm_abilities[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selected, true)); ?> />
												<code style="font-size:12px;"><?php echo esc_html($slug); ?></code>
												<?php if ($label) : ?>
													<span style="color:#50575e;">&mdash; <?php echo esc_html($label); ?></span>
												<?php endif; ?>
											</label>
										<?php endforeach; ?>
									</div>
								</details>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<p style="margin-top:15px;">
						<button type="submit" class="button button-primary"><?php esc_html_e('Save permissions', 'mcp-sentinel'); ?></button>
						<a class="button" href="<?php echo esc_url($back_url); ?>"><?php esc_html_e('Cancel', 'mcp-sentinel'); ?></a>
					</p>
				</form>
			</div>
			<?php
		}

		/**
		 * Return all registered sentinel/* abilities grouped by inferred area.
		 *
		 * @return array<string, array<string,string>>
		 */
		private function get_mcpcomal_abilities_grouped(): array
		{
			if (! function_exists('wp_get_abilities')) {
				return [];
			}

			$grouped = [];
			$labels  = [
				'wc'   => __('WooCommerce (read-only)', 'mcp-sentinel'),
				'i18n' => __('Multilingual (read-only)', 'mcp-sentinel'),
				'seo'  => __('SEO (read-only)', 'mcp-sentinel'),
			];

			foreach ((array) wp_get_abilities() as $ability) {
				if (! is_object($ability) || ! method_exists($ability, 'get_name')) {
					continue;
				}
				$slug = (string) $ability->get_name();
				if (0 !== strpos($slug, 'sentinel/')) {
					continue;
				}

				$short = substr($slug, strlen('sentinel/'));
				$group = __('Core', 'mcp-sentinel');

				if (0 === strpos($short, 'wc-')) {
					$group = $labels['wc'];
				} elseif (0 === strpos($short, 'i18n-')) {
					$group = $labels['i18n'];
				} elseif (0 === strpos($short, 'seo-')) {
					$group = $labels['seo'];
				}

				$label = method_exists($ability, 'get_label') ? (string) $ability->get_label() : '';

				$grouped[$group][$slug] = $label;
			}

			ksort($grouped);
			foreach ($grouped as &$slugs) {
				ksort($slugs);
			}
			unset($slugs);

			return $grouped;
		}
	}
}
