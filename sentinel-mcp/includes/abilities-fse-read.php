<?php

namespace SentinelMCP;

/**
 * Full Site Editing read abilities (Sprint 1.4).
 *
 * Read-only inspection of registered blocks, block patterns and FSE templates.
 * Returns metadata only (never the full template content) — full template
 * editing is reserved for the Premium edition.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link    ??  ??  ??  ??  ??  ??  ??  ??  ??  ??  ??
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-fse',
			array(
				'label'       => __( 'Full Site Editing (read-only)', 'mcp-sentinel' ),
				'description' => __( 'Read-only inspection of block patterns and FSE templates. Editing is Premium.', 'mcp-sentinel' ),
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	function () {

		// 1. List registered block types.

		wp_register_ability(
			'sentinel/list-blocks-registered',
			array(
				'label'               => 'List registered blocks',
				'category'            => 'sentinel-discovery',
				'description'         => 'Read-only. Lists every block type registered on the site (core, theme, plugins) with name, title, category, description and the list of attribute keys. Render callbacks and full attribute schemas are omitted to keep the payload small.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'category' => array(
							'type'        => 'string',
							'description' => 'Optional: filter by block category slug (e.g. "text", "media", "design", "widgets").',
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input = null ) {
					if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
						return array(
							'count'  => 0,
							'blocks' => array(),
						);
					}

					$filter   = isset( $input['category'] ) ? sanitize_key( (string) $input['category'] ) : '';
					$registry = WP_Block_Type_Registry::get_instance();
					$all      = $registry->get_all_registered();
					$result   = array();

					foreach ( $all as $name => $block ) {
						$cat = isset( $block->category ) ? (string) $block->category : '';
						if ( '' !== $filter && $cat !== $filter ) {
							continue;
						}
						$attrs = is_array( $block->attributes ) ? array_keys( $block->attributes ) : array();
						$result[] = array(
							'name'        => (string) $name,
							'title'       => isset( $block->title ) ? (string) $block->title : '',
							'description' => isset( $block->description ) ? (string) $block->description : '',
							'category'    => $cat,
							'icon'        => isset( $block->icon ) && is_string( $block->icon ) ? $block->icon : '',
							'keywords'    => is_array( $block->keywords ) ? array_values( $block->keywords ) : array(),
							'attributes'  => $attrs,
							'is_dynamic'  => is_callable( $block->render_callback ),
						);
					}

					return array(
						'count'  => count( $result ),
						'blocks' => $result,
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
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

		// 2. List block patterns.

		wp_register_ability(
			'sentinel/list-block-patterns',
			array(
				'label'               => 'List block patterns',
				'category'            => 'sentinel-fse',
				'description'         => 'Read-only. Lists every block pattern registered on the site (core + theme + plugins) with name, title, description and category. Returns names only — pattern content is not included.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'category' => array(
							'type'        => 'string',
							'description' => 'Optional: filter by pattern category slug (e.g. "header", "footer", "buttons").',
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input = null ) {
					if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
						return array(
							'count'    => 0,
							'patterns' => array(),
						);
					}

					$filter   = isset( $input['category'] ) ? sanitize_key( (string) $input['category'] ) : '';
					$registry = WP_Block_Patterns_Registry::get_instance();
					$all      = $registry->get_all_registered();
					$result   = array();

					foreach ( $all as $pattern ) {
						$categories = isset( $pattern['categories'] ) && is_array( $pattern['categories'] ) ? $pattern['categories'] : array();
						if ( '' !== $filter && ! in_array( $filter, $categories, true ) ) {
							continue;
						}
						$result[] = array(
							'name'        => isset( $pattern['name'] ) ? (string) $pattern['name'] : '',
							'title'       => isset( $pattern['title'] ) ? (string) $pattern['title'] : '',
							'description' => isset( $pattern['description'] ) ? (string) $pattern['description'] : '',
							'categories'  => $categories,
							'keywords'    => isset( $pattern['keywords'] ) && is_array( $pattern['keywords'] ) ? $pattern['keywords'] : array(),
							'block_types' => isset( $pattern['blockTypes'] ) && is_array( $pattern['blockTypes'] ) ? $pattern['blockTypes'] : array(),
							'source'      => isset( $pattern['source'] ) ? (string) $pattern['source'] : '',
						);
					}

					return array(
						'count'    => count( $result ),
						'patterns' => $result,
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
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

		// 3. List FSE templates and template parts (metadata only).

		wp_register_ability(
			'sentinel/list-fse-templates',
			array(
				'label'               => 'List FSE templates',
				'category'            => 'sentinel-fse',
				'description'         => 'Read-only. Lists FSE templates and template parts (metadata only: slug, type, theme, title, area, source). Template content is not returned — fetching and editing the template body is reserved for the Premium edition.',

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
					if ( ! function_exists( 'get_block_templates' ) ) {
						return array(
							'templates'      => array(),
							'template_parts' => array(),
							'note'           => 'FSE block templates are not available on this WordPress version.',
						);
					}

					$templates = (array) get_block_templates();
					$parts     = (array) get_block_templates( array(), 'wp_template_part' );

					$format = function ( $tpl ) {
						return array(
							'slug'        => isset( $tpl->slug ) ? (string) $tpl->slug : '',
							'id'          => isset( $tpl->id ) ? (string) $tpl->id : '',
							'theme'       => isset( $tpl->theme ) ? (string) $tpl->theme : '',
							'title'       => isset( $tpl->title ) ? (string) $tpl->title : '',
							'description' => isset( $tpl->description ) ? (string) $tpl->description : '',
							'type'        => isset( $tpl->type ) ? (string) $tpl->type : '',
							'area'        => isset( $tpl->area ) ? (string) $tpl->area : '',
							'source'      => isset( $tpl->source ) ? (string) $tpl->source : '',
							'status'      => isset( $tpl->status ) ? (string) $tpl->status : '',
							'has_theme_file' => isset( $tpl->has_theme_file ) ? (bool) $tpl->has_theme_file : false,
						);
					};

					return array(
						'templates'           => array_map( $format, $templates ),
						'template_parts'      => array_map( $format, $parts ),
						'templates_count'     => count( $templates ),
						'template_parts_count' => count( $parts ),
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
 *   list-blocks-registered  readOnly idempotent
 *   list-block-patterns     readOnly idempotent
 *   list-fse-templates      readOnly idempotent
 */
