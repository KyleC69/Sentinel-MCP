<?php
/**
 * OAuth 2.0 Manager.
 *
 * Provides a modern, streamlined OAuth 2.0 Authorization Code flow with PKCE.
 * This class is intended to replace the existing custom OAuth implementation
 * while keeping backward compatibility via hooks.
 *
 * @package SENTINEL
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'MCPCOMAL_OAuth_Manager' ) ) {

    class MCPCOMAL_OAuth_Manager {
        /**
         * Initialize hooks.
         */
        public static function init(): void {
            // Register endpoint for callback.
            add_action( 'admin_init', [ __CLASS__, 'register_callback_endpoint' ] );
        }

        /**
         * Register the OAuth callback endpoint.
         */
        public static function register_callback_endpoint(): void {
            // Use admin-ajax for simplicity.
            add_action( 'wp_ajax_mcp_oauth_callback', [ __CLASS__, 'handle_callback' ] );
        }

        /**
         * Generate a PKCE code verifier.
         */
        private static function generate_code_verifier(): string {
            return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
        }

        /**
         * Generate a PKCE code challenge.
         */
        private static function generate_code_challenge( string $verifier ): string {
            return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
        }

        /**
         * Build the authorization URL.
         *
         * @param array $args Optional overrides: client_id, redirect_uri, scope.
         * @return string URL to redirect the user to.
         */
        public static function get_authorization_url( array $args = [] ): string {
            $client_id    = $args['client_id'] ?? self::get_option( 'client_id' );
            $redirect_uri = $args['redirect_uri'] ?? admin_url( 'admin-ajax.php?action=mcp_oauth_callback' );
            $scope        = $args['scope'] ?? 'mcp:tools';

            $verifier = self::generate_code_verifier();
            $challenge = self::generate_code_challenge( $verifier );

            // Store verifier in transient for later verification.
            set_transient( 'mcp_oauth_pkce_' . $client_id, $verifier, HOUR_IN_SECONDS );

            $params = array(
                'response_type' => 'code',
                'client_id'     => $client_id,
                'redirect_uri'  => $redirect_uri,
                'scope'         => $scope,
                'state'         => wp_create_nonce( 'mcp_oauth_state' ),
                'code_challenge'=> $challenge,
                'code_challenge_method' => 'S256',
            );

            $auth_endpoint = self::get_option( 'auth_endpoint' );
            return add_query_arg( $params, $auth_endpoint );
        }

        /**
         * Handle the OAuth callback.
         */
        public static function handle_callback(): void {
            // Verify state nonce.
            if ( ! isset( $_GET['state'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['state'] ) ), 'mcp_oauth_state' ) ) {
                wp_die( 'Invalid OAuth state.' );
            }

            $code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
            if ( ! $code ) {
                wp_die( 'Authorization code missing.' );
            }

            $client_id = self::get_option( 'client_id' );
            $verifier  = get_transient( 'mcp_oauth_pkce_' . $client_id );
            if ( ! $verifier ) {
                wp_die( 'PKCE verifier not found.' );
            }

            // Exchange code for tokens.
            $token_endpoint = self::get_option( 'token_endpoint' );
            $response = wp_remote_post( $token_endpoint, array(
                'body' => array(
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => admin_url( 'admin-ajax.php?action=mcp_oauth_callback' ),
                    'client_id'     => $client_id,
                    'code_verifier' => $verifier,
                ),
                'timeout' => 30,
                'sslverify' => true,
            ) );

            if ( is_wp_error( $response ) ) {
                wp_die( $response->get_error_message() );
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            if ( ! $data || empty( $data['access_token'] ) ) {
                wp_die( 'Failed to obtain access token.' );
            }

            // Store tokens securely.
            self::store_token_data( $data );

            // Redirect back to admin page with success notice.
            $redirect = add_query_arg( array(
                'page' => 'sentinel-settings',
                'tab'  => 'oauth',
                'mcp_oauth' => 'connected',
            ), admin_url( 'options-general.php' ) );
            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * Store token data in encrypted option.
         */
        private static function store_token_data( array $data ): void {
            $encrypted = openssl_encrypt( wp_json_encode( $data ), 'AES-256-CBC', AUTH_KEY, 0, substr( AUTH_SALT, 0, 16 ) );
            update_option( 'mcp_oauth_token_data', $encrypted, false );
        }

        /**
         * Retrieve stored token data.
         */
        public static function get_token_data(): array {
            $encrypted = get_option( 'mcp_oauth_token_data' );
            if ( ! $encrypted ) {
                return array();
            }
            $json = openssl_decrypt( $encrypted, 'AES-256-CBC', AUTH_KEY, 0, substr( AUTH_SALT, 0, 16 ) );
            $data = json_decode( $json, true );
            return is_array( $data ) ? $data : array();
        }

        /**
         * Refresh the access token if needed.
         */
        public static function refresh_token(): bool {
            $token_data = self::get_token_data();
            if ( empty( $token_data['refresh_token'] ) ) {
                return false;
            }

            $client_id = self::get_option( 'client_id' );
            $token_endpoint = self::get_option( 'token_endpoint' );

            $response = wp_remote_post( $token_endpoint, array(
                'body' => array(
                    'grant_type'    => 'refresh_token',
                    'refresh_token'=> $token_data['refresh_token'],
                    'client_id'     => $client_id,
                ),
                'timeout' => 30,
                'sslverify' => true,
            ) );

            if ( is_wp_error( $response ) ) {
                return false;
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            if ( ! $data || empty( $data['access_token'] ) ) {
                return false;
            }

            // Merge new data with existing.
            $new_data = array_merge( $token_data, $data );
            self::store_token_data( $new_data );
            return true;
        }

        /**
         * Helper to get plugin options for OAuth configuration.
         */
        private static function get_option( string $key ) {
            $options = get_option( 'mcpcomal_oauth_config', array() );
            return $options[ $key ] ?? '';
        }
    }

    // Initialise manager.
    MCPCOMAL_OAuth_Manager::init();
}
?>
