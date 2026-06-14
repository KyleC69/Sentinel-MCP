<?php

/**
 * Prompt gallery (Sprint 4.2).
 *
 * Loads curated prompts from data/prompts.json so site owners can copy ready-made
 * prompts into their AI client. The catalog is data-driven and can be extended
 * via the `mcpcomal_prompts_catalog` filter.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://mcpwp.com/
 */

defined('ABSPATH') || exit;

if (! class_exists('SENTINEL_Prompt_Gallery')) {

	/**
	 * Reads data/prompts.json and exposes filtered helpers for the admin UI.
	 */
	class SENTINEL_Prompt_Gallery
	{

		/**
		 * Static cache of the parsed catalog.
		 *
		 * @var array|null
		 */
		protected static ?array $cache = null;

		/**
		 * Load and decode the catalog. Returns empty structure on missing/invalid file.
		 *
		 * @return array{categories:array<int,array<string,mixed>>}
		 */
		public static function load(): array
		{
			if (null !== self::$cache) {
				return self::$cache;
			}

			$path = SENTINEL_PATH . 'data/prompts.json';
			if (! is_readable($path)) {
				self::$cache = array('categories' => array());
				return self::$cache;
			}

			$raw     = file_get_contents($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$decoded = is_string($raw) ? json_decode($raw, true) : null;
			if (! is_array($decoded) || empty($decoded['categories'])) {
				self::$cache = array('categories' => array());
				return self::$cache;
			}

			self::$cache = apply_filters('mcpcomal_prompts_catalog', $decoded);
			return self::$cache;
		}

		/**
		 * Total prompt count across all categories.
		 */
		public static function total_count(): int
		{
			$catalog = self::load();
			$total   = 0;
			foreach ((array) $catalog['categories'] as $cat) {
				if (isset($cat['prompts']) && is_array($cat['prompts'])) {
					$total += count($cat['prompts']);
				}
			}
			return $total;
		}

		/**
		 * Filter prompts by category slug and free-text keyword (matches title, description, prompt).
		 *
		 * @param string|null $category_slug Category slug (e.g. "content"). Null = all.
		 * @param string|null $keyword       Case-insensitive substring. Null = no filter.
		 * @return array<int,array<string,mixed>> Matching categories with filtered prompts.
		 */
		public static function filter(?string $category_slug = null, ?string $keyword = null): array
		{
			$catalog  = self::load();
			$keyword  = $keyword ? strtolower(trim($keyword)) : null;
			$category = $category_slug ? strtolower(trim($category_slug)) : null;

			$out = array();
			foreach ((array) $catalog['categories'] as $cat) {
				if ($category && strtolower((string) ($cat['slug'] ?? '')) !== $category) {
					continue;
				}
				$prompts = isset($cat['prompts']) && is_array($cat['prompts']) ? $cat['prompts'] : array();
				if ($keyword) {
					$prompts = array_values(
						array_filter(
							$prompts,
							function ($p) use ($keyword) {
								$haystack = strtolower(
									(string) ($p['title'] ?? '') . ' '
										. (string) ($p['description'] ?? '') . ' '
										. (string) ($p['prompt'] ?? '')
								);
								return false !== strpos($haystack, $keyword);
							}
						)
					);
				}
				if (empty($prompts) && ($category || $keyword)) {
					continue;
				}
				$cat['prompts'] = $prompts;
				$out[]          = $cat;
			}
			return $out;
		}
	}
}
