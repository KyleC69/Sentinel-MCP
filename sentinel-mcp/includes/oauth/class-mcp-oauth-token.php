<?php

/**
 * OAuth 2.1 Token Endpoint.
 *
 * Handles authorization code exchange, refresh token flow,
 * token revocation, and PKCE verification.
 *
 * @package    SENTINEL
 * @author     José Conti <j.conti@joseconti.com>
 * @copyright  2026 José Conti
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @link       https://github.com/KyleC69/Sentinel-MCP
 **/


defined('ABSPATH') || exit;

if (! class_exists('SENTINEL_OAuth_Token')) {

	/**
	 * Token endpoint handler for the OAuth 2.1 subsystem.
	 */
	class SENTINEL_OAuth_Token
	{

		/**
		 * Main dispatcher -- routes by grant_type.
		 *
		 * @param WP_REST_Request $request The incoming REST request.
		 * @return WP_REST_Response|WP_Error
		 */
		public static function handle(WP_REST_Request $request): WP_REST_Response|WP_Error
		{
			$grant_type = sanitize_text_field($request->get_param('grant_type') ?? '');

			// Opportunistic cleanup.
			SENTINEL_OAuth_DB::cleanup_expired_codes();

			return match ($grant_type) {
				'authorization_code' => self::handle_authorization_code($request),
				'refresh_token'      => self::handle_refresh_token($request),
				default              => self::oauth_error('unsupported_grant_type', 'grant_type must be "authorization_code" or "refresh_token".'),
			};
		}

		/**
		 * Exchange an authorization code for tokens.
		 *
		 * @param WP_REST_Request $request The incoming REST request.
		 * @return WP_REST_Response|WP_Error
		 */
		private static function handle_authorization_code(WP_REST_Request $request): WP_REST_Response|WP_Error
		{
			$code          = sanitize_text_field($request->get_param('code') ?? '');
			$redirect_uri  = esc_url_raw($request->get_param('redirect_uri') ?? '');
			$client_id     = sanitize_text_field($request->get_param('client_id') ?? '');
			$code_verifier = sanitize_text_field($request->get_param('code_verifier') ?? '');

			if (empty($code) || empty($client_id) || empty($code_verifier)) {
				return self::oauth_error('invalid_request', 'Missing required parameters: code, client_id, code_verifier.');
			}

			// Generic error message per RFC 6749 to prevent state enumeration.
			$grant_error = 'The provided authorization grant is invalid, expired, or revoked.';

			// 1. Look up the authorization code.
			$code_row = SENTINEL_OAuth_DB::get_code($code);
			if (! $code_row) {
				return self::oauth_error('invalid_grant', $grant_error);
			}

			// 2. If already used -- revoke all tokens for this client (RFC 6749 Section 4.1.2).
			if (1 === (int) $code_row['used']) {
				SENTINEL_OAuth_DB::revoke_all_for_client($code_row['client_id']);
				return self::oauth_error('invalid_grant', $grant_error);
			}

			// 3. Check expiration BEFORE marking as used.
			if (strtotime($code_row['expires_at']) < time()) {
				return self::oauth_error('invalid_grant', $grant_error);
			}

			// 4. Mark as used IMMEDIATELY (single-use enforcement).
			SENTINEL_OAuth_DB::mark_code_used($code);

			// 5. Verify client_id matches (timing-safe comparison).
			if (! hash_equals($code_row['client_id'], $client_id)) {
				return self::oauth_error('invalid_grant', $grant_error);
			}

			// 6. Verify redirect_uri matches (timing-safe comparison).
			if (! hash_equals($code_row['redirect_uri'], $redirect_uri)) {
				return self::oauth_error('invalid_grant', $grant_error);
			}

			// 7. PKCE verification (already timing-safe via hash_equals internally).
			if (! self::verify_pkce($code_verifier, $code_row['code_challenge'], $code_row['code_challenge_method'])) {
				return self::oauth_error('invalid_grant', $grant_error);
			}

			// 8. Generate tokens.
			$token_data = SENTINEL_OAuth_DB::insert_token(
				array(
					'client_id' => $client_id,
					'user_id'   => (int) $code_row['user_id'],
					'scope'     => $code_row['scope'],
				)
			);

			if (! $token_data) {
				return self::oauth_error('server_error', 'Could not generate tokens.', 500);
			}

			return new WP_REST_Response(
				array(
					'access_token'  => $token_data['access_token'],
					'token_type'    => 'Bearer',
					'expires_in'    => $token_data['expires_in'],
					'refresh_token' => $token_data['refresh_token'],
					'scope'         => $token_data['scope'],
				),
				200
			);
		}

		/**
		 * Exchange a refresh token for a new token pair.
		 *
		 * @param WP_REST_Request $request The incoming REST request.
		 * @return WP_REST_Response|WP_Error
		 */
		private static function handle_refresh_token(WP_REST_Request $request): WP_REST_Response|WP_Error
		{
			$refresh_token = sanitize_text_field($request->get_param('refresh_token') ?? '');
			$client_id     = sanitize_text_field($request->get_param('client_id') ?? '');

			if (empty($refresh_token) || empty($client_id)) {
				return self::oauth_error('invalid_request', 'Missing required parameters: refresh_token, client_id.');
			}

			$refresh_hash = SENTINEL_OAuth_DB::hash_token($refresh_token);
			$token_row    = SENTINEL_OAuth_DB::get_token_by_refresh_hash($refresh_hash);

			if (! $token_row) {
				return self::oauth_error('invalid_grant', 'The provided refresh token is invalid, expired, or revoked.');
			}

			// Timing-safe comparison to prevent side-channel attacks.
			if (! hash_equals($token_row['client_id'], $client_id)) {
				return self::oauth_error('invalid_grant', 'The provided refresh token is invalid, expired, or revoked.');
			}

			// Revoke old token pair (rotation).
			SENTINEL_OAuth_DB::revoke_token_by_refresh_hash($refresh_hash);

			// Issue new token pair.
			$new_tokens = SENTINEL_OAuth_DB::insert_token(
				array(
					'client_id' => $client_id,
					'user_id'   => (int) $token_row['user_id'],
					'scope'     => $token_row['scope'],
				)
			);

			if (! $new_tokens) {
				return self::oauth_error('server_error', 'Could not generate tokens.', 500);
			}

			return new WP_REST_Response(
				array(
					'access_token'  => $new_tokens['access_token'],
					'token_type'    => 'Bearer',
					'expires_in'    => $new_tokens['expires_in'],
					'refresh_token' => $new_tokens['refresh_token'],
					'scope'         => $new_tokens['scope'],
				),
				200
			);
		}

		/**
		 * Handle token revocation (RFC 7009).
		 *
		 * @param WP_REST_Request $request The incoming REST request.
		 * @return WP_REST_Response
		 */
		public static function handle_revoke(WP_REST_Request $request): WP_REST_Response
		{
			$token      = sanitize_text_field($request->get_param('token') ?? '');
			$token_hint = sanitize_text_field($request->get_param('token_type_hint') ?? '');

			if (empty($token)) {
				// RFC 7009: always return 200 even for invalid/empty tokens.
				return new WP_REST_Response(null, 200);
			}

			$hash = SENTINEL_OAuth_DB::hash_token($token);

			if ('refresh_token' === $token_hint) {
				SENTINEL_OAuth_DB::revoke_token_by_refresh_hash($hash);
			} else {
				// Try access token first, then refresh.
				$revoked = SENTINEL_OAuth_DB::revoke_token_by_access_hash($hash);
				if (! $revoked) {
					SENTINEL_OAuth_DB::revoke_token_by_refresh_hash($hash);
				}
			}

			return new WP_REST_Response(null, 200);
		}

		/**
		 * Verify PKCE code_verifier against stored code_challenge.
		 *
		 * RFC 7636 Section 4.6:
		 * code_challenge = BASE64URL(SHA256(code_verifier))
		 *
		 * @param string $code_verifier    The PKCE code verifier from the client.
		 * @param string $stored_challenge The stored code challenge.
		 * @param string $method           The code challenge method (must be S256).
		 * @return bool
		 */
		public static function verify_pkce(string $code_verifier, string $stored_challenge, string $method): bool
		{
			if ('S256' !== $method) {
				return false;
			}

			$computed_challenge = rtrim(
				strtr(
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for PKCE S256 challenge computation per RFC 7636.
					base64_encode(hash('sha256', $code_verifier, true)),
					'+/',
					'-_'
				),
				'='
			);

			return hash_equals($stored_challenge, $computed_challenge);
		}

		/**
		 * Create a standardized OAuth error response.
		 *
		 * @param string $code        The error code.
		 * @param string $description The error description.
		 * @param int    $status      The HTTP status code.
		 * @return WP_Error
		 */
		private static function oauth_error(string $code, string $description, int $status = 400): WP_Error
		{
			return new WP_Error($code, $description, array('status' => $status));
		}
	}
}
