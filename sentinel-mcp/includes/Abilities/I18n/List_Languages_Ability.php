<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\I18n;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class List_Languages_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/i18n-list-languages';
    }

    public static function label(): string
    {
        return 'List site languages';
    }

    public static function category(): string
    {
        return 'sentinel-i18n';
    }

    public static function description(): string
    {
        return 'Read-only. Returns the languages active on the site (code, name, locale, flag URL, default flag) using whichever multilingual plugin is detected (Polylang, WPML, TranslatePress). Returns an empty list with a "no_plugin" hint if no multilingual plugin is active.';
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
        return \SentinelMCP\mcpcomal_ability_permission('read');
    }

    public static function execute(array $input = array()): array
    {
        $adapter = \SentinelMCP\I18n_Adapter::active();
        if ('' === $adapter) {
            return array(
                'plugin'    => '',
                'languages' => array(),
                'note'      => 'no_plugin: no multilingual plugin detected (Polylang, WPML, TranslatePress).',
            );
        }
        return array(
            'plugin'    => $adapter::slug(),
            'languages' => $adapter::list_languages(),
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
