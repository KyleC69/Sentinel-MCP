<?php

namespace SentinelMCP;

/**
 * System / cron read abilities (Sprint 1.8).
 *
 * Exposes a read-only view of WP-Cron events and the registered user roles.
 * Cancellation and scheduling of cron events are reserved for Premium.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://mcpwp.com/
 */

defined('ABSPATH') || exit;

add_action(
	'wp_abilities_api_init',
	function () {

		// 1. List cron events.

		wp_register_ability(
			'sentinel/list-cron-events',
			array(
				'label'               => 'List WP-Cron events',
				'category'            => 'sentinel-system',
				'description'         => 'Read-only. Returns all scheduled WP-Cron events: hook name, args signature, schedule (interval slug), next run UNIX timestamp and human-readable time. Useful to diagnose missed/overdue tasks. Scheduling and cancelling cron events is Premium.',

				'input_schema'        => array(
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
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ($input = null) {
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
				},

				'permission_callback' => function () {
					return current_user_can('manage_options');
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => true,
							'destructiveHint' => false,
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);

		// 2. List user roles.

		wp_register_ability(
			'sentinel/list-user-roles',
			array(
				'label'               => 'List user roles',
				'category'            => 'sentinel-discovery',
				'description'         => 'Read-only. Lists every WordPress role registered on the site with its key, display name and the names of the capabilities granted (capability values themselves are summarized as a count to avoid bloat). Use list-users-meta-keys for per-user data.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'include_capabilities' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ($input = null) {
					$include_caps = ! empty($input['include_capabilities']);
					$roles_obj    = wp_roles();
					$roles        = is_object($roles_obj) && isset($roles_obj->roles) ? (array) $roles_obj->roles : array();

					$result = array();
					foreach ($roles as $key => $role) {
						$caps = isset($role['capabilities']) && is_array($role['capabilities']) ? $role['capabilities'] : array();
						$entry = array(
							'key'         => (string) $key,
							'name'        => isset($role['name']) ? (string) $role['name'] : (string) $key,
							'cap_count'   => count($caps),
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
				},

				'permission_callback' => function () {
					return current_user_can('list_users');
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => true,
							'destructiveHint' => false,
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);
	}
);

/*
 * MCP annotations summary for this file:
 *
 *   list-cron-events  readOnly idempotent
 *   list-user-roles   readOnly idempotent
 */
