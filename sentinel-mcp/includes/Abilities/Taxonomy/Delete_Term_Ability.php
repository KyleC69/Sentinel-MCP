<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Taxonomy;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * Delete taxonomy term ability.
 *
 * Permanently deletes a term from a taxonomy.
 */
class Delete_Term_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/delete-term';
    }

    public static function label(): string
    {
        return 'Delete taxonomy term';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'Required: term_id (integer), taxonomy (string). '
            . 'Permanently deletes a term from a taxonomy. Posts assigned to this term '
            . 'will have the term removed (they are NOT deleted). '
            . 'Alias: id is also accepted instead of term_id.';
    }

    public static function input_schema(): array
    {
        return array(
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
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'       => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'message' => array('type' => 'string'),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\mcpcomal_ability_permission('manage_categories');
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
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta(array('readOnlyHint' => false, 'destructiveHint' => true));
    }
}
