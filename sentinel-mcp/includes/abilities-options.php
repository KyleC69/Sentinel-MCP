<?php
/**
 * WordPress Options Abilities.
 *
 * Read WordPress options through a security whitelist.
 * Dangerous options (siteurl, home, active_plugins) are read-only.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       http://github.com/KyleC69/Sentinel-MCP
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_init',
	function () {
		/*
		 * GET OPTION
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/get-option',
			array(
				'label'               => 'Read WordPress option',
				'category'            => 'sentinel-system',
				'description'         => 'All parameters optional. '
								. 'Reads WordPress options (site title, URL, timezone, permalinks, etc.) '
								. 'from a security whitelist. Pass a specific option name, or omit to get all '
								. 'whitelisted options. Includes WooCommerce options if WC is active.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Option name to read. Omit to return all whitelisted options. '
										. 'Examples: "blogname", "permalink_structure", "woocommerce_currency".',
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'options' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				),

				'execute_callback'    => function ( $input ) {
					return SENTINEL_Options_Manager::get_option( $input );
				},

				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => true,
							'destructiveHint' => false,
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);

		/*
		 * LIST OPTIONS BY PREFIX
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/list-options-by-prefix',
			array(
				'label'               => 'List options by prefix',
				'category'            => 'sentinel-system',
				'description'         => 'Required: prefix (string). '
								. 'Lists all wp_options matching a prefix pattern. Useful for discovering '
								. 'which options a plugin created (e.g., prefix "woocommerce_subscriptions" to find all '
								. 'WooCommerce Subscriptions options). Transient options are excluded.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'required'   => array( 'prefix' ),
					'properties' => array(
						'prefix' => array(
							'type'        => 'string',
							'description' => 'Option name prefix to search for (e.g., "woocommerce_", "yoast_", "rank_math_").',
						),
						'count'  => array(
							'type'        => 'integer',
							'description' => 'Maximum number of options to return. Default: 100, max: 500.',
						),
					),
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input ) {
					$input  = is_array( $input ) ? $input : array();
					$prefix = sanitize_text_field( $input['prefix'] ?? '' );
					$count  = isset( $input['count'] ) ? absint( $input['count'] ) : ( isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 100 );
					$count  = max( 1, min( 500, $count ) );

					if ( empty( $prefix ) ) {
						return array(
							'success' => false,
							'message' => 'The "prefix" parameter is required.',
						);
					}

					global $wpdb;

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$rows = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT option_name, option_value FROM {$wpdb->options}
							WHERE option_name LIKE %s
							AND option_name NOT LIKE %s
							AND option_name NOT LIKE %s
							ORDER BY option_name ASC
							LIMIT %d",
							$wpdb->esc_like( $prefix ) . '%',
							'\_transient\_%',
							'\_site\_transient\_%',
							$count
						),
						ARRAY_A
					);

					$options = array();
					foreach ( $rows as $row ) {
						$value = maybe_unserialize( $row['option_value'] );
						$options[ $row['option_name'] ] = $value;
					}

					return array(
						'success' => true,
						'prefix'  => $prefix,
						'count'   => count( $options ),
						'options' => $options,
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => true,
							'destructiveHint' => false,
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);

		/*
		 * LIST REGISTERED SETTINGS
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/list-registered-settings',
			array(
				'label'               => 'List registered settings',
				'category'            => 'sentinel-system',
				'description'         => 'Lists all settings registered via the WordPress Settings API (register_setting). '
								. 'Shows option name, group, type, description, and default value. '
								. 'Useful for discovering configurable options of installed plugins. '
								. 'Use the optional "group" parameter to filter by settings group/page.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'group' => array(
							'type'        => 'string',
							'description' => 'Filter by settings group (e.g., "general", "reading", "discussion"). Omit to list all.',
						),
					),
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input ) {
					$input = is_array( $input ) ? $input : array();
					$group = sanitize_text_field( $input['group'] ?? '' );

					$all_settings = get_registered_settings();
					$result       = array();

					foreach ( $all_settings as $option_name => $args ) {
						if ( ! empty( $group ) && ( $args['group'] ?? '' ) !== $group ) {
							continue;
						}

						$result[] = array(
							'option_name'  => $option_name,
							'group'        => $args['group'] ?? '',
							'type'         => $args['type'] ?? 'string',
							'description'  => $args['description'] ?? '',
							'default'      => $args['default'] ?? null,
							'show_in_rest' => ! empty( $args['show_in_rest'] ),
						);
					}

					return array(
						'success'  => true,
						'count'    => count( $result ),
						'settings' => $result,
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => true,
							'destructiveHint' => false,
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);
	}
);
