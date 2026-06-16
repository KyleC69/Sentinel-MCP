<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Menus_Widgets;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * List sidebars ability.
 *
 * Lists every sidebar registered by the active theme or plugins.
 */
class List_Sidebars_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-sidebars';
    }

    public static function label(): string
    {
        return 'List sidebars';
    }

    public static function category(): string
    {
        return 'sentinel-menus-widgets';
    }

    public static function description(): string
    {
        return 'Read-only. Lists every sidebar registered by the active theme or plugins (id, name, description, before/after wrapper). Useful to know where widgets can be placed.';
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
            'type'                 => 'object',
            'additionalProperties' => true,
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\mcpcomal_ability_permission('edit_theme_options');
    }

    public static function execute(array $input = array()): array
    {
        $registered = isset($GLOBALS['wp_registered_sidebars']) && is_array($GLOBALS['wp_registered_sidebars'])
            ? $GLOBALS['wp_registered_sidebars']
            : array();

        $result = array();
        foreach ($registered as $id => $sb) {
            $result[] = array(
                'id'            => (string) $id,
                'name'          => isset($sb['name']) ? (string) $sb['name'] : '',
                'description'   => isset($sb['description']) ? (string) $sb['description'] : '',
                'before_widget' => isset($sb['before_widget']) ? (string) $sb['before_widget'] : '',
                'after_widget'  => isset($sb['after_widget']) ? (string) $sb['after_widget'] : '',
                'before_title'  => isset($sb['before_title']) ? (string) $sb['before_title'] : '',
                'after_title'   => isset($sb['after_title']) ? (string) $sb['after_title'] : '',
            );
        }

        return array(
            'count'    => count($result),
            'sidebars' => $result,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
