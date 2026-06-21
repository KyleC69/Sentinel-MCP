<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\FSE;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class List_Block_Patterns_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-block-patterns';
    }

    public static function label(): string
    {
        return 'List block patterns';
    }

    public static function category(): string
    {
        return 'sentinel-fse';
    }

    public static function description(): string
    {
        return 'Read-only. Lists every block pattern registered on the site (core + theme + plugins) with name, title, description and category. Returns names only — pattern content is not included.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'properties'           => array(
                'category' => array(
                    'type'        => 'string',
                    'description' => 'Optional: filter by pattern category slug (e.g. "header", "footer", "buttons").',
                ),
            ),
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
        return \SentinelMCP\SENTINEL_ability_permission('edit_posts');
    }

    public static function execute(array $input = array()): array
    {
        if (! class_exists('WP_Block_Patterns_Registry')) {
            return array(
                'count'    => 0,
                'patterns' => array(),
            );
        }

        $filter   = isset($input['category']) ? sanitize_key((string) $input['category']) : '';
        $registry = \WP_Block_Patterns_Registry::get_instance();
        $all      = $registry->get_all_registered();
        $result   = array();

        foreach ($all as $pattern) {
            $categories = isset($pattern['categories']) && is_array($pattern['categories']) ? $pattern['categories'] : array();
            if ('' !== $filter && ! in_array($filter, $categories, true)) {
                continue;
            }
            $result[] = array(
                'name'        => isset($pattern['name']) ? (string) $pattern['name'] : '',
                'title'       => isset($pattern['title']) ? (string) $pattern['title'] : '',
                'description' => isset($pattern['description']) ? (string) $pattern['description'] : '',
                'categories'  => $categories,
                'keywords'    => isset($pattern['keywords']) && is_array($pattern['keywords']) ? $pattern['keywords'] : array(),
                'block_types' => isset($pattern['blockTypes']) && is_array($pattern['blockTypes']) ? $pattern['blockTypes'] : array(),
                'source'      => isset($pattern['source']) ? (string) $pattern['source'] : '',
            );
        }

        return array(
            'count'    => count($result),
            'patterns' => $result,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
