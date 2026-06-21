<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Discovery_Extended;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class List_Taxonomies_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-taxonomies';
    }

    public static function label(): string
    {
        return 'List taxonomies';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'Read-only. Lists every taxonomy registered on the site with name, label, the post types it applies to (object_types), hierarchical flag and public flag.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'properties'           => array(
                'public_only' => array(
                    'type'    => 'boolean',
                    'default' => false,
                ),
            ),
            'additionalProperties' => false,
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'  => 'array',
            'items' => array(
                'type'                 => 'object',
                'additionalProperties' => true,
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\SENTINEL_ability_permission('read');
    }

    public static function execute(array $input = array()): array
    {
        $args = array();
        if (! empty($input['public_only'])) {
            $args['public'] = true;
        }

        $taxes  = get_taxonomies($args, 'objects');
        $result = array();

        foreach ($taxes as $tax) {
            $result[] = array(
                'name'         => $tax->name,
                'label'        => $tax->label,
                'object_types' => array_values((array) $tax->object_type),
                'public'       => (bool) $tax->public,
                'hierarchical' => (bool) $tax->hierarchical,
                'show_in_rest' => (bool) $tax->show_in_rest,
                'rewrite_slug' => is_array($tax->rewrite) && isset($tax->rewrite['slug']) ? (string) $tax->rewrite['slug'] : '',
            );
        }

        return $result;
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
