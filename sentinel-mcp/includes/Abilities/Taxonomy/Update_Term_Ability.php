<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Taxonomy;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * Update taxonomy term ability.
 *
 * Updates an existing term: name, slug, description, or parent.
 */
class Update_Term_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/update-term';
    }

    public static function label(): string
    {
        return 'Update taxonomy term';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'Required: term_id (integer), taxonomy (string). '
            . 'Updates an existing term: name, slug, description, or parent. '
            . 'Only the provided fields are modified. '
            . 'Alias: id is also accepted instead of term_id.';
    }

    public static function input_schema(): array
    {
        return array(
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
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'       => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'term_id' => array('type' => 'integer'),
                'message' => array('type' => 'string'),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\SENTINEL_ability_permission('manage_categories');
    }

    public static function execute(array $input = array()): array
    {
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
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta(array('readOnlyHint' => false));
    }
}
