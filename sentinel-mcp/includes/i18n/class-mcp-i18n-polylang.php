<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Polylang i18n adapter (Sprint 4.1).
 *
 * Read-only adapter for the Polylang plugin family (free + Pro).
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Polylang read-only adapter.
 */
class I18n_Polylang extends I18n_Adapter
{

	public static function is_active(): bool
	{
		return defined('POLYLANG_VERSION') || function_exists('pll_languages_list');
	}

	public static function slug(): string
	{
		return 'polylang';
	}

	public static function list_languages(): array
	{
		$result = array();
		if (! function_exists('pll_languages_list')) {
			return $result;
		}

		$slugs = (array) pll_languages_list();
		foreach ($slugs as $slug) {
			$entry = array(
				'code'    => (string) $slug,
				'name'    => '',
				'locale'  => '',
				'flag'    => '',
				'default' => false,
			);

			if (function_exists('PLL')) {
				$pll = PLL();
				if (is_object($pll) && isset($pll->model) && method_exists($pll->model, 'get_language')) {
					$lang = $pll->model->get_language($slug);
					if ($lang) {
						$entry['name']   = isset($lang->name) ? (string) $lang->name : '';
						$entry['locale'] = isset($lang->locale) ? (string) $lang->locale : '';
						$entry['flag']   = isset($lang->flag_url) ? (string) $lang->flag_url : '';
					}
				}
			}

			if (function_exists('pll_default_language')) {
				$entry['default'] = (string) pll_default_language() === (string) $slug;
			}

			$result[] = $entry;
		}

		return $result;
	}

	public static function list_translations_for_post(int $post_id): array
	{
		if (! function_exists('pll_get_post_translations')) {
			return array();
		}
		$map    = (array) pll_get_post_translations($post_id);
		$result = array();
		foreach ($map as $lang => $translated_id) {
			$translated_id = (int) $translated_id;
			$post          = $translated_id ? get_post($translated_id) : null;
			$result[]      = array(
				'language' => (string) $lang,
				'post_id'  => $translated_id,
				'status'   => $post ? (string) $post->post_status : '',
				'title'    => $post ? (string) $post->post_title : '',
			);
		}
		return $result;
	}

	public static function get_post_in_language(int $post_id, string $language): ?int
	{
		if (function_exists('pll_get_post')) {
			$id = (int) pll_get_post($post_id, $language);
			return $id ? $id : null;
		}
		return null;
	}

	public static function list_string_translations(int $page = 1, int $per_page = 50): array
	{
		// Polylang stores string translations per language in a postmeta-backed
		// custom registration. There is no public API to enumerate them all
		// without inspecting internal tables; return an empty list and signal
		// the limitation rather than hitting private internals.
		return array(
			'_partial' => 'Polylang does not expose a public API to enumerate all string translations.',
		);
	}
}
