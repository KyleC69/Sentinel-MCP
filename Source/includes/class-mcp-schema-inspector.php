<?php
/**
 * Inspector de esquemas de WordPress.
 *
 * Autodescubre dinámicamente:
 *  - Todos los CPTs registrados y sus capacidades
 *  - Todas las taxonomías y sus términos
 *  - Campos meta registrados (register_meta)
 *  - Campos ACF (si está activo)
 *  - Meta boxes registrados
 *
 * Esto permite que Cowork/Claude conozca la estructura completa
 * del sitio sin que el usuario tenga que configurar nada.
 *
 * @package    SENTINEL
 * @author     José Conti <j.conti@joseconti.com>
 * @copyright  2026 José Conti
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'SENTINEL_Schema_Inspector' ) ) {

	if ( class_exists( 'SENTINEL_Schema_Inspector' ) ) {
		return;
	}

	/**
	 * Dynamic schema inspector for WordPress post types, taxonomies, and meta fields.
	 */
	class SENTINEL_Schema_Inspector {

		/**
		 * Get all public post types with full detail.
		 *
		 * @param bool $include_builtin Incluir post y page.
		 * @return array
		 */
		public static function get_post_types( bool $include_builtin = true ): array {
			$post_types = get_post_types( array( 'public' => true ), 'objects' );
			$result     = array();

			foreach ( $post_types as $pt ) {
				// Exclude attachment.
				if ( 'attachment' === $pt->name ) {
					continue;
				}
				if ( ! $include_builtin && in_array( $pt->name, array( 'post', 'page' ), true ) ) {
					continue;
				}

				// Defensive: wrap taxonomy/meta inspection so one bad CPT doesn't break the whole list.
				try {
					$taxonomies  = self::get_taxonomies_for_post_type( $pt->name );
					$meta_fields = self::get_meta_fields_for_post_type( $pt->name );
				} catch ( \Throwable $e ) {
					$taxonomies  = array();
					$meta_fields = array();
				}

				$result[] = array(
					'name'         => $pt->name,
					'label'        => $pt->label,
					'description'  => $pt->description ? $pt->description : '',
					'hierarchical' => $pt->hierarchical,
					'has_archive'  => (bool) $pt->has_archive,
					'supports'     => get_all_post_type_supports( $pt->name ),
					'taxonomies'   => $taxonomies,
					'meta_fields'  => $meta_fields,
					'rest_base'    => $pt->rest_base ? $pt->rest_base : $pt->name,
					'menu_icon'    => $pt->menu_icon ? $pt->menu_icon : '',
					'labels'       => array(
						'singular' => $pt->labels->singular_name ?? $pt->label,
						'plural'   => $pt->labels->name ?? $pt->label,
						'add_new'  => $pt->labels->add_new_item ?? '',
					),
				);
			}

			return $result;
		}

		/**
		 * Get taxonomies of a post type with their terms.
		 *
		 * @param string $post_type Post type slug to inspect.
		 * @return array
		 */
		public static function get_taxonomies_for_post_type( string $post_type ): array {
			$taxonomies = get_object_taxonomies( $post_type, 'objects' );
			$result     = array();

			foreach ( $taxonomies as $tax ) {
				$terms = get_terms(
					array(
						'taxonomy'   => $tax->name,
						'hide_empty' => false,
						'number'     => 100,
					)
				);

				$term_list = array();
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$term_list[] = array(
							'id'     => $term->term_id,
							'name'   => $term->name,
							'slug'   => $term->slug,
							'count'  => $term->count,
							'parent' => $term->parent,
						);
					}
				}

				$result[] = array(
					'name'         => $tax->name,
					'label'        => $tax->label,
					'hierarchical' => $tax->hierarchical,
					'terms'        => $term_list,
				);
			}

			return $result;
		}

		/**
		 * Get meta fields for a post type.
		 *
		 * Fuentes:
		 *  1. register_meta() — campos registrados oficialmente
		 *  2. ACF (si está activo) — grupos de campos
		 *  3. Meta keys existentes en la BD (fallback)
		 *
		 * @param string $post_type Post type slug to get meta fields for.
		 * @return array
		 */
		public static function get_meta_fields_for_post_type( string $post_type ): array {
			$fields = array();

			// 1. Campos registrados con register_meta.
			$registered = get_registered_meta_keys( 'post', $post_type );
			foreach ( $registered as $key => $schema ) {
				// Ignorar campos internos de WP.
				if ( str_starts_with( $key, '_' ) && ! str_starts_with( $key, '_mcpcomal_' ) ) {
					continue;
				}
				$fields[ $key ] = array(
					'key'         => $key,
					'type'        => $schema['type'] ?? 'string',
					'description' => $schema['description'] ?? '',
					'source'      => 'register_meta',
					'required'    => false,
				);
			}

			// 2. ACF fields (if active).
			if ( function_exists( 'acf_get_field_groups' ) ) {
				try {
					$groups = acf_get_field_groups( array( 'post_type' => $post_type ) );
					foreach ( $groups as $group ) {
						$acf_fields = acf_get_fields( $group['key'] );
						if ( ! $acf_fields || ! is_array( $acf_fields ) ) {
							continue;
						}
						foreach ( $acf_fields as $field ) {
							if ( empty( $field['name'] ) ) {
								continue;
							}
							$fields[ $field['name'] ] = array(
								'key'         => $field['name'],
								'type'        => self::map_acf_type( $field['type'] ?? 'text' ),
								'description' => ( $field['label'] ?? '' ) . ( ! empty( $field['instructions'] ) ? ' — ' . $field['instructions'] : '' ),
								'source'      => 'acf',
								'acf_type'    => $field['type'] ?? 'text',
								'required'    => (bool) ( $field['required'] ?? false ),
								'choices'     => $field['choices'] ?? null,
								'default'     => $field['default_value'] ?? null,
							);
						}
					}
				} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
					// ACF field inspection failed for this post type — skip gracefully.
				}
			}

			// 3. Existing meta fields in DB (sample from latest posts).
			$sample_fields = self::get_sample_meta_keys( $post_type );
			foreach ( $sample_fields as $key ) {
				if ( ! isset( $fields[ $key ] ) ) {
					$fields[ $key ] = array(
						'key'         => $key,
						'type'        => 'string',
						'description' => 'Field detected in existing posts.',
						'source'      => 'database',
						'required'    => false,
					);
				}
			}

			return array_values( $fields );
		}

		/**
		 * Get meta keys from the latest posts as a sample.
		 *
		 * @param string $post_type Post type slug to sample meta keys from.
		 * @return array
		 */
		private static function get_sample_meta_keys( string $post_type ): array {
			global $wpdb;

			$like_underscore = $wpdb->esc_like( '_' ) . '%';
			$like_field      = $wpdb->esc_like( 'field_' ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Meta key discovery query, results vary per post type.
			$keys = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT pm.meta_key
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = %s
			   AND pm.meta_key NOT LIKE %s
			   AND pm.meta_key NOT LIKE %s
			 ORDER BY pm.meta_key
			 LIMIT 50",
					$post_type,
					$like_underscore,
					$like_field
				)
			);

			return $keys ? $keys : array();
		}

		/**
		 * Mapear tipos de ACF a tipos JSON Schema.
		 *
		 * @param string $acf_type ACF field type to map.
		 * @return string
		 */
		private static function map_acf_type( string $acf_type ): string {
			$map = array(
				'text'             => 'string',
				'textarea'         => 'string',
				'number'           => 'number',
				'range'            => 'number',
				'email'            => 'string',
				'url'              => 'string',
				'password'         => 'string',
				'image'            => 'integer',
				'file'             => 'integer',
				'wysiwyg'          => 'string',
				'oembed'           => 'string',
				'gallery'          => 'array',
				'select'           => 'string',
				'checkbox'         => 'array',
				'radio'            => 'string',
				'button_group'     => 'string',
				'true_false'       => 'boolean',
				'link'             => 'object',
				'post_object'      => 'integer',
				'page_link'        => 'string',
				'relationship'     => 'array',
				'taxonomy'         => 'array',
				'user'             => 'integer',
				'google_map'       => 'object',
				'date_picker'      => 'string',
				'date_time_picker' => 'string',
				'time_picker'      => 'string',
				'color_picker'     => 'string',
				'message'          => 'string',
				'tab'              => 'string',
				'group'            => 'object',
				'repeater'         => 'array',
				'flexible_content' => 'array',
				'clone'            => 'object',
			);

			return $map[ $acf_type ] ?? 'string';
		}

		/**
		 * Get a compact site summary so Claude has context.
		 *
		 * @return array
		 */
		public static function get_site_schema_summary(): array {
			$post_types = self::get_post_types();

			$summary = array(
				'site_name'  => get_bloginfo( 'name' ),
				'site_url'   => home_url(),
				'post_types' => array(),
				'total_cpts' => 0,
			);

			foreach ( $post_types as $pt ) {
				$tax_names  = array_column( $pt['taxonomies'], 'name' );
				$meta_names = array_column( $pt['meta_fields'], 'key' );

				$summary['post_types'][] = array(
					'name'             => $pt['name'],
					'label'            => $pt['label'],
					'hierarchical'     => $pt['hierarchical'],
					'taxonomy_count'   => count( $pt['taxonomies'] ),
					'taxonomies'       => $tax_names,
					'meta_field_count' => count( $pt['meta_fields'] ),
					'meta_fields'      => $meta_names,
					'supports'         => array_keys( array_filter( $pt['supports'] ) ),
				);
			}

			$summary['total_cpts'] = count( $summary['post_types'] );

			return $summary;
		}
	}

}
