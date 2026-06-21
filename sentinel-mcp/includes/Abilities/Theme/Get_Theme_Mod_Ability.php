<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Theme;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * Read theme modification ability.
 *
 * Reads theme modifications (theme_mods) for the active theme.
 */
class Get_Theme_Mod_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/get-theme-mod';
    }

    public static function label(): string
    {
        return 'Read theme modification';
    }

    public static function category(): string
    {
        return 'sentinel-system';
    }

    public static function description(): string
    {
        return 'All parameters optional. '
            . 'Reads theme modifications (theme_mods) for the active theme. '
            . 'Pass a specific key to get one value, or omit to get all theme_mods. '
            . 'Common keys: custom_logo (attachment ID), background_color, header_textcolor, '
            . 'header_image, nav_menu_locations. Keys are theme-specific.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'key' => array(
                    'type'        => 'string',
                    'description' => 'Theme mod key to read. Omit to return all theme mods. '
                        . 'Alias: name is also accepted.',
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
        return \SentinelMCP\SENTINEL_ability_permission('edit_theme_options');
    }

    public static function execute(array $input = array()): array
    {
        $input = is_array($input) ? $input : array();
        $key   = sanitize_text_field($input['key'] ?? $input['name'] ?? '');

        if (! empty($key)) {
            $value = get_theme_mod($key, '__SENTINEL_NOT_SET__');
            if ('__SENTINEL_NOT_SET__' === $value) {
                return array(
                    'success' => false,
                    'message' => sprintf('Theme mod "%s" is not set.', $key),
                );
            }

            return array(
                'success' => true,
                'theme'   => get_stylesheet(),
                'mods'    => array($key => $value),
            );
        }

        // Return all theme mods.
        $mods = get_theme_mods();
        if (! is_array($mods)) {
            $mods = array();
        }

        // Remove internal keys.
        unset($mods[0]);

        return array(
            'success' => true,
            'theme'   => get_stylesheet(),
            'count'   => count($mods),
            'mods'    => $mods,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
