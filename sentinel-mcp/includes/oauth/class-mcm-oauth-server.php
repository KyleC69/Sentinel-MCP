<?php
/**
 * OAuth 2.1 Server -- Orchestrator.
 *
 * Registers .well-known endpoints, REST API routes,
 * CORS handling, and initialises the Bearer token interceptor.
 *
 * @package    MCPCOMAL
 * @author     José Conti <j.conti@joseconti.com>
 * @copyright  2026 José Conti
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/mcp-content-manager-for-wordpress/
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'MCPCOMAL_OAuth_Server' ) ) {

	/**
	 * OAuth 2.1 server orchestrator for MCP Content Manager.
	 */
	class MCPCOMAL_OAuth_Server {

		/**
		 * Bootstrap all OAuth hooks and filters.
		 *
		 * @return void
		 */
		public static function init(): void {
			// .well-known endpoints -- early in init, before WP routing.
			add_action( 'init', array( __CLASS__, 'handle_well_known' ), 1 );

			// CORS preflight (OPTIONS).
			add_action( 'init', array( __CLASS__, 'handle_preflight' ), 1 );

			// REST API route registration.
			add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

			// CORS headers on REST responses.
			add_action( 'rest_api_init', array( __CLASS__, 'add_cors_filters' ) );

			// Bearer token interceptor.
			MCPCOMAL_OAuth_Interceptor::init();
		}

		/**
		 * Handle .well-known OAuth endpoint requests.
		 *
		 * @return void
		 */
		public static function handle_well_known(): void {
			if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
				return;
			}

			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$request_uri = strtok( $request_uri, '?' );

			// Handle subdirectory installs by stripping the home_url path.
			$home_path = wp_parse_url( home_url(), PHP_URL_PATH );
			$home_path = $home_path ? $home_path : '';
			$relative  = $home_path ? substr( $request_uri, strlen( $home_path ) ) : $request_uri;

			if ( '/.well-known/oauth-protected-resource' === $relative ) {
				self::send_protected_resource_metadata();
			}

			if ( '/.well-known/oauth-authorization-server' === $relative ) {
				self::send_authorization_server_metadata();
			}
		}

		/**
		 * Send the OAuth protected resource metadata JSON.
		 *
		 * @return void
		 */
		private static function send_protected_resource_metadata(): void {
			self::send_cors_headers();
			wp_send_json(
				array(
					'resource'                 => home_url(),
					'authorization_servers'    => array( home_url() ),
					'bearer_methods_supported' => array( 'header' ),
					'scopes_supported'         => array( 'mcp:tools', 'mcp:read', 'mcp:write' ),
				)
			);
		}

		/**
		 * Send the OAuth authorization server metadata JSON.
		 *
		 * @return void
		 */
		private static function send_authorization_server_metadata(): void {
			self::send_cors_headers();
			wp_send_json(
				array(
					'issuer'                           => home_url(),
					'authorization_endpoint'           => rest_url( 'mcpcomal-auth/v1/authorize' ),
					'token_endpoint'                   => rest_url( 'mcpcomal-auth/v1/token' ),
					'registration_endpoint'            => rest_url( 'mcpcomal-auth/v1/register' ),
					'revocation_endpoint'              => rest_url( 'mcpcomal-auth/v1/revoke' ),
					'scopes_supported'                 => array( 'mcp:tools', 'mcp:read', 'mcp:write' ),
					'response_types_supported'         => array( 'code' ),
					'grant_types_supported'            => array( 'authorization_code', 'refresh_token' ),
					'token_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post' ),
					'code_challenge_methods_supported' => array( 'S256' ),
				)
			);
		}

		/**
		 * Register all OAuth REST API routes.
		 *
		 * All OAuth 2.1 protocol endpoints use __return_true as permission_callback
		 * because they MUST be publicly accessible per the OAuth 2.1 specification.
		 * Each endpoint implements its own security controls:
		 *
		 *  - /register : Dynamic Client Registration (RFC 7591) -- public by design,
		 *                validates client metadata and redirect_uris (HTTPS only).
		 *  - /authorize: User-facing consent page -- redirects to wp-login.php if not
		 *                logged in; verifies nonce on POST; validates client + PKCE.
		 *  - /token    : Token exchange (RFC 6749 §4.1.3) -- validates authorization
		 *                code, PKCE code_verifier, client_id, and redirect_uri.
		 *  - /revoke   : Token revocation (RFC 7009) -- always returns 200 per spec;
		 *                the token itself serves as proof of possession.
		 *
		 * @return void
		 */
		public static function register_routes(): void {
			$namespace = 'mcpcomal-auth/v1';

			// Dynamic Client Registration (DCR) -- RFC 7591.
			// Intentionally public: MCP clients must self-register before OAuth flow.
			register_rest_route(
				$namespace,
				'/register',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_register' ),
					'permission_callback' => '__return_true', // Public per RFC 7591.
				)
			);

			// Authorization Endpoint -- GET (display) + POST (form submit).
			// Intentionally public: the callback redirects to wp-login.php if the
			// user is not logged in, then verifies a nonce on form submission.
			register_rest_route(
				$namespace,
				'/authorize',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( 'MCPCOMAL_OAuth_Authorize', 'handle_get' ),
						'permission_callback' => '__return_true', // Login enforced in callback.
					),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( 'MCPCOMAL_OAuth_Authorize', 'handle_post' ),
						'permission_callback' => '__return_true', // Nonce + login verified in callback.
					),
				)
			);

			// Token Endpoint -- RFC 6749 §4.1.3.
			// Intentionally public: clients exchange auth codes + PKCE verifiers for tokens.
			register_rest_route(
				$namespace,
				'/token',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( 'MCPCOMAL_OAuth_Token', 'handle' ),
					'permission_callback' => '__return_true', // Public per RFC 6749.
				)
			);

			// Token Revocation -- RFC 7009.
			// Intentionally public: the token itself serves as proof of possession.
			register_rest_route(
				$namespace,
				'/revoke',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( 'MCPCOMAL_OAuth_Token', 'handle_revoke' ),
					'permission_callback' => '__return_true', // Public per RFC 7009.
				)
			);
		}

		/**
		 * Handle Dynamic Client Registration (DCR) requests.
		 *
		 * @param WP_REST_Request $request The incoming REST request.
		 * @return WP_REST_Response|WP_Error
		 */
		public static function handle_register( WP_REST_Request $request ): WP_REST_Response|WP_Error {
			$body = $request->get_json_params();

			$client_name    = sanitize_text_field( $body['client_name'] ?? '' );
			$redirect_uris  = $body['redirect_uris'] ?? array();
			$grant_types    = $body['grant_types'] ?? array( 'authorization_code', 'refresh_token' );
			$response_types = $body['response_types'] ?? array( 'code' );
			$auth_method    = sanitize_text_field( $body['token_endpoint_auth_method'] ?? 'none' );

			// Validate required fields.
			if ( empty( $client_name ) || empty( $redirect_uris ) || ! is_array( $redirect_uris ) ) {
				return new WP_Error(
					'invalid_client_metadata',
					'client_name and redirect_uris are required.',
					array( 'status' => 400 )
				);
			}

			// Validate redirect_uris are valid HTTPS URLs.
			foreach ( $redirect_uris as $uri ) {
				$uri = esc_url_raw( $uri );
				if ( empty( $uri ) || 0 !== strpos( $uri, 'https://' ) ) {
					return new WP_Error(
						'invalid_redirect_uri',
						'All redirect_uris must be valid HTTPS URLs.',
						array( 'status' => 400 )
					);
				}
			}

			// Sanitize redirect_uris.
			$redirect_uris = array_map( 'esc_url_raw', $redirect_uris );

			// Validate auth method.
			if ( ! in_array( $auth_method, array( 'none', 'client_secret_post' ), true ) ) {
				return new WP_Error(
					'invalid_client_metadata',
					'token_endpoint_auth_method must be "none" or "client_secret_post".',
					array( 'status' => 400 )
				);
			}

			$client = MCPCOMAL_OAuth_DB::insert_client(
				array(
					'client_name'                => $client_name,
					'redirect_uris'              => $redirect_uris,
					'grant_types'                => $grant_types,
					'token_endpoint_auth_method' => $auth_method,
				)
			);

			if ( ! $client ) {
				return new WP_Error(
					'server_error',
					'Could not register client.',
					array( 'status' => 500 )
				);
			}

			$response_data = array(
				'client_id'                  => $client['client_id'],
				'client_name'                => $client['client_name'],
				'redirect_uris'              => $client['redirect_uris'],
				'grant_types'                => $client['grant_types'],
				'response_types'             => $response_types,
				'token_endpoint_auth_method' => $client['token_endpoint_auth_method'],
			);

			if ( ! empty( $client['client_secret'] ) ) {
				$response_data['client_secret'] = $client['client_secret'];
			}

			return new WP_REST_Response( $response_data, 201 );
		}

		/**
		 * Handle CORS preflight (OPTIONS) requests for OAuth endpoints.
		 *
		 * @return void
		 */
		public static function handle_preflight(): void {
			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'OPTIONS' !== $_SERVER['REQUEST_METHOD'] ) {
				return;
			}

			$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

			// Only handle preflight for our endpoints.
			$needs_cors = false !== strpos( $request_uri, 'mcpcomal-auth' )
			|| false !== strpos( $request_uri, '.well-known/oauth' )
			|| false !== strpos( $request_uri, '/mcp/' );

			if ( ! $needs_cors ) {
				return;
			}

			self::send_cors_headers();
			status_header( 204 );
			exit;
		}

		/**
		 * Send CORS headers for allowed origins.
		 *
		 * @return void
		 */
		public static function send_cors_headers(): void {
			$origin = isset( $_SERVER['HTTP_ORIGIN'] )
			? sanitize_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) )
			: '';

			$allowed_origins = array( 'https://claude.ai', 'https://claude.com' );

			if ( in_array( $origin, $allowed_origins, true ) ) {
				header( 'Access-Control-Allow-Origin: ' . $origin );
			}

			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Vary: Origin' );
		}

		/**
		 * Add CORS filter to REST API responses for MCP/OAuth routes.
		 *
		 * @return void
		 */
		public static function add_cors_filters(): void {
			add_filter(
				'rest_pre_serve_request',
				function ( $served, $result, $request ) {
					$route = $request->get_route();

					if ( 0 === strpos( $route, '/mcpcomal-auth/' ) || 0 === strpos( $route, '/mcp/' ) ) {
						MCPCOMAL_OAuth_Server::send_cors_headers();
					}

					// Prevent caching of token responses (RFC 6749 Section 5.1).
					if ( '/mcpcomal-auth/token' === $route || '/mcpcomal-auth/revoke' === $route ) {
						header( 'Cache-Control: no-store' );
						header( 'Pragma: no-cache' );
					}

					return $served;
				},
				10,
				4
			);
		}
	}

}
