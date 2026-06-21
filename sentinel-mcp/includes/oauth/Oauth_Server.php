<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * OAuth 2.1 Server -- Orchestrator.
 *
 * Registers .well-known endpoints, REST API routes,
 * CORS handling, and initialises the Bearer token interceptor.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * OAuth 2.1 server orchestrator for MCP Content Manager.
 */
class OAuth_Server
{

	/**
	 * Bootstrap all OAuth hooks and filters.
	 *
	 * @return void
	 */
	public static function init(): void
	{
		// .well-known endpoints -- early in init, before WP routing.
		add_action('init', array(__CLASS__, 'handle_well_known'), 1);

		// CORS preflight (OPTIONS).
		add_action('init', array(__CLASS__, 'handle_preflight'), 1);

		// REST API route registration.
		add_action('rest_api_init', array(__CLASS__, 'register_routes'));

		// CORS headers on REST responses.
		add_action('rest_api_init', array(__CLASS__, 'add_cors_filters'));

		// Bearer token interceptor.
		OAuth_Interceptor::init();
	}

	/**
	 * Handle .well-known OAuth endpoint requests.
	 *
	 * @return void
	 */
	public static function handle_well_known(): void
	{
		if (! isset($_SERVER['REQUEST_URI'])) {
			return;
		}

		$request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
		$request_uri = strtok($request_uri, '?');

		// Handle subdirectory installs by stripping the home_url path.
		$home_path = wp_parse_url(home_url(), PHP_URL_PATH);
		$home_path = $home_path ? $home_path : '';
		$relative  = $home_path ? substr($request_uri, strlen($home_path)) : $request_uri;

		if ('/.well-known/oauth-protected-resource' === $relative) {
			self::send_protected_resource_metadata();
		}

		if ('/.well-known/oauth-authorization-server' === $relative) {
			self::send_authorization_server_metadata();
		}
	}

	/**
	 * Send the OAuth protected resource metadata JSON.
	 *
	 * @return void
	 */
	private static function send_protected_resource_metadata(): void
	{
		self::send_cors_headers();
		wp_send_json(
			array(
				'resource'                 => rest_url('mcp/mcp-adapter-default-server'),
				'authorization_servers'    => array(home_url()),
				'bearer_methods_supported' => array('header'),
				'scopes_supported'         => array('mcp:tools', 'mcp:read', 'mcp:write'),
			)
		);
	}

	/**
	 * Send the OAuth authorization server metadata JSON.
	 *
	 * @return void
	 */
	private static function send_authorization_server_metadata(): void
	{
		self::send_cors_headers();
		wp_send_json(
			array(
				'issuer'                           => home_url(),
				'authorization_endpoint'           => rest_url('sentinel-auth/v1/authorize'),
				'token_endpoint'                   => rest_url('sentinel-auth/v1/token'),
				'registration_endpoint'            => rest_url('sentinel-auth/v1/register'),
				'revocation_endpoint'              => rest_url('sentinel-auth/v1/revoke'),
				'scopes_supported'                 => array('mcp:tools', 'mcp:read', 'mcp:write'),
				'response_types_supported'         => array('code'),
				'grant_types_supported'            => array('authorization_code', 'refresh_token'),
				'token_endpoint_auth_methods_supported' => array('none', 'client_secret_post'),
				'code_challenge_methods_supported' => array('S256'),
			)
		);
	}

	/**
	 * Register all OAuth REST API routes.
	 *
	 * All OAuth 2.1 protocol endpoints use a dedicated public permission callback
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
	public static function register_routes(): void
	{
		$namespace = 'sentinel-auth/v1';

		// Dynamic Client Registration (DCR) -- RFC 7591.
		// Intentionally public: MCP clients must self-register before OAuth flow.
		register_rest_route(
			$namespace,
			'/register',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array(__CLASS__, 'handle_register'),
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
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array('SentinelMCP\OAuth_Authorize', 'handle_get'),
					'permission_callback' => '__return_true', // Login enforced in callback.
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array('SentinelMCP\OAuth_Authorize', 'handle_post'),
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
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array('SentinelMCP\OAuth_Token', 'handle'),
				'permission_callback' => '__return_true', // Public per RFC 6749.
			)
		);

		// Token Revocation -- RFC 7009.
		// Intentionally public: the token itself serves as proof of possession.
		register_rest_route(
			$namespace,
			'/revoke',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array('SentinelMCP\OAuth_Token', 'handle_revoke'),
				'permission_callback' => '__return_true', // Public per RFC 7009.
			)
		);

		register_rest_route(
			$namespace,
			'/debug-probe',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array(__CLASS__, 'handle_debug_probe'),
				'permission_callback' => function (): bool {
					return current_user_can('manage_options');
				},
			)
		);
	}

	/**
	 * Run a one-off HTTP probe against a target OAuth endpoint for troubleshooting.
	 *
	 * @param \WP_REST_Request $request The incoming REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_debug_probe(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
	{
		$endpoint = esc_url_raw($request->get_param('endpoint') ?? '');
		$method   = strtoupper(sanitize_text_field($request->get_param('method') ?? 'POST'));
		$body     = $request->get_param('body');
		$headers  = $request->get_param('headers') ?? array();

		if ('' === $endpoint) {
			return new \WP_Error('invalid_request', 'An endpoint URL is required.', array('status' => 400));
		}

		if (! is_array($headers)) {
			$headers = array();
		}

		$probe_headers = array(
			'Accept'       => '*/*',
			'Content-Type' => 'application/json',
		);
		foreach ($headers as $name => $value) {
			if (is_string($name) && is_string($value) && '' !== trim($value)) {
				$probe_headers[$name] = $value;
			}
		}

		$args = array(
			'method'    => in_array($method, array('GET', 'POST', 'PUT', 'PATCH', 'DELETE'), true) ? $method : 'POST',
			'timeout'   => 30,
			'sslverify' => true,
			'headers'   => $probe_headers,
		);

		if (is_string($body) && '' !== trim($body)) {
			$args['body'] = $body;
		}

		$response = wp_remote_request($endpoint, $args);
		if (is_wp_error($response)) {
			return new \WP_Error('probe_failed', $response->get_error_message(), array('status' => 500));
		}

		$response_code = (int) wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);
		$response_headers = wp_remote_retrieve_headers($response);

		return new \WP_REST_Response(
			array(
				'status'   => $response_code,
				'body'     => $response_body,
				'headers'  => is_array($response_headers) ? $response_headers : array(),
				'request'  => array(
					'endpoint' => $endpoint,
					'method'   => $args['method'],
					'body'     => $args['body'] ?? '',
				),
			),
			200
		);
	}

	/**
	 * Handle Dynamic Client Registration (DCR) requests.
	 *
	 * @param \WP_REST_Request $request The incoming REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_register(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
	{
		$body = $request->get_json_params();

		$client_name    = sanitize_text_field($body['client_name'] ?? '');
		$redirect_uris  = $body['redirect_uris'] ?? array();
		$grant_types    = $body['grant_types'] ?? array('authorization_code', 'refresh_token');
		$response_types = $body['response_types'] ?? array('code');
		$auth_method    = sanitize_text_field($body['token_endpoint_auth_method'] ?? 'none');

		// Validate required fields.
		if (empty($client_name) || empty($redirect_uris) || ! is_array($redirect_uris)) {
			return new \WP_Error(
				'invalid_client_metadata',
				'client_name and redirect_uris are required.',
				array('status' => 400)
			);
		}

		// Validate redirect_uris are valid URLs. Allow HTTPS, HTTP (for localhost), and vscode scheme.
		foreach ($redirect_uris as $uri) {
			$uri = self::sanitize_redirect_uri($uri);
			if (empty($uri)) {
				return new \WP_Error(
					'invalid_redirect_uri',
					'All redirect_uris must be non‑empty URLs.',
					array('status' => 400)
				);
			}
			// Parse scheme to allow https, http (localhost), or vscode.
			$scheme = parse_url($uri, PHP_URL_SCHEME);
			if (! in_array($scheme, array('https', 'http', 'vscode'), true)) {
				return new \WP_Error(
					'invalid_redirect_uri',
					'All redirect_uris must use https, http (for localhost), or vscode scheme.',
					array('status' => 400)
				);
			}
			// If http, ensure it is localhost to avoid insecure redirects.
			if ('http' === $scheme) {
				$host = parse_url($uri, PHP_URL_HOST);
				if ('127.0.0.1' !== $host && 'localhost' !== $host) {
					return new \WP_Error(
						'invalid_redirect_uri',
						'HTTP redirect_uris are only allowed for localhost.',
						array('status' => 400)
					);
				}
			}
		}

		// Sanitize redirect_uris.
		$redirect_uris = array_map(array(__CLASS__, 'sanitize_redirect_uri'), $redirect_uris);

		// Validate auth method.
		if (! in_array($auth_method, array('none', 'client_secret_post'), true)) {
			return new \WP_Error(
				'invalid_client_metadata',
				'token_endpoint_auth_method must be "none" or "client_secret_post".',
				array('status' => 400)
			);
		}

		$client = OAuth_DB::insert_client(
			array(
				'client_name'                => $client_name,
				'redirect_uris'              => $redirect_uris,
				'grant_types'                => $grant_types,
				'token_endpoint_auth_method' => $auth_method,
			)
		);

		if (! $client) {
			return new \WP_Error(
				'server_error',
				'Could not register client.',
				array('status' => 500)
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

		if (! empty($client['client_secret'])) {
			$response_data['client_secret'] = $client['client_secret'];
		}

		return new \WP_REST_Response($response_data, 201);
	}

	/**
	 * Handle CORS preflight (OPTIONS) requests for OAuth endpoints.
	 *
	 * @return void
	 */
	public static function handle_preflight(): void
	{
		if (! isset($_SERVER['REQUEST_METHOD']) || 'OPTIONS' !== $_SERVER['REQUEST_METHOD']) {
			return;
		}

		$request_uri = isset($_SERVER['REQUEST_URI'])
			? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
			: '';

		// Only handle preflight for our endpoints.
		$needs_cors = false !== strpos($request_uri, 'sentinel-auth')
			|| false !== strpos($request_uri, '.well-known/oauth')
			|| false !== strpos($request_uri, '/mcp/');

		if (! $needs_cors) {
			return;
		}

		self::send_cors_headers();
		status_header(204);
		exit;
	}

	/**
	 * Send CORS headers for allowed origins.
	 *
	 * @return void
	 */
	public static function send_cors_headers(): void
	{
		$origin = isset($_SERVER['HTTP_ORIGIN'])
			? sanitize_url(wp_unslash($_SERVER['HTTP_ORIGIN']))
			: '';

		$allowed_origins = array('https://claude.ai', 'https://claude.com');

		if (in_array($origin, $allowed_origins, true)) {
			header('Access-Control-Allow-Origin: ' . $origin);
		}

		header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
		header('Access-Control-Allow-Headers: Authorization, Content-Type');
		header('Access-Control-Allow-Credentials: true');
		header('Vary: Origin');
	}

	/**
	 * Add CORS filter to REST API responses for MCP/OAuth routes.
	 *
	 * @return void
	 */
	public static function add_cors_filters(): void
	{
		add_filter(
			'rest_pre_serve_request',
			function ($served, $result, $request) {
				$route = $request->get_route();

				if (0 === strpos($route, '/sentinel-auth/') || 0 === strpos($route, '/mcp/')) {
					OAuth_Server::send_cors_headers();
				}

				// Prevent caching of token responses (RFC 6749 Section 5.1).
				if ('/sentinel-auth/v1/token' === $route || '/sentinel-auth/v1/revoke' === $route) {
					header('Cache-Control: no-store');
					header('Pragma: no-cache');
				}

				return $served;
			},
			10,
			4
		);
	}

	/**
	 * Sanitize a URL while preserving the vscode: scheme.
	 *
	 * WordPress esc_url_raw strips non-standard schemes. This helper
	 * keeps vscode: intact so VS Code redirect URIs survive validation.
	 *
	 * @param string $url Raw URL.
	 * @return string Sanitized URL.
	 */
	public static function sanitize_redirect_uri(string $url): string
	{
		$url = trim($url);
		if (0 === stripos($url, 'vscode://')) {
			// Only allow alphanum, dot, slash, hyphen, underscore in the path.
			$path = substr($url, strlen('vscode://'));
			$path = preg_replace('|[^a-zA-Z0-9._\-/]|', '', $path);
			return 'vscode://' . $path;
		}
		return esc_url_raw($url);
	}
}
