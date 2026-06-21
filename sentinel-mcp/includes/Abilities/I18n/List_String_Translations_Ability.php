<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\I18n;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class List_String_Translations_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/i18n-list-string-translations';
    }

    public static function label(): string
    {
        return 'List string translations';
    }

    public static function category(): string
    {
        return 'sentinel-i18n';
    }

    public static function description(): string
    {
        return 'Read-only. Lists translated theme/plugin strings, when the active multilingual plugin exposes them (WPML icl_strings, TranslatePress dictionary). Polylang has no public enumeration API and returns a partial hint.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'properties'           => array(
                'page'     => array(
                    'type'    => 'integer',
                    'minimum' => 1,
                    'default' => 1,
                ),
                'per_page' => array(
                    'type'    => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 50,
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
        return \SentinelMCP\SENTINEL_ability_permission('manage_options');
    }

    public static function execute(array $input = array()): array
    {
        $page     = isset($input['page']) ? max(1, (int) $input['page']) : 1;
        $per_page = isset($input['per_page']) ? max(1, min(100, (int) $input['per_page'])) : 50;

        $adapter = \SentinelMCP\I18n_Adapter::active();
        if ('' === $adapter) {
            return array(
                'plugin' => '',
                'items'  => array(),
                'note'   => 'no_plugin: no multilingual plugin detected.',
            );
        }
        $result           = $adapter::list_string_translations($page, $per_page);
        $result['plugin'] = $adapter::slug();
        return $result;
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
