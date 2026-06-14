<?php

namespace SentinelMCP;

/**
 * Recovery & Site Management Abilities.
 *
 * Provides abilities for site diagnostics, plugin management,
 * debug toggle, and recovery via the WordPress Abilities API.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Category: Recovery.
 */

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-recovery',
			array(
				'label'       => __( 'Recovery and Maintenance', 'mcp-sentinel' ),
				'description' => __( 'Site diagnostics, plugin management, and recovery tools.', 'mcp-sentinel' ),
			)
		);
	}
);

/**
 * Abilities.
 */

add_action(
	'wp_abilities_api_init',
	function () {
		// SITE HEALTH.

		wp_register_ability(
			'sentinel/site-health',
			array(
				'label'               => 'Site health status',
				'category'            => 'sentinel-recovery',
				'description'         => 'Shows site status: WP/PHP version, last fatal error, paused plugins, '
								. 'memory usage, and disk space.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(),
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input = null ) {
					return SENTINEL_File_Manager::get_site_health();
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

		// LIST PLUGINS.

		wp_register_ability(
			'sentinel/list-plugins',
			array(
				'label'               => 'List all plugins',
				'category'            => 'sentinel-recovery',
				'description'         => 'Lists all installed plugins with their status: active, inactive, or paused (due to fatal error). '
								. 'Includes name, slug, version, and plugin file.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(),
				),

				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'name'    => array( 'type' => 'string' ),
							'slug'    => array( 'type' => 'string' ),
							'version' => array( 'type' => 'string' ),
							'active'  => array( 'type' => 'boolean' ),
							'paused'  => array( 'type' => 'boolean' ),
							'file'    => array( 'type' => 'string' ),
						),
					),
				),

				'execute_callback'    => function ( $input = null ) {
					return SENTINEL_File_Manager::list_plugins();
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

		// TOGGLE PLUGIN.

		wp_register_ability(
			'sentinel/toggle-plugin',
			array(
				'label'               => 'Activate or deactivate a plugin',
				'category'            => 'sentinel-recovery',
				'description'         => 'Required: plugin (string), action (string: "activate" or "deactivate"). '
								. 'Activates or deactivates a plugin. Use "sentinel/list-plugins" first to get the plugin "file" value. '
								. 'CAUTION: Activating a plugin with errors will cause a fatal error. '
								. 'Alias: file is also accepted instead of plugin.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'required'   => array( 'plugin', 'action' ),
					'properties' => array(
						'plugin' => array(
							'type'        => 'string',
							'description' => 'Plugin path ("file" value from sentinel/list-plugins). E.g.: "my-plugin/my-plugin.php"',
						),
						'action' => array(
							'type'        => 'string',
							'description' => '"activate" to activate, "deactivate" to deactivate.',
							'enum'        => array( 'activate', 'deactivate' ),
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'plugin'     => array( 'type' => 'string' ),
						'new_status' => array( 'type' => 'string' ),
						'message'    => array( 'type' => 'string' ),
					),
				),

				'execute_callback'    => function ( $input ) {
					return SENTINEL_File_Manager::toggle_plugin( $input['plugin'] ?? $input['file'] ?? '', $input['action'] );
				},

				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => false,
							'destructiveHint' => true,
							'idempotentHint'  => false,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);

		// CLEAR RECOVERY - Clear WordPress recovery flags.

		wp_register_ability(
			'sentinel/clear-recovery',
			array(
				'label'               => 'Clear recovery mode',
				'category'            => 'sentinel-recovery',
				'description'         => 'Clears WordPress recovery flags (recovery_keys and paused_extensions). '
								. 'Use it after fixing a fatal error or if recovery mode was triggered by a transient issue. '
								. 'Safe to run at any time: if no flags are active, it does nothing harmful.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
				),

				'execute_callback'    => function () {
					$cleared = array();

					// Clear WordPress recovery keys.
					delete_option( 'recovery_keys' );
					$cleared[] = 'recovery_keys';

					// Clear paused extensions.
					if ( function_exists( 'wp_paused_plugins' ) ) {
						$paused = wp_paused_plugins()->get_all();
						foreach ( array_keys( $paused ) as $plugin ) {
							wp_paused_plugins()->delete( $plugin );
						}
						if ( ! empty( $paused ) ) {
							$cleared[] = count( $paused ) . ' paused plugin(s)';
						}
					}
					if ( function_exists( 'wp_paused_themes' ) ) {
						$paused = wp_paused_themes()->get_all();
						foreach ( array_keys( $paused ) as $theme ) {
							wp_paused_themes()->delete( $theme );
						}
						if ( ! empty( $paused ) ) {
							$cleared[] = count( $paused ) . ' paused theme(s)';
						}
					}

					return array(
						'success' => true,
						'message' => 'Recovery flags cleared: ' . implode( ', ', $cleared ) . '.',
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => false,
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
