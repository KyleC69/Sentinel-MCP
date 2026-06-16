<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Media;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\Media_Manager;

defined('ABSPATH') || exit;

/**
 * Set featured image ability.
 *
 * Sets or removes the featured image (post thumbnail) for any post, page, or product.
 */
class Set_Featured_Image_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/set-featured-image';
    }

    public static function label(): string
    {
        return 'Set featured image';
    }

    public static function category(): string
    {
        return 'sentinel-media';
    }

    public static function description(): string
    {
        return 'Required: post_id (integer). '
            . 'Sets or removes the featured image (post thumbnail) for any post, page, or product. '
            . 'Pass attachment_id to set, or 0 / omit to remove the current featured image. '
            . 'Use sentinel/list-media or sentinel/upload-media first to get the attachment ID. '
            . 'Alias: id is also accepted instead of post_id.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'post_id'       => array(
                    'type'        => 'integer',
                    'description' => 'The post/page/product ID to set the featured image on.',
                ),
                'id'            => array(
                    'type'        => 'integer',
                    'description' => 'Alias for post_id.',
                ),
                'attachment_id' => array(
                    'type'        => 'integer',
                    'description' => 'The media attachment ID to use as featured image. Pass 0 or omit to remove.',
                    'default'     => 0,
                ),
            ),
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'       => 'object',
            'properties' => array(
                'success'       => array('type' => 'boolean'),
                'post_id'       => array('type' => 'integer'),
                'attachment_id' => array('type' => 'integer'),
                'thumbnail_url' => array('type' => 'string'),
                'message'       => array('type' => 'string'),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\mcpcomal_ability_permission('upload_files');
    }

    public static function execute(array $input = array()): array
    {
        $input['post_id'] = $input['post_id'] ?? $input['id'] ?? 0;
        return Media_Manager::set_featured_image($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta(array('readOnlyHint' => false));
    }
}
