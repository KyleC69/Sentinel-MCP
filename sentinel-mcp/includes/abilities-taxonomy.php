<?php

/**
 * Taxonomy CRUD Abilities.
 *
 * Create, update, and delete terms in any taxonomy.
 * Complements the existing sentinel/list-terms ability in abilities-discovery.php.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 */

defined('ABSPATH') || exit;

add_action(
	'wp_abilities_api_init',
	function () {
		/*
		 * CREATE TERM
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/create-term',
			array(
				'label'               => 'Create taxonomy term',
				'category'            => 'sentinel-discovery',
				'description'         => 'Required: taxonomy (string), name (string). '
					. 'Creates a new term in any taxonomy (categories, tags, product_cat, etc.). '
					. 'Call sentinel/list-terms first to see existing terms and avoid duplicates. '
					. 'For hierarchical taxonomies, set parent to nest terms.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'required'   => array('taxonomy', 'name'),
					'properties' => array(
						'taxonomy'    => array(
							'type'        => 'string',
							'description' => 'Taxonomy slug. E.g.: "category", "post_tag", "product_cat".',
						),
						'name'        => array(
							'type'        => 'string',
							'description' => 'Term name.',
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => 'Custom slug (auto-generated from name if omitted).',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Term description.',
						),
						'parent'      => array(
							'type'        => 'integer',
							'description' => 'Parent term ID for hierarchical taxonomies.',
							'default'     => 0,
						),
						'meta'        => array(
							'type'                 => 'object',
							'description'          => 'Term meta key-value pairs.',
							'additionalProperties' => array('type' => 'string'),
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array('type' => 'boolean'),
						'term_id'  => array('type' => 'integer'),
						'name'     => array('type' => 'string'),
						'slug'     => array('type' => 'string'),
						'taxonomy' => array('type' => 'string'),
						'message'  => array('type' => 'string'),
					),
				),

				'execute_callback'    => function ($input) {
					$taxonomy = sanitize_text_field($input['taxonomy']);
					$name     = sanitize_text_field($input['name']);

					if (! taxonomy_exists($taxonomy)) {
						return array(
							'success' => false,
							'message' => sprintf('Taxonomy "%s" not found.', $taxonomy),
						);
					}

					$args = array();
					if (! empty($input['slug'])) {
						$args['slug'] = sanitize_title($input['slug']);
					}
					if (! empty($input['description'])) {
						$args['description'] = sanitize_text_field($input['description']);
					}
					if (! empty($input['parent'])) {
						$args['parent'] = absint($input['parent']);
					}

					$result = wp_insert_term($name, $taxonomy, $args);

					if (is_wp_error($result)) {
						return array(
							'success' => false,
							'message' => $result->get_error_message(),
						);
					}

					$term = get_term($result['term_id'], $taxonomy);

					// Set meta if provided.
					if (! empty($input['meta']) && is_array($input['meta'])) {
						foreach ($input['meta'] as $key => $value) {
							update_term_meta($term->term_id, sanitize_text_field($key), sanitize_text_field($value));
						}
					}

					return array(
						'success'  => true,
						'term_id'  => $term->term_id,
						'name'     => $term->name,
						'slug'     => $term->slug,
						'taxonomy' => $taxonomy,
						'message'  => sprintf('Term "%s" created in "%s" (ID: %d).', $term->name, $taxonomy, $term->term_id),
					);
				},

				'permission_callback' => function () {
					return current_user_can('manage_categories');
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => false,
							'destructiveHint' => false,
							'idempotentHint'  => false,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);

		/*
		 * UPDATE TERM
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/update-term',
			array(
				'label'               => 'Update taxonomy term',
				'category'            => 'sentinel-discovery',
				'description'         => 'Required: term_id (integer), taxonomy (string). '
					. 'Updates an existing term: name, slug, description, or parent. '
					. 'Only the provided fields are modified. '
					. 'Alias: id is also accepted instead of term_id.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'required'   => array('taxonomy'),
					'properties' => array(
						'term_id'     => array(
							'type'        => 'integer',
							'description' => 'Term ID to update.',
						),
						'id'          => array(
							'type'        => 'integer',
							'description' => 'Alias for term_id.',
						),
						'taxonomy'    => array(
							'type'        => 'string',
							'description' => 'Taxonomy slug (required by WordPress API).',
						),
						'name'        => array(
							'type'        => 'string',
							'description' => 'New term name.',
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => 'New term slug.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'New term description.',
						),
						'parent'      => array(
							'type'        => 'integer',
							'description' => 'New parent term ID.',
						),
						'meta'        => array(
							'type'                 => 'object',
							'description'          => 'Term meta key-value pairs to update.',
							'additionalProperties' => array('type' => 'string'),
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array('type' => 'boolean'),
						'term_id' => array('type' => 'integer'),
						'message' => array('type' => 'string'),
					),
				),

				'execute_callback'    => function ($input) {
					$term_id  = absint($input['term_id'] ?? $input['id'] ?? 0);
					$taxonomy = sanitize_text_field($input['taxonomy']);

					if (! taxonomy_exists($taxonomy)) {
						return array(
							'success' => false,
							'message' => sprintf('Taxonomy "%s" not found.', $taxonomy),
						);
					}

					$term = get_term($term_id, $taxonomy);
					if (! $term || is_wp_error($term)) {
						return array(
							'success' => false,
							'message' => sprintf('Term #%d not found in "%s".', $term_id, $taxonomy),
						);
					}

					$args    = array();
					$updated = array();

					if (! empty($input['name'])) {
						$args['name'] = sanitize_text_field($input['name']);
						$updated[]    = 'name';
					}
					if (! empty($input['slug'])) {
						$args['slug'] = sanitize_title($input['slug']);
						$updated[]    = 'slug';
					}
					if (isset($input['description'])) {
						$args['description'] = sanitize_text_field($input['description']);
						$updated[]           = 'description';
					}
					if (isset($input['parent'])) {
						$args['parent'] = absint($input['parent']);
						$updated[]      = 'parent';
					}

					if (! empty($args)) {
						$result = wp_update_term($term_id, $taxonomy, $args);
						if (is_wp_error($result)) {
							return array(
								'success' => false,
								'message' => $result->get_error_message(),
							);
						}
					}

					// Update meta if provided.
					if (! empty($input['meta']) && is_array($input['meta'])) {
						foreach ($input['meta'] as $key => $value) {
							update_term_meta($term_id, sanitize_text_field($key), sanitize_text_field($value));
						}
						$updated[] = 'meta';
					}

					if (empty($updated)) {
						return array(
							'success' => false,
							'message' => 'No fields to update.',
						);
					}

					return array(
						'success' => true,
						'term_id' => $term_id,
						'message' => sprintf('Term #%d updated: %s.', $term_id, implode(', ', $updated)),
					);
				},

				'permission_callback' => function () {
					return current_user_can('manage_categories');
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

		/*
		 * DELETE TERM
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/delete-term',
			array(
				'label'               => 'Delete taxonomy term',
				'category'            => 'sentinel-discovery',
				'description'         => 'Required: term_id (integer), taxonomy (string). '
					. 'Permanently deletes a term from a taxonomy. Posts assigned to this term '
					. 'will have the term removed (they are NOT deleted). '
					. 'Alias: id is also accepted instead of term_id.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'required'   => array('taxonomy'),
					'properties' => array(
						'term_id'  => array(
							'type'        => 'integer',
							'description' => 'Term ID to delete.',
						),
						'id'       => array(
							'type'        => 'integer',
							'description' => 'Alias for term_id.',
						),
						'taxonomy' => array(
							'type'        => 'string',
							'description' => 'Taxonomy slug.',
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array('type' => 'boolean'),
						'message' => array('type' => 'string'),
					),
				),

				'execute_callback'    => function ($input) {
					$term_id  = absint($input['term_id'] ?? $input['id'] ?? 0);
					$taxonomy = sanitize_text_field($input['taxonomy']);

					if (! taxonomy_exists($taxonomy)) {
						return array(
							'success' => false,
							'message' => sprintf('Taxonomy "%s" not found.', $taxonomy),
						);
					}

					$term = get_term($term_id, $taxonomy);
					if (! $term || is_wp_error($term)) {
						return array(
							'success' => false,
							'message' => sprintf('Term #%d not found in "%s".', $term_id, $taxonomy),
						);
					}

					$name   = $term->name;
					$result = wp_delete_term($term_id, $taxonomy);

					if (is_wp_error($result)) {
						return array(
							'success' => false,
							'message' => $result->get_error_message(),
						);
					}

					if (false === $result) {
						return array(
							'success' => false,
							'message' => 'Cannot delete the default term.',
						);
					}

					return array(
						'success' => true,
						'message' => sprintf('Term "%s" (ID: %d) deleted from "%s".', $name, $term_id, $taxonomy),
					);
				},

				'permission_callback' => function () {
					return current_user_can('manage_categories');
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => false,
							'destructiveHint' => true,
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);
	}
);
