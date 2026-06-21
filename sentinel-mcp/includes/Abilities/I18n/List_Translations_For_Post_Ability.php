<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\I18n;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class List_Translations_For_Post_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/i18n-list-translations-for-post';
    }

    public static function label(): string
    {
        return 'List translations of a post';
    }

    public static function category(): string
    {
        return 'sentinel-i18n';
    }

    public static function description(): string
    {
        return 'Read-only. For a given post_id, returns the translation map across languages: each entry has language code, translated post_id, status and title. Adapters that translate inline (TranslatePress) return a single ID per language with a note.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'required'             => array('post_id'),
            'properties'           => array(
                'post_id' => array(
                    'type'    => 'integer',
                    'minimum' => 1,
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
        return function ($input) {
            $post_id = isset($input['post_id']) ? absint($input['post_id']) : 0;
            return $post_id ? current_user_can('read_post', $post_id) : false;
        };
    }

    public static function execute(array $input = array()): array
    {
        $post_id = isset($input['post_id']) ? absint($input['post_id']) : 0;
        if (! $post_id || ! get_post($post_id)) {
            return array(
                'success' => false,
                'message' => 'Post not found.',
            );
        }
        $adapter = \SentinelMCP\I18n_Adapter::active();
        if ('' === $adapter) {
            return array(
                'plugin'       => '',
                'translations' => array(),
                'note'         => 'no_plugin: no multilingual plugin detected.',
            );
        }
        return array(
            'plugin'       => $adapter::slug(),
            'post_id'      => $post_id,
            'translations' => $adapter::list_translations_for_post($post_id),
        );
    }

    public static function meta(): array
    {
        return array(
            'mcp' => array(
                'public'      => true,
                'annotations' => array(
                    'readOnlyHint'    => true,
                    'destructiveHint' => false,
                    'idempotentHint'  => true,
                    'openWorldHint'   => false,
                ),
            ),
        );
    }
}
