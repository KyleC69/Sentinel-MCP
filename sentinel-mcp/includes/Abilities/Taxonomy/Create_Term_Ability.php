<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Taxonomy;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * Create taxonomy term ability.
 *
 * Creates a new term in any taxonomy.
 */
class Create_Term_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/create-term';
    }

    public static function label(): string
    {
        return 'Create taxonomy term';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'Required: taxonomy (string), name (string). '
            . 'Creates a new term in any taxonomy (categories, tags, product_cat, etc.). '
            . 'Call sentinel/list-terms first to see existing terms and avoid duplicates. '
            . 'For hierarchical taxonomies, set parent to nest terms.';
    }

    public static function input_schema(): array
    {
        return array(
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
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'       => 'object',
            'properties' => array(
                'success'  => array('type' => 'boolean'),
                'term_id'  => array('type' => 'integer'),
                'name'     => array('type' => 'string'),
                'slug'     => array('type' => 'string'),
                'taxonomy' => array('type' => 'string'),
                'message'  => array('type' => 'string'),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\mcpcomal_ability_permission('manage_categories');
    }

    public static function execute(array $input = array()): array
    {
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
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta(array('readOnlyHint' => false, 'idempotentHint' => false));
    }
}
