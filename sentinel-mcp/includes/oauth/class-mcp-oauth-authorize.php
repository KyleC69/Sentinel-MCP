<?php

/**
 * OAuth 2.1 Authorization Endpoint.
 *
 * Handles the authorization page display and form processing.
 * Validates client, redirect_uri, PKCE, user session, and generates
 * authorization codes.
 *
 * @package    SENTINEL
 * @author     José Conti <j.conti@joseconti.com>
 * @copyright  2026 José Conti
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 */

defined('ABSPATH') || exit;

if (! class_exists('SENTINEL_OAuth_Authorize')) {

	/**
	 * Authorization endpoint handler for the OAuth 2.1 subsystem.
	 */
	class SENTINEL_OAuth_Authorize
	{

		/**
		 * GET /authorize -- validate params, check login, render consent page.
		 *
		 * @param WP_REST_Request $request The incoming REST request.
		 * @return WP_Error|void
		 */
		public static function handle_get(WP_REST_Request $request)
		{
			$params = self::extract_params($request);

			// Validate response_type.
			if ('code' !== $params['response_type']) {
				return new WP_Error(
					'unsupported_response_type',
					'Only response_type=code is supported.',
					array('status' => 400)
				);
			}

			// Validate client exists.
			$client = SENTINEL_OAuth_DB::get_client_by_id($params['client_id']);
			if (! $client) {
				return new WP_Error(
					'invalid_client',
					'Unknown client_id.',
					array('status' => 400)
				);
			}

			// Validate redirect_uri matches registered URIs.
			if (! in_array($params['redirect_uri'], $client['redirect_uris'], true)) {
				return new WP_Error(
					'invalid_redirect_uri',
					'redirect_uri does not match registered URIs.',
					array('status' => 400)
				);
			}

			// Validate PKCE.
			if ('S256' !== $params['code_challenge_method'] || empty($params['code_challenge'])) {
				return new WP_Error(
					'invalid_request',
					'PKCE with S256 is required.',
					array('status' => 400)
				);
			}

			// WordPress REST API strips cookie auth when there is no wp_rest nonce.
			// Since this is a browser-based redirect flow, restore the user from
			// the logged_in cookie directly.
			self::restore_user_from_cookie();

			// If user is not logged in, redirect to wp-login.php.
			if (! is_user_logged_in()) {
				$current_url = rest_url('sentinel-auth/v1/authorize') . '?' . http_build_query($params);
				wp_safe_redirect(wp_login_url($current_url));
				exit;
			}

			// Render authorization page.
			self::render_authorize_page($client, $params);
			exit;
		}

		/**
		 * POST /authorize -- process form submission (approve or deny).
		 *
		 * @param WP_REST_Request $request The incoming REST request.
		 * @return WP_Error|void
		 */
		public static function handle_post(WP_REST_Request $request)
		{
			// Restore user from cookie (REST API strips cookie auth without wp_rest nonce).
			self::restore_user_from_cookie();

			// Verify our custom nonce (named sentinel_oauth_nonce to avoid conflict
			// with the REST API's reserved _wpnonce parameter).
			$nonce = sanitize_text_field($request->get_param('sentinel_oauth_nonce') ?? '');
			if (! wp_verify_nonce($nonce, 'sentinel_oauth_authorize')) {
				return new WP_Error(
					'invalid_nonce',
					'Security check failed.',
					array('status' => 403)
				);
			}

			// Must be logged in.
			if (! is_user_logged_in()) {
				return new WP_Error(
					'unauthorized',
					'User must be logged in.',
					array('status' => 401)
				);
			}

			$action                = sanitize_text_field($request->get_param('action') ?? '');
			$client_id             = sanitize_text_field($request->get_param('client_id') ?? '');
			$redirect_uri          = esc_url_raw($request->get_param('redirect_uri') ?? '');
			$scope                 = sanitize_text_field($request->get_param('scope') ?? '');
			$state                 = sanitize_text_field($request->get_param('state') ?? '');
			$code_challenge        = sanitize_text_field($request->get_param('code_challenge') ?? '');
			$code_challenge_method = sanitize_text_field($request->get_param('code_challenge_method') ?? '');

			// Re-validate client and redirect_uri (defense in depth).
			$client = SENTINEL_OAuth_DB::get_client_by_id($client_id);
			if (! $client || ! in_array($redirect_uri, $client['redirect_uris'], true)) {
				return new WP_Error(
					'invalid_client',
					'Invalid client or redirect URI.',
					array('status' => 400)
				);
			}

			// User denied authorization.
			if ('authorize' !== $action) {
				$deny_url = add_query_arg(
					array(
						'error'             => 'access_denied',
						'error_description' => 'User denied the authorization request.',
						'state'             => $state,
					),
					$redirect_uri
				);
				// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to external client redirect_uri validated above.
				wp_redirect($deny_url);
				exit;
			}

			// Generate authorization code.
			$code = SENTINEL_OAuth_DB::insert_code(
				array(
					'client_id'             => $client_id,
					'user_id'               => get_current_user_id(),
					'redirect_uri'          => $redirect_uri,
					'scope'                 => $scope,
					'code_challenge'        => $code_challenge,
					'code_challenge_method' => $code_challenge_method,
				)
			);

			if (! $code) {
				return new WP_Error(
					'server_error',
					'Could not generate authorization code.',
					array('status' => 500)
				);
			}

			// Redirect back to the client with the authorization code.
			$success_url = add_query_arg(
				array(
					'code'  => $code,
					'state' => $state,
				),
				$redirect_uri
			);
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to external client redirect_uri validated above.
			wp_redirect($success_url);
			exit;
		}

		/**
		 * Restore the current user from the logged_in cookie.
		 *
		 * WordPress REST API intentionally clears cookie-based authentication
		 * when there is no valid wp_rest nonce (CSRF protection). Since the
		 * OAuth authorize flow is a browser-based redirect, there is no nonce
		 * in the initial GET request. This method validates the cookie directly
		 * and restores the user so is_user_logged_in() works correctly.
		 *
		 * @return void
		 */
		private static function restore_user_from_cookie(): void
		{
			if (is_user_logged_in()) {
				return;
			}

			// wp_set_current_user() is required here because the WordPress REST API
			// intentionally strips cookie-based authentication when the wp_rest nonce
			// is missing (CSRF protection). The OAuth authorize endpoint is a
			// browser-based redirect, so there is no nonce on the initial GET.
			// We validate the logged_in cookie directly via wp_validate_auth_cookie()
			// and then restore the user session. This does NOT create or log in users --
			// it only restores the already-authenticated session for the consent page.
			$user_id = wp_validate_auth_cookie('', 'logged_in');
			if ($user_id) {
				wp_set_current_user($user_id);
			}
		}

		/**
		 * Extract and sanitize authorization parameters from the request.
		 *
		 * @param WP_REST_Request $request The incoming REST request.
		 * @return array Sanitized parameter array.
		 */
		private static function extract_params(WP_REST_Request $request): array
		{
			return array(
				'response_type'         => sanitize_text_field($request->get_param('response_type') ?? ''),
				'client_id'             => sanitize_text_field($request->get_param('client_id') ?? ''),
				'redirect_uri'          => esc_url_raw($request->get_param('redirect_uri') ?? ''),
				'scope'                 => sanitize_text_field($request->get_param('scope') ?? 'mcp:tools'),
				'state'                 => sanitize_text_field($request->get_param('state') ?? ''),
				'code_challenge'        => sanitize_text_field($request->get_param('code_challenge') ?? ''),
				'code_challenge_method' => sanitize_text_field($request->get_param('code_challenge_method') ?? ''),
			);
		}

		/**
		 * Render the authorization consent page HTML.
		 *
		 * @param array $client The OAuth client data.
		 * @param array $params The authorization request parameters.
		 * @return void
		 */
		private static function render_authorize_page(array $client, array $params): void
		{
			$current_user = wp_get_current_user();
			$site_name    = get_bloginfo('name');
			$site_icon    = get_site_icon_url(64);
			$nonce        = wp_create_nonce('sentinel_oauth_authorize');
			$form_action  = rest_url('sentinel-auth/v1/authorize');

			$scope_labels = array(
				'mcp:tools' => __('Use MCP tools to manage content', 'mcp-sentinel'),
				'mcp:read'  => __('Read content from your site', 'mcp-sentinel'),
				'mcp:write' => __('Create and modify content on your site', 'mcp-sentinel'),
			);

			$requested_scopes = array_map('trim', explode(' ', $params['scope']));

			SENTINEL_OAuth_Server::send_cors_headers();
			status_header(200);
			header('Content-Type: text/html; charset=utf-8');

?>
			<!DOCTYPE html>
			<html <?php language_attributes(); ?>>

			<head>
				<meta charset="<?php bloginfo('charset'); ?>" />
				<meta name="viewport" content="width=device-width, initial-scale=1" />
				<title>
					<?php
					/* translators: %s: client application name */
					echo esc_html(sprintf(__('Authorize %s', 'mcp-sentinel'), $client['client_name']) . ' — ' . $site_name);
					?>
				</title>
				<?php
				wp_register_style(
					'sentinel-oauth-authorize',
					plugins_url('assets/css/oauth-authorize.css', dirname(__DIR__)),
					array(),
					SENTINEL_VERSION
				);
				wp_print_styles('sentinel-oauth-authorize');
				?>
			</head>

			<body>
				<div class="sentinel-oauth-card">
					<div class="sentinel-oauth-header">
						<?php if ($site_icon) : ?>
							<img src="<?php echo esc_url($site_icon); ?>" alt="<?php echo esc_attr($site_name); ?>" />
						<?php endif; ?>
						<h1><?php echo esc_html($site_name); ?></h1>
						<p>
							<?php
							printf(
								/* translators: %s: client application name */
								esc_html__('The application %s is requesting access to your site.', 'mcp-sentinel'),
								'<strong>' . esc_html($client['client_name']) . '</strong>'
							);
							?>
						</p>
					</div>

					<div class="sentinel-oauth-scopes">
						<h3><?php esc_html_e('Requested permissions', 'mcp-sentinel'); ?></h3>
						<ul>
							<?php foreach ($requested_scopes as $scope) : ?>
								<?php if (isset($scope_labels[$scope])) : ?>
									<li><?php echo esc_html($scope_labels[$scope]); ?></li>
								<?php else : ?>
									<li><?php echo esc_html($scope); ?></li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					</div>

					<p class="sentinel-oauth-user">
						<?php
						printf(
							/* translators: %s: current user display name */
							esc_html__('Logged in as %s', 'mcp-sentinel'),
							'<strong>' . esc_html($current_user->display_name) . '</strong>'
						);
						?>
					</p>

					<form method="post" action="<?php echo esc_url($form_action); ?>">
						<input type="hidden" name="sentinel_oauth_nonce" value="<?php echo esc_attr($nonce); ?>" />
						<input type="hidden" name="client_id" value="<?php echo esc_attr($params['client_id']); ?>" />
						<input type="hidden" name="redirect_uri" value="<?php echo esc_url($params['redirect_uri']); ?>" />
						<input type="hidden" name="scope" value="<?php echo esc_attr($params['scope']); ?>" />
						<input type="hidden" name="state" value="<?php echo esc_attr($params['state']); ?>" />
						<input type="hidden" name="code_challenge" value="<?php echo esc_attr($params['code_challenge']); ?>" />
						<input type="hidden" name="code_challenge_method" value="<?php echo esc_attr($params['code_challenge_method']); ?>" />

						<div class="sentinel-oauth-actions">
							<button type="submit" name="action" value="deny" class="sentinel-oauth-btn-deny">
								<?php esc_html_e('Deny', 'mcp-sentinel'); ?>
							</button>
							<button type="submit" name="action" value="authorize" class="sentinel-oauth-btn-authorize">
								<?php esc_html_e('Authorize', 'mcp-sentinel'); ?>
							</button>
						</div>
					</form>
				</div>
			</body>

			</html>
<?php
		}
	}
}
