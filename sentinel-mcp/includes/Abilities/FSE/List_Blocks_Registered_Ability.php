<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\FSE;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class List_Blocks_Registered_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-blocks-registered';
    }

    public static function label(): string
    {
        return 'List registered blocks';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'Read-only. Lists every block type registered on the site (core, theme, plugins) with name, title, category, description and the list of attribute keys. Render callbacks and full attribute schemas are omitted to keep the payload small.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'properties'           => array(
                'category' => array(
                    'type'        => 'string',
                    'description' => 'Optional: filter by block category slug (e.g. "text", "media", "design", "widgets").',
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
        return \SentinelMCP\mcpcomal_ability_permission('edit_posts');
    }

    public static function execute(array $input = array()): array
    {
        if (! class_exists('WP_Block_Type_Registry')) {
            return array(
                'count'  => 0,
                'blocks' => array(),
            );
        }

        $filter   = isset($input['category']) ? sanitize_key((string) $input['category']) : '';
        $registry = \WP_Block_Type_Registry::get_instance();
        $all      = $registry->get_all_registered();
        $result   = array();

        foreach ($all as $name => $block) {
            $cat = isset($block->category) ? (string) $block->category : '';
            if ('' !== $filter && $cat !== $filter) {
                continue;
            }
            $attrs = is_array($block->attributes) ? array_keys($block->attributes) : array();
            $result[] = array(
                'name'        => (string) $name,
                'title'       => isset($block->title) ? (string) $block->title : '',
                'description' => isset($block->description) ? (string) $block->description : '',
                'category'    => $cat,
                'icon'        => isset($block->icon) && is_string($block->icon) ? $block->icon : '',
                'keywords'    => is_array($block->keywords) ? array_values($block->keywords) : array(),
                'attributes'  => $attrs,
                'is_dynamic'  => is_callable($block->render_callback),
            );
        }

        return array(
            'count'  => count($result),
            'blocks' => $result,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
