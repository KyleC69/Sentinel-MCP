<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Discovery_Extended;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class List_Post_Types_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-post-types';
    }

    public static function label(): string
    {
        return 'List post types';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'Read-only. Lists every post type registered on the site (public and private), with name, label, hierarchical flag, has_archive flag, public flag, rewrite slug and the list of supported features (title, editor, thumbnail, etc.). '
            . 'Useful as a quick directory before calling inspect-post-type or before creating content.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'properties'           => array(
                'public_only' => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'When true, only post types declared as public are returned.',
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
        return \SentinelMCP\mcpcomal_ability_permission('read');
    }

    public static function execute(array $input = array()): array
    {
        $args = array();
        if (! empty($input['public_only'])) {
            $args['public'] = true;
        }

        $types  = get_post_types($args, 'objects');
        $result = array();

        foreach ($types as $pt) {
            $result[] = array(
                'name'         => $pt->name,
                'label'        => $pt->label,
                'public'       => (bool) $pt->public,
                'hierarchical' => (bool) $pt->hierarchical,
                'has_archive'  => (bool) $pt->has_archive,
                'show_in_rest' => (bool) $pt->show_in_rest,
                'rewrite_slug' => is_array($pt->rewrite) && isset($pt->rewrite['slug']) ? (string) $pt->rewrite['slug'] : '',
                'supports'     => array_keys(get_all_post_type_supports($pt->name)),
            );
        }

        return $result;
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
