<?php
/**
 * Extended discovery abilities (Sprint 1.1).
 *
 * Read-only abilities that round out site discovery: full lists of post types,
 * taxonomies, post statuses, registered shortcodes, and permalink structure.
 * They reuse the existing "sentinel-discovery" category registered in
 * abilities-discovery.php.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_init',
	function () {

		// 1. List post types.

		wp_register_ability(
			'sentinel/list-post-types',
			array(
				'label'               => 'List post types',
				'category'            => 'sentinel-discovery',
				'description'         => 'Read-only. Lists every post type registered on the site (public and private), with name, label, hierarchical flag, has_archive flag, public flag, rewrite slug and the list of supported features (title, editor, thumbnail, etc.). '
									. 'Useful as a quick directory before calling inspect-post-type or before creating content.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'public_only' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'When true, only post types declared as public are returned.',
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				),

				'execute_callback'    => function ( $input = null ) {
					$args      = array();
					$only_pub  = ! empty( $input['public_only'] );
					if ( $only_pub ) {
						$args['public'] = true;
					}

					$types  = get_post_types( $args, 'objects' );
					$result = array();

					foreach ( $types as $pt ) {
						$result[] = array(
							'name'         => $pt->name,
							'label'        => $pt->label,
							'public'       => (bool) $pt->public,
							'hierarchical' => (bool) $pt->hierarchical,
							'has_archive'  => (bool) $pt->has_archive,
							'show_in_rest' => (bool) $pt->show_in_rest,
							'rewrite_slug' => is_array( $pt->rewrite ) && isset( $pt->rewrite['slug'] ) ? (string) $pt->rewrite['slug'] : '',
							'supports'     => array_keys( get_all_post_type_supports( $pt->name ) ),
						);
					}

					return $result;
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

		// 2. List taxonomies.

		wp_register_ability(
			'sentinel/list-taxonomies',
			array(
				'label'               => 'List taxonomies',
				'category'            => 'sentinel-discovery',
				'description'         => 'Read-only. Lists every taxonomy registered on the site with name, label, the post types it applies to (object_types), hierarchical flag and public flag.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'public_only' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				),

				'execute_callback'    => function ( $input = null ) {
					$args = array();
					if ( ! empty( $input['public_only'] ) ) {
						$args['public'] = true;
					}

					$taxes  = get_taxonomies( $args, 'objects' );
					$result = array();

					foreach ( $taxes as $tax ) {
						$result[] = array(
							'name'         => $tax->name,
							'label'        => $tax->label,
							'object_types' => array_values( (array) $tax->object_type ),
							'public'       => (bool) $tax->public,
							'hierarchical' => (bool) $tax->hierarchical,
							'show_in_rest' => (bool) $tax->show_in_rest,
							'rewrite_slug' => is_array( $tax->rewrite ) && isset( $tax->rewrite['slug'] ) ? (string) $tax->rewrite['slug'] : '',
						);
					}

					return $result;
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

		// 3. List post statuses.

		wp_register_ability(
			'sentinel/list-post-statuses',
			array(
				'label'               => 'List post statuses',
				'category'            => 'sentinel-discovery',
				'description'         => 'Read-only. Lists every post status registered on the site (publish, draft, pending, private, future, trash, plus any custom ones) with label and visibility flags (public, internal, protected, exclude_from_search).',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				),

				'execute_callback'    => function () {
					$statuses = get_post_stati( array(), 'objects' );
					$result   = array();

					foreach ( $statuses as $st ) {
						$result[] = array(
							'name'                => $st->name,
							'label'               => $st->label,
							'public'              => (bool) $st->public,
							'internal'            => (bool) $st->internal,
							'protected'           => (bool) $st->protected,
							'private'             => (bool) $st->private,
							'exclude_from_search' => (bool) $st->exclude_from_search,
							'show_in_admin_all_list'    => (bool) $st->show_in_admin_all_list,
							'show_in_admin_status_list' => (bool) $st->show_in_admin_status_list,
						);
					}

					return $result;
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

		// 4. List shortcodes.

		wp_register_ability(
			'sentinel/list-shortcodes',
			array(
				'label'               => 'List registered shortcodes',
				'category'            => 'sentinel-discovery',
				'description'         => 'Read-only. Returns the names of all shortcodes registered on the site (no callbacks, no source code). Helps the AI client know which shortcodes exist before suggesting content that uses them.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'count'      => array( 'type' => 'integer' ),
						'shortcodes' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),

				'execute_callback'    => function () {
					global $shortcode_tags;
					$names = is_array( $shortcode_tags ) ? array_keys( $shortcode_tags ) : array();
					sort( $names );

					return array(
						'count'      => count( $names ),
						'shortcodes' => $names,
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

		// 5. Get permalink structure.

		wp_register_ability(
			'sentinel/get-permalink-structure',
			array(
				'label'               => 'Get permalink structure',
				'category'            => 'sentinel-discovery',
				'description'         => 'Read-only. Returns the active permalink structure (e.g. "/%postname%/"), example URLs for each public post type, the category and tag bases, and rewrite slugs for custom post types and taxonomies. Use this to generate correct internal links.',

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
					$structure = (string) get_option( 'permalink_structure', '' );

					$post_type_examples = array();
					$post_types         = get_post_types( array( 'public' => true ), 'objects' );
					foreach ( $post_types as $pt ) {
						$sample_id = 0;
						$query     = new WP_Query(
							array(
								'post_type'              => $pt->name,
								'post_status'            => 'publish',
								'posts_per_page'         => 1,
								'no_found_rows'          => true,
								'update_post_meta_cache' => false,
								'update_post_term_cache' => false,
								'fields'                 => 'ids',
							)
						);
						if ( ! empty( $query->posts ) ) {
							$sample_id = (int) $query->posts[0];
						}

						$post_type_examples[ $pt->name ] = array(
							'rewrite_slug'        => is_array( $pt->rewrite ) && isset( $pt->rewrite['slug'] ) ? (string) $pt->rewrite['slug'] : '',
							'has_archive'         => (bool) $pt->has_archive,
							'archive_url'         => $pt->has_archive ? (string) get_post_type_archive_link( $pt->name ) : '',
							'sample_post_id'      => $sample_id,
							'sample_permalink'    => $sample_id ? (string) get_permalink( $sample_id ) : '',
						);
					}

					$taxonomy_rewrites = array();
					$taxes             = get_taxonomies( array( 'public' => true ), 'objects' );
					foreach ( $taxes as $tax ) {
						$taxonomy_rewrites[ $tax->name ] = array(
							'rewrite_slug' => is_array( $tax->rewrite ) && isset( $tax->rewrite['slug'] ) ? (string) $tax->rewrite['slug'] : '',
						);
					}

					return array(
						'permalink_structure' => $structure,
						'is_pretty'           => '' !== $structure,
						'category_base'       => (string) get_option( 'category_base', '' ),
						'tag_base'            => (string) get_option( 'tag_base', '' ),
						'home_url'            => (string) home_url( '/' ),
						'site_url'            => (string) site_url( '/' ),
						'post_types'          => $post_type_examples,
						'taxonomies'          => $taxonomy_rewrites,
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

/*
 * MCP annotations summary for this file:
 *
 *   list-post-types         readOnly idempotent  (no destructive)
 *   list-taxonomies         readOnly idempotent
 *   list-post-statuses      readOnly idempotent
 *   list-shortcodes         readOnly idempotent
 *   get-permalink-structure readOnly idempotent
 */
