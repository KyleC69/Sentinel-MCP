<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Recovery;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Toggle_Plugin_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/toggle-plugin';
    }

    public static function label(): string
    {
        return 'Activate or deactivate a plugin';
    }

    public static function category(): string
    {
        return 'sentinel-recovery';
    }

    public static function description(): string
    {
        return 'Required: plugin (string), action (string: "activate" or "deactivate"). '
            . 'Activates or deactivates a plugin. Use "sentinel/list-plugins" first to get the plugin "file" value. '
            . 'CAUTION: Activating a plugin with errors will cause a fatal error. '
            . 'Alias: file is also accepted instead of plugin.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'required'   => array('plugin', 'action'),
            'properties' => array(
                'plugin' => array(
                    'type'        => 'string',
                    'description' => 'Plugin path ("file" value from sentinel/list-plugins). E.g.: "my-plugin/my-plugin.php"',
                ),
                'action' => array(
                    'type'        => 'string',
                    'description' => '"activate" to activate, "deactivate" to deactivate.',
                    'enum'        => array('activate', 'deactivate'),
                ),
            ),
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'       => 'object',
            'properties' => array(
                'success'    => array('type' => 'boolean'),
                'plugin'     => array('type' => 'string'),
                'new_status' => array('type' => 'string'),
                'message'    => array('type' => 'string'),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\mcpcomal_ability_permission('manage_options');
    }

    public static function execute(array $input = array()): array
    {
        return \SentinelMCP\File_Manager::toggle_plugin($input['plugin'] ?? $input['file'] ?? '', $input['action']);
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta(array('readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false));
    }
}
