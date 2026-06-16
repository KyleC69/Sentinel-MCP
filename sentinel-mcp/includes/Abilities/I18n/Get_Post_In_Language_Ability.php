<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\I18n;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Get_Post_In_Language_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/i18n-get-post-in-language';
    }

    public static function label(): string
    {
        return 'Get post in another language';
    }

    public static function category(): string
    {
        return 'sentinel-i18n';
    }

    public static function description(): string
    {
        return 'Read-only. Resolves the post ID in the target language and returns its full content (shortcut to read-content for the translated ID). Returns null if no translation exists. Alias: lang is also accepted instead of language.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'required'             => array('post_id'),
            'properties'           => array(
                'post_id'  => array(
                    'type'    => 'integer',
                    'minimum' => 1,
                ),
                'language' => array(
                    'type'        => 'string',
                    'description' => 'Target language code (e.g. "es", "en", "fr"). Required (or pass "lang" as alias).',
                ),
                'lang'     => array(
                    'type'        => 'string',
                    'description' => 'Alias for language.',
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
        $post_id      = isset($input['post_id']) ? absint($input['post_id']) : 0;
        $language_raw = $input['language'] ?? $input['lang'] ?? '';
        $language     = sanitize_key((string) $language_raw);

        if (! $post_id || ! get_post($post_id)) {
            return array(
                'success' => false,
                'message' => 'Post not found.',
            );
        }
        if ('' === $language) {
            return array(
                'success' => false,
                'message' => 'Language code is required.',
            );
        }

        $adapter = \SentinelMCP\I18n_Adapter::active();
        if ('' === $adapter) {
            return array(
                'success' => false,
                'message' => 'no_plugin: no multilingual plugin detected.',
            );
        }

        $translated_id = $adapter::get_post_in_language($post_id, $language);
        if (null === $translated_id) {
            return array(
                'success' => false,
                'plugin'  => $adapter::slug(),
                'message' => 'No translation found for that language.',
            );
        }

        $post = get_post($translated_id);
        return array(
            'success'   => true,
            'plugin'    => $adapter::slug(),
            'language'  => $language,
            'post_id'   => $translated_id,
            'title'     => $post ? (string) $post->post_title : '',
            'status'    => $post ? (string) $post->post_status : '',
            'content'   => $post ? (string) $post->post_content : '',
            'permalink' => $post ? (string) get_permalink($post) : '',
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
