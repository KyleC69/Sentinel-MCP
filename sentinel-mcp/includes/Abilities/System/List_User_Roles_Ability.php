<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\System;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * List user roles ability.
 *
 * Returns every WordPress role with its key, display name, and capability summary.
 */
class List_User_Roles_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-user-roles';
    }

    public static function label(): string
    {
        return 'List user roles';
    }

    public static function category(): string
    {
        return 'sentinel-discovery';
    }

    public static function description(): string
    {
        return 'Read-only. Lists every WordPress role registered on the site with its key, display name and the names of the capabilities granted (capability values themselves are summarized as a count to avoid bloat). Use list-users-meta-keys for per-user data.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'properties'           => array(
                'include_capabilities' => array(
                    'type'    => 'boolean',
                    'default' => false,
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
        return \SentinelMCP\mcpcomal_ability_permission('list_users');
    }

    public static function execute(array $input = array()): array
    {
        $include_caps = ! empty($input['include_capabilities']);
        $roles_obj    = wp_roles();
        $roles        = is_object($roles_obj) && isset($roles_obj->roles) ? (array) $roles_obj->roles : array();

        $result = array();
        foreach ($roles as $key => $role) {
            $caps = isset($role['capabilities']) && is_array($role['capabilities']) ? $role['capabilities'] : array();
            $entry = array(
                'key'       => (string) $key,
                'name'      => isset($role['name']) ? (string) $role['name'] : (string) $key,
                'cap_count' => count($caps),
            );
            if ($include_caps) {
                $entry['capabilities'] = array_keys($caps);
            }
            $result[] = $entry;
        }

        return array(
            'count' => count($result),
            'roles' => $result,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
