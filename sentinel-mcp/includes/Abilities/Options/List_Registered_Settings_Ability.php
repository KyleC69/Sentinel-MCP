<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Options;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * List registered settings ability.
 *
 * Lists all settings registered via the WordPress Settings API.
 */
class List_Registered_Settings_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-registered-settings';
    }

    public static function label(): string
    {
        return 'List registered settings';
    }

    public static function category(): string
    {
        return 'sentinel-system';
    }

    public static function description(): string
    {
        return 'Lists all settings registered via the WordPress Settings API (register_setting). '
            . 'Shows option name, group, type, description, and default value. '
            . 'Useful for discovering configurable options of installed plugins. '
            . 'Use the optional "group" parameter to filter by settings group/page.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'group' => array(
                    'type'        => 'string',
                    'description' => 'Filter by settings group (e.g., "general", "reading", "discussion"). Omit to list all.',
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
        $input = is_array($input) ? $input : array();
        $group = sanitize_text_field($input['group'] ?? '');

        $all_settings = get_registered_settings();
        $result       = array();

        foreach ($all_settings as $option_name => $args) {
            if (! empty($group) && ($args['group'] ?? '') !== $group) {
                continue;
            }

            $result[] = array(
                'option_name'  => $option_name,
                'group'        => $args['group'] ?? '',
                'type'         => $args['type'] ?? 'string',
                'description'  => $args['description'] ?? '',
                'default'      => $args['default'] ?? null,
                'show_in_rest' => ! empty($args['show_in_rest']),
            );
        }

        return array(
            'success'  => true,
            'count'    => count($result),
            'settings' => $result,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
