<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Menus_Widgets;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * List widgets ability.
 *
 * Returns the widget instances currently active in every sidebar.
 */
class List_Widgets_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-widgets';
    }

    public static function label(): string
    {
        return 'List widgets';
    }

    public static function category(): string
    {
        return 'sentinel-menus-widgets';
    }

    public static function description(): string
    {
        return 'Read-only. Returns the widget instances currently active in every sidebar (sidebar_id => array of widget ids). Includes the widget base type and a copy of its instance settings when available.';
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
        return \SentinelMCP\SENTINEL_ability_permission('edit_theme_options');
    }

    public static function execute(array $input = array()): array
    {
        $sidebars = (array) wp_get_sidebars_widgets();
        $result   = array();

        foreach ($sidebars as $sidebar_id => $widget_ids) {
            if (! is_array($widget_ids)) {
                continue;
            }

            $widgets = array();
            foreach ($widget_ids as $widget_id) {
                $base = preg_replace('/-\d+$/', '', (string) $widget_id);
                $num  = 0;
                if (preg_match('/-(\d+)$/', (string) $widget_id, $m)) {
                    $num = (int) $m[1];
                }

                $instance = array();
                if ($base) {
                    $option = get_option('widget_' . $base);
                    if (is_array($option) && isset($option[$num]) && is_array($option[$num])) {
                        $instance = $option[$num];
                    }
                }

                $widgets[] = array(
                    'id'       => (string) $widget_id,
                    'base'     => (string) $base,
                    'instance' => $instance,
                );
            }

            $result[(string) $sidebar_id] = $widgets;
        }

        return array(
            'sidebars' => $result,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
