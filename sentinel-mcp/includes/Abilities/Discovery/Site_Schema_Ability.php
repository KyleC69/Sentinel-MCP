<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Discovery;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\Schema_Inspector;

defined('ABSPATH') || exit;

class Site_Schema_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/site-schema';
    }

    public static function label(): string
    {
        return 'View site structure';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'All parameters optional. '
            . 'Returns a complete summary of the WordPress site structure: '
            . 'all content types (CPTs), their taxonomies, meta fields (including ACF), '
            . 'and supported features. This is the first thing to call to understand '
            . 'what content types are available on this site.';
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
        return Schema_Inspector::get_site_schema_summary();
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
