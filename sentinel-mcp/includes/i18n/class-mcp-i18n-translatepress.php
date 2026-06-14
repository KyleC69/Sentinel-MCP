<?php

namespace SentinelMCP;

/**
 * TranslatePress i18n adapter (Sprint 4.1).
 *
 * Read-only adapter for TranslatePress (TRP).
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://mcpwp.com/
 */

defined('ABSPATH') || exit;

if (! class_exists('SentinelMCP\SENTINEL_I18n_TranslatePress')) {

	/**
	 * TranslatePress read-only adapter.
	 */
	class SENTINEL_I18n_TranslatePress extends SENTINEL_I18n_Adapter
	{

		public static function is_active(): bool
		{
			return defined('TRP_PLUGIN_VERSION') || class_exists('TRP_Translate_Press');
		}

		public static function slug(): string
		{
			return 'translatepress';
		}

		public static function list_languages(): array
		{
			$settings = (array) get_option('trp_settings', array());
			if (empty($settings['translation-languages'])) {
				return array();
			}
			$default = isset($settings['default-language']) ? (string) $settings['default-language'] : '';
			$names   = isset($settings['publish-languages']) && is_array($settings['publish-languages']) ? $settings['publish-languages'] : array();

			$result = array();
			foreach ((array) $settings['translation-languages'] as $code) {
				$result[] = array(
					'code'    => (string) $code,
					'name'    => '',
					'locale'  => (string) $code,
					'flag'    => '',
					'default' => $default === (string) $code,
					'published' => in_array((string) $code, $names, true),
				);
			}
			return $result;
		}

		public static function list_translations_for_post(int $post_id): array
		{
			// TranslatePress translates strings inline rather than maintaining
			// per-language post duplicates. Return a single entry pointing at
			// the original post so the caller knows there is no separate ID.
			$post = get_post($post_id);
			if (! $post) {
				return array();
			}
			$languages = self::list_languages();
			$result    = array();
			foreach ($languages as $lang) {
				$result[] = array(
					'language' => (string) $lang['code'],
					'post_id'  => $post_id,
					'status'   => (string) $post->post_status,
					'title'    => (string) $post->post_title,
					'note'     => 'TranslatePress translates content inline; the post ID is the same across languages.',
				);
			}
			return $result;
		}

		public static function get_post_in_language(int $post_id, string $language): ?int
		{
			// See note in list_translations_for_post — same ID across languages.
			return get_post($post_id) ? $post_id : null;
		}

		public static function list_string_translations(int $page = 1, int $per_page = 50): array
		{
			global $wpdb;

			$languages = self::list_languages();
			$rows      = array();

			foreach ($languages as $lang) {
				$code  = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $lang['code']);
				$table = $wpdb->prefix . 'trp_dictionary_' . $code;
				$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ($found !== $table) {
					continue;
				}
				$offset = max(0, ($page - 1) * $per_page);
				$lang_rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->prepare(
						"SELECT id, original, translated FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
						$per_page,
						$offset
					),
					ARRAY_A
				);
				foreach ($lang_rows as $r) {
					$r['language'] = (string) $lang['code'];
					$rows[]        = $r;
				}
			}

			return array(
				'page'     => $page,
				'per_page' => $per_page,
				'items'    => $rows,
			);
		}
	}
}
