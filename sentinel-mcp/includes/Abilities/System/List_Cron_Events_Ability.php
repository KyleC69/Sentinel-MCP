<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\System;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * List WP-Cron events ability.
 *
 * Returns all scheduled WP-Cron events with hook name, next run time,
 * schedule, interval, and overdue status.
 */
class List_Cron_Events_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-cron-events';
    }

    public static function label(): string
    {
        return 'List WP-Cron events';
    }

    public static function category(): string
    {
        return 'sentinel-system';
    }

    public static function description(): string
    {
        return 'Read-only. Returns all scheduled WP-Cron events: hook name, args signature, schedule (interval slug), next run UNIX timestamp and human-readable time. Useful to diagnose missed/overdue tasks. Scheduling and cancelling cron events is Premium.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'properties'           => array(
                'overdue_only' => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'When true, only events whose next run is in the past are returned.',
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
        return \SentinelMCP\SENTINEL_ability_permission('manage_options');
    }

    public static function execute(array $input = array()): array
    {
        $overdue_only = ! empty($input['overdue_only']);
        $crons        = _get_cron_array();
        $now          = time();
        $events       = array();

        if (is_array($crons)) {
            foreach ($crons as $timestamp => $hooks) {
                $ts = (int) $timestamp;
                if ($overdue_only && $ts > $now) {
                    continue;
                }
                foreach ((array) $hooks as $hook => $signatures) {
                    foreach ((array) $signatures as $sig => $event) {
                        $events[] = array(
                            'hook'        => (string) $hook,
                            'next_run_ts' => $ts,
                            'next_run'    => date_i18n('Y-m-d H:i:s', $ts),
                            'time_to_run' => $ts - $now,
                            'is_overdue'  => $ts < $now,
                            'schedule'    => isset($event['schedule']) ? (string) $event['schedule'] : '',
                            'interval'    => isset($event['interval']) ? (int) $event['interval'] : 0,
                            'args_count'  => isset($event['args']) && is_array($event['args']) ? count($event['args']) : 0,
                        );
                    }
                }
            }
        }

        return array(
            'count'  => count($events),
            'events' => $events,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
