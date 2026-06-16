<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Options Manager for MCP Content Manager.
 *
 * Provides safe read/write access to WordPress options
 * through a prefix-pattern whitelist with explicit exceptions.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * WordPress options manager with security whitelist.
 */
class Options_Manager
{

	/**
	 * Prefixes that indicate a readable option.
	 *
	 * @var array
	 */
	private const READABLE_PREFIXES = array(
		'blog',
		'site',
		'home',
		'admin',
		'date',
		'time',
		'timezone',
		'gmt',
		'WPLANG',
		'start_of_week',
		'default_',
		'use_',
		'ping',
		'posts_',
		'rss_',
		'show_',
		'page_',
		'require_',
		'comment',
		'moderation',
		'comments',
		'thread',
		'close_',
		'avatar',
		'thumbnail',
		'medium',
		'large',
		'uploads',
		'permalink',
		'category',
		'tag',
		'wp_page',
		'active_',
		'template',
		'stylesheet',
		'current_',
		'users',
		'woocommerce_',
	);

	/**
	 * Explicitly readable options that do not match a prefix.
	 *
	 * @var array
	 */
	private const READABLE_EXCEPTIONS = array(
		'home',
		'WPLANG',
		'site_icon',
	);

	/**
	 * Options that can be written (safe subset of readable).
	 *
	 * @var array
	 */
	private const WRITABLE_PREFIXES = array(
		'blog',
		'site',
		'admin',
		'date',
		'time',
		'timezone',
		'gmt',
		'WPLANG',
		'start_of_week',
		'default_',
		'use_',
		'ping',
		'posts_',
		'rss_',
		'show_',
		'page_',
		'require_',
		'comment',
		'moderation',
		'comments',
		'thread',
		'close_',
		'avatar',
		'thumbnail',
		'medium',
		'large',
		'uploads',
		'permalink',
		'category',
		'tag',
		'wp_page',
		'users',
		'woocommerce_',
	);

	/**
	 * Explicitly writable options that do not match a prefix.
	 *
	 * @var array
	 */
	private const WRITABLE_EXCEPTIONS = array(
		'WPLANG',
		'site_icon',
	);

	/**
	 * Check whether an option name is readable.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	private static function is_readable_option(string $name): bool
	{
		if (in_array($name, self::READABLE_EXCEPTIONS, true)) {
			return true;
		}

		foreach (self::READABLE_PREFIXES as $prefix) {
			if (str_starts_with($name, $prefix)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether an option name is writable.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	private static function is_writable_option(string $name): bool
	{
		if (in_array($name, self::WRITABLE_EXCEPTIONS, true)) {
			return true;
		}

		foreach (self::WRITABLE_PREFIXES as $prefix) {
			if (str_starts_with($name, $prefix)) {
				return true;
			}
		}

		return false;
	}



	/**
	 * Get one or all whitelisted options.
	 *
	 * @param array $input Ability input parameters.
	 * @return array
	 */
	public static function get_option(array $input): array
	{
		$name = sanitize_text_field($input['name'] ?? '');

		// Return a specific option.
		if (! empty($name)) {
			if (! self::is_readable_option($name)) {
				return array(
					'success' => false,
					'message' => sprintf('Option "%s" is not in the readable whitelist.', $name),
					'allowed_prefixes' => self::READABLE_PREFIXES,
				);
			}

			return array(
				'success' => true,
				'options' => array($name => get_option($name)),
			);
		}

		// Return all readable options from the database.
		$all_names = Database::get_all_option_names();
		$options   = array();
		foreach ($all_names as $option_name) {
			if (self::is_readable_option($option_name)) {
				$value = get_option($option_name, '__SENTINEL_NOT_SET__');
				if ('__SENTINEL_NOT_SET__' !== $value) {
					$options[$option_name] = $value;
				}
			}
		}

		return array(
			'success' => true,
			'options' => $options,
		);
	}

	/**
	 * Update a whitelisted option.
	 *
	 * @param array $input Ability input parameters.
	 * @return array
	 */
	public static function update_option(array $input): array
	{
		$name = sanitize_text_field($input['name'] ?? '');

		if (empty($name)) {
			return array(
				'success' => false,
				'message' => 'Option name is required.',
			);
		}

		if (! self::is_writable_option($name)) {
			$reason = self::is_readable_option($name)
				? 'This option is read-only for safety (changing it could break the site).'
				: 'This option is not in the whitelist.';

			return array(
				'success'          => false,
				'message'          => sprintf('Cannot write option "%s". %s', $name, $reason),
				'allowed_prefixes' => self::WRITABLE_PREFIXES,
			);
		}

		if (! array_key_exists('value', $input)) {
			return array(
				'success' => false,
				'message' => 'Option value is required.',
			);
		}

		$old_value = get_option($name);
		$new_value = $input['value'];

		// Sanitize based on expected type.
		if (is_string($new_value)) {
			$new_value = sanitize_text_field($new_value);
		} elseif (is_numeric($new_value)) {
			$new_value = is_int($new_value) ? (int) $new_value : (float) $new_value;
		}

		update_option($name, $new_value);

		// Flush rewrite rules when permalink structure changes.
		if ('permalink_structure' === $name) {
			flush_rewrite_rules();
		}

		return array(
			'success'   => true,
			'name'      => $name,
			'old_value' => $old_value,
			'new_value' => $new_value,
			'message'   => sprintf('Option "%s" updated.', $name),
		);
	}
}
