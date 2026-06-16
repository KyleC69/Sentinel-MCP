<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Options;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * List options by prefix ability.
 *
 * Lists all wp_options matching a prefix pattern, excluding transients.
 */
class List_Options_By_Prefix_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-options-by-prefix';
    }

    public static function label(): string
    {
        return 'List options by prefix';
    }

    public static function category(): string
    {
        return 'sentinel-system';
    }

    public static function description(): string
    {
        return 'Required: prefix (string). '
            . 'Lists all wp_options matching a prefix pattern. Useful for discovering '
            . 'which options a plugin created (e.g., prefix "woocommerce_subscriptions" to find all '
            . 'WooCommerce Subscriptions options). Transient options are excluded.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'required'   => array('prefix'),
            'properties' => array(
                'prefix' => array(
                    'type'        => 'string',
                    'description' => 'Option name prefix to search for (e.g., "woocommerce_", "yoast_", "rank_math_").',
                ),
                'count'  => array(
                    'type'        => 'integer',
                    'description' => 'Maximum number of options to return. Default: 100, max: 500.',
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
        return \SentinelMCP\mcpcomal_ability_permission('manage_options');
    }

    public static function execute(array $input = array()): array
    {
        $input  = is_array($input) ? $input : array();
        $prefix = sanitize_text_field($input['prefix'] ?? '');
        $count  = isset($input['count']) ? absint($input['count']) : (isset($input['per_page']) ? absint($input['per_page']) : 100);
        $count  = max(1, min(500, $count));

        if (empty($prefix)) {
            return array(
                'success' => false,
                'message' => 'The "prefix" parameter is required.',
            );
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options}
                WHERE option_name LIKE %s
                AND option_name NOT LIKE %s
                AND option_name NOT LIKE %s
                ORDER BY option_name ASC
                LIMIT %d",
                $wpdb->esc_like($prefix) . '%',
                '\_transient\_%',
                '\_site\_transient\_%',
                $count
            ),
            ARRAY_A
        );

        $options = array();
        foreach ($rows as $row) {
            $value = maybe_unserialize($row['option_value']);
            $options[$row['option_name']] = $value;
        }

        return array(
            'success' => true,
            'prefix'  => $prefix,
            'count'   => count($options),
            'options' => $options,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
