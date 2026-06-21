<?php
/**
 * OAuth 2.1 Database layer.
 *
 * Creates tables and provides CRUD operations for
 * OAuth clients, authorization codes, and tokens.
 *
 * @package    MCPCOMAL
 * @author     José Conti <j.conti@joseconti.com>
 * @copyright  2026 José Conti
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/mcp-content-manager-for-wordpress/
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'MCPCOMAL_OAuth_DB' ) ) {

	/**
	 * Database operations for the OAuth 2.1 subsystem.
	 */
	class MCPCOMAL_OAuth_DB {

		const DB_VERSION     = '1.1.0';
		const OPT_DB_VERSION = 'mcpcomal_oauth_db_version';

		/**
		 * Get the clients table name.
		 *
		 * @return string
		 */
		public static function table_clients(): string {
			global $wpdb;
			return $wpdb->prefix . 'mcpcomal_oauth_clients';
		}

		/**
		 * Get the authorization codes table name.
		 *
		 * @return string
		 */
		public static function table_codes(): string {
			global $wpdb;
			return $wpdb->prefix . 'mcpcomal_oauth_codes';
		}

		/**
		 * Get the tokens table name.
		 *
		 * @return string
		 */
		public static function table_tokens(): string {
			global $wpdb;
			return $wpdb->prefix . 'mcpcomal_oauth_tokens';
		}

		/**
		 * Create all OAuth database tables.
		 *
		 * @return void
		 */
		public static function create_tables(): void {
			global $wpdb;

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$charset_collate = $wpdb->get_charset_collate();
			$t_clients       = self::table_clients();
			$t_codes         = self::table_codes();
			$t_tokens        = self::table_tokens();

			$sql_clients = "CREATE TABLE {$t_clients} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id varchar(64) NOT NULL,
			client_secret varchar(255) DEFAULT NULL,
			client_name varchar(255) NOT NULL,
			redirect_uris text NOT NULL,
			grant_types text NOT NULL,
			token_endpoint_auth_method varchar(50) NOT NULL DEFAULT 'none',
			allowed_abilities text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY client_id (client_id)
		) {$charset_collate};";

			$sql_codes = "CREATE TABLE {$t_codes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(128) NOT NULL,
			client_id varchar(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			redirect_uri text NOT NULL,
			scope varchar(255) NOT NULL DEFAULT 'mcp:tools',
			code_challenge varchar(128) NOT NULL,
			code_challenge_method varchar(10) NOT NULL DEFAULT 'S256',
			expires_at datetime NOT NULL,
			used tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY code (code),
			KEY client_id (client_id)
		) {$charset_collate};";

			$sql_tokens = "CREATE TABLE {$t_tokens} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			access_token_hash varchar(64) NOT NULL,
			refresh_token_hash varchar(64) NOT NULL,
			client_id varchar(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			scope varchar(255) NOT NULL DEFAULT 'mcp:tools',
			access_expires_at datetime NOT NULL,
			refresh_expires_at datetime NOT NULL,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY access_token_hash (access_token_hash),
			UNIQUE KEY refresh_token_hash (refresh_token_hash),
			KEY client_id (client_id),
			KEY user_id (user_id)
		) {$charset_collate};";

			dbDelta( $sql_clients );
			dbDelta( $sql_codes );
			dbDelta( $sql_tokens );

			update_option( self::OPT_DB_VERSION, self::DB_VERSION );
		}

		/**
		 * Upgrade tables if the DB version has changed.
		 *
		 * @return void
		 */
		public static function maybe_upgrade(): void {
			if ( get_option( self::OPT_DB_VERSION ) !== self::DB_VERSION ) {
				self::create_tables();
			}
		}

		/**
		 * Drop all OAuth database tables.
		 *
		 * @return void
		 */
		public static function drop_tables(): void {
			global $wpdb;

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- DDL commands for uninstall, intentional schema removal.
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcpcomal_oauth_tokens" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcpcomal_oauth_codes" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcpcomal_oauth_clients" );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

			delete_option( self::OPT_DB_VERSION );
		}

		/**
		 * Insert a new OAuth client.
		 *
		 * @param array $data Client registration data.
		 * @return array|false Client data on success, false on failure.
		 */
		public static function insert_client( array $data ): array|false {
			global $wpdb;

			$client_id = bin2hex( random_bytes( 16 ) );

			$client_secret = null;
			$auth_method   = sanitize_text_field( $data['token_endpoint_auth_method'] ?? 'none' );
			if ( 'none' !== $auth_method ) {
				$client_secret = bin2hex( random_bytes( 32 ) );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$inserted = $wpdb->insert(
				self::table_clients(),
				array(
					'client_id'                  => $client_id,
					'client_secret'              => $client_secret,
					'client_name'                => sanitize_text_field( $data['client_name'] ),
					'redirect_uris'              => wp_json_encode( $data['redirect_uris'] ),
					'grant_types'                => wp_json_encode( $data['grant_types'] ?? array( 'authorization_code', 'refresh_token' ) ),
					'token_endpoint_auth_method' => $auth_method,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( ! $inserted ) {
				return false;
			}

			return array(
				'client_id'                  => $client_id,
				'client_secret'              => $client_secret,
				'client_name'                => $data['client_name'],
				'redirect_uris'              => $data['redirect_uris'],
				'grant_types'                => $data['grant_types'] ?? array( 'authorization_code', 'refresh_token' ),
				'token_endpoint_auth_method' => $auth_method,
			);
		}

		/**
		 * Get a client by its client_id.
		 *
		 * @param string $client_id The OAuth client ID.
		 * @return array|null Client row or null if not found.
		 */
		public static function get_client_by_id( string $client_id ): array|null {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE client_id = %s',
					self::table_clients(),
					$client_id
				),
				ARRAY_A
			);

			if ( ! $row ) {
				return null;
			}

			$row['redirect_uris'] = json_decode( $row['redirect_uris'], true ) ? json_decode( $row['redirect_uris'], true ) : array();
			$row['grant_types']   = json_decode( $row['grant_types'], true ) ? json_decode( $row['grant_types'], true ) : array();

			return $row;
		}

		/**
		 * Delete a client by its client_id.
		 *
		 * @param string $client_id The OAuth client ID.
		 * @return bool True on success, false on failure.
		 */
		public static function delete_client( string $client_id ): bool {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$deleted = $wpdb->delete(
				self::table_clients(),
				array( 'client_id' => $client_id ),
				array( '%s' )
			);

			return false !== $deleted;
		}

		/**
		 * Get all registered OAuth clients.
		 *
		 * @return array List of client rows.
		 */
		public static function get_all_clients(): array {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC', self::table_clients() ),
				ARRAY_A
			);

			if ( ! $rows ) {
				return array();
			}

			foreach ( $rows as &$row ) {
				$row['redirect_uris'] = json_decode( $row['redirect_uris'], true ) ? json_decode( $row['redirect_uris'], true ) : array();
				$row['grant_types']   = json_decode( $row['grant_types'], true ) ? json_decode( $row['grant_types'], true ) : array();
			}

			return $rows;
		}

		/**
		 * Insert a new authorization code.
		 *
		 * @param array $data Authorization code data.
		 * @return string|false The code string on success, false on failure.
		 */
		public static function insert_code( array $data ): string|false {
			global $wpdb;

			$code       = bin2hex( random_bytes( 32 ) );
			$expires_at = gmdate( 'Y-m-d H:i:s', time() + 60 );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$inserted = $wpdb->insert(
				self::table_codes(),
				array(
					'code'                  => $code,
					'client_id'             => $data['client_id'],
					'user_id'               => (int) $data['user_id'],
					'redirect_uri'          => $data['redirect_uri'],
					'scope'                 => $data['scope'] ?? 'mcp:tools',
					'code_challenge'        => $data['code_challenge'],
					'code_challenge_method' => $data['code_challenge_method'] ?? 'S256',
					'expires_at'            => $expires_at,
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
			);

			return $inserted ? $code : false;
		}

		/**
		 * Get an authorization code row.
		 *
		 * @param string $code The authorization code.
		 * @return array|null Code row or null if not found.
		 */
		public static function get_code( string $code ): array|null {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE code = %s',
					self::table_codes(),
					$code
				),
				ARRAY_A
			);

			return $row ? $row : null;
		}

		/**
		 * Mark an authorization code as used.
		 *
		 * @param string $code The authorization code.
		 * @return bool True on success, false on failure.
		 */
		public static function mark_code_used( string $code ): bool {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$updated = $wpdb->update(
				self::table_codes(),
				array( 'used' => 1 ),
				array( 'code' => $code ),
				array( '%d' ),
				array( '%s' )
			);

			return false !== $updated;
		}

		/**
		 * Delete expired and used authorization codes.
		 *
		 * @return int Number of deleted rows.
		 */
		public static function cleanup_expired_codes(): int {
			global $wpdb;

			$now = gmdate( 'Y-m-d H:i:s' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE expires_at < %s OR (used = 1 AND expires_at < %s)',
					self::table_codes(),
					$now,
					$now
				)
			);

			return (int) $deleted;
		}

		/**
		 * Hash a token string using SHA-256.
		 *
		 * @param string $token The raw token string.
		 * @return string The hashed token.
		 */
		public static function hash_token( string $token ): string {
			return hash( 'sha256', $token );
		}

		/**
		 * Insert a new token pair (access + refresh).
		 *
		 * @param array $data Token data including client_id, user_id, scope.
		 * @return array|false Token data on success, false on failure.
		 */
		public static function insert_token( array $data ): array|false {
			global $wpdb;

			$access_token  = bin2hex( random_bytes( 32 ) );
			$refresh_token = bin2hex( random_bytes( 32 ) );

			$access_hash  = self::hash_token( $access_token );
			$refresh_hash = self::hash_token( $refresh_token );

			$access_expires_at  = gmdate( 'Y-m-d H:i:s', time() + 3600 );
			$refresh_expires_at = gmdate( 'Y-m-d H:i:s', time() + ( 30 * DAY_IN_SECONDS ) );

			$scope = $data['scope'] ?? 'mcp:tools';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$inserted = $wpdb->insert(
				self::table_tokens(),
				array(
					'access_token_hash'  => $access_hash,
					'refresh_token_hash' => $refresh_hash,
					'client_id'          => $data['client_id'],
					'user_id'            => (int) $data['user_id'],
					'scope'              => $scope,
					'access_expires_at'  => $access_expires_at,
					'refresh_expires_at' => $refresh_expires_at,
				),
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);

			if ( ! $inserted ) {
				return false;
			}

			return array(
				'access_token'  => $access_token,
				'refresh_token' => $refresh_token,
				'expires_in'    => 3600,
				'scope'         => $scope,
			);
		}

		/**
		 * Get a token row by its access token hash.
		 *
		 * @param string $access_hash The hashed access token.
		 * @return array|null Token row or null if not found/expired/revoked.
		 */
		public static function get_token_by_access_hash( string $access_hash ): array|null {
			global $wpdb;

			$now = gmdate( 'Y-m-d H:i:s' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE access_token_hash = %s AND revoked = 0 AND access_expires_at > %s',
					self::table_tokens(),
					$access_hash,
					$now
				),
				ARRAY_A
			);

			return $row ? $row : null;
		}

		/**
		 * Get a token row by its refresh token hash.
		 *
		 * @param string $refresh_hash The hashed refresh token.
		 * @return array|null Token row or null if not found/expired/revoked.
		 */
		public static function get_token_by_refresh_hash( string $refresh_hash ): array|null {
			global $wpdb;

			$now = gmdate( 'Y-m-d H:i:s' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE refresh_token_hash = %s AND revoked = 0 AND refresh_expires_at > %s',
					self::table_tokens(),
					$refresh_hash,
					$now
				),
				ARRAY_A
			);

			return $row ? $row : null;
		}

		/**
		 * Revoke a token by its access token hash.
		 *
		 * @param string $access_hash The hashed access token.
		 * @return bool True if a token was revoked.
		 */
		public static function revoke_token_by_access_hash( string $access_hash ): bool {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$updated = $wpdb->update(
				self::table_tokens(),
				array( 'revoked' => 1 ),
				array( 'access_token_hash' => $access_hash ),
				array( '%d' ),
				array( '%s' )
			);

			return $updated > 0;
		}

		/**
		 * Revoke a token by its refresh token hash.
		 *
		 * @param string $refresh_hash The hashed refresh token.
		 * @return bool True if a token was revoked.
		 */
		public static function revoke_token_by_refresh_hash( string $refresh_hash ): bool {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$updated = $wpdb->update(
				self::table_tokens(),
				array( 'revoked' => 1 ),
				array( 'refresh_token_hash' => $refresh_hash ),
				array( '%d' ),
				array( '%s' )
			);

			return $updated > 0;
		}

		/**
		 * Revoke a token by its database row ID.
		 *
		 * @param int $id The token row ID.
		 * @return bool True if a token was revoked.
		 */
		public static function revoke_token_by_id( int $id ): bool {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$updated = $wpdb->update(
				self::table_tokens(),
				array( 'revoked' => 1 ),
				array( 'id' => $id ),
				array( '%d' ),
				array( '%d' )
			);

			return $updated > 0;
		}

		/**
		 * Revoke all active tokens for a given client.
		 *
		 * @param string $client_id The OAuth client ID.
		 * @return int Number of tokens revoked.
		 */
		public static function revoke_all_for_client( string $client_id ): int {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$updated = $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET revoked = 1 WHERE client_id = %s AND revoked = 0',
					self::table_tokens(),
					$client_id
				)
			);

			return (int) $updated;
		}

		/**
		 * Get all active (non-revoked, non-expired) tokens with client names.
		 *
		 * @return array List of active token rows.
		 */
		public static function get_active_tokens(): array {
			global $wpdb;

			$now       = gmdate( 'Y-m-d H:i:s' );
			$t_tokens  = self::table_tokens();
			$t_clients = self::table_clients();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT t.*, c.client_name
				 FROM %i t
				 LEFT JOIN %i c ON t.client_id = c.client_id
				 WHERE t.revoked = 0 AND t.access_expires_at > %s
				 ORDER BY t.created_at DESC',
					$t_tokens,
					$t_clients,
					$now
				),
				ARRAY_A
			);

			return $rows ? $rows : array();
		}

		/**
		 * Delete expired and revoked tokens.
		 *
		 * @return int Number of deleted rows.
		 */
		public static function cleanup_expired_tokens(): int {
			global $wpdb;

			$now = gmdate( 'Y-m-d H:i:s' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom OAuth tables, no WP cache API applicable.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE (revoked = 1 AND created_at < %s) OR (access_expires_at < %s AND refresh_expires_at < %s)',
					self::table_tokens(),
					gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
					$now,
					$now
				)
			);

			return (int) $deleted;
		}
	}

}
