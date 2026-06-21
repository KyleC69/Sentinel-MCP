<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * OAuth 2.0 Manager.
 *
 * Provides a modern, streamlined OAuth 2.0 Authorization Code flow with PKCE.
 * This class is intended to replace the existing custom OAuth implementation
 * while keeping backward compatibility via hooks.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

class OAuth_Manager
{
    /**
     * Initialize hooks.
     */
    public static function init(): void
    {
        // Register endpoint for callback.
        add_action('admin_init', [__CLASS__, 'register_callback_endpoint']);
    }

    /**
     * Register the OAuth callback endpoint.
     */
    public static function register_callback_endpoint(): void
    {
        // Use admin-ajax for simplicity.
        add_action('wp_ajax_mcp_oauth_callback', [__CLASS__, 'handle_callback']);
    }

    /**
     * Generate a PKCE code verifier.
     */
    private static function generate_code_verifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Generate a PKCE code challenge.
     */
    private static function generate_code_challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * Build the authorization URL.
     *
     * Uses the provided {@code client_id} when available, otherwise falls back
     * to the stored option and resolves legacy server-name values to the real
     * registered OAuth client ID before building the redirect URL.
     *
     * @param array $args Optional overrides:
     *   - client_id (string)   Optional. The OAuth client identifier.
     *   - redirect_uri (string) Optional. Must be a valid URL; HTTPS, HTTP (localhost) or the vscode scheme are accepted.
     *   - scope (string)        Optional OAuth scope.
     * @return string|\WP_Error Authorization URL or error.
     */
    /**
     * Resolve the stored client_id to a real registered client.
     *
     * The plugin may have an outdated option value that contains the server name
     * instead of the generated client_id. This helper attempts to look up the
     * client by ID first, then falls back to a lookup by client_name. If a valid
     * client is found, the option is updated to the correct ID.
     *
     * @return string|\WP_Error Resolved client_id or \WP_Error on failure.
     */
    private static function resolve_client_id(?string $client_id = null): string|\WP_Error
    {
        if (null === $client_id) {
            $client_id = get_option('sentinel_oauth_client_id');
        }

        if (! is_string($client_id) || '' === trim($client_id)) {
            // If no explicit client_id was supplied and the option is empty,
            // fall back to the most recently registered OAuth client so the
            // auth flow can proceed after a fresh DCR.
            if (null === $client_id) {
                $clients = OAuth_DB::get_all_clients();
                if (! empty($clients)) {
                    $latest_client = reset($clients);
                    if (! empty($latest_client['client_id'])) {
                        update_option('sentinel_oauth_client_id', $latest_client['client_id'], false);
                        return $latest_client['client_id'];
                    }
                }
            }

            sentinel_debug_log(
                array(
                    'oauth_error'     => 'manager_missing_client_id',
                    'oauth_supplied'  => $client_id,
                )
            );
            return new \WP_Error('missing_client_id', 'client_id is required.', array('status' => 400));
        }

        $client = OAuth_DB::get_client_by_id($client_id);
        if ($client) {
            return $client['client_id'];
        }

        $client_by_name = OAuth_DB::get_client_by_name($client_id);
        if ($client_by_name) {
            update_option('sentinel_oauth_client_id', $client_by_name['client_id'], false);
            return $client_by_name['client_id'];
        }

        // The supplied value is neither a valid client_id nor a client_name.
        // If this was an implicit lookup (no explicit argument), try the most
        // recently registered client as a recovery path.
        if (null === $client_id) {
            $clients = OAuth_DB::get_all_clients();
            if (! empty($clients)) {
                $latest_client = reset($clients);
                if (! empty($latest_client['client_id'])) {
                    update_option('sentinel_oauth_client_id', $latest_client['client_id'], false);
                    return $latest_client['client_id'];
                }
            }
        }

        sentinel_debug_log(
            array(
                'oauth_error'     => 'manager_invalid_client_id',
                'oauth_client_id' => $client_id,
            )
        );
        return new \WP_Error('invalid_client_id', 'Provided client_id does not correspond to a registered OAuth client.', array('status' => 400));
    }

    public static function get_authorization_url(array $args = []): string|\WP_Error
    {
        // Allow explicit override via args, otherwise resolve stored option.
        $client_id = $args['client_id'] ?? null;
        if (empty($client_id)) {
            $resolved = self::resolve_client_id();
            if (is_wp_error($resolved)) {
                return $resolved;
            }
            $client_id = $resolved;
        } else {
            $resolved = self::resolve_client_id($client_id);
            if (is_wp_error($resolved)) {
                return $resolved;
            }
            $client_id = $resolved;
        }

        // Allow custom redirect URIs; default to admin‑ajax callback.
        $redirect_uri = $args['redirect_uri'] ?? admin_url('admin-ajax.php?action=mcp_oauth_callback');
        // Basic validation – allow https, http (localhost), and vscode schemes.
        $scheme = parse_url($redirect_uri, PHP_URL_SCHEME);
        $allowed = array('https', 'http', 'vscode');
        if (! in_array($scheme, $allowed, true)) {
            return new \WP_Error('invalid_redirect_uri', 'Provided redirect_uri must use https, http (localhost), or vscode scheme.', array('status' => 400));
        }
        // If http, ensure it's localhost to avoid insecure redirects.
        if ('http' === $scheme) {
            $host = parse_url($redirect_uri, PHP_URL_HOST);
            if ('127.0.0.1' !== $host && 'localhost' !== $host) {
                return new \WP_Error('invalid_redirect_uri', 'HTTP redirect_uri is only allowed for localhost.', array('status' => 400));
            }
        }

        $scope = $args['scope'] ?? 'mcp:tools';

        $verifier = self::generate_code_verifier();
        $challenge = self::generate_code_challenge($verifier);

        // Store verifier in transient for later verification.
        set_transient('mcp_oauth_pkce_' . $client_id, $verifier, HOUR_IN_SECONDS);

        $params = array(
            'response_type' => 'code',
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'scope'         => $scope,
            'state'         => wp_create_nonce('mcp_oauth_state'),
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        );

        $auth_endpoint = self::get_option('auth_endpoint');
        return add_query_arg($params, $auth_endpoint);
    }

    /**
     * Handle the OAuth callback.
     */
    public static function handle_callback(): void
    {
        // Verify state nonce.
        if (! isset($_GET['state']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['state'])), 'mcp_oauth_state')) {
            wp_die('Invalid OAuth state.');
        }

        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        if (! $code) {
            wp_die('Authorization code missing.');
        }

        $client_id_res = self::resolve_client_id();
        if (is_wp_error($client_id_res)) {
            wp_die($client_id_res->get_error_message());
        }
        $client_id = $client_id_res;
        $verifier  = get_transient('mcp_oauth_pkce_' . $client_id);
        if (! $verifier) {
            wp_die('PKCE verifier not found.');
        }

        // Exchange code for tokens.
        $token_endpoint = self::get_option('token_endpoint');
        $response = wp_remote_post($token_endpoint, array(
            'body' => array(
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => admin_url('admin-ajax.php?action=mcp_oauth_callback'),
                'client_id'     => $client_id,
                'code_verifier' => $verifier,
            ),
            'timeout' => 30,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            wp_die($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (! $data || empty($data['access_token'])) {
            wp_die('Failed to obtain access token.');
        }

        // Store tokens securely.
        self::store_token_data($data);

        // Redirect back to admin page with success notice.
        $redirect = add_query_arg(array(
            'page' => 'sentinel-settings',
            'tab'  => 'oauth',
            'mcp_oauth' => 'connected',
        ), admin_url('options-general.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Store token data in encrypted option.
     */
    private static function store_token_data(array $data): void
    {
        $encrypted = openssl_encrypt(wp_json_encode($data), 'AES-256-CBC', AUTH_KEY, 0, substr(AUTH_SALT, 0, 16));
        update_option('mcp_oauth_token_data', $encrypted, false);
    }

    /**
     * Retrieve stored token data.
     */
    public static function get_token_data(): array
    {
        $encrypted = get_option('mcp_oauth_token_data');
        if (! $encrypted) {
            return array();
        }
        $json = openssl_decrypt($encrypted, 'AES-256-CBC', AUTH_KEY, 0, substr(AUTH_SALT, 0, 16));
        $data = json_decode($json, true);
        return is_array($data) ? $data : array();
    }

    /**
     * Refresh the access token if needed.
     */
    public static function refresh_token(): bool
    {
        $token_data = self::get_token_data();
        if (empty($token_data['refresh_token'])) {
            return false;
        }

        $client_id_res = self::resolve_client_id();
        if (is_wp_error($client_id_res)) {
            return false;
        }
        $client_id = $client_id_res;
        $token_endpoint = self::get_option('token_endpoint');

        $response = wp_remote_post($token_endpoint, array(
            'body' => array(
                'grant_type'    => 'refresh_token',
                'refresh_token' => $token_data['refresh_token'],
                'client_id'     => $client_id,
            ),
            'timeout' => 30,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (! $data || empty($data['access_token'])) {
            return false;
        }

        // Merge new data with existing.
        $new_data = array_merge($token_data, $data);
        self::store_token_data($new_data);
        return true;
    }

    /**
     * Helper to get plugin options for OAuth configuration.
     *
     * Falls back to the live REST endpoint URLs so the manager always
     * points at the current site regardless of stale option values.
     */
    private static function get_option(string $key)
    {
        // For the client_id we store it separately to allow dynamic updates.
        if ('client_id' === $key) {
            $stored = get_option('sentinel_oauth_client_id');
            if (! empty($stored)) {
                return $stored;
            }
        }

        $options = get_option('sentinel_oauth_config', array());
        if (! empty($options[$key])) {
            return $options[$key];
        }

        // Fallback to live REST endpoints so the manager never returns
        // empty strings for the OAuth protocol URLs.
        return match ($key) {
            'auth_endpoint'   => rest_url('sentinel-auth/v1/authorize'),
            'token_endpoint'  => rest_url('sentinel-auth/v1/token'),
            default           => '',
        };
    }
}

// Initialise manager.
OAuth_Manager::init();
