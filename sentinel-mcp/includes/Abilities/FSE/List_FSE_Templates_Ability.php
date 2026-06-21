<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\FSE;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class List_FSE_Templates_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-fse-templates';
    }

    public static function label(): string
    {
        return 'List FSE templates';
    }

    public static function category(): string
    {
        return 'sentinel-fse';
    }

    public static function description(): string
    {
        return 'Read-only. Lists FSE templates and template parts (metadata only: slug, type, theme, title, area, source). Template content is not returned — fetching and editing the template body is reserved for the Premium edition.';
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
        return \SentinelMCP\SENTINEL_ability_permission('edit_theme_options');
    }

    public static function execute(array $input = array()): array
    {
        if (! function_exists('get_block_templates')) {
            return array(
                'templates'      => array(),
                'template_parts' => array(),
                'note'           => 'FSE block templates are not available on this WordPress version.',
            );
        }

        $templates = (array) get_block_templates();
        $parts     = (array) get_block_templates(array(), 'wp_template_part');

        $format = function ($tpl) {
            return array(
                'slug'           => isset($tpl->slug) ? (string) $tpl->slug : '',
                'id'             => isset($tpl->id) ? (string) $tpl->id : '',
                'theme'          => isset($tpl->theme) ? (string) $tpl->theme : '',
                'title'          => isset($tpl->title) ? (string) $tpl->title : '',
                'description'    => isset($tpl->description) ? (string) $tpl->description : '',
                'type'           => isset($tpl->type) ? (string) $tpl->type : '',
                'area'           => isset($tpl->area) ? (string) $tpl->area : '',
                'source'         => isset($tpl->source) ? (string) $tpl->source : '',
                'status'         => isset($tpl->status) ? (string) $tpl->status : '',
                'has_theme_file' => isset($tpl->has_theme_file) ? (bool) $tpl->has_theme_file : false,
            );
        };

        return array(
            'templates'            => array_map($format, $templates),
            'template_parts'       => array_map($format, $parts),
            'templates_count'      => count($templates),
            'template_parts_count' => count($parts),
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
