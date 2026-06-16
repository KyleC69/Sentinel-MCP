<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Content;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Search_Content_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/search-content';
    }

    public static function label(): string
    {
        return 'Search content';
    }

    public static function category(): string
    {
        return 'sentinel-content';
    }

    public static function description(): string
    {
        return 'All parameters optional. '
            . 'Searches content across any post type by text, category, custom taxonomy, '
            . 'meta field, date range, or any combination of filters. '
            . 'Supports publish date and modified date filters '
            . 'to find outdated or recently updated content.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'search'          => array(
                    'type'        => 'string',
                    'description' => 'Search text (searches in title and content).',
                    'default'     => '',
                ),
                'post_type'       => array(
                    'type'        => 'string',
                    'description' => 'Post type slug. Use "any" for all types.',
                    'default'     => 'any',
                ),
                'taxonomy_filter' => array(
                    'type'                 => 'object',
                    'description'          => 'Taxonomy filter. E.g.: {"category": "news"} or {"doc_category": "guides"}.',
                    'additionalProperties' => true,
                    'default'              => array(),
                ),
                'meta_filter'     => array(
                    'type'                 => 'object',
                    'description'          => 'Meta filter. E.g.: {"level": "advanced"}. Each key is meta_key, value is meta_value.',
                    'additionalProperties' => true,
                    'default'              => array(),
                ),
                'post_status'     => array(
                    'type'        => 'string',
                    'default'     => 'any',
                    'description' => '"any", "publish", "draft", etc.',
                ),
                'count'           => array(
                    'type'        => 'integer',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 50,
                    'description' => 'Number of results per page (max 50). Alias: per_page is also accepted.',
                ),
                'orderby'         => array(
                    'type'    => 'string',
                    'default' => 'date',
                    'enum'    => array('date', 'title', 'modified', 'menu_order', 'rand'),
                ),
                'order'           => array(
                    'type'    => 'string',
                    'default' => 'DESC',
                    'enum'    => array('ASC', 'DESC'),
                ),
                'date_after'      => array(
                    'type'        => 'string',
                    'description' => 'Posts published after this date (YYYY-MM-DD).',
                ),
                'date_before'     => array(
                    'type'        => 'string',
                    'description' => 'Posts published before this date (YYYY-MM-DD).',
                ),
                'modified_after'  => array(
                    'type'        => 'string',
                    'description' => 'Posts modified after this date (YYYY-MM-DD). Useful to find recently updated content.',
                ),
                'modified_before' => array(
                    'type'        => 'string',
                    'description' => 'Posts modified before this date (YYYY-MM-DD). Useful to find outdated content.',
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
                    'ID'         => array('type' => 'integer'),
                    'title'      => array('type' => 'string'),
                    'url'        => array('type' => 'string'),
                    'date'       => array('type' => 'string'),
                    'modified'   => array('type' => 'string'),
                    'status'     => array('type' => 'string'),
                    'post_type'  => array('type' => 'string'),
                    'excerpt'    => array('type' => 'string'),
                    'word_count' => array('type' => 'integer'),
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
        $allowed_orderby = array('date', 'title', 'modified', 'ID', 'name', 'author', 'rand', 'comment_count', 'menu_order', 'none');
        $orderby         = sanitize_text_field($input['orderby'] ?? 'date');
        $order           = strtoupper(sanitize_text_field($input['order'] ?? 'DESC'));

        $args = array(
            'numberposts' => min(absint($input['count'] ?? $input['per_page'] ?? 10), 100),
            'post_type'   => sanitize_text_field($input['post_type'] ?? 'any'),
            'post_status' => sanitize_text_field($input['post_status'] ?? 'any'),
            'orderby'     => in_array($orderby, $allowed_orderby, true) ? $orderby : 'date',
            'order'       => in_array($order, array('ASC', 'DESC'), true) ? $order : 'DESC',
        );

        if ('any' === $args['post_status']) {
            $args['post_status'] = array('publish', 'draft', 'pending', 'private');
        }

        if (! empty($input['search'])) {
            $args['s'] = sanitize_text_field($input['search']);
        }

        if (! empty($input['taxonomy_filter']) && is_array($input['taxonomy_filter'])) {
            $tax_query = array();
            foreach ($input['taxonomy_filter'] as $tax => $term_slug) {
                $tax_query[] = array(
                    'taxonomy' => sanitize_text_field($tax),
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($term_slug),
                );
            }
            $args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for taxonomy filtering.
        }

        if (! empty($input['meta_filter']) && is_array($input['meta_filter'])) {
            $meta_query = array();
            foreach ($input['meta_filter'] as $key => $value) {
                $meta_query[] = array(
                    'key'   => sanitize_text_field($key),
                    'value' => sanitize_text_field($value),
                );
            }
            $args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for meta filtering.
        }

        $date_query = array();
        if (! empty($input['date_after'])) {
            $date_query[] = array(
                'column' => 'post_date',
                'after'  => sanitize_text_field($input['date_after']),
            );
        }
        if (! empty($input['date_before'])) {
            $date_query[] = array(
                'column' => 'post_date',
                'before' => sanitize_text_field($input['date_before']),
            );
        }
        if (! empty($input['modified_after'])) {
            $date_query[] = array(
                'column' => 'post_modified',
                'after'  => sanitize_text_field($input['modified_after']),
            );
        }
        if (! empty($input['modified_before'])) {
            $date_query[] = array(
                'column' => 'post_modified',
                'before' => sanitize_text_field($input['modified_before']),
            );
        }
        if (! empty($date_query)) {
            $args['date_query'] = $date_query;
        }

        $posts = get_posts($args);

        return array_map(
            function ($post) {
                return array(
                    'ID'         => $post->ID,
                    'title'      => $post->post_title,
                    'url'        => get_permalink($post),
                    'date'       => $post->post_date,
                    'modified'   => $post->post_modified,
                    'status'     => $post->post_status,
                    'post_type'  => $post->post_type,
                    'excerpt'    => wp_trim_words($post->post_content, 30),
                    'word_count' => str_word_count(wp_strip_all_tags($post->post_content)),
                );
            },
            $posts
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
