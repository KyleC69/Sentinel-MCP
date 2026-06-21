<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\System_Info;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\System_Info;

defined('ABSPATH') || exit;

/**
 * System information ability.
 *
 * Returns comprehensive server, PHP, database, and WordPress diagnostics.
 */
class System_Info_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/system-info';
    }

    public static function label(): string
    {
        return 'System information';
    }

    public static function category(): string
    {
        return 'sentinel-system';
    }

    public static function description(): string
    {
        return 'All parameters optional. Returns comprehensive server environment information equivalent to WooCommerce System Status. '
            . 'Works with or without WooCommerce. Includes: WordPress version and config, PHP version '
            . 'and extensions, database details with per-table sizes, web server with remote connectivity '
            . 'tests, active/inactive plugins with update status, theme with WC template overrides, '
            . 'security configuration, WordPress constants, WooCommerce store settings (currency, HPOS, '
            . 'gateways, pages, features), post type counts, and logging status. '
            . 'Ideal for diagnosing issues, checking compatibility, and recommending improvements.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'sections' => array(
                    'type'        => 'array',
                    'description' => 'Sections to include. All by default. The "woocommerce" section only returns data when WooCommerce is active.',
                    'items'       => array(
                        'type' => 'string',
                        'enum' => array('wordpress', 'server', 'php', 'database', 'theme', 'plugins', 'security', 'constants', 'woocommerce', 'post_type_counts', 'logging'),
                    ),
                    'default'     => array('wordpress', 'server', 'php', 'database', 'theme', 'plugins', 'security', 'constants', 'woocommerce', 'post_type_counts', 'logging'),
                ),
            ),
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
        return System_Info::get_info($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
