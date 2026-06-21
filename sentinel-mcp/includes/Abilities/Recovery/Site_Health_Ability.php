<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Recovery;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Site_Health_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/site-health';
    }

    public static function label(): string
    {
        return 'Site health status';
    }

    public static function category(): string
    {
        return 'sentinel-recovery';
    }

    public static function description(): string
    {
        return 'Shows site status: WP/PHP version, last fatal error, paused plugins, memory usage, and disk space.';
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
        return \SentinelMCP\SENTINEL_ability_permission('manage_options');
    }

    public static function execute(array $input = array()): array
    {
        return \SentinelMCP\File_Manager::get_site_health();
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
