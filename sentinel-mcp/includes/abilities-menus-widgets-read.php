<?php

namespace SentinelMCP;

/**
 * Menus, widgets and sidebars read abilities (Sprint 1.5).
 *
 * Read-only inspection of classic nav menus, widget instances per sidebar,
 * and registered sidebars. Editing menus/widgets is reserved for Premium.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-menus-widgets',
			array(
				'label'       => __( 'Menus, widgets and sidebars (read-only)', 'mcp-sentinel' ),
				'description' => __( 'Read-only access to classic navigation menus, widget instances and registered sidebars.', 'mcp-sentinel' ),
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	function () {

		// 1. List nav menus.

		wp_register_ability(
			'sentinel/list-nav-menus',
			array(
				'label'               => 'List navigation menus',
				'category'            => 'sentinel-menus-widgets',
				'description'         => 'Read-only. Lists every classic navigation menu (wp_get_nav_menus) with id, name, slug, item count and the theme locations it is assigned to. For each menu it also returns its items: id, title, url, parent, type, object.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'include_items' => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input = null ) {
					$include_items = ! isset( $input['include_items'] ) || ! empty( $input['include_items'] );

					$menus     = wp_get_nav_menus();
					$locations = (array) get_nav_menu_locations();
					$result    = array();

					foreach ( (array) $menus as $menu ) {
						$assigned = array();
						foreach ( $locations as $location => $menu_id ) {
							if ( (int) $menu_id === (int) $menu->term_id ) {
								$assigned[] = (string) $location;
							}
						}

						$entry = array(
							'id'        => (int) $menu->term_id,
							'name'      => (string) $menu->name,
							'slug'      => (string) $menu->slug,
							'count'     => (int) $menu->count,
							'locations' => $assigned,
						);

						if ( $include_items ) {
							$items   = wp_get_nav_menu_items( $menu->term_id );
							$mapped  = array();
							if ( is_array( $items ) ) {
								foreach ( $items as $item ) {
									$mapped[] = array(
										'id'      => (int) $item->ID,
										'title'   => (string) $item->title,
										'url'     => (string) $item->url,
										'parent'  => (int) $item->menu_item_parent,
										'order'   => (int) $item->menu_order,
										'type'    => (string) $item->type,
										'object'  => (string) $item->object,
										'object_id' => (int) $item->object_id,
									);
								}
							}
							$entry['items'] = $mapped;
						}

						$result[] = $entry;
					}

					return array(
						'count' => count( $result ),
						'menus' => $result,
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
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

		// 2. List widgets per sidebar.

		wp_register_ability(
			'sentinel/list-widgets',
			array(
				'label'               => 'List widgets',
				'category'            => 'sentinel-menus-widgets',
				'description'         => 'Read-only. Returns the widget instances currently active in every sidebar (sidebar_id => array of widget ids). Includes the widget base type and a copy of its instance settings when available.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function () {
					$sidebars = (array) wp_get_sidebars_widgets();
					$result   = array();

					foreach ( $sidebars as $sidebar_id => $widget_ids ) {
						if ( ! is_array( $widget_ids ) ) {
							continue;
						}

						$widgets = array();
						foreach ( $widget_ids as $widget_id ) {
							$base = preg_replace( '/-\d+$/', '', (string) $widget_id );
							$num  = 0;
							if ( preg_match( '/-(\d+)$/', (string) $widget_id, $m ) ) {
								$num = (int) $m[1];
							}

							$instance = array();
							if ( $base ) {
								$option = get_option( 'widget_' . $base );
								if ( is_array( $option ) && isset( $option[ $num ] ) && is_array( $option[ $num ] ) ) {
									$instance = $option[ $num ];
								}
							}

							$widgets[] = array(
								'id'       => (string) $widget_id,
								'base'     => (string) $base,
								'instance' => $instance,
							);
						}

						$result[ (string) $sidebar_id ] = $widgets;
					}

					return array(
						'sidebars' => $result,
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
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

		// 3. List registered sidebars.

		wp_register_ability(
			'sentinel/list-sidebars',
			array(
				'label'               => 'List sidebars',
				'category'            => 'sentinel-menus-widgets',
				'description'         => 'Read-only. Lists every sidebar registered by the active theme or plugins (id, name, description, before/after wrapper). Useful to know where widgets can be placed.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function () {
					$registered = isset( $GLOBALS['wp_registered_sidebars'] ) && is_array( $GLOBALS['wp_registered_sidebars'] )
						? $GLOBALS['wp_registered_sidebars']
						: array();

					$result = array();
					foreach ( $registered as $id => $sb ) {
						$result[] = array(
							'id'             => (string) $id,
							'name'           => isset( $sb['name'] ) ? (string) $sb['name'] : '',
							'description'    => isset( $sb['description'] ) ? (string) $sb['description'] : '',
							'before_widget'  => isset( $sb['before_widget'] ) ? (string) $sb['before_widget'] : '',
							'after_widget'   => isset( $sb['after_widget'] ) ? (string) $sb['after_widget'] : '',
							'before_title'   => isset( $sb['before_title'] ) ? (string) $sb['before_title'] : '',
							'after_title'    => isset( $sb['after_title'] ) ? (string) $sb['after_title'] : '',
						);
					}

					return array(
						'count'    => count( $result ),
						'sidebars' => $result,
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
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

/*
 * MCP annotations summary for this file:
 *
 *   list-nav-menus    readOnly idempotent
 *   list-widgets      readOnly idempotent
 *   list-sidebars     readOnly idempotent
 */
