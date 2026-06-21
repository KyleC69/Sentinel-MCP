<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * User Manager for MCP Content Manager.
 *
 * CRUD operations for WordPress users and roles.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * WordPress user management operations.
 */
class User_Manager
{

	/**
	 * Meta keys that should never be exposed.
	 *
	 * @var array
	 */
	private const SENSITIVE_META_KEYS = array(
		'session_tokens',
		'wp_user-settings',
		'wp_user-settings-time',
		'wp_dashboard_quick_press_last_post_id',
	);

	/**
	 * Meta key prefixes that should never be exposed.
	 *
	 * @var array
	 */
	private const SENSITIVE_META_PREFIXES = array(
		'_transient_',
		'_site_transient_',
	);

	/**
	 * List users with filters.
	 *
	 * @param array $input Ability input parameters.
	 * @return array
	 */
	public static function list_users(array $input): array
	{
		$count = min(absint($input['count'] ?? $input['per_page'] ?? 20), 100);
		$page  = max(absint($input['page'] ?? 1), 1);

		$args = array(
			'number' => $count,
			'paged'  => $page,
		);

		if (! empty($input['role'])) {
			$args['role'] = sanitize_text_field($input['role']);
		}
		if (! empty($input['search'])) {
			$args['search']         = '*' . sanitize_text_field($input['search']) . '*';
			$args['search_columns'] = array('user_login', 'user_email', 'display_name', 'user_nicename');
		}
		if (! empty($input['orderby'])) {
			$allowed_orderby = array('ID', 'display_name', 'user_login', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'post_count', 'include');
			$orderby         = sanitize_text_field($input['orderby']);
			$args['orderby'] = in_array($orderby, $allowed_orderby, true) ? $orderby : 'user_login';
		}
		if (! empty($input['order'])) {
			$order         = strtoupper(sanitize_text_field($input['order']));
			$args['order'] = in_array($order, array('ASC', 'DESC'), true) ? $order : 'ASC';
		}

		$query = new \WP_User_Query($args);
		$users = array();

		foreach ($query->get_results() as $user) {
			$users[] = array(
				'ID'              => $user->ID,
				'username'        => $user->user_login,
				'email'           => $user->user_email,
				'display_name'    => $user->display_name,
				'role'            => ! empty($user->roles) ? $user->roles[0] : '',
				'registered_date' => $user->user_registered,
				'post_count'      => count_user_posts($user->ID),
			);
		}

		$role_counts = count_users();

		return array(
			'success'     => true,
			'users'       => $users,
			'total'       => (int) $query->get_total(),
			'page'        => $page,
			'role_counts' => $role_counts['avail_roles'] ?? array(),
		);
	}

	/**
	 * Read a single user with full details.
	 *
	 * @param array $input Ability input parameters.
	 * @return array
	 */
	public static function read_user(array $input): array
	{
		$user_id = absint($input['user_id'] ?? 0);
		if (! $user_id) {
			return array(
				'success' => false,
				'message' => 'user_id is required.',
			);
		}

		$user = get_userdata($user_id);
		if (! $user) {
			return array(
				'success' => false,
				'message' => sprintf('User #%d not found.', $user_id),
			);
		}

		$data = array(
			'success'         => true,
			'ID'              => $user->ID,
			'username'        => $user->user_login,
			'email'           => $user->user_email,
			'display_name'    => $user->display_name,
			'first_name'      => $user->first_name,
			'last_name'       => $user->last_name,
			'role'            => ! empty($user->roles) ? $user->roles[0] : '',
			'roles'           => $user->roles,
			'registered_date' => $user->user_registered,
			'url'             => $user->user_url,
			'bio'             => get_user_meta($user_id, 'description', true),
			'post_count'      => count_user_posts($user_id),
		);

		// All user meta, filtering out sensitive keys.
		$all_meta = get_user_meta($user_id);
		$meta     = array();

		foreach ($all_meta as $key => $values) {
			if (in_array($key, self::SENSITIVE_META_KEYS, true)) {
				continue;
			}

			$skip = false;
			foreach (self::SENSITIVE_META_PREFIXES as $prefix) {
				if (str_starts_with($key, $prefix)) {
					$skip = true;
					break;
				}
			}
			if ($skip) {
				continue;
			}

			// get_user_meta returns arrays; unwrap single values.
			$meta[$key] = (1 === count($values)) ? $values[0] : $values;
		}

		$data['meta'] = $meta;

		return $data;
	}

	/**
	 * Create a new user.
	 *
	 * Uses wp_insert_user() (the standard WordPress API) so that all
	 * core hooks (user_register, wp_pre_insert_user_data, etc.) fire
	 * normally. Security plugins that hook into user creation will
	 * work as expected. The calling ability's permission_callback
	 * requires manage_options, so only administrators can invoke this.
	 *
	 * @param array $input Ability input parameters.
	 * @return array
	 */
	public static function create_user(array $input): array
	{
		$username = sanitize_user($input['username'] ?? '');
		$email    = sanitize_email($input['email'] ?? '');

		if (empty($username)) {
			return array(
				'success' => false,
				'message' => 'Username is required.',
			);
		}
		if (empty($email) || ! is_email($email)) {
			return array(
				'success' => false,
				'message' => 'A valid email is required.',
			);
		}

		if (username_exists($username)) {
			return array(
				'success' => false,
				'message' => sprintf('Username "%s" already exists.', $username),
			);
		}
		if (email_exists($email)) {
			return array(
				'success' => false,
				'message' => sprintf('Email "%s" is already registered.', $email),
			);
		}

		$role = sanitize_text_field($input['role'] ?? 'subscriber');

		// Security: prevent creating administrators unless current user is admin.
		if ('administrator' === $role && ! current_user_can('manage_options')) {
			return array(
				'success' => false,
				'message' => 'Only administrators can create other administrators.',
			);
		}

		$password           = ! empty($input['password']) ? $input['password'] : wp_generate_password(16, true, true);
		$password_generated = empty($input['password']);

		$userdata = array(
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'role'         => $role,
			'display_name' => sanitize_text_field($input['display_name'] ?? $username),
			'first_name'   => sanitize_text_field($input['first_name'] ?? ''),
			'last_name'    => sanitize_text_field($input['last_name'] ?? ''),
			'user_url'     => esc_url_raw($input['url'] ?? ''),
		);

		$user_id = wp_insert_user($userdata);

		if (is_wp_error($user_id)) {
			return array(
				'success' => false,
				'message' => $user_id->get_error_message(),
			);
		}

		// Send notification if requested.
		if ((bool) ($input['send_notification'] ?? true)) {
			wp_new_user_notification($user_id, null, 'both');
		}

		return array(
			'success'            => true,
			'user_id'            => $user_id,
			'username'           => $username,
			'email'              => $email,
			'role'               => $role,
			'password_generated' => $password_generated,
			'message'            => sprintf('User "%s" created (ID: %d, role: %s).', $username, $user_id, $role),
		);
	}

	/**
	 * Update an existing user.
	 *
	 * Uses wp_update_user() (the standard WordPress API) so that all
	 * core hooks (profile_update, wp_pre_insert_user_data, etc.) fire
	 * normally. Security plugins that hook into user updates will
	 * work as expected. The calling ability's permission_callback
	 * requires manage_options, so only administrators can invoke this.
	 *
	 * @param array $input Ability input parameters.
	 * @return array
	 */
	public static function update_user(array $input): array
	{
		$user_id = absint($input['user_id'] ?? 0);
		if (! $user_id) {
			return array(
				'success' => false,
				'message' => 'user_id is required.',
			);
		}

		$user = get_userdata($user_id);
		if (! $user) {
			return array(
				'success' => false,
				'message' => sprintf('User #%d not found.', $user_id),
			);
		}

		$userdata = array('ID' => $user_id);
		$updated  = array();

		// Role change guards.
		if (! empty($input['role'])) {
			$new_role    = sanitize_text_field($input['role']);
			$current_uid = get_current_user_id();

			// Cannot change own role.
			if ($user_id === $current_uid) {
				return array(
					'success' => false,
					'message' => 'You cannot change your own role.',
				);
			}

			// Cannot promote to admin unless current user is admin.
			if ('administrator' === $new_role && ! current_user_can('manage_options')) {
				return array(
					'success' => false,
					'message' => 'Only administrators can promote users to administrator.',
				);
			}

			$userdata['role'] = $new_role;
			$updated[]        = 'role';
		}

		$field_map = array(
			'email'        => 'user_email',
			'first_name'   => 'first_name',
			'last_name'    => 'last_name',
			'display_name' => 'display_name',
			'url'          => 'user_url',
		);

		foreach ($field_map as $input_key => $wp_key) {
			if (isset($input[$input_key])) {
				$value = 'email' === $input_key
					? sanitize_email($input[$input_key])
					: ('url' === $input_key
						? esc_url_raw($input[$input_key])
						: sanitize_text_field($input[$input_key])
					);

				$userdata[$wp_key] = $value;
				$updated[]           = $input_key;
			}
		}

		if (! empty($input['password'])) {
			$userdata['user_pass'] = $input['password'];
			$updated[]             = 'password';
		}

		if (count($updated) > 0) {
			$result = wp_update_user($userdata);
			if (is_wp_error($result)) {
				return array(
					'success' => false,
					'message' => $result->get_error_message(),
				);
			}
		}

		// Update meta if provided.
		if (! empty($input['meta']) && is_array($input['meta'])) {
			foreach ($input['meta'] as $key => $value) {
				$key = sanitize_text_field($key);
				if (in_array($key, self::SENSITIVE_META_KEYS, true)) {
					continue;
				}
				update_user_meta($user_id, $key, sanitize_text_field($value));
			}
			$updated[] = 'meta';
		}

		if (empty($updated)) {
			return array(
				'success' => false,
				'message' => 'No fields to update.',
			);
		}

		return array(
			'success'        => true,
			'user_id'        => $user_id,
			'updated_fields' => $updated,
			'message'        => sprintf('User #%d updated: %s.', $user_id, implode(', ', $updated)),
		);
	}

	/**
	 * List all distinct user meta keys in the database.
	 *
	 * Helps discover what custom fields are available (DNI, user type, etc.)
	 * so the AI can send the right data when creating or updating users.
	 *
	 * @param array $input Ability input parameters.
	 * @return array
	 */
	public static function list_meta_keys(array $input): array
	{
		global $wpdb;

		$inspect_key = sanitize_text_field($input['inspect_key'] ?? '');

		// If inspect_key is provided, return distinct values for that key.
		if ('' !== $inspect_key) {
			return self::inspect_meta_key($wpdb, $inspect_key);
		}

		$include_counts = (bool) ($input['include_counts'] ?? false);

		if ($include_counts) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Discovery query, no WP API equivalent.
			$rows = $wpdb->get_results(
				"SELECT meta_key, COUNT(*) AS user_count
				 FROM {$wpdb->usermeta}
				 GROUP BY meta_key
				 ORDER BY user_count DESC",
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Discovery query, no WP API equivalent.
			$rows = $wpdb->get_results(
				"SELECT DISTINCT meta_key FROM {$wpdb->usermeta} ORDER BY meta_key ASC",
				ARRAY_A
			);
		}

		// Categorize meta keys.
		$wp_core_keys = array(
			'nickname',
			'first_name',
			'last_name',
			'description',
			'rich_editing',
			'syntax_highlighting',
			'comment_shortcuts',
			'admin_color',
			'use_ssl',
			'show_admin_bar_front',
			'locale',
			'dismissed_wp_pointers',
			'show_welcome_panel',
			'wp_capabilities',
			'wp_user_level',
		);

		$wc_keys_prefix = array('billing_', 'shipping_', 'woocommerce_', '_woocommerce_', 'paying_customer', '_order_count', '_money_spent');

		$result = array(
			'wordpress' => array(),
			'custom'    => array(),
		);
		$has_wc = class_exists('WooCommerce');
		if ($has_wc) {
			$result['woocommerce'] = array();
		}

		foreach ($rows as $row) {
			$key  = $row['meta_key'];
			$info = array('key' => $key);

			if ($include_counts) {
				$info['user_count'] = (int) $row['user_count'];
			}

			// Skip sensitive/internal.
			if (in_array($key, self::SENSITIVE_META_KEYS, true)) {
				continue;
			}
			$skip = false;
			foreach (self::SENSITIVE_META_PREFIXES as $prefix) {
				if (str_starts_with($key, $prefix)) {
					$skip = true;
					break;
				}
			}
			if ($skip) {
				continue;
			}

			// Categorize.
			if (in_array($key, $wp_core_keys, true) || str_starts_with($key, 'wp_') || str_starts_with($key, 'meta-box-order_') || str_starts_with($key, 'metaboxhidden_') || str_starts_with($key, 'manageedit-') || str_starts_with($key, 'closedpostboxes_') || str_starts_with($key, 'edit_') || str_starts_with($key, 'screen_layout_')) {
				$result['wordpress'][] = $info;
			} elseif ($has_wc && self::is_wc_meta_key($key, $wc_keys_prefix)) {
				$result['woocommerce'][] = $info;
			} else {
				$result['custom'][] = $info;
			}
		}

		$result['total_keys']  = count($result['wordpress']) + count($result['custom']) + (isset($result['woocommerce']) ? count($result['woocommerce']) : 0);
		$result['custom_keys'] = count($result['custom']);

		return $result;
	}

	/**
	 * Check if a meta key belongs to WooCommerce.
	 *
	 * @param string $key      Meta key.
	 * @param array  $prefixes WC prefixes.
	 * @return bool
	 */
	private static function is_wc_meta_key(string $key, array $prefixes): bool
	{
		foreach ($prefixes as $prefix) {
			if (str_starts_with($key, $prefix)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Inspect a specific meta key: distinct values, usage count, and sample users.
	 *
	 * Useful for discovering fixed/enum values (e.g. customer_type => wholesale, retail, vip).
	 *
	 * @param wpdb   $wpdb The WordPress database object.
	 * @param string $key  The meta key to inspect.
	 * @return array
	 */
	private static function inspect_meta_key($wpdb, string $key): array
	{
		// Block sensitive keys.
		if (in_array($key, self::SENSITIVE_META_KEYS, true)) {
			return array(
				'success' => false,
				'message' => 'This meta key cannot be inspected.',
			);
		}

		// Total users with this key.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Discovery query, no WP API equivalent.
		$total_users = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s",
				$key
			)
		);

		// Distinct values with counts (top 50).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Discovery query, no WP API equivalent.
		$values = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value, COUNT(*) AS count
				 FROM {$wpdb->usermeta}
				 WHERE meta_key = %s
				 GROUP BY meta_value
				 ORDER BY count DESC
				 LIMIT 50",
				$key
			),
			ARRAY_A
		);

		$distinct_values = array();
		$is_likely_enum  = true;

		foreach ($values as $row) {
			$val = $row['meta_value'];

			// If any value is longer than 100 chars, it's probably free-text, not an enum.
			if (strlen($val) > 100) {
				$is_likely_enum = false;
			}

			$distinct_values[] = array(
				'value' => $val,
				'count' => (int) $row['count'],
			);
		}

		// More than 30 distinct values is unlikely to be an enum.
		if (count($distinct_values) > 30) {
			$is_likely_enum = false;
		}

		return array(
			'key'             => $key,
			'total_users'     => $total_users,
			'distinct_count'  => count($distinct_values),
			'is_likely_enum'  => $is_likely_enum,
			'distinct_values' => $distinct_values,
		);
	}
}
