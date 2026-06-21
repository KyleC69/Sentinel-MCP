<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Content;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Update_Content_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/update-content';
    }

    public static function label(): string
    {
        return 'Update existing content';
    }

    public static function category(): string
    {
        return 'sentinel-content';
    }

    public static function description(): string
    {
        return 'Required: post_id (integer). '
            . 'Updates any field of an existing post: title, content, excerpt, '
            . 'status, taxonomies, meta fields. Only provided fields are updated. '
            . 'IMPORTANT: Content MUST use Gutenberg block markup (<!-- wp:paragraph --><p>text</p>'
            . '<!-- /wp:paragraph -->). Plain HTML without block delimiters may be emptied by '
            . 'sanitization. Call "sentinel/gutenberg-reference" FIRST to get the correct block syntax. '
            . 'Alias: id is also accepted instead of post_id.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'post_id'     => array(
                    'type'        => 'integer',
                    'description' => 'ID of the post to update.',
                ),
                'id'          => array(
                    'type'        => 'integer',
                    'description' => 'Alias for post_id.',
                ),
                'title'       => array('type' => 'string'),
                'content'     => array(
                    'type'        => 'string',
                    'description' => 'Content as HTML or Gutenberg block markup. '
                        . 'Plain HTML is auto-converted to blocks. '
                        . 'For advanced layouts (columns, buttons, cover, groups, etc.) '
                        . 'send Gutenberg block markup with <!-- wp:blockname --> delimiters. '
                        . 'Call "sentinel/gutenberg-reference" first to see the block syntax guide.',
                ),
                'excerpt'     => array('type' => 'string'),
                'post_status' => array(
                    'type' => 'string',
                    'enum' => array('draft', 'publish', 'pending', 'private'),
                ),
                'slug'        => array('type' => 'string'),
                'taxonomies'  => array(
                    'type'                 => 'object',
                    'description'          => 'Taxonomies to update (replaces existing terms).',
                    'additionalProperties' => true,
                ),
                'meta'        => array(
                    'type'                 => 'object',
                    'description'          => 'Meta fields to update.',
                    'additionalProperties' => true,
                ),
            ),
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'       => 'object',
            'properties' => array(
                'success'  => array('type' => 'boolean'),
                'post_id'  => array('type' => 'integer'),
                'post_url' => array('type' => 'string'),
                'message'  => array('type' => 'string'),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\SENTINEL_ability_permission('edit_posts');
    }

    public static function execute(array $input = array()): array
    {
        return \SentinelMCP\SENTINEL_universal_update($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta(array('readOnlyHint' => false));
    }
}
