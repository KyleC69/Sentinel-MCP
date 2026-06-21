<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Stats;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Get_Site_Stats_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/get-site-stats';
    }

    public static function label(): string
    {
        return 'Get site stats';
    }

    public static function category(): string
    {
        return 'sentinel-stats';
    }

    public static function description(): string
    {
        return 'Read-only. Returns counts of content across the site: posts per registered post type broken down by status (publish, draft, pending, future, trash, private), comments grouped by status (approved, hold, spam, trash), users grouped by role, and total media items. Use this for quick audits and to find buckets of work (e.g. "how many drafts do I have?").';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'properties'           => array(
                'include_private_post_types' => array(
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'When false, only public post types are counted.',
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
        return \SentinelMCP\SENTINEL_ability_permission('read');
    }

    public static function execute(array $input = array()): array
    {
        $include_private = ! isset($input['include_private_post_types']) || ! empty($input['include_private_post_types']);

        $args       = $include_private ? array() : array('public' => true);
        $post_types = get_post_types($args, 'objects');

        $post_counts = array();
        foreach ($post_types as $pt) {
            $counts = wp_count_posts($pt->name);
            if (! $counts) {
                continue;
            }
            $post_counts[$pt->name] = array(
                'label'      => $pt->label,
                'publish'    => isset($counts->publish) ? (int) $counts->publish : 0,
                'draft'      => isset($counts->draft) ? (int) $counts->draft : 0,
                'pending'    => isset($counts->pending) ? (int) $counts->pending : 0,
                'future'     => isset($counts->future) ? (int) $counts->future : 0,
                'private'    => isset($counts->private) ? (int) $counts->private : 0,
                'trash'      => isset($counts->trash) ? (int) $counts->trash : 0,
                'auto-draft' => isset($counts->{'auto-draft'}) ? (int) $counts->{'auto-draft'} : 0,
            );
        }

        $comment_raw = wp_count_comments();
        $comments    = array(
            'approved'       => isset($comment_raw->approved) ? (int) $comment_raw->approved : 0,
            'moderated'      => isset($comment_raw->moderated) ? (int) $comment_raw->moderated : 0,
            'spam'           => isset($comment_raw->spam) ? (int) $comment_raw->spam : 0,
            'trash'          => isset($comment_raw->trash) ? (int) $comment_raw->trash : 0,
            'post-trashed'   => isset($comment_raw->{'post-trashed'}) ? (int) $comment_raw->{'post-trashed'} : 0,
            'total_comments' => isset($comment_raw->total_comments) ? (int) $comment_raw->total_comments : 0,
        );

        $user_raw = count_users();
        $users    = array(
            'total'   => isset($user_raw['total_users']) ? (int) $user_raw['total_users'] : 0,
            'by_role' => array(),
        );
        if (isset($user_raw['avail_roles']) && is_array($user_raw['avail_roles'])) {
            foreach ($user_raw['avail_roles'] as $role => $count) {
                $users['by_role'][$role] = (int) $count;
            }
        }

        $media_total = wp_count_posts('attachment');
        $media_count = (isset($media_total->inherit) ? (int) $media_total->inherit : 0)
            + (isset($media_total->publish) ? (int) $media_total->publish : 0);

        return array(
            'post_counts' => $post_counts,
            'comments'    => $comments,
            'users'       => $users,
            'media_total' => $media_count,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
