<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * OAuth 2.1 MCP Request Interceptor.
 *
 * Validates Bearer tokens on incoming MCP REST API requests
 * and sets the WordPress current user accordingly.
 * Returns 401 with discovery metadata when no token is present.
 *
 * @package    SENTINEL
 */

defined('ABSPATH') || exit;

class OAuth_Interceptor
{
	/**
	 * Resolved OAuth context for the current REST request.
	 *
	 * @var array{client_id?:string,user_id?:int,token_id?:int}
	 */
	protected static array $current = array();

	/**
	 * Register the authentication filter.
	 *
	 * @return void
	 */
	public static function init(): void
	{
		add_filter('rest_authentication_errors', array(__CLASS__, 'authenticate'), 5);
	}

	/**
	 * Return the OAuth client_id for the current request, if any.
	 */
	public static function get_current_client_id(): string
	{
		return isset(self::$current['client_id']) ? (string) self::$current['client_id'] : '';
	}

	/**
	 * Return the OAuth context for the current request.
	 *
	 * @return array{client_id?:string,user_id?:int,token_id?:int}
	 */
	public static function get_current_context(): array
	{
		return self::$current;
	}

	/**
	 * Authenticate MCP requests via Bearer token.
	 *
	 * @param \WP_Error|null|true $result Existing authentication result.
	 * @return \WP_Error|null|true
	 */
	public static function authenticate($result)
	{
		if (null !== $result) {
			return $result;
		}

		$request_uri = isset($_SERVER['REQUEST_URI'])
			? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
			: '';

		if (false === strpos($request_uri, '/mcp/')) {
			return $result;
		}

		if (false !== strpos($request_uri, '/sentinel-auth/')) {
			return $result;
		}

		$auth_header = self::get_authorization_header();

		if (empty($auth_header)) {
			if (is_user_logged_in()) {
				OAuth_Server::send_cors_headers();
				return $result;
			}

			sentinel_debug_log(
				array(
					'oauth_error'       => 'interceptor_no_auth_header',
					'oauth_request_uri' => $request_uri,
				)
			);

			OAuth_Server::send_cors_headers();
			header(
				sprintf(
					'WWW-Authenticate: Bearer resource_metadata="%s"',
					esc_url(home_url('/.well-known/oauth-protected-resource'))
				)
			);

			return new \WP_Error(
				'rest_not_logged_in',
				'Authentication required. Use OAuth 2.1 Bearer token.',
				array('status' => 401)
			);
		}

		// NEW: Case-insensitive, whitespace-tolerant Bearer scheme validation
		if (!preg_match('/^Bearer\s+(.+)$/i', $auth_header, $matches)) {
			sentinel_debug_log(
				array(
					'oauth_error'       => 'interceptor_non_bearer_scheme',
					'oauth_request_uri' => $request_uri,
					'oauth_auth_scheme' => $auth_header,
				)
			);

			OAuth_Server::send_cors_headers();
			header(
				sprintf(
					'WWW-Authenticate: Bearer resource_metadata="%s"',
					esc_url(home_url('/.well-known/oauth-protected-resource'))
				)
			);

			if (is_wp_error($result)) {
				return $result;
			}

			return new \WP_Error(
				'rest_invalid_auth',
				'Authorization header must use Bearer scheme.',
				array('status' => 401)
			);
		}

		// Extract token safely
		$token = trim($matches[1]);
		$token_hash = OAuth_DB::hash_token($token);

		sentinel_debug_log(
			array(
				'oauth_auth_header_raw' => $auth_header,
				'oauth_token_length'    => strlen($token),
				'oauth_token_hash'      => $token_hash,
			)
		);

		$token_row = OAuth_DB::get_token_by_access_hash($token_hash);

		if (!$token_row) {
			sentinel_debug_log(
				array(
					'oauth_error'      => 'interceptor_token_not_found',
					'oauth_token_hash' => $token_hash,
				)
			);

			OAuth_Server::send_cors_headers();
			header(
				sprintf(
					'WWW-Authenticate: Bearer resource_metadata="%s"',
					esc_url(home_url('/.well-known/oauth-protected-resource'))
				)
			);

			return new \WP_Error(
				'rest_invalid_token',
				'Invalid or expired access token.',
				array('status' => 401)
			);
		}

		wp_set_current_user((int) $token_row['user_id']);

		sentinel_debug_log(
			array(
				'oauth_token_user_id'        => (int) $token_row['user_id'],
				'oauth_current_user_id'      => get_current_user_id(),
				'oauth_current_user_can_read' => current_user_can('read'),
			)
		);

		self::$current = array(
			'client_id' => isset($token_row['client_id']) ? (string) $token_row['client_id'] : '',
			'user_id'   => (int) $token_row['user_id'],
			'token_id'  => isset($token_row['id']) ? (int) $token_row['id'] : 0,
		);

		OAuth_Server::send_cors_headers();

		return true;
	}

	/**
	 * Retrieve the Authorization header from multiple sources.
	 */
	private static function get_authorization_header(): string
	{
		$header = '';

		if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
			$header = (string) wp_unslash($_SERVER['HTTP_AUTHORIZATION']);
		} elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
			$header = (string) wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
		} elseif (function_exists('apache_request_headers')) {
			$headers = apache_request_headers();
			if (is_array($headers)) {
				foreach ($headers as $key => $value) {
					if ('authorization' === strtolower((string) $key)) {
						$header = (string) $value;
						break;
					}
				}
			}
		}

		// Trim only harmless whitespace
		$header = trim($header, " \t\n\r\0\x0B");

		// NEW: Do NOT reject non-matching headers here.
		// Let authenticate() handle validation with regex.
		return $header;
	}
}
