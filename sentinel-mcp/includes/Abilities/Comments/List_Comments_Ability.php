<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Comments;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\Comment_Manager;

defined('ABSPATH') || exit;

/**
 * List and search comments ability.
 *
 * Lists site comments with flexible filters by post, status, author, date, or text.
 */
class List_Comments_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-comments';
    }

    public static function label(): string
    {
        return 'List and search comments';
    }

    public static function category(): string
    {
        return 'sentinel-comments';
    }

    public static function description(): string
    {
        return 'All parameters optional. '
            . 'Lists site comments with flexible filters: by post, status, author, '
            . 'date range, or free text. Useful for summarizing recent comments, '
            . 'finding comments pending moderation, or analyzing reader engagement '
            . 'on a specific post.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'post_id'      => array(
                    'type'        => 'integer',
                    'description' => 'Filter by specific post ID.',
                ),
                'status'       => array(
                    'type'        => 'string',
                    'description' => 'Comment status to display.',
                    'enum'        => array('approved', 'hold', 'spam', 'trash', 'all'),
                    'default'     => 'all',
                ),
                'search'       => array(
                    'type'        => 'string',
                    'description' => 'Search text in comment content or author name.',
                    'default'     => '',
                ),
                'author_email' => array(
                    'type'        => 'string',
                    'description' => 'Filter by comment author email.',
                    'default'     => '',
                ),
                'date_after'   => array(
                    'type'        => 'string',
                    'description' => 'Comments after this date (YYYY-MM-DD).',
                ),
                'date_before'  => array(
                    'type'        => 'string',
                    'description' => 'Comments before this date (YYYY-MM-DD).',
                ),
                'count'        => array(
                    'type'        => 'integer',
                    'default'     => 20,
                    'minimum'     => 1,
                    'maximum'     => 100,
                    'description' => 'Number of results per page (max 100). Alias: per_page is also accepted.',
                ),
                'orderby'      => array(
                    'type'    => 'string',
                    'default' => 'comment_date_gmt',
                    'enum'    => array('comment_date_gmt', 'comment_post_ID'),
                ),
                'order'        => array(
                    'type'    => 'string',
                    'default' => 'DESC',
                    'enum'    => array('ASC', 'DESC'),
                ),
            ),
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'  => 'array',
            'items' => array(
                'type'       => 'object',
                'properties' => array(
                    'comment_ID'   => array('type' => 'integer'),
                    'post_id'      => array('type' => 'integer'),
                    'post_title'   => array('type' => 'string'),
                    'author'       => array('type' => 'string'),
                    'author_email' => array('type' => 'string'),
                    'content'      => array('type' => 'string'),
                    'date'         => array('type' => 'string'),
                    'status'       => array('type' => 'string'),
                    'parent'       => array('type' => 'integer'),
                    'type'         => array('type' => 'string'),
                ),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\SENTINEL_ability_permission('moderate_comments');
    }

    public static function execute(array $input = array()): array
    {
        return Comment_Manager::list_comments($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
