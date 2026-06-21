<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Media;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\Media_Manager;

defined('ABSPATH') || exit;

/**
 * Upload media from URL ability.
 *
 * Downloads a file from a URL and adds it to the WordPress Media Library.
 */
class Upload_Media_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/upload-media';
    }

    public static function label(): string
    {
        return 'Upload media from URL';
    }

    public static function category(): string
    {
        return 'sentinel-media';
    }

    public static function description(): string
    {
        return 'Required: url (string). '
            . 'Downloads a file from a URL and adds it to the WordPress Media Library. '
            . 'Supports images, videos, audio, PDFs, and common document types. '
            . 'Optionally set title, alt text, caption, and attach to a post. '
            . 'After uploading, use sentinel/set-featured-image to assign it as a featured image.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'required'   => array('url'),
            'properties' => array(
                'url'         => array(
                    'type'        => 'string',
                    'description' => 'Full URL of the file to download and upload to the media library.',
                ),
                'title'       => array(
                    'type'        => 'string',
                    'description' => 'Override the media title. If omitted, extracted from the filename.',
                ),
                'alt_text'    => array(
                    'type'        => 'string',
                    'description' => 'Alt text for images (for accessibility and SEO).',
                ),
                'caption'     => array(
                    'type'        => 'string',
                    'description' => 'Media caption.',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'Media description.',
                ),
                'post_id'     => array(
                    'type'        => 'integer',
                    'description' => 'Attach the media to this post ID. 0 = unattached.',
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
                'attachment_id' => array('type' => 'integer'),
                'url'           => array('type' => 'string'),
                'mime_type'     => array('type' => 'string'),
                'message'       => array('type' => 'string'),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\SENTINEL_ability_permission('upload_files');
    }

    public static function execute(array $input = array()): array
    {
        return Media_Manager::upload_media($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta(array('readOnlyHint' => false, 'idempotentHint' => false));
    }
}
