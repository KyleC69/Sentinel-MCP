<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Discovery_Extended;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class List_Post_Statuses_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-post-statuses';
    }

    public static function label(): string
    {
        return 'List post statuses';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'Read-only. Lists every post status registered on the site (publish, draft, pending, private, future, trash, plus any custom ones) with label and visibility flags (public, internal, protected, exclude_from_search).';
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
        $statuses = get_post_stati(array(), 'objects');
        $result   = array();

        foreach ($statuses as $st) {
            $result[] = array(
                'name'                      => $st->name,
                'label'                     => $st->label,
                'public'                    => (bool) $st->public,
                'internal'                  => (bool) $st->internal,
                'protected'                 => (bool) $st->protected,
                'private'                   => (bool) $st->private,
                'exclude_from_search'       => (bool) $st->exclude_from_search,
                'show_in_admin_all_list'    => (bool) $st->show_in_admin_all_list,
                'show_in_admin_status_list' => (bool) $st->show_in_admin_status_list,
            );
        }

        return $result;
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
