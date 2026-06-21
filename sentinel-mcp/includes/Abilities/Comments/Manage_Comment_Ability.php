<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Comments;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\Comment_Manager;

defined('ABSPATH') || exit;

/**
 * Moderate and reply to comments ability.
 *
 * Manages a specific comment: approve, unapprove, spam, trash, delete, or reply.
 */
class Manage_Comment_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/manage-comment';
    }

    public static function label(): string
    {
        return 'Moderate and reply to comments';
    }

    public static function category(): string
    {
        return 'sentinel-comments';
    }

    public static function description(): string
    {
        return 'Required: comment_id (integer), action (string). '
            . 'Manages a specific comment: approve, unapprove, mark as spam, '
            . 'trash, permanently delete, or reply. '
            . 'To reply, provide reply_content with the response text. '
            . 'Alias: id is also accepted instead of comment_id.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'required'   => array('action'),
            'properties' => array(
                'comment_id'    => array(
                    'type'        => 'integer',
                    'description' => 'ID of the comment to manage.',
                ),
                'id'            => array(
                    'type'        => 'integer',
                    'description' => 'Alias for comment_id.',
                ),
                'action'        => array(
                    'type'        => 'string',
                    'description' => 'Action to perform on the comment.',
                    'enum'        => array('approve', 'unapprove', 'spam', 'trash', 'delete', 'reply'),
                ),
                'reply_content' => array(
                    'type'        => 'string',
                    'description' => 'HTML content of the reply. Required when action is "reply".',
                    'default'     => '',
                ),
            ),
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'       => 'object',
            'properties' => array(
                'success'    => array('type' => 'boolean'),
                'comment_id' => array('type' => 'integer'),
                'new_status' => array('type' => 'string'),
                'message'    => array('type' => 'string'),
                'reply_id'   => array('type' => 'integer'),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\SENTINEL_ability_permission('moderate_comments');
    }

    public static function execute(array $input = array()): array
    {
        $input['comment_id'] = $input['comment_id'] ?? $input['id'] ?? 0;
        return Comment_Manager::manage_comment($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta(array('readOnlyHint' => false, 'destructiveHint' => true));
    }
}
