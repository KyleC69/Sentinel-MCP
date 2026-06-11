<?php

/**
 * Admin page.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

if (! class_exists('SENTINEL_Admin')) {

	/**
	 * Admin settings page for Sentinel-MCP.
	 */
	class SENTINEL_Admin
	{

		/**
		 * Constructor. Registers admin hooks.
		 */
		public function __construct()
		{
			add_action('admin_menu', array($this, 'add_menu'));
			add_action('admin_init', array($this, 'register_settings'));
			add_action('admin_init', array($this, 'handle_oauth_actions'));

			// Debug log to confirm the admin class is instantiated.
			if (function_exists('error_log')) {
				error_log('[SENTINEL-DEBUG] SENTINEL_Admin instantiated'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			// Add a temporary admin notice to verify the class runs.
			add_action('admin_notices', function () {
				printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__('SENTINEL_Admin active', 'mcp-sentinel'));
			});
		}

		/**
		 * Add the settings submenu page under Settings.
		 */
		public function add_menu(): void
		{
			// Debug log to confirm add_menu is executed.
			if (function_exists('error_log')) {
				error_log('[SENTINEL-DEBUG] SENTINEL_Admin::add_menu called'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			add_options_page(
				'Sentinel-MCP',
				'Sentinel-MCP',
				'manage_options',
				'sentinel-settings',
				array($this, 'render_page')
			);

			// Temporary admin notice to confirm the menu was added.
			add_action('admin_notices', function () {
				printf('\u003cdiv class="notice notice-success is-dismissible"\u003e\u003cp\u003e%s\u003c/p\u003e\u003c/div\u003e', esc_html__('SENTINEL_Admin menu added', 'mcp-sentinel'));
			});

			// Chat AI page (hidden from menu, accessed via admin bar button).
			add_submenu_page(
				null,
				'Chat AI — Sentinel-MCP',
				'Chat AI',
				'manage_options',
				'sentinel-chat',
				array('SENTINEL_Admin_Chat', 'render_page')
			);
		}

		/**
		 * Register plugin settings with the Settings API.
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
			return array(
				'getstarted' => __('Get Started', 'mcp-sentinel'),
				'status'     => __('Status', 'mcp-sentinel'),
				'connect'    => __('Connect', 'mcp-sentinel'),
				'prompts'    => __('Prompts', 'mcp-sentinel'),
				'settings'   => __('Settings', 'mcp-sentinel'),
				'oauth'      => __('Authentication', 'mcp-sentinel'),
				'activity'   => __('Activity Log', 'mcp-sentinel'),
				'info'       => __('Info', 'mcp-sentinel'),
				'premium'    => __('Go Premium', 'mcp-sentinel'),
			);
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
			if (! array_key_exists($tab, $this->get_tabs())) {
				$tab = 'getstarted';
			}
			return $tab;
		}

		/**
		 * Store a flash notice and redirect to a tab.
		 *
		 * @param string $tab     Target tab slug.
		 * @param string $type    Notice type (success, error, warning, info).
		 * @param string $message Notice message.
		 */
		private function redirect_with_notice(string $tab, string $type, string $message): void
		{
			set_transient(
				'mcpcomal_admin_notice_' . get_current_user_id(),
				array(
					'type'    => $type,
					'message' => $message,
				),
				30
			);
			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => 'sentinel-settings',
						'tab'  => $tab,
					),
					admin_url('options-general.php')
				)
			);
			exit;
		}

		/**
		 * Handle admin POST actions for OAuth management.
		 */
		public function handle_oauth_actions(): void
		{
			if (! current_user_can('manage_options')) {
				return;
			}

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
						: array();
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
					array('name' => $app_name)
				);
				if (is_wp_error($result)) {
					$this->redirect_with_notice('oauth', 'error', $result->get_error_message());
				}
				// $result[0] = unhashed password (only available now, never again).
				set_transient(
					'mcpcomal_new_app_password_' . $user_id,
					array(
						'password' => $result[0],
						'name'     => $app_name,
					),
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

			// Save general settings (debug logging + Gemini config).
			if (isset($_POST['mcpcomal_save_settings'])) {
				check_admin_referer('mcpcomal_save_settings');

				// Explicit "Remove key" button takes precedence over the input field.
				if (! empty($_POST['mcpcomal_clear_gemini_api_key'])) {
					update_option('mcpcomal_gemini_api_key', '', false);
					$this->redirect_with_notice('settings', 'success', 'Gemini API key removed.');
				}

				update_option('mcpcomal_debug_logging', ! empty($_POST['mcpcomal_debug_logging']));

				if (isset($_POST['mcpcomal_gemini_api_key'])) {
					$key = trim(sanitize_text_field(wp_unslash((string) $_POST['mcpcomal_gemini_api_key'])));
					// Empty input = keep current key (so re-saving the form does not wipe it).
					if ('' !== $key) {
						update_option('mcpcomal_gemini_api_key', $key, false);
					}
				}
				if (isset($_POST['mcpcomal_gemini_model'])) {
					$model = sanitize_text_field(wp_unslash((string) $_POST['mcpcomal_gemini_model']));
					update_option('mcpcomal_gemini_model', '' === $model ? SENTINEL_Image_Generator::DEFAULT_MODEL : $model, false);
				}

				$this->redirect_with_notice('settings', 'success', 'Settings saved.');
			}
		}

		/**
		 * Render the admin settings page.
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
							array(
								'page' => 'sentinel-settings',
								'tab'  => $slug,
							),
							admin_url('options-general.php')
						);
						$style   = '';
						?>
						<a href="<?php echo esc_url($tab_url); ?>"
							class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>"
							<?php echo $style ? 'style="' . esc_attr($style) . '"' : ''; ?>>
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

				switch ($current_tab) {
					case 'getstarted':
						$this->render_tab_getstarted();
						break;
					case 'connect':
						$this->render_tab_connect();
						break;
					case 'prompts':
						$this->render_tab_prompts();
						break;
					case 'settings':
						$this->render_tab_settings();
						break;
					case 'oauth':
						$this->render_tab_oauth();
						break;
					case 'activity':
						$this->render_tab_activity();
						break;
					case 'info':
						$this->render_tab_info();
						break;
					case 'premium':
						$this->render_tab_premium();
						break;
					case 'status':
						$this->render_tab_status();
						break;
					default:
						$this->render_tab_getstarted();
						break;
				}
				?>
			</div>
		<?php
		}

		/**
		 * Render the Status tab: MCP Status and OAuth 2.1 endpoints.
		 */
		private function render_tab_status(): void
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

			<!-- Upsell banner -->
			<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;border-left:4px solid #d63638;">
				<p style="margin:0;">
					<strong><?php esc_html_e('Need more power?', 'mcp-sentinel'); ?></strong>
					<?php esc_html_e('Unlock WooCommerce, SEO, Time Machine, Gemini AI and 27+ modules with', 'mcp-sentinel'); ?>
					<a href="<?php echo esc_url(SENTINEL_PREMIUM_PRODUCT_URL); ?>" target="_blank" rel="noopener noreferrer" style="font-weight:bold;">
						<?php esc_html_e('MCP Content Manager Premium', 'mcp-sentinel'); ?> &rarr;
					</a>
				</p>
			</div>
		<?php
		}

		/**
		 * Render "Detected on this site" cards for popular plugins.
		 */
		private function render_detected_integrations(): void
		{
			$badges = array();

			if (class_exists('WooCommerce')) {
				$badges[] = array(
					'label'   => __('WooCommerce detected', 'mcp-sentinel'),
					'message' => __('Premium adds 8 modules with 60+ abilities for products, orders, customers, coupons, shipping, taxes and subscriptions.', 'mcp-sentinel'),
					'cat'     => 'woocommerce',
				);
			}
			if (defined('WPSEO_VERSION')) {
				$badges[] = array(
					'label'   => __('Yoast SEO detected', 'mcp-sentinel'),
					'message' => __('Premium adds full write SEO management across Yoast, Rank Math and AIOSEO.', 'mcp-sentinel'),
					'cat'     => 'seo',
				);
			}
			if (defined('RANK_MATH_VERSION')) {
				$badges[] = array(
					'label'   => __('Rank Math detected', 'mcp-sentinel'),
					'message' => __('Premium adds full write SEO management for Rank Math.', 'mcp-sentinel'),
					'cat'     => 'seo',
				);
			}
			if (defined('POLYLANG_VERSION') || defined('ICL_SITEPRESS_VERSION') || defined('TRP_PLUGIN_VERSION')) {
				$badges[] = array(
					'label'   => __('Multilingual plugin detected', 'mcp-sentinel'),
					'message' => __('Premium adds translation creation and per-language sync for Polylang, WPML and TranslatePress.', 'mcp-sentinel'),
					'cat'     => 'multilingual',
				);
			}
			if (class_exists('ACF') || function_exists('get_field')) {
				$badges[] = array(
					'label'   => __('ACF detected', 'mcp-sentinel'),
					'message' => __('Premium adds universal field write across all ACF field types.', 'mcp-sentinel'),
					'cat'     => 'custom-fields',
				);
			}

			if (empty($badges)) {
				return;
			}

		?>
			<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
				<h3 style="margin-top:0;"><?php esc_html_e('Detected on this site', 'mcp-sentinel'); ?></h3>
				<?php foreach ($badges as $badge) : ?>
					<?php
					$cat_url = add_query_arg(
						array(
							'page' => 'sentinel-settings',
							'tab'  => 'premium',
							'cat'  => $badge['cat'],
						),
						admin_url('options-general.php')
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

		/**
		 * Render the Settings tab: Debug logging.
		 */
		private function render_tab_settings(): void
		{
			$debug_enabled = (bool) get_option('mcpcomal_debug_logging', false);
			$gemini_key    = (string) get_option('mcpcomal_gemini_api_key', '');
			$gemini_model  = (string) get_option('mcpcomal_gemini_model', SENTINEL_Image_Generator::DEFAULT_MODEL);
			$key_masked    = '' === $gemini_key ? '' : str_repeat('•', max(0, strlen($gemini_key) - 4)) . substr($gemini_key, -4);

		?>
			<form method="post">
				<?php wp_nonce_field('mcpcomal_save_settings'); ?>

				<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
					<h2 style="margin-top:0;"><?php esc_html_e('Debug Logging', 'mcp-sentinel'); ?></h2>
					<p><?php esc_html_e('When enabled, MCP Content Manager writes debug messages to the PHP error log. Useful for troubleshooting, but should be disabled in production to avoid excessive log growth.', 'mcp-sentinel'); ?></p>
					<table class="form-table" style="margin:0;">
						<tr>
							<th scope="row" style="padding:8px 10px 8px 0;"><?php esc_html_e('Enable debug logging', 'mcp-sentinel'); ?></th>
							<td style="padding:8px 10px;">
								<label>
									<input type="checkbox" name="mcpcomal_debug_logging" value="1" <?php checked($debug_enabled); ?> />
									<?php esc_html_e('Write [SENTINEL-DEBUG] messages to the PHP error log.', 'mcp-sentinel'); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
					<h2 style="margin-top:0;"><?php esc_html_e('AI Image Generation (Gemini)', 'mcp-sentinel'); ?></h2>
					<p>
						<?php esc_html_e('Configure a Google Gemini API key to enable the generate-image and set-featured-from-prompt abilities. Lite uses the Gemini generateContent endpoint with image output. Imagen API, multiple aspect ratios, 2K/4K, image editing and safety controls are reserved for Premium.', 'mcp-sentinel'); ?>
					</p>
					<p>
						<?php
						printf(
							/* translators: %s: link to Google AI Studio */
							esc_html__('Get a free API key at %s.', 'mcp-sentinel'),
							'<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">aistudio.google.com</a>'
						);
						?>
					</p>
					<table class="form-table" style="margin:0;">
						<tr>
							<th scope="row" style="padding:8px 10px 8px 0;"><?php esc_html_e('Gemini API key', 'mcp-sentinel'); ?></th>
							<td style="padding:8px 10px;">
								<?php if ('' !== $gemini_key) : ?>
									<p style="margin:0 0 6px;color:#50575e;">
										<?php esc_html_e('Currently configured:', 'mcp-sentinel'); ?>
										<code><?php echo esc_html($key_masked); ?></code>
									</p>
								<?php endif; ?>
								<input type="password"
									name="mcpcomal_gemini_api_key"
									value=""
									placeholder="<?php echo '' === $gemini_key ? esc_attr__('Paste your API key', 'mcp-sentinel') : esc_attr__('Leave blank to keep current key', 'mcp-sentinel'); ?>"
									autocomplete="off"
									style="width:380px;font-family:monospace;" />
								<?php if ('' !== $gemini_key) : ?>
									<button type="submit" name="mcpcomal_clear_gemini_api_key" value="1" class="button button-link-delete" formnovalidate>
										<?php esc_html_e('Remove key', 'mcp-sentinel'); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row" style="padding:8px 10px 8px 0;"><?php esc_html_e('Model', 'mcp-sentinel'); ?></th>
							<td style="padding:8px 10px;">
								<input type="text"
									name="mcpcomal_gemini_model"
									value="<?php echo esc_attr($gemini_model); ?>"
									style="width:380px;font-family:monospace;" />
								<p class="description">
									<?php
									printf(
										/* translators: %s: default model id */
										esc_html__('Default: %s. Must be a Gemini model that supports image output.', 'mcp-sentinel'),
										'<code>' . esc_html(SENTINEL_Image_Generator::DEFAULT_MODEL) . '</code>'
									);
									?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<p><button type="submit" name="mcpcomal_save_settings" value="1" class="button button-primary"><?php esc_html_e('Save Settings', 'mcp-sentinel'); ?></button></p>
			</form>
		<?php
		}

		/**
		 * Render the OAuth tab: Registered clients and active tokens.
		 */
		private function render_tab_oauth(): void
		{
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
			$subview = isset($_GET['subview']) ? sanitize_key((string) $_GET['subview']) : '';
			if ('permissions' === $subview) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
				$client_id = isset($_GET['client']) ? sanitize_text_field(wp_unslash((string) $_GET['client'])) : '';
				$this->render_oauth_permissions_view($client_id);
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

			<!-- ============================================ -->
			<!-- Option 1: OAuth 2.1 (Recommended)           -->
			<!-- ============================================ -->
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
										$perm_url = add_query_arg(
											array(
												'page'    => 'sentinel-settings',
												'tab'     => 'oauth',
												'subview' => 'permissions',
												'client'  => $oc['client_id'],
											),
											admin_url('options-general.php')
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

			<!-- ============================================ -->
			<!-- Option 2: Application Passwords              -->
			<!-- ============================================ -->
			<?php $this->render_app_passwords_section($mcp_url); ?>
			<?php
		}

		/**
		 * Render the Application Passwords section of the Authentication tab.
		 *
		 * @param string $mcp_url The MCP endpoint URL.
		 */
		private function render_app_passwords_section(string $mcp_url): void
		{
			$current_user = wp_get_current_user();
			$user_id      = $current_user->ID;

			// Check if Application Passwords are available.
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

			// Check for newly created password (transient from POST handler).
			$transient_key = 'mcpcomal_new_app_password_' . $user_id;
			$new_password  = get_transient($transient_key);
			if ($new_password) {
				delete_transient($transient_key);
			}

			// Build the connection URL with credentials if we have a new password.
			$connection_url = '';
			if ($new_password && ! empty($mcp_url)) {
				$parsed = wp_parse_url($mcp_url);
				$scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'https';
				$host   = isset($parsed['host']) ? $parsed['host'] : '';
				$port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
				$path   = isset($parsed['path']) ? $parsed['path'] : '';

				// Use email instead of user_login when the login contains spaces
				// or non-ASCII characters, as these cause issues in URL credentials.
				// WordPress accepts both user_login and email for Application Passwords.
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
				<p>Use Application Passwords when your MCP client does not support OAuth (e.g., Claude Desktop, Cursor, ChatGPT, custom scripts).
					Credentials are sent via HTTP Basic Auth on every request.</p>
				<p class="description" style="margin-bottom:15px;">Less secure than OAuth (no token rotation, no PKCE). Use HTTPS. Each password can be individually revoked.</p>

				<?php if (! empty($connection_url)) : ?>
					<!-- Newly created password — show connection URL -->
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

				<!-- Existing Application Passwords for current user -->
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

				<!-- Create new Application Password -->
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
		 * Render the Get Started tab: status checks + pointers to next steps.
		 */
		private function render_tab_getstarted(): void
		{
			$checks = array(
				array(
					'label' => 'PHP &ge; 8.3',
					'pass'  => version_compare(PHP_VERSION, '8.3', '>='),
					'value' => PHP_VERSION,
				),
				array(
					'label' => 'WordPress &ge; 6.9',
					'pass'  => version_compare(get_bloginfo('version'), '6.9', '>='),
					'value' => (string) get_bloginfo('version'),
				),
				array(
					'label' => 'Application Passwords available',
					'pass'  => class_exists('WP_Application_Passwords') && wp_is_application_passwords_available(),
					'value' => '',
				),
				array(
					'label' => 'MCP Adapter loaded',
					'pass'  => class_exists('\WP\MCP\Core\McpAdapter'),
					'value' => '',
				),
				array(
					'label' => 'OAuth tables present',
					'pass'  => self::oauth_tables_exist(),
					'value' => '',
				),
			);

			$oauth_url   = add_query_arg(
				array(
					'page' => 'sentinel-settings',
					'tab'  => 'oauth',
				),
				admin_url('options-general.php')
			);
			$connect_url = add_query_arg(
				array(
					'page' => 'sentinel-settings',
					'tab'  => 'connect',
				),
				admin_url('options-general.php')
			);

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
		 * Render the Connect tab: client picker + config exporter.
		 */
		private function render_tab_connect(): void
		{
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
			$selected = isset($_GET['client']) ? sanitize_key((string) $_GET['client']) : 'claude_desktop';
			$clients  = SENTINEL_Config_Exporter::clients();
			if (! array_key_exists($selected, $clients)) {
				$selected = 'claude_desktop';
			}

			$config = SENTINEL_Config_Exporter::for_client($selected);

		?>
			<div class="card" style="max-width:900px;margin-bottom:20px;padding:15px;">
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
		<?php
		}

		/**
		 * Render the Prompts tab: gallery of curated prompts.
		 */
		private function render_tab_prompts(): void
		{
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters from URL.
			$category = isset($_GET['cat']) ? sanitize_key((string) $_GET['cat']) : '';
			$keyword  = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$catalog    = SENTINEL_Prompt_Gallery::load();
			$total      = SENTINEL_Prompt_Gallery::total_count();
			$categories = SENTINEL_Prompt_Gallery::filter(
				'' === $category ? null : $category,
				'' === $keyword ? null : $keyword
			);

		?>
			<div class="card" style="max-width:900px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Prompts gallery (<?php echo esc_html((string) $total); ?>)</h2>
				<p>Copy any of these prompts and paste them into your AI client (Claude Desktop, Cursor, ChatGPT&hellip;) connected to this site.</p>

				<form method="get" style="margin-bottom:15px;">
					<input type="hidden" name="page" value="sentinel-settings">
					<input type="hidden" name="tab" value="prompts">
					<select name="cat">
						<option value="">All categories</option>
						<?php foreach ((array) $catalog['categories'] as $cat) : ?>
							<option value="<?php echo esc_attr((string) ($cat['slug'] ?? '')); ?>" <?php selected($category, (string) ($cat['slug'] ?? '')); ?>>
								<?php echo esc_html((string) ($cat['label'] ?? $cat['slug'] ?? '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<input type="text" name="q" value="<?php echo esc_attr($keyword); ?>" placeholder="Search&hellip;" style="width:240px;">
					<button type="submit" class="button">Filter</button>
				</form>

				<?php if (empty($categories)) : ?>
					<p>No prompts match the current filters.</p>
				<?php else : ?>
					<?php foreach ($categories as $cat) : ?>
						<h3 style="margin-top:20px;"><?php echo esc_html((string) ($cat['label'] ?? '')); ?></h3>
						<?php foreach ((array) ($cat['prompts'] ?? array()) as $prompt) : ?>
							<div style="border:1px solid #dcdcde;border-radius:4px;padding:10px;margin-bottom:8px;">
								<strong><?php echo esc_html((string) ($prompt['title'] ?? '')); ?></strong>
								<?php if (! empty($prompt['description'])) : ?>
									<p style="margin:4px 0;color:#666;font-size:13px;"><?php echo esc_html((string) $prompt['description']); ?></p>
								<?php endif; ?>
								<textarea readonly rows="2" style="width:100%;font-family:monospace;font-size:12px;" onclick="this.select()"><?php echo esc_textarea((string) ($prompt['prompt'] ?? '')); ?></textarea>
							</div>
						<?php endforeach; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Quick check whether OAuth tables exist.
		 */
		protected static function oauth_tables_exist(): bool
		{
			global $wpdb;
			$table = $wpdb->prefix . 'sentinel_oauth_clients';
			$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $found === $table;
		}

		/**
		 * Render the per-client OAuth permissions editor (subview of the OAuth tab).
		 *
		 * @param string $client_id OAuth client_id.
		 */
		private function render_oauth_permissions_view(string $client_id): void
		{
			$client = $client_id ? SENTINEL_OAuth_DB::get_client_by_id($client_id) : null;
			$back_url = add_query_arg(
				array(
					'page' => 'sentinel-settings',
					'tab'  => 'oauth',
				),
				admin_url('options-general.php')
			);

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
			$selected  = is_array($current) ? $current : array();
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
				return array();
			}

			$grouped = array();
			$labels  = array(
				'wc'   => __('WooCommerce (read-only)', 'mcp-sentinel'),
				'i18n' => __('Multilingual (read-only)', 'mcp-sentinel'),
				'seo'  => __('SEO (read-only)', 'mcp-sentinel'),
			);

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

		/**
		 * Render the Activity Log tab.
		 */
		private function render_tab_activity(): void
		{
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters from URL.
			$page      = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
			$client_id = isset($_GET['filter_client']) ? sanitize_text_field(wp_unslash((string) $_GET['filter_client'])) : '';
			$status    = isset($_GET['filter_status']) ? sanitize_key((string) $_GET['filter_status']) : '';
			$ability   = isset($_GET['filter_ability']) ? sanitize_text_field(wp_unslash((string) $_GET['filter_ability'])) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$result = SENTINEL_Activity_Log::query(
				array(
					'page'      => $page,
					'per_page'  => 50,
					'client_id' => $client_id,
					'status'    => $status,
					'ability'   => $ability,
				)
			);

			$total       = (int) $result['total'];
			$total_pages = max(1, (int) ceil($total / 50));
			$base_url    = add_query_arg(
				array(
					'page' => 'sentinel-settings',
					'tab'  => 'activity',
				),
				admin_url('options-general.php')
			);

		?>
			<div class="card" style="max-width:900px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Activity Log</h2>
				<p>Last 30 days of MCP tool calls. Retention is fixed at 30 days; older entries are purged daily.</p>

				<form method="get" style="margin:10px 0;">
					<input type="hidden" name="page" value="sentinel-settings">
					<input type="hidden" name="tab" value="activity">
					<input type="text" name="filter_client" value="<?php echo esc_attr($client_id); ?>" placeholder="Client ID" style="width:240px;">
					<input type="text" name="filter_ability" value="<?php echo esc_attr($ability); ?>" placeholder="Ability slug" style="width:240px;">
					<select name="filter_status">
						<option value="">All statuses</option>
						<?php foreach (array('ok', 'error', 'denied', 'rate_limited') as $st) : ?>
							<option value="<?php echo esc_attr($st); ?>" <?php selected($status, $st); ?>><?php echo esc_html($st); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button">Filter</button>
					<?php if ($client_id || $status || $ability) : ?>
						<a class="button" href="<?php echo esc_url($base_url); ?>">Clear</a>
					<?php endif; ?>
				</form>

				<table class="widefat striped">
					<thead>
						<tr>
							<th>Time (UTC)</th>
							<th>Client</th>
							<th>User</th>
							<th>Ability</th>
							<th>Status</th>
							<th>Duration</th>
							<th>Error</th>
							<th>IP</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($result['items'])) : ?>
							<tr>
								<td colspan="8" style="text-align:center;padding:20px;">No entries.</td>
							</tr>
						<?php else : ?>
							<?php foreach ($result['items'] as $row) : ?>
								<tr>
									<td><?php echo esc_html((string) $row['ts']); ?></td>
									<td><code><?php echo esc_html((string) ($row['oauth_client_id'] ?? '')); ?></code></td>
									<td><?php echo $row['user_id'] ? esc_html('#' . (int) $row['user_id']) : '&mdash;'; ?></td>
									<td><code><?php echo esc_html((string) $row['ability_slug']); ?></code></td>
									<td><?php echo esc_html((string) $row['status']); ?></td>
									<td><?php echo null !== $row['duration_ms'] ? esc_html((int) $row['duration_ms'] . ' ms') : '&mdash;'; ?></td>
									<td><?php echo $row['error_code'] ? esc_html((string) $row['error_code']) : '&mdash;'; ?></td>
									<td><?php echo $row['ip'] ? esc_html((string) $row['ip']) : '&mdash;'; ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<?php if ($total_pages > 1) : ?>
					<div style="margin-top:10px;">
						<?php
						$paginate = paginate_links(
							array(
								'base'      => add_query_arg('paged', '%#%', $base_url),
								'format'    => '',
								'current'   => $page,
								'total'     => $total_pages,
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							)
						);
						echo wp_kses_post((string) $paginate);
						?>
					</div>
				<?php endif; ?>

				<p style="margin-top:15px;color:#666;font-size:13px;">
					Need before/after diffs, rollback, full-text search and CSV export?
					<a href="<?php echo esc_url(SENTINEL_PREMIUM_PRODUCT_URL); ?>" target="_blank" rel="noopener">See Premium</a>.
				</p>
			</div>
		<?php
		}

		/**
		 * Render the Info tab: Site structure and registered abilities.
		 */
		private function render_tab_info(): void
		{
			$post_types = SENTINEL_Schema_Inspector::get_site_schema_summary();

		?>
			<!-- Site structure -->
			<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Site structure (<?php echo esc_html($post_types['total_cpts']); ?> content types)</h2>
				<p>This is what your AI assistant (Claude, ChatGPT, Copilot, etc.) will be able to view and manage automatically:</p>
				<table class="widefat striped" style="max-width:100%;">
					<thead>
						<tr>
							<th>Type</th>
							<th>Slug</th>
							<th>Taxonomies</th>
							<th>Meta fields</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($post_types['post_types'] as $pt) : ?>
							<tr>
								<td><strong><?php echo esc_html($pt['label']); ?></strong></td>
								<td><code><?php echo esc_html($pt['name']); ?></code></td>
								<td>
									<?php
									$tax_list = implode(', ', $pt['taxonomies']);
									echo esc_html($tax_list ? $tax_list : '—');
									?>
								</td>
								<td><?php echo (int) $pt['meta_field_count']; ?> fields</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Registered abilities (dynamic) -->
			<?php if (function_exists('wp_get_abilities') && function_exists('wp_get_ability_categories')) : ?>
				<?php
				// Only show this plugin's abilities (sentinel/* namespace).
				$all_abilities = array_filter(
					wp_get_abilities(),
					function ($ability) {
						return 0 === strpos($ability->get_name(), 'sentinel/');
					}
				);

				$all_categories = wp_get_ability_categories();

				// Group abilities by category.
				$grouped    = array();
				$no_cat     = array();
				$cat_labels = array();

				foreach ($all_categories as $cat) {
					$cat_labels[$cat->get_slug()] = $cat->get_label();
					$grouped[$cat->get_slug()]    = array();
				}

				foreach ($all_abilities as $ability) {
					$cat_slug = $ability->get_category();
					if ($cat_slug && isset($grouped[$cat_slug])) {
						$grouped[$cat_slug][] = $ability;
					} else {
						$no_cat[] = $ability;
					}
				}

				// Remove empty categories.
				$grouped = array_filter($grouped);

				$total_count = count($all_abilities);
				?>
				<div class="card" style="max-width:700px;padding:15px;">
					<h2 style="margin-top:0;">Registered MCP abilities (<?php echo (int) $total_count; ?>)</h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th>Ability</th>
								<th>Description</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($grouped as $cat_slug => $abilities) : ?>
								<tr>
									<td colspan="2" style="background:#f0f0f1;font-weight:bold;">
										<?php echo esc_html($cat_labels[$cat_slug] ?? $cat_slug); ?>
									</td>
								</tr>
								<?php foreach ($abilities as $ability) : ?>
									<tr>
										<td><code><?php echo esc_html($ability->get_name()); ?></code></td>
										<td><?php echo esc_html($ability->get_description()); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endforeach; ?>
							<?php if (! empty($no_cat)) : ?>
								<tr>
									<td colspan="2" style="background:#f0f0f1;font-weight:bold;">Other</td>
								</tr>
								<?php foreach ($no_cat as $ability) : ?>
									<tr>
										<td><code><?php echo esc_html($ability->get_name()); ?></code></td>
										<td><?php echo esc_html($ability->get_description()); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<div class="card" style="max-width:700px;padding:15px;">
					<h2 style="margin-top:0;">Registered MCP abilities</h2>
					<p>The WordPress Abilities API is not available. Install the MCP Adapter to see registered abilities.</p>
				</div>
			<?php endif; ?>
		<?php
		}

		/**
		 * Render the Go Premium tab: Feature list and CTA.
		 */
		private function render_tab_premium(): void
		{
			$premium_url = SENTINEL_PREMIUM_PRODUCT_URL;
			$catalog     = mcpcomal_load_premium_features_catalog();

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
			$keyword = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
			$cat_filter = isset($_GET['cat']) ? sanitize_key((string) $_GET['cat']) : '';

			$filtered     = mcpcomal_filter_premium_features(
				$catalog,
				'' === $cat_filter ? null : $cat_filter,
				'' === $keyword ? null : $keyword
			);
			$total_filter = mcpcomal_count_features($filtered);
			$total_all    = mcpcomal_count_features($catalog);
			$cat_count    = isset($catalog['categories']) && is_array($catalog['categories']) ? count($catalog['categories']) : 0;

		?>
			<div class="card" style="max-width:900px;margin-bottom:20px;padding:20px 24px;">
				<h2 style="margin-top:0;font-size:1.4em;">
					<?php esc_html_e('Unlock the Full Power of MCP Content Manager', 'mcp-sentinel'); ?>
				</h2>
				<p style="font-size:14px;color:#50575e;">
					<?php
					printf(
						/* translators: 1: total feature count, 2: category count */
						esc_html__('Premium ships %1$s+ abilities across %2$s categories. Manage your entire WordPress site — content, store, security and more — from Claude, ChatGPT, Copilot or any MCP client.', 'mcp-sentinel'),
						esc_html((string) $total_all),
						esc_html((string) $cat_count)
					);
					?>
				</p>

				<p style="text-align:center;margin:16px 0 8px;">
					<a href="<?php echo esc_url($premium_url); ?>" target="_blank" rel="noopener noreferrer"
						class="button button-primary button-hero" style="font-size:16px;padding:8px 32px;">
						<?php esc_html_e('Get MCP Content Manager Premium', 'mcp-sentinel'); ?> &rarr;
					</a>
				</p>

				<form method="get" style="margin:10px 0 20px;">
					<input type="hidden" name="page" value="sentinel-settings">
					<input type="hidden" name="tab" value="premium">
					<select name="cat">
						<option value=""><?php esc_html_e('All categories', 'mcp-sentinel'); ?></option>
						<?php foreach ((array) ($catalog['categories'] ?? array()) as $cat) : ?>
							<option value="<?php echo esc_attr((string) ($cat['slug'] ?? '')); ?>" <?php selected($cat_filter, (string) ($cat['slug'] ?? '')); ?>>
								<?php echo esc_html((string) ($cat['label'] ?? $cat['slug'] ?? '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<input type="text" name="q" value="<?php echo esc_attr($keyword); ?>" placeholder="<?php esc_attr_e('Search features…', 'mcp-sentinel'); ?>" style="width:240px;">
					<button type="submit" class="button"><?php esc_html_e('Filter', 'mcp-sentinel'); ?></button>
					<?php if ($cat_filter || $keyword) : ?>
						<a class="button" href="<?php echo esc_url(admin_url('options-general.php?page=sentinel-settings&tab=premium')); ?>">
							<?php esc_html_e('Clear', 'mcp-sentinel'); ?>
						</a>
					<?php endif; ?>
				</form>

				<?php if (($cat_filter || $keyword) && 0 === $total_filter) : ?>
					<p><?php esc_html_e('No features match the current filters.', 'mcp-sentinel'); ?></p>
				<?php endif; ?>

				<?php foreach ((array) ($filtered['categories'] ?? array()) as $cat) : ?>
					<details open style="border:1px solid #dcdcde;border-radius:4px;margin-bottom:10px;padding:10px;">
						<summary style="font-weight:bold;font-size:14px;cursor:pointer;">
							<?php echo esc_html((string) ($cat['label'] ?? '')); ?>
							<span style="color:#50575e;font-weight:normal;">
								(<?php echo esc_html((string) count((array) ($cat['features'] ?? array()))); ?>)
							</span>
						</summary>
						<?php if (! empty($cat['summary'])) : ?>
							<p style="color:#50575e;font-size:13px;margin:6px 0;"><?php echo esc_html((string) $cat['summary']); ?></p>
						<?php endif; ?>
						<table class="widefat" style="margin-top:10px;">
							<tbody>
								<?php foreach ((array) ($cat['features'] ?? array()) as $feat) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html((string) ($feat['label'] ?? '')); ?></strong>
											<?php if (! empty($feat['description'])) : ?>
												<br><span style="color:#50575e;font-size:13px;"><?php echo esc_html((string) $feat['description']); ?></span>
											<?php endif; ?>
											<?php if (! empty($feat['example_prompt'])) : ?>
												<details style="margin-top:6px;">
													<summary style="cursor:pointer;color:#2271b1;"><?php esc_html_e('Example prompt', 'mcp-sentinel'); ?></summary>
													<code style="display:block;background:#f6f7f7;padding:6px;margin-top:4px;font-size:12px;"><?php echo esc_html((string) $feat['example_prompt']); ?></code>
												</details>
											<?php endif; ?>
										</td>
										<td style="width:120px;text-align:right;">
											<?php
											$learn_more = ! empty($feat['learn_more_url']) ? (string) $feat['learn_more_url'] : $premium_url;
											?>
											<a class="button" href="<?php echo esc_url($learn_more); ?>" target="_blank" rel="noopener noreferrer">
												<?php esc_html_e('Learn more', 'mcp-sentinel'); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</details>
				<?php endforeach; ?>

				<p style="text-align:center;margin:20px 0 8px;">
					<a href="<?php echo esc_url($premium_url); ?>" target="_blank" rel="noopener noreferrer"
						class="button button-primary button-hero" style="font-size:16px;padding:8px 32px;">
						<?php esc_html_e('Get MCP Content Manager Premium', 'mcp-sentinel'); ?> &rarr;
					</a>
				</p>
				<p style="text-align:center;color:#50575e;font-size:13px;">
					<?php esc_html_e('Your Lite settings and data will be preserved automatically when you upgrade.', 'mcp-sentinel'); ?>
				</p>
			</div>
<?php
		}
	}
}
