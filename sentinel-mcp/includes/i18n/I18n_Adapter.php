<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * I18n adapter base + locator (Sprint 4.1).
 *
 * Detects which multilingual plugin is active (Polylang, WPML, TranslatePress)
 * and dispatches read-only queries to the corresponding adapter. All adapters
 * are defensive: they verify the underlying plugin's API exists before calling.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

	/**
	 * Locator for the active i18n adapter.
	 */
	abstract class I18n_Adapter
	{

		/**
		 * Resolve the active adapter class name. Returns empty string if none.
		 */
		public static function active(): string
		{
			if (I18n_Polylang::is_active()) {
				return 'I18n_Polylang';
			}
			if (I18n_WPML::is_active()) {
				return 'I18n_WPML';
			}
			if (I18n_TranslatePress::is_active()) {
				return 'I18n_TranslatePress';
			}
			return '';
		}

		/**
		 * Whether this adapter's underlying plugin is active.
		 */
		abstract public static function is_active(): bool;

		/**
		 * Slug identifying the underlying plugin (polylang|wpml|translatepress).
		 */
		abstract public static function slug(): string;

		/**
		 * List languages available on the site.
		 *
		 * @return array<int,array<string,mixed>>
		 */
		abstract public static function list_languages(): array;

		/**
		 * List translations of a post (one entry per language).
		 *
		 * @param int $post_id Post ID.
		 * @return array<int,array<string,mixed>>
		 */
		abstract public static function list_translations_for_post(int $post_id): array;

		/**
		 * Resolve the post ID in another language.
		 */
		abstract public static function get_post_in_language(int $post_id, string $language): ?int;

		/**
		 * List string translations (theme/plugin strings translated). Optional;
		 * adapters that don't support this return an empty array.
		 */
		abstract public static function list_string_translations(int $page = 1, int $per_page = 50): array;
	}
}
