<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Menus_Widgets;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * List navigation menus ability.
 *
 * Lists every classic navigation menu with its items and assigned theme locations.
 */
class List_Nav_Menus_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-nav-menus';
    }

    public static function label(): string
    {
        return 'List navigation menus';
    }

    public static function category(): string
    {
        return 'sentinel-menus-widgets';
    }

    public static function description(): string
    {
        return 'Read-only. Lists every classic navigation menu (wp_get_nav_menus) with id, name, slug, item count and the theme locations it is assigned to. For each menu it also returns its items: id, title, url, parent, type, object.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'properties'           => array(
                'include_items' => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
            ),
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
        $include_items = ! isset($input['include_items']) || ! empty($input['include_items']);

        $menus     = wp_get_nav_menus();
        $locations = (array) get_nav_menu_locations();
        $result    = array();

        foreach ((array) $menus as $menu) {
            $assigned = array();
            foreach ($locations as $location => $menu_id) {
                if ((int) $menu_id === (int) $menu->term_id) {
                    $assigned[] = (string) $location;
                }
            }

            $entry = array(
                'id'        => (int) $menu->term_id,
                'name'      => (string) $menu->name,
                'slug'      => (string) $menu->slug,
                'count'     => (int) $menu->count,
                'locations' => $assigned,
            );

            if ($include_items) {
                $items  = wp_get_nav_menu_items($menu->term_id);
                $mapped = array();
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $mapped[] = array(
                            'id'        => (int) $item->ID,
                            'title'     => (string) $item->title,
                            'url'       => (string) $item->url,
                            'parent'    => (int) $item->menu_item_parent,
                            'order'     => (int) $item->menu_order,
                            'type'      => (string) $item->type,
                            'object'    => (string) $item->object,
                            'object_id' => (int) $item->object_id,
                        );
                    }
                }
                $entry['items'] = $mapped;
            }

            $result[] = $entry;
        }

        return array(
            'count' => count($result),
            'menus' => $result,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
