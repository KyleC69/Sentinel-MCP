<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Stats;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Get_Media_Stats_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/get-media-stats';
    }

    public static function label(): string
    {
        return 'Get media stats';
    }

    public static function category(): string
    {
        return 'sentinel-stats';
    }

    public static function description(): string
    {
        return 'Read-only. Returns a breakdown of the media library by main mime type (image/jpeg, image/png, image/webp, application/pdf, video/mp4, etc.) and the total disk size used by all attachments. Useful for storage audits.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'properties'           => array(),
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
        return \SentinelMCP\SENTINEL_ability_permission('upload_files');
    }

    public static function execute(array $input = array()): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT post_mime_type AS mime, COUNT(*) AS total
            FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            GROUP BY post_mime_type
            ORDER BY total DESC"
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $by_mime = array();
        foreach ((array) $rows as $row) {
            $mime = (string) $row->mime;
            if ('' === $mime) {
                $mime = 'unknown';
            }
            $by_mime[$mime] = (int) $row->total;
        }

        $upload_dir  = wp_get_upload_dir();
        $base_dir    = isset($upload_dir['basedir']) ? (string) $upload_dir['basedir'] : '';
        $total_bytes = 0;

        if ('' !== $base_dir && is_dir($base_dir)) {
            $total_bytes = \SentinelMCP\SENTINEL_stats_dir_size($base_dir);
        }

        return array(
            'by_mime'            => $by_mime,
            'total_attachments'  => array_sum($by_mime),
            'uploads_path'       => $base_dir,
            'uploads_size_bytes' => $total_bytes,
            'uploads_size_human' => size_format($total_bytes, 2),
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
