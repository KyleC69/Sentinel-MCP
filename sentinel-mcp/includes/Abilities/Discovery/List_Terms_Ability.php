<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Discovery;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class List_Terms_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-terms';
    }

    public static function label(): string
    {
        return 'List terms of a taxonomy';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'Required: taxonomy (string, e.g. "category", "post_tag", "product_cat"). '
            . 'Lists all terms of a specific taxonomy. Returns term ID, name, slug, post count, and parent.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'required'   => array('taxonomy'),
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
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'  => 'array',
            'items' => array(
                'type'       => 'object',
                'properties' => array(
                    'id'     => array('type' => 'integer'),
                    'name'   => array('type' => 'string'),
                    'slug'   => array('type' => 'string'),
                    'count'  => array('type' => 'integer'),
                    'parent' => array('type' => 'integer'),
                ),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\mcpcomal_ability_permission('read');
    }

    public static function execute(array $input = array()): array
    {
        $taxonomy = sanitize_text_field($input['taxonomy']);

        if (! taxonomy_exists($taxonomy)) {
            return array(
                'success' => false,
                'message' => sprintf('Taxonomy "%s" not found.', $taxonomy),
            );
        }

        $terms = get_terms(
            array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => $input['hide_empty'] ?? false,
                'number'     => 200,
            )
        );

        if (is_wp_error($terms)) {
            return array(
                'success' => false,
                'message' => $terms->get_error_message(),
            );
        }

        return array_map(
            function ($term) {
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
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
