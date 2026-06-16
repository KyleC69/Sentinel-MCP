<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Recovery;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class List_Plugins_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-plugins';
    }

    public static function label(): string
    {
        return 'List all plugins';
    }

    public static function category(): string
    {
        return 'sentinel-recovery';
    }

    public static function description(): string
    {
        return 'Lists all installed plugins with their status: active, inactive, or paused (due to fatal error). '
            . 'Includes name, slug, version, and plugin file.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(),
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'  => 'array',
            'items' => array(
                'type'       => 'object',
                'properties' => array(
                    'name'    => array('type' => 'string'),
                    'slug'    => array('type' => 'string'),
                    'version' => array('type' => 'string'),
                    'active'  => array('type' => 'boolean'),
                    'paused'  => array('type' => 'boolean'),
                    'file'    => array('type' => 'string'),
                ),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\mcpcomal_ability_permission('manage_options');
    }

    public static function execute(array $input = array()): array
    {
        return \SentinelMCP\File_Manager::list_plugins();
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
