<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\SEO;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Read_SEO_Meta_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/seo-read-meta';
    }

    public static function label(): string
    {
        return 'Read SEO meta for a post';
    }

    public static function category(): string
    {
        return 'sentinel-seo';
    }

    public static function description(): string
    {
        return 'Read-only. Returns the SEO metadata of a post (title, description, focus keyword, canonical, robots_noindex) detected from the active SEO plugin. Supports Yoast, Rank Math, AIOSEO, The SEO Framework, SureRank, SEOPress, Slim SEO and Squirrly. If multiple SEO plugins are active, returns one entry per plugin so the caller can compare. Bulk SEO rewriting is reserved for the Premium edition.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'required'             => array('post_id'),
            'properties'           => array(
                'post_id' => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'ID of the post whose SEO meta to read.',
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

        $detected = \SentinelMCP\SEO_Adapter::detect_active_plugins();
        $entries  = \SentinelMCP\SEO_Adapter::read_for_post($post_id);

        $response = array(
            'post_id'          => $post_id,
            'detected_plugins' => $detected,
            'entries'          => $entries,
        );

        return $response;
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
