<?php

/**
 * WPML i18n adapter (Sprint 4.1).
 *
 * Read-only adapter for WPML and its sibling plugins.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://mcpwp.com/
 */

defined('ABSPATH') || exit;

if (! class_exists('SENTINEL_I18n_WPML')) {

	/**
	 * WPML read-only adapter.
	 */
	class SENTINEL_I18n_WPML extends SENTINEL_I18n_Adapter
	{

		public static function is_active(): bool
		{
			return defined('ICL_SITEPRESS_VERSION') || class_exists('SitePress');
		}

		public static function slug(): string
		{
			return 'wpml';
		}

		public static function list_languages(): array
		{
			$result    = array();
			$languages = apply_filters('wpml_active_languages', null, array('skip_missing' => 0));
			if (! is_array($languages)) {
				return $result;
			}
			foreach ($languages as $code => $lang) {
				$result[] = array(
					'code'    => (string) ($lang['code'] ?? $code),
					'name'    => (string) ($lang['translated_name'] ?? $lang['native_name'] ?? ''),
					'locale'  => (string) ($lang['default_locale'] ?? ''),
					'flag'    => (string) ($lang['country_flag_url'] ?? ''),
					'default' => ! empty($lang['default_locale']) && (string) $lang['default_locale'] === (string) apply_filters('wpml_default_language', null),
				);
			}
			return $result;
		}

		public static function list_translations_for_post(int $post_id): array
		{
			$result    = array();
			$type_info = apply_filters('wpml_element_type', get_post_type($post_id));
			$trid      = apply_filters('wpml_element_trid', null, $post_id, $type_info);
			if (! $trid) {
				return $result;
			}
			$translations = apply_filters('wpml_get_element_translations', null, $trid, $type_info);
			if (! is_array($translations)) {
				return $result;
			}
			foreach ($translations as $lang_code => $entry) {
				$translated_id = isset($entry->element_id) ? (int) $entry->element_id : 0;
				$post          = $translated_id ? get_post($translated_id) : null;
				$result[]      = array(
					'language' => (string) $lang_code,
					'post_id'  => $translated_id,
					'status'   => $post ? (string) $post->post_status : '',
					'title'    => $post ? (string) $post->post_title : '',
				);
			}
			return $result;
		}

		public static function get_post_in_language(int $post_id, string $language): ?int
		{
			$type_info     = apply_filters('wpml_element_type', get_post_type($post_id));
			$translated_id = apply_filters('wpml_object_id', $post_id, $type_info, false, $language);
			$translated_id = (int) $translated_id;
			return $translated_id ? $translated_id : null;
		}

		public static function list_string_translations(int $page = 1, int $per_page = 50): array
		{
			global $wpdb;

			$table = $wpdb->prefix . 'icl_strings';
			$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ($found !== $table) {
				return array();
			}

			$offset = max(0, ($page - 1) * $per_page);
			$rows   = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
					"SELECT id, context, name, value, language FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
					$per_page,
					$offset
				),
				ARRAY_A
			);

			return array(
				'page'     => $page,
				'per_page' => $per_page,
				'items'    => $rows,
			);
		}
	}
}
