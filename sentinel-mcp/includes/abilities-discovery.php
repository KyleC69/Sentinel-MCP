<?php

namespace SentinelMCP;

/**
 * Abilities de descubrimiento.
 *
 * Permiten a Claude/Cowork explorar la estructura completa
 * del sitio WordPress de forma dinámica:
 *  - List all CPTs with detail
 *  - Inspect a specific CPT (taxonomies, meta, etc.)
 *  - Ver resumen del sitio
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register ability categories.
 */

add_action(
	'wp_abilities_api_categories_init',
	function () {

		wp_register_ability_category(
			'sentinel-discovery',
			array(
				'label'       => __( 'Content Discovery', 'mcp-sentinel' ),
				'description' => __( 'Explore site structure: post types, taxonomies, custom fields, and terms.', 'mcp-sentinel' ),
			)
		);

		wp_register_ability_category(
			'sentinel-content',
			array(
				'label'       => __( 'Content Management', 'mcp-sentinel' ),
				'description' => __( 'Create, read, update, delete, and search content across all post types.', 'mcp-sentinel' ),
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	function () {
		// Resumen del sitio.

		wp_register_ability(
			'sentinel/site-schema',
			array(
				'label'               => 'View site structure',
				'category'            => 'sentinel-discovery',
				'description'         => 'All parameters optional. '
								. 'Returns a complete summary of the WordPress site structure: '
								. 'all content types (CPTs), their taxonomies, meta fields (including ACF), '
								. 'and supported features. This is the first thing to call to understand '
								. 'what content types are available on this site.',

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
					return SENTINEL_Schema_Inspector::get_site_schema_summary();
				},

				'permission_callback' => function () {
					return current_user_can( 'read' );
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

		// Inspect a specific CPT.

		wp_register_ability(
			'sentinel/inspect-post-type',
			array(
				'label'               => 'Inspect a content type',
				'category'            => 'sentinel-discovery',
				'description'         => 'Required: post_type (string, e.g. "post", "page", "product"). '
								. 'Returns the full structure of a specific content type: '
								. 'taxonomies with all their terms, detailed meta fields (type, description, '
								. 'required flag, ACF options), and supported features (thumbnail, excerpt, etc.). '
								. 'Call this before creating content in a CPT to know which fields to fill.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'required'   => array( 'post_type' ),
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'Post type slug to inspect. E.g.: "post", "page", "product", "doc".',
						),
					),
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input ) {
					$post_type_slug = sanitize_text_field( $input['post_type'] );
					$pt_object      = get_post_type_object( $post_type_slug );

					if ( ! $pt_object ) {
						return array(
							'success' => false,
							'message' => sprintf( 'Post type "%s" not found.', $post_type_slug ),
						);
					}

					return array(
						'name'         => $pt_object->name,
						'label'        => $pt_object->label,
						'description'  => $pt_object->description ? $pt_object->description : '',
						'hierarchical' => $pt_object->hierarchical,
						'supports'     => get_all_post_type_supports( $pt_object->name ),
						'taxonomies'   => SENTINEL_Schema_Inspector::get_taxonomies_for_post_type( $pt_object->name ),
						'meta_fields'  => SENTINEL_Schema_Inspector::get_meta_fields_for_post_type( $pt_object->name ),
						'labels'       => array(
							'singular' => $pt_object->labels->singular_name ?? '',
							'plural'   => $pt_object->labels->name ?? '',
							'add_new'  => $pt_object->labels->add_new_item ?? '',
						),
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'read' );
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

		// List terms of a taxonomy.

		wp_register_ability(
			'sentinel/list-terms',
			array(
				'label'               => 'List terms of a taxonomy',
				'category'            => 'sentinel-discovery',
				'description'         => 'Required: taxonomy (string, e.g. "category", "post_tag", "product_cat"). '
								. 'Lists all terms of a specific taxonomy. Returns term ID, name, slug, post count, and parent.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'required'   => array( 'taxonomy' ),
					'properties' => array(
						'taxonomy'   => array(
							'type'        => 'string',
							'description' => 'Taxonomy slug. E.g.: "category", "post_tag", "product_cat".',
						),
						'hide_empty' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),

				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'     => array( 'type' => 'integer' ),
							'name'   => array( 'type' => 'string' ),
							'slug'   => array( 'type' => 'string' ),
							'count'  => array( 'type' => 'integer' ),
							'parent' => array( 'type' => 'integer' ),
						),
					),
				),

				'execute_callback'    => function ( $input ) {
					$taxonomy = sanitize_text_field( $input['taxonomy'] );

					if ( ! taxonomy_exists( $taxonomy ) ) {
						return array(
							'success' => false,
							'message' => sprintf( 'Taxonomy "%s" not found.', $taxonomy ),
						);
					}

					$terms = get_terms(
						array(
							'taxonomy'   => $taxonomy,
							'hide_empty' => $input['hide_empty'] ?? false,
							'number'     => 200,
						)
					);

					if ( is_wp_error( $terms ) ) {
						return array(
							'success' => false,
							'message' => $terms->get_error_message(),
						);
					}

					return array_map(
						function ( $term ) {
							return array(
								'id'     => $term->term_id,
								'name'   => $term->name,
								'slug'   => $term->slug,
								'count'  => $term->count,
								'parent' => $term->parent,
							);
						},
						$terms
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'read' );
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
