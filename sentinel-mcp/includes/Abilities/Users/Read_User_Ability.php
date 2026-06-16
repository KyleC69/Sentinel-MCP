<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Users;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\User_Manager;

defined('ABSPATH') || exit;

/**
 * View user details ability.
 *
 * Returns full details for a WordPress user.
 */
class Read_User_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/read-user';
    }

    public static function label(): string
    {
        return 'View user details';
    }

    public static function category(): string
    {
        return 'sentinel-users';
    }

    public static function description(): string
    {
        return 'Required: user_id (integer). '
            . 'Returns full details for a WordPress user: name, email, roles, '
            . 'registration date, URL, bio, post count, and all user meta fields. '
            . 'Sensitive meta (session tokens, internal settings) is excluded. '
            . 'Alias: id is also accepted instead of user_id.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(
                'user_id' => array(
                    'type'        => 'integer',
                    'description' => 'User ID to read.',
                ),
                'id'      => array(
                    'type'        => 'integer',
                    'description' => 'Alias for user_id.',
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
        return \SentinelMCP\mcpcomal_ability_permission('list_users');
    }

    public static function execute(array $input = array()): array
    {
        $input['user_id'] = $input['user_id'] ?? $input['id'] ?? 0;
        return User_Manager::read_user($input);
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
