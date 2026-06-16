<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Gutenberg;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Gutenberg_Reference_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/gutenberg-reference';
    }

    public static function label(): string
    {
        return 'Gutenberg block markup reference';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'All parameters optional. Returns a complete reference guide for writing Gutenberg block markup. '
            . 'Includes syntax examples for all core blocks (paragraphs, headings, lists, columns, '
            . 'groups, buttons, images, cover, media-text, separator, spacer, table, code, quote, '
            . 'and more) plus the list of all blocks registered on this site. '
            . 'Call this BEFORE creating content if you need to use advanced Gutenberg layouts. '
            . 'The content field in sentinel/create-content and sentinel/update-content accepts '
            . 'Gutenberg block markup directly (<!-- wp:blockname --> delimiters).';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'section' => array(
                    'type'        => 'string',
                    'description' => 'Which section to return: "guide" for markup examples, '
                        . '"registry" for all registered blocks on this site, '
                        . '"all" for both.',
                    'enum'        => array('guide', 'registry', 'all'),
                    'default'     => 'all',
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
        return \SentinelMCP\mcpcomal_ability_permission('edit_posts');
    }

    public static function execute(array $input = array()): array
    {
        return \SentinelMCP\mcpcomal_gutenberg_reference_execute($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
