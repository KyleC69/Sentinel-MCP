<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Recovery;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

class Clear_Recovery_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/clear-recovery';
    }

    public static function label(): string
    {
        return 'Clear recovery mode';
    }

    public static function category(): string
    {
        return 'sentinel-recovery';
    }

    public static function description(): string
    {
        return 'Clears WordPress recovery flags (recovery_keys and paused_extensions). '
            . 'Use it after fixing a fatal error or if recovery mode was triggered by a transient issue. '
            . 'Safe to run at any time: if no flags are active, it does nothing harmful.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'       => 'object',
            'default'    => array(),
            'properties' => array(),
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'       => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'message' => array('type' => 'string'),
            ),
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\mcpcomal_ability_permission('manage_options');
    }

    public static function execute(array $input = array()): array
    {
        $cleared = array();

        delete_option('recovery_keys');
        $cleared[] = 'recovery_keys';

        if (function_exists('wp_paused_plugins')) {
            $paused = wp_paused_plugins()->get_all();
            foreach (array_keys($paused) as $plugin) {
                wp_paused_plugins()->delete($plugin);
            }
            if (! empty($paused)) {
                $cleared[] = count($paused) . ' paused plugin(s)';
            }
        }

        if (function_exists('wp_paused_themes')) {
            $paused = wp_paused_themes()->get_all();
            foreach (array_keys($paused) as $theme) {
                wp_paused_themes()->delete($theme);
            }
            if (! empty($paused)) {
                $cleared[] = count($paused) . ' paused theme(s)';
            }
        }

        return array(
            'success' => true,
            'message' => 'Recovery flags cleared: ' . implode(', ', $cleared) . '.',
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta(array('readOnlyHint' => false));
    }
}
