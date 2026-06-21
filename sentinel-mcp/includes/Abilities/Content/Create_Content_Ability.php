<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Content;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Create_Content_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/create-content';
    }

    public static function label(): string
    {
        return 'Create content in any post type';
    }

    public static function category(): string
    {
        return 'sentinel-content';
    }

    public static function description(): string
    {
        return 'Required: title (string). '
            . 'Creates content in any post type (post, page, or custom CPTs). '
            . 'Supports title, content, excerpt, status, taxonomies (categories, tags, custom), '
            . 'meta fields (standard and ACF), parent page (for hierarchical), and menu order. '
            . 'IMPORTANT: Content MUST use Gutenberg block markup (<!-- wp:paragraph --><p>text</p>'
            . '<!-- /wp:paragraph -->). Plain HTML without block delimiters may be emptied by '
            . 'sanitization. Call "sentinel/gutenberg-reference" FIRST to get the correct block syntax. '
            . 'Use "sentinel/inspect-post-type" to discover available fields for the CPT.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'required'   => array('title'),
            'properties' => array(
                'post_type'   => array(
                    'type'        => 'string',
                    'description' => 'Post type slug. E.g.: "post", "page", "guide", "doc". Defaults to "post".',
                    'default'     => 'post',
                ),
                'title'       => array(
                    'type'        => 'string',
                    'description' => 'Content title.',
                ),
                'content'     => array(
                    'type'        => 'string',
                    'description' => 'Post content in Gutenberg block markup format. '
                        . 'Use <!-- wp:paragraph --><p>text</p><!-- /wp:paragraph --> delimiters. '
                        . 'Plain HTML without block delimiters may be emptied by sanitization. '
                        . 'Call "sentinel/gutenberg-reference" FIRST to get the correct block syntax.',
                    'default'     => '',
                ),
                'excerpt'     => array(
                    'type'        => 'string',
                    'description' => 'Short excerpt or summary.',
                    'default'     => '',
                ),
                'post_status' => array(
                    'type'        => 'string',
                    'description' => 'Status: "draft", "publish", "pending", "private".',
                    'enum'        => array('draft', 'publish', 'pending', 'private'),
                    'default'     => 'draft',
                ),
                'post_parent' => array(
                    'type'        => 'integer',
                    'description' => 'Parent page/post ID (for hierarchical CPTs).',
                    'default'     => 0,
                ),
                'menu_order'  => array(
                    'type'        => 'integer',
                    'description' => 'Menu order (for hierarchical CPTs).',
                    'default'     => 0,
                ),
                'slug'        => array(
                    'type'        => 'string',
                    'description' => 'Custom URL slug. If omitted, generated from title.',
                    'default'     => '',
                ),
                'taxonomies'  => array(
                    'type'                 => 'object',
                    'description'          => 'Taxonomies to assign. Object where each key is the taxonomy slug '
                        . 'and value is an array of term slugs. '
                        . 'E.g.: {"category": ["news"], "post_tag": ["redsys", "woocommerce"], '
                        . '"doc_category": ["redsys-guides"]}.',
                    'additionalProperties' => true,
                    'default'              => array(),
                ),
                'meta'        => array(
                    'type'                 => 'object',
                    'description'          => 'Meta fields to save. Object where each key is the meta_key '
                        . 'and value is the meta_value. Works with standard and ACF fields. '
                        . 'E.g.: {"price": "49.99", "difficulty_level": "intermediate"}.',
                    'additionalProperties' => true,
                    'default'              => array(),
                ),
            ),
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'       => 'object',
            'properties' => array(
                'success'   => array('type' => 'boolean'),
                'post_id'   => array('type' => 'integer'),
                'post_url'  => array('type' => 'string'),
                'edit_url'  => array('type' => 'string'),
                'post_type' => array('type' => 'string'),
                'message'   => array('type' => 'string'),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\SENTINEL_ability_permission('publish_posts');
    }

    public static function execute(array $input = array()): array
    {
        return \SentinelMCP\SENTINEL_universal_create($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta(array('readOnlyHint' => false, 'idempotentHint' => false));
    }
}
