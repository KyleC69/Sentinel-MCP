<?php

/**
 * User Management Abilities.
 *
 * List and read WordPress users.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 */

defined('ABSPATH') || exit;

/*
 * Category
 * ─────────────────────────────────────────
 */

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-users',
			array(
				'label'       => __('User Management', 'mcp-sentinel'),
				'description' => __('List and read WordPress users.', 'mcp-sentinel'),
			)
		);
	}
);

/*
 * Abilities
 * ─────────────────────────────────────────
 */

add_action(
	'wp_abilities_api_init',
	function () {
		/*
		 * LIST USERS
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/list-users',
			array(
				'label'               => 'List WordPress users',
				'category'            => 'sentinel-users',
				'description'         => 'All parameters optional. '
					. 'Lists WordPress users with filters for role, search (name/email), '
					. 'and ordering. Returns user ID, username, email, display name, role, '
					. 'registration date, post count, and role distribution summary.',

				'input_schema'        => array(
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
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ($input) {
					return SENTINEL_User_Manager::list_users($input);
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

		/*
		 * READ USER
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/read-user',
			array(
				'label'               => 'View user details',
				'category'            => 'sentinel-users',
				'description'         => 'Required: user_id (integer). '
					. 'Returns full details for a WordPress user: name, email, roles, '
					. 'registration date, URL, bio, post count, and all user meta fields. '
					. 'Sensitive meta (session tokens, internal settings) is excluded. '
					. 'Alias: id is also accepted instead of user_id.',

				'input_schema'        => array(
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
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ($input) {
					$input['user_id'] = $input['user_id'] ?? $input['id'] ?? 0;
					return SENTINEL_User_Manager::read_user($input);
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

		/*
		 * LIST USER META KEYS
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/list-user-meta-keys',
			array(
				'label'               => 'Discover user meta fields',
				'category'            => 'sentinel-users',
				'description'         => 'Lists all distinct user meta keys present in the database, categorized as '
					. 'WordPress core, WooCommerce (billing/shipping), or custom fields. '
					. 'Use this to discover what custom meta fields exist (e.g. DNI, customer type, '
					. 'company ID) before creating or updating users. Optionally includes usage counts. '
					. 'Pass inspect_key to get the distinct values of a specific meta key '
					. '(useful for enum-like fields such as customer_type).',

				'input_schema'        => array(
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
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ($input) {
					return SENTINEL_User_Manager::list_meta_keys($input);
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
