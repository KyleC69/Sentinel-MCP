<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Discovery_Extended;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class List_Shortcodes_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-shortcodes';
    }

    public static function label(): string
    {
        return 'List registered shortcodes';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'Read-only. Returns the names of all shortcodes registered on the site (no callbacks, no source code). Helps the AI client know which shortcodes exist before suggesting content that uses them.';
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
            'type'       => 'object',
            'properties' => array(
                'count'      => array('type' => 'integer'),
                'shortcodes' => array(
                    'type'  => 'array',
                    'items' => array('type' => 'string'),
                ),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\SENTINEL_ability_permission('read');
    }

    public static function execute(array $input = array()): array
    {
        global $shortcode_tags;
        $names = is_array($shortcode_tags) ? array_keys($shortcode_tags) : array();
        sort($names);

        return array(
            'count'      => count($names),
            'shortcodes' => $names,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
