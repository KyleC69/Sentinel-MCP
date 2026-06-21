<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Users;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\User_Manager;

defined('ABSPATH') || exit;

/**
 * List WordPress users ability.
 *
 * Lists WordPress users with filters for role, search, and ordering.
 */
class List_Users_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-users';
    }

    public static function label(): string
    {
        return 'List WordPress users';
    }

    public static function category(): string
    {
        return 'sentinel-users';
    }

    public static function description(): string
    {
        return 'All parameters optional. '
            . 'Lists WordPress users with filters for role, search (name/email), '
            . 'and ordering. Returns user ID, username, email, display name, role, '
            . 'registration date, post count, and role distribution summary.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'role'    => array(
                    'type'        => 'string',
                    'description' => 'Filter by role slug: administrator, editor, author, contributor, subscriber, customer.',
                ),
                'search'  => array(
                    'type'        => 'string',
                    'description' => 'Search in username, email, and display name.',
                ),
                'orderby' => array(
                    'type'    => 'string',
                    'default' => 'registered',
                    'enum'    => array('registered', 'display_name', 'login', 'email'),
                ),
                'order'   => array(
                    'type'    => 'string',
                    'default' => 'DESC',
                    'enum'    => array('ASC', 'DESC'),
                ),
                'count'   => array(
                    'type'        => 'integer',
                    'default'     => 20,
                    'minimum'     => 1,
                    'maximum'     => 100,
                    'description' => 'Number of results per page (max 100). Alias: per_page is also accepted.',
                ),
                'page'    => array(
                    'type'    => 'integer',
                    'default' => 1,
                    'minimum' => 1,
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
        return User_Manager::list_users($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
