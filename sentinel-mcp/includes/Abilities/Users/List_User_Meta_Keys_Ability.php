<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Users;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\User_Manager;

defined('ABSPATH') || exit;

/**
 * Discover user meta fields ability.
 *
 * Lists all distinct user meta keys present in the database.
 */
class List_User_Meta_Keys_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-user-meta-keys';
    }

    public static function label(): string
    {
        return 'Discover user meta fields';
    }

    public static function category(): string
    {
        return 'sentinel-users';
    }

    public static function description(): string
    {
        return 'Lists all distinct user meta keys present in the database, categorized as '
            . 'WordPress core, WooCommerce (billing/shipping), or custom fields. '
            . 'Use this to discover what custom meta fields exist (e.g. DNI, customer type, '
            . 'company ID) before creating or updating users. Optionally includes usage counts. '
            . 'Pass inspect_key to get the distinct values of a specific meta key '
            . '(useful for enum-like fields such as customer_type).';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'include_counts' => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Include the number of users that have each meta key.',
                ),
                'inspect_key'    => array(
                    'type'        => 'string',
                    'description' => 'If provided, returns the distinct values for this specific meta key instead of listing all keys. '
                        . 'Useful for discovering fixed/enum values (e.g. customer_type => wholesale, retail, vip).',
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
        return \SentinelMCP\SENTINEL_ability_permission('list_users');
    }

    public static function execute(array $input = array()): array
    {
        return User_Manager::list_meta_keys($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
