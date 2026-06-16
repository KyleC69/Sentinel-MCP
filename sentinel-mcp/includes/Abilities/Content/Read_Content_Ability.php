<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Content;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Read_Content_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/read-content';
    }

    public static function label(): string
    {
        return 'Read content from any post';
    }

    public static function category(): string
    {
        return 'sentinel-content';
    }

    public static function description(): string
    {
        return 'Required: post_id (integer). '
            . 'Reads the full content of any post by its ID. '
            . 'Includes title, content, meta, taxonomies with terms, and post type. '
            . 'Alias: id is also accepted instead of post_id.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'post_id' => array(
                    'type'        => 'integer',
                    'description' => 'ID of the post to read.',
                ),
                'id'      => array(
                    'type'        => 'integer',
                    'description' => 'Alias for post_id.',
                ),
            ),
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'       => 'object',
            'properties' => array(
                'ID'         => array('type' => 'integer'),
                'title'      => array('type' => 'string'),
                'content'    => array('type' => 'string'),
                'excerpt'    => array('type' => 'string'),
                'status'     => array('type' => 'string'),
                'post_type'  => array('type' => 'string'),
                'date'       => array('type' => 'string'),
                'url'        => array('type' => 'string'),
                'parent'     => array('type' => 'integer'),
                'slug'       => array('type' => 'string'),
                'taxonomies' => array(
                    'type'                 => 'object',
                    'additionalProperties' => true,
                ),
                'meta'       => array(
                    'type'                 => 'object',
                    'additionalProperties' => true,
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
        $post = get_post((int) ($input['post_id'] ?? $input['id'] ?? 0));
        if (! $post) {
            return array(
                'success' => false,
                'message' => 'Post not found.',
            );
        }

        $taxonomies = get_object_taxonomies($post->post_type, 'objects');
        $tax_data   = array();
        foreach ($taxonomies as $tax) {
            $terms = wp_get_object_terms($post->ID, $tax->name);
            if (! is_wp_error($terms) && ! empty($terms)) {
                $tax_data[$tax->name] = array_map(
                    function ($t) {
                        return array(
                            'id'   => $t->term_id,
                            'name' => $t->name,
                            'slug' => $t->slug,
                        );
                    },
                    $terms
                );
            }
        }

        $all_meta  = get_post_meta($post->ID);
        $meta_data = array();
        foreach ($all_meta as $key => $values) {
            if (str_starts_with($key, '_edit_') || str_starts_with($key, '_wp_')) {
                continue;
            }
            if (str_starts_with($key, '_') && isset($all_meta['_' . $key])) {
                continue;
            }
            $meta_data[$key] = count($values) === 1 ? $values[0] : $values;
        }

        return array(
            'ID'         => $post->ID,
            'title'      => $post->post_title,
            'content'    => $post->post_content,
            'excerpt'    => $post->post_excerpt,
            'status'     => $post->post_status,
            'post_type'  => $post->post_type,
            'date'       => $post->post_date,
            'url'        => get_permalink($post->ID),
            'parent'     => $post->post_parent,
            'slug'       => $post->post_name,
            'taxonomies' => $tax_data,
            'meta'       => $meta_data,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
