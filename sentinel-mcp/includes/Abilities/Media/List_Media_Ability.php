<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Media;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\Media_Manager;

defined('ABSPATH') || exit;

/**
 * List media attachments ability.
 *
 * Lists media items from the WordPress Media Library with filters for MIME type,
 * date range, and search.
 */
class List_Media_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-media';
    }

    public static function label(): string
    {
        return 'List media attachments';
    }

    public static function category(): string
    {
        return 'sentinel-media';
    }

    public static function description(): string
    {
        return 'All parameters optional. '
            . 'Lists media items from the WordPress Media Library with filters for MIME type, '
            . 'date range, and search. Returns ID, title, URL, dimensions, alt text, and thumbnail. '
            . 'Use this to find images before assigning them as featured images.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'search'      => array(
                    'type'        => 'string',
                    'description' => 'Search in attachment title and description.',
                ),
                'mime_type'   => array(
                    'type'        => 'string',
                    'description' => 'Filter by MIME type prefix. E.g.: "image", "video", "audio", "application/pdf", or "any" for all.',
                    'default'     => 'any',
                ),
                'date_after'  => array(
                    'type'        => 'string',
                    'description' => 'Only media uploaded after this date (YYYY-MM-DD).',
                ),
                'date_before' => array(
                    'type'        => 'string',
                    'description' => 'Only media uploaded before this date (YYYY-MM-DD).',
                ),
                'count'       => array(
                    'type'        => 'integer',
                    'description' => 'Number of results per page (max 100). Alias: per_page is also accepted.',
                    'default'     => 20,
                ),
                'page'        => array(
                    'type'        => 'integer',
                    'description' => 'Page number for pagination.',
                    'default'     => 1,
                ),
                'orderby'     => array(
                    'type'        => 'string',
                    'description' => 'Order by field: "date", "title", or "modified".',
                    'default'     => 'date',
                    'enum'        => array('date', 'title', 'modified'),
                ),
                'order'       => array(
                    'type'        => 'string',
                    'description' => 'Sort direction: "ASC" or "DESC".',
                    'default'     => 'DESC',
                    'enum'        => array('ASC', 'DESC'),
                ),
            ),
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
        return Media_Manager::list_media($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
