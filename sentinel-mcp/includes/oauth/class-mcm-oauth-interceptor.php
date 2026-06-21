<?php
/**
 * OAuth 2.1 MCP Request Interceptor.
 *
 * Validates Bearer tokens on incoming MCP REST API requests
 * and sets the WordPress current user accordingly.
 * Returns 401 with discovery metadata when no token is present.
 *
 * @package    MCPCOMAL
 * @author     José Conti <j.conti@joseconti.com>
 * @copyright  2026 José Conti
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/mcp-content-manager-for-wordpress/
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'MCPCOMAL_OAuth_Interceptor' ) ) {

	/**
	 * Validates Bearer tokens on incoming MCP REST API requests.
	 */
	class MCPCOMAL_OAuth_Interceptor {

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
		public static function init(): void {
			add_filter( 'rest_authentication_errors', array( __CLASS__, 'authenticate' ), 5 );
		}

		/**
		 * Return the OAuth client_id for the current request, if any.
		 *
		 * Empty string when the request is not authenticated via OAuth (e.g. cookie or app password).
		 */
		public static function get_current_client_id(): string
		{
			return isset( self::$current['client_id'] ) ? (string) self::$current['client_id'] : '';
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
		 * @param WP_Error|null|true $result Existing authentication result.
		 * @return WP_Error|null|true
		 */
		public static function authenticate( $result ) {
			// Another auth mechanism already resolved — do not interfere.
			if ( null !== $result ) {
				return $result;
			}

			$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

			// Only intercept MCP adapter namespace requests.
			if ( false === strpos( $request_uri, '/mcp/' ) ) {
				return $result;
			}

			// Do NOT intercept our own OAuth endpoints.
			if ( false !== strpos( $request_uri, '/mcpcomal-auth/' ) ) {
				return $result;
			}

			$auth_header = self::get_authorization_header();

			// No Authorization header — check if user is already authenticated
			// via other mechanisms (Application Passwords, cookie auth, etc.).
			if ( empty( $auth_header ) ) {
				if ( is_user_logged_in() ) {
					// User authenticated via another method (e.g., Application Passwords).
					MCPCOMAL_OAuth_Server::send_cors_headers();
					return $result;
				}

				MCPCOMAL_OAuth_Server::send_cors_headers();
				header(
					sprintf(
						'WWW-Authenticate: Bearer resource_metadata="%s"',
						esc_url( home_url( '/.well-known/oauth-protected-resource' ) )
					)
				);
				return new WP_Error(
					'rest_not_logged_in',
					'Authentication required. Use OAuth 2.1 Bearer token.',
					array( 'status' => 401 )
				);
			}

			// Must be Bearer scheme.
			if ( 0 !== strpos( $auth_header, 'Bearer ' ) ) {
				return new WP_Error(
					'rest_invalid_auth',
					'Authorization header must use Bearer scheme.',
					array( 'status' => 401 )
				);
			}

			$token      = substr( $auth_header, 7 );
			$token_hash = MCPCOMAL_OAuth_DB::hash_token( $token );
			$token_row  = MCPCOMAL_OAuth_DB::get_token_by_access_hash( $token_hash );

			if ( ! $token_row ) {
				return new WP_Error(
					'rest_invalid_token',
					'Invalid or expired access token.',
					array( 'status' => 401 )
				);
			}

			// Set the WordPress current user to the token owner.
			// wp_set_current_user() is required here to authenticate API requests
			// via OAuth 2.1 Bearer tokens -- this is the same pattern WordPress core
			// uses for Application Passwords (see wp-includes/user.php). The token
			// has been validated above via MCPCOMAL_OAuth_DB. This does NOT create
			// or log in users -- it maps a validated token to its owner.
			wp_set_current_user( (int) $token_row['user_id'] );

			self::$current = array(
				'client_id' => isset( $token_row['client_id'] ) ? (string) $token_row['client_id'] : '',
				'user_id'   => (int) $token_row['user_id'],
				'token_id'  => isset( $token_row['id'] ) ? (int) $token_row['id'] : 0,
			);

			// Send CORS headers early, before the MCP Adapter processes the request.
			// This ensures headers are present even if the adapter sends its own
			// response (e.g., SSE streams) that bypasses rest_pre_serve_request.
			MCPCOMAL_OAuth_Server::send_cors_headers();

			return true;
		}

		/**
		 * Retrieve the Authorization header from multiple sources.
		 *
		 * Different server configurations (Apache mod_php, mod_cgi, nginx)
		 * expose the header in different $_SERVER keys.
		 */
		private static function get_authorization_header(): string {
			if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
				return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
			}

			// Apache mod_rewrite may strip the header into this variable.
			if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
				return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
			}

			// Apache mod_cgi fallback.
			if ( function_exists( 'apache_request_headers' ) ) {
				$headers = apache_request_headers();
				if ( is_array( $headers ) ) {
					foreach ( $headers as $key => $value ) {
						if ( 'authorization' === strtolower( $key ) ) {
							return sanitize_text_field( $value );
						}
					}
				}
			}

			return '';
		}
	}

}
