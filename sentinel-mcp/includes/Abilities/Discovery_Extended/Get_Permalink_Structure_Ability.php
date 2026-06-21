<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Discovery_Extended;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Get_Permalink_Structure_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/get-permalink-structure';
    }

    public static function label(): string
    {
        return 'Get permalink structure';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'Read-only. Returns the active permalink structure (e.g. "/%postname%/"), example URLs for each public post type, the category and tag bases, and rewrite slugs for custom post types and taxonomies. Use this to generate correct internal links.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'properties'           => array(),
            'additionalProperties' => false,
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => true,
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\SENTINEL_ability_permission('read');
    }

    public static function execute(array $input = array()): array
    {
        $structure = (string) get_option('permalink_structure', '');

        $post_type_examples = array();
        $post_types         = get_post_types(array('public' => true), 'objects');
        foreach ($post_types as $pt) {
            $sample_id = 0;
            $query     = new \WP_Query(
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
            if (! empty($query->posts)) {
                $sample_id = (int) $query->posts[0];
            }

            $post_type_examples[$pt->name] = array(
                'rewrite_slug'     => is_array($pt->rewrite) && isset($pt->rewrite['slug']) ? (string) $pt->rewrite['slug'] : '',
                'has_archive'      => (bool) $pt->has_archive,
                'archive_url'      => $pt->has_archive ? (string) get_post_type_archive_link($pt->name) : '',
                'sample_post_id'   => $sample_id,
                'sample_permalink' => $sample_id ? (string) get_permalink($sample_id) : '',
            );
        }

        $taxonomy_rewrites = array();
        $taxes             = get_taxonomies(array('public' => true), 'objects');
        foreach ($taxes as $tax) {
            $taxonomy_rewrites[$tax->name] = array(
                'rewrite_slug' => is_array($tax->rewrite) && isset($tax->rewrite['slug']) ? (string) $tax->rewrite['slug'] : '',
            );
        }

        return array(
            'permalink_structure' => $structure,
            'is_pretty'           => '' !== $structure,
            'category_base'       => (string) get_option('category_base', ''),
            'tag_base'            => (string) get_option('tag_base', ''),
            'home_url'            => (string) home_url('/'),
            'site_url'            => (string) site_url('/'),
            'post_types'          => $post_type_examples,
            'taxonomies'          => $taxonomy_rewrites,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
