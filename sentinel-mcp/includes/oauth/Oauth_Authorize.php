<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * OAuth 2.1 Authorization Endpoint.
 *
 * Handles the authorization page display and form processing.
 * Validates client, redirect_uri, PKCE, user session, and generates
 * authorization codes.
 * *
 * *
 * *
 * *  TODO: enforce per-client scopes
 * *  Add basic rate limit and logging
 * *  add feature flag for loopback
 * *
 * *
 *
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Authorization endpoint handler for the OAuth 2.1 subsystem.
 */
class OAuth_Authorize
{

	/**
	 * GET /authorize -- validate params, check login, render consent page.
	 *
	 * @param \WP_REST_Request $request The incoming REST request.
	 * @return \WP_Error|void
	 */
	public static function handle_get(\WP_REST_Request $request)
	{
		$params = self::extract_params($request);

		// Only the authorization code flow is supported.
		if ('code' !== $params['response_type']) {
			return new \WP_Error(
				'unsupported_response_type',
				'Only response_type=code is supported.',
				array('status' => 400)
			);
		}

		// In production, client_id must be a real registered client ID.
		$client = OAuth_DB::get_client_by_id($params['client_id']);
		if (! $client) {
			return new \WP_Error(
				'invalid_client',
				'Unknown client_id.',
				array('status' => 400)
			);
		}

		// Canonicalize to the stored client_id.
		$params['client_id'] = $client['client_id'];

		// Strict redirect_uri validation (now loopback-aware once you patch validate_redirect_uri()).
		$redirect_uri_result = self::validate_redirect_uri($params['redirect_uri'], $client);
		if (is_wp_error($redirect_uri_result)) {
			return $redirect_uri_result;
		}
		$params['redirect_uri'] = $redirect_uri_result;

		// Enforce PKCE with S256.
		if ('S256' !== $params['code_challenge_method'] || empty($params['code_challenge'])) {
			return new \WP_Error(
				'invalid_request',
				'PKCE with S256 is required.',
				array('status' => 400)
			);
		}

		// Restore user from logged_in cookie (REST strips cookie auth without wp_rest nonce).
		self::restore_user_from_cookie();

		// If user is not logged in, send them to wp-login.php and bounce back here.
		if (! is_user_logged_in()) {
			$rest_prefix = rest_get_url_prefix();
			$current_url = home_url("{$rest_prefix}/sentinel-auth/v1/authorize") . '?' . http_build_query($params);
			wp_safe_redirect(wp_login_url($current_url));
			exit;
		}

		// Render authorization (consent) page.
		self::render_authorize_page($client, $params);
		exit;
	}


	/**
	 * POST /authorize -- process form submission (approve or deny).
	 *
	 * @param \WP_REST_Request $request The incoming REST request.
	 * @return \WP_Error|void
	 */
	public static function handle_post(\WP_REST_Request $request)
	{
		// Restore user from cookie (REST API strips cookie auth without wp_rest nonce).
		self::restore_user_from_cookie();

		// Verify our custom nonce (named sentinel_oauth_nonce to avoid conflict
		// with the REST API's reserved _wpnonce parameter).
		$nonce = sanitize_text_field($request->get_param('sentinel_oauth_nonce') ?? '');
		if (! wp_verify_nonce($nonce, 'sentinel_oauth_authorize')) {
			return new \WP_Error(
				'invalid_nonce',
				'Security check failed.',
				array('status' => 403)
			);
		}

		// Must be logged in to approve or deny.
		if (! is_user_logged_in()) {
			return new \WP_Error(
				'unauthorized',
				'User must be logged in.',
				array('status' => 401)
			);
		}

		$action                = sanitize_text_field($request->get_param('action') ?? '');
		$client_id_param       = sanitize_text_field($request->get_param('client_id') ?? '');
		$redirect_uri_param    = (string) ($request->get_param('redirect_uri') ?? '');
		$scope                 = sanitize_text_field($request->get_param('scope') ?? '');
		$state                 = sanitize_text_field($request->get_param('state') ?? '');
		$code_challenge        = sanitize_text_field($request->get_param('code_challenge') ?? '');
		$code_challenge_method = sanitize_text_field($request->get_param('code_challenge_method') ?? '');

		// In production, only look up by client_id (no name fallback).
		$client = OAuth_DB::get_client_by_id($client_id_param);
		if (! $client) {
			return new \WP_Error(
				'invalid_client',
				'Invalid client.',
				array('status' => 400)
			);
		}

		// Strict redirect_uri validation, same rules as GET.
		$redirect_uri_result = self::validate_redirect_uri($redirect_uri_param, $client);
		if (is_wp_error($redirect_uri_result)) {
			return $redirect_uri_result;
		}
		$redirect_uri = $redirect_uri_result;

		$client_id = $client['client_id'];

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
			// Redirecting to external client redirect_uri validated above.
			wp_redirect($deny_url);
			exit;
		}

		// Enforce PKCE again on POST (defense in depth).
		if ('S256' !== $code_challenge_method || empty($code_challenge)) {
			return new \WP_Error(
				'invalid_request',
				'PKCE with S256 is required.',
				array('status' => 400)
			);
		}

		// Generate authorization code.
		$code = OAuth_DB::insert_code(
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
			return new \WP_Error(
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
		// Redirecting to external client redirect_uri validated above.
		wp_redirect($success_url);
		exit;
	}

	/**
	 * Strictly validate a redirect_uri against the client's registered URIs.
	 *
	 * - Sanitizes the URI.
	 * - Requires scheme + host.
	 * - Enforces HTTPS.
	 * - Forbids loopback hosts (localhost, 127.0.0.1) for web clients.
	 * - Requires exact match against client['redirect_uris'].
	 *
	 * @param string $redirect_uri Raw redirect_uri from the request.
	 * @param array  $client       Client record from the database.
	 * @return string|\WP_Error     Validated redirect_uri or \WP_Error.
	 */




	private static function validate_redirect_uri(string $redirect_uri, array $client)
	{
		$redirect_uri = OAuth_Server::sanitize_redirect_uri($redirect_uri);


		//Missing Redirect
		if (empty($redirect_uri)) {
			return new \WP_Error(
				'invalid_redirect_uri',
				'Missing redirect_uri.',
				array('status' => 400)
			);
		}

		$parsed = wp_parse_url($redirect_uri);
		if (! isset($parsed['scheme'], $parsed['host'])) {
			return new \WP_Error(
				'invalid_redirect_uri',
				'Malformed redirect_uri.',
				array('status' => 400)
			);
		}

		$scheme = strtolower($parsed['scheme']);
		$host   = strtolower($parsed['host']);





		// Loopback support for native clients (VS Code, Insomnia, etc.).
		$loopback_hosts = array('127.0.0.1', 'localhost');
		if (in_array($host, $loopback_hosts, true)) {
			// Loopback must use http.
			if ('http' !== $scheme) {
				return new \WP_Error(
					'invalid_redirect_uri',
					'Loopback redirect URIs must use http.',
					array('status' => 400)
				);
			}

			if (empty($client['redirect_uris']) || ! is_array($client['redirect_uris'])) {
				return new \WP_Error(
					'invalid_redirect_uri',
					'Client has no registered redirect URIs.',
					array('status' => 400)
				);
			}

			// For loopback, match on scheme + host (+ optional path), ignore port.
			$normalized_requested = $scheme . '://' . $host . (isset($parsed['path']) ? $parsed['path'] : '/');

			$allowed = false;
			foreach ($client['redirect_uris'] as $registered) {
				$reg = wp_parse_url($registered);
				if (! isset($reg['scheme'], $reg['host'])) {
					continue;
				}

				$reg_scheme = strtolower($reg['scheme']);
				$reg_host   = strtolower($reg['host']);
				$reg_path   = isset($reg['path']) ? $reg['path'] : '/';

				$normalized_registered = $reg_scheme . '://' . $reg_host . $reg_path;

				if ($normalized_registered === $normalized_requested) {
					$allowed = true;
					break;
				}
			}

			if (! $allowed) {
				return new \WP_Error(
					'invalid_redirect_uri',
					'redirect_uri does not match registered URIs.',
					array('status' => 400)
				);
			}

			return $redirect_uri;
		}

		// Non-loopback clients must use HTTPS.
		if ('https' !== $scheme) {
			return new \WP_Error(
				'invalid_redirect_uri',
				'redirect_uri must use HTTPS.',
				array('status' => 400)
			);
		}

		if (empty($client['redirect_uris']) || ! is_array($client['redirect_uris'])) {
			return new \WP_Error(
				'invalid_redirect_uri',
				'Client has no registered redirect URIs.',
				array('status' => 400)
			);
		}

		if (! in_array($redirect_uri, $client['redirect_uris'], true)) {
			return new \WP_Error(
				'invalid_redirect_uri',
				'redirect_uri does not match registered URIs.',
				array('status' => 400)
			);
		}

		return $redirect_uri;
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

		$user_id = wp_validate_auth_cookie('', 'logged_in');
		if ($user_id) {
			wp_set_current_user($user_id);
		}
	}

	/**
	 * Extract and sanitize authorization parameters from the request.
	 *
	 * @param \WP_REST_Request $request The incoming REST request.
	 * @return array Sanitized parameter array.
	 */
	private static function extract_params(\WP_REST_Request $request): array
	{
		return array(
			'response_type'         => sanitize_text_field($request->get_param('response_type') ?? ''),
			'client_id'             => sanitize_text_field($request->get_param('client_id') ?? ''),
			'redirect_uri'          => OAuth_Server::sanitize_redirect_uri($request->get_param('redirect_uri') ?? ''),
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

		OAuth_Server::send_cors_headers();
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
