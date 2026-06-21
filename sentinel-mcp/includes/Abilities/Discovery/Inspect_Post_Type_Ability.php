<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Discovery;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\Schema_Inspector;

defined('ABSPATH') || exit;

class Inspect_Post_Type_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/inspect-post-type';
    }

    public static function label(): string
    {
        return 'Inspect a content type';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'Required: post_type (string, e.g. "post", "page", "product"). '
            . 'Returns the full structure of a specific content type: '
            . 'taxonomies with all their terms, detailed meta fields (type, description, '
            . 'required flag, ACF options), and supported features (thumbnail, excerpt, etc.). '
            . 'Call this before creating content in a CPT to know which fields to fill.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'required'   => array('post_type'),
            'properties' => array(
                'post_type' => array(
                    'type'        => 'string',
                    'description' => 'Post type slug to inspect. E.g.: "post", "page", "product", "doc".',
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
        return \SentinelMCP\SENTINEL_ability_permission('read');
    }

    public static function execute(array $input = array()): array
    {
        $post_type_slug = sanitize_text_field($input['post_type']);
        $pt_object      = get_post_type_object($post_type_slug);

        if (! $pt_object) {
            return array(
                'success' => false,
                'message' => sprintf('Post type "%s" not found.', $post_type_slug),
            );
        }

        return array(
            'name'         => $pt_object->name,
            'label'        => $pt_object->label,
            'description'  => $pt_object->description ? $pt_object->description : '',
            'hierarchical' => $pt_object->hierarchical,
            'supports'     => get_all_post_type_supports($pt_object->name),
            'taxonomies'   => Schema_Inspector::get_taxonomies_for_post_type($pt_object->name),
            'meta_fields'  => Schema_Inspector::get_meta_fields_for_post_type($pt_object->name),
            'labels'       => array(
                'singular' => $pt_object->labels->singular_name ?? '',
                'plural'   => $pt_object->labels->name ?? '',
                'add_new'  => $pt_object->labels->add_new_item ?? '',
            ),
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
