<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Options;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\Options_Manager;

defined('ABSPATH') || exit;

/**
 * Read WordPress option ability.
 *
 * Reads WordPress options from a security whitelist.
 */
class Get_Option_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/get-option';
    }

    public static function label(): string
    {
        return 'Read WordPress option';
    }

    public static function category(): string
    {
        return 'sentinel-system';
    }

    public static function description(): string
    {
        return 'All parameters optional. '
            . 'Reads WordPress options (site title, URL, timezone, permalinks, etc.) '
            . 'from a security whitelist. Pass a specific option name, or omit to get all '
            . 'whitelisted options. Includes WooCommerce options if WC is active.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'name' => array(
                    'type'        => 'string',
                    'description' => 'Option name to read. Omit to return all whitelisted options. '
                        . 'Examples: "blogname", "permalink_structure", "woocommerce_currency".',
                ),
            ),
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'       => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'options' => array(
                    'type'                 => 'object',
                    'additionalProperties' => true,
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
        return Options_Manager::get_option($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
