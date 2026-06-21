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
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Validates Bearer tokens on incoming MCP REST API requests.
 */
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
		// Priority 5 runs before WordPress core application-password/cookie
		// checks (priority 10) and before the MCP adapter registers its route
		// (priority 16), so the OAuth 2.1 Bearer challenge is returned first.
		add_filter('rest_authentication_errors', array(__CLASS__, 'authenticate'), 5);
	}

	/**
	 * Return the OAuth client_id for the current request, if any.
	 *
	 * Empty string when the request is not authenticated via OAuth (e.g. cookie or app password).
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
	 * Basic Auth / Application Passwords are handled by WordPress core at
	 * priority 10. This filter runs at priority 15 and acts as an OAuth 2.1
	 * Bearer-token fallback for MCP requests that did not authenticate via
	 * core mechanisms.
	 *
	 * @param \WP_Error|null|true $result Existing authentication result.
	 * @return \WP_Error|null|true
	 */
	public static function authenticate($result)
	{
		// Another auth mechanism already resolved — do not interfere.
		if (null !== $result) {
			return $result;
		}


		$request_uri = isset($_SERVER['REQUEST_URI'])
			? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
			: '';

		// Only intercept MCP adapter namespace requests.
		if (false === strpos($request_uri, '/mcp/')) {
			return $result;
		}

		// Do NOT intercept our own OAuth endpoints.
		if (false !== strpos($request_uri, '/sentinel-auth/')) {
			return $result;
		}

		$auth_header = self::get_authorization_header();

		// ***  NEW  ****
		// Core auth succeeded (Basic Auth, Application Passwords, cookie).
		// Leave the current user as-is and do not interfere.
		if (empty($auth_header)) {
			if (is_user_logged_in()) {
				OAuth_Server::send_cors_headers();
				return $result;
			}


			// No Bearer token present. Preserve any existing core error so that
			// Basic Auth clients receive the correct 401 from WordPress.
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

		// Must be Bearer scheme (RFC 6750 scheme is case-insensitive).
		if (0 !== stripos($auth_header, 'Bearer ')) {
			if (is_wp_error($result)) {
				return $result;
			}

			return new \WP_Error(
				'rest_invalid_auth',
				'Authorization header must use Bearer scheme.',
				array('status' => 401)
			);
		}

		$token      = trim(substr($auth_header, 7));
		$token_hash = OAuth_DB::hash_token($token);

		sentinel_debug_log(
			array(
				'oauth_auth_header_raw' => $auth_header,
				'oauth_token_length'    => strlen($token),
				'oauth_token_hash'      => $token_hash,
			)
		);

		$token_row  = OAuth_DB::get_token_by_access_hash($token_hash);

		if (! $token_row) {
			return new \WP_Error(
				'rest_invalid_token',
				'Invalid or expired access token.',
				array('status' => 401)
			);
		}

		// Set the WordPress current user to the token owner.
		// wp_set_current_user() is required here to authenticate API requests
		// via OAuth 2.1 Bearer tokens -- this is the same pattern WordPress core
		// uses for Application Passwords (see wp-includes/user.php). The token
		// has been validated above via OAuth_DB. This does NOT create
		// or log in users -- it maps a validated token to its owner.
		wp_set_current_user((int) $token_row['user_id']);

		sentinel_debug_log(
			array(
				'oauth_token_user_id'       => (int) $token_row['user_id'],
				'oauth_current_user_id'     => get_current_user_id(),
				'oauth_current_user_can_read' => current_user_can('read'),
			)
		);

		self::$current = array(
			'client_id' => isset($token_row['client_id']) ? (string) $token_row['client_id'] : '',
			'user_id'   => (int) $token_row['user_id'],
			'token_id'  => isset($token_row['id']) ? (int) $token_row['id'] : 0,
		);

		// Send CORS headers early, before the MCP Adapter processes the request.
		// This ensures headers are present even if the adapter sends its own
		// response (e.g., SSE streams) that bypasses rest_pre_serve_request.
		OAuth_Server::send_cors_headers();

		return true;
	}

	/**
	 * Retrieve the Authorization header from multiple sources.
	 *
	 * Different server configurations (Apache mod_php, mod_cgi, nginx)
	 * expose the header in different $_SERVER keys.
	 */
	private static function get_authorization_header(): string
	{
		$header = '';

		if (! empty($_SERVER['HTTP_AUTHORIZATION'])) {
			$header = (string) wp_unslash($_SERVER['HTTP_AUTHORIZATION']);
		} elseif (! empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
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

		// Preserve the opaque credential bytes. Only strip harmless whitespace;
		// do not use sanitize_text_field(), which can corrupt base64/hex tokens.
		$header = trim($header, " \t\n\r\0\x0B");

		// Reject anything that does not look like a Bearer header.
		if ('' !== $header && 0 !== stripos($header, 'Bearer ')) {
			return '';
		}

		return $header;
	}
}
