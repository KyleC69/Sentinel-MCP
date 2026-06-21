<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Media;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\Media_Manager;

defined('ABSPATH') || exit;

/**
 * Delete media attachment ability.
 *
 * Deletes a media attachment from the WordPress Media Library.
 */
class Delete_Media_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/delete-media';
    }

    public static function label(): string
    {
        return 'Delete media attachment';
    }

    public static function category(): string
    {
        return 'sentinel-media';
    }

    public static function description(): string
    {
        return 'Required: attachment_id (integer). '
            . 'Deletes a media attachment from the WordPress Media Library. '
            . 'By default moves to trash. Set force=true for permanent deletion '
            . '(removes the file from disk). Use sentinel/list-media to find the attachment ID. '
            . 'Alias: id is also accepted instead of attachment_id.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'attachment_id' => array(
                    'type'        => 'integer',
                    'description' => 'The attachment ID to delete.',
                ),
                'id'            => array(
                    'type'        => 'integer',
                    'description' => 'Alias for attachment_id.',
                ),
                'force'         => array(
                    'type'        => 'boolean',
                    'description' => 'true = permanent deletion (removes files from disk). false = move to trash.',
                    'default'     => false,
                ),
            ),
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'       => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'message' => array('type' => 'string'),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\SENTINEL_ability_permission('upload_files');
    }

    public static function execute(array $input = array()): array
    {
        $input['attachment_id'] = $input['attachment_id'] ?? $input['id'] ?? 0;
        return Media_Manager::delete_media($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta(array('readOnlyHint' => false, 'destructiveHint' => true));
    }
}
