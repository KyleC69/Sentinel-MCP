<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Content;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Delete_Content_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/delete-content';
    }

    public static function label(): string
    {
        return 'Delete content';
    }

    public static function category(): string
    {
        return 'sentinel-content';
    }

    public static function description(): string
    {
        return 'Required: post_id (integer). '
            . 'Moves a post to the trash or permanently deletes it. '
            . 'Alias: id is also accepted instead of post_id.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'post_id' => array(
                    'type'        => 'integer',
                    'description' => 'ID of the post to delete.',
                ),
                'id'      => array(
                    'type'        => 'integer',
                    'description' => 'Alias for post_id.',
                ),
                'force'   => array(
                    'type'        => 'boolean',
                    'description' => 'true = permanently delete, false = move to trash.',
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
        return \SentinelMCP\mcpcomal_ability_permission('delete_posts');
    }

    public static function execute(array $input = array()): array
    {
        $post_id = (int) ($input['post_id'] ?? $input['id'] ?? 0);
        $post    = get_post($post_id);

        if (! $post) {
            return array(
                'success' => false,
                'message' => 'Post not found.',
            );
        }

        $title = $post->post_title;
        $force = (bool) ($input['force'] ?? false);

        $result = wp_delete_post($post_id, $force);

        if (! $result) {
            return array(
                'success' => false,
                'message' => 'Failed to delete the post.',
            );
        }

        return array(
            'success' => true,
            'message' => sprintf(
                '"%s" %s successfully.',
                $title,
                $force ? 'permanently deleted' : 'moved to trash'
            ),
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta(array('readOnlyHint' => false, 'destructiveHint' => true));
    }
}
