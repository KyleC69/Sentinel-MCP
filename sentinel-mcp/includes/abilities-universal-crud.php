<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Content\Create_Content_Ability;
use SentinelMCP\Abilities\Content\Read_Content_Ability;
use SentinelMCP\Abilities\Content\Update_Content_Ability;
use SentinelMCP\Abilities\Content\Search_Content_Ability;
use SentinelMCP\Abilities\Content\Delete_Content_Ability;

/**
 * Universal CRUD Abilities.
 *
 * Operations that work with ANY post type:
 *  - Create content (any CPT, with meta and taxonomies)
 *  - Read content
 *  - Update content
 *  - Delete content
 *  - Search content
 *
 * Claude/Cowork first inspects the site schema
 * (sentinel/site-schema or sentinel/inspect-post-type) to discover
 * available fields, then uses these abilities to operate.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;


/**
 * Sanitise post content with block-awareness.
 *
 * If the content already contains Gutenberg block delimiters it is
 * sanitised with filter_block_content() which preserves the block
 * structure.  Otherwise it goes through wp_kses_post() and is then
 * auto-converted to block markup via SENTINEL_content_to_blocks().
 *
 * @param string $raw       Raw content from MCP input.
 * @param string $post_type Post type slug.
 * @return string Sanitised content ready for wp_insert_post / wp_update_post.
 */
function SENTINEL_sanitize_content(string $raw, string $post_type = 'post'): string
{
	$raw = trim($raw);
	if (empty($raw)) {
		return '';
	}

	// Content with block delimiters → use block-aware sanitiser.
	if (str_contains($raw, '<!-- wp:')) {
		if (function_exists('filter_block_content')) {
			return filter_block_content($raw, 'post');
		}
		return wp_kses_post($raw);
	}

	// Plain HTML → sanitise then auto-convert to blocks.
	return HTML_To_Blocks_Converter::convert(wp_kses_post($raw), $post_type);
}

// Ensure the content category exists (defensive: abilities-discovery.php registers it too).
add_action(
	'wp_abilities_api_categories_init',
	function () {
		if (function_exists('wp_has_ability_category') && ! wp_has_ability_category('sentinel-content')) {
			wp_register_ability_category(
				'sentinel-content',
				[
					'label'       => __('Content Management', 'mcp-sentinel'),
					'description' => __('Create, read, update, delete, and search content across all post types.', 'mcp-sentinel'),
				]
			);
		}
	}
);

Registry::register(new Create_Content_Ability());
Registry::register(new Read_Content_Ability());
Registry::register(new Update_Content_Ability());
Registry::register(new Search_Content_Ability());
Registry::register(new Delete_Content_Ability());
Registry::init();

/**
 * Create content universally for any post type.
 *
 * @param array $input Input data for creating the post.
 * @return array Result with success status and post data.
 */
function SENTINEL_universal_create(array $input): array
{

	$post_type   = sanitize_text_field($input['post_type'] ?? 'post');
	$title       = sanitize_text_field($input['title']);
	$raw_content = $input['content'] ?? '';
	$content     = SENTINEL_sanitize_content($raw_content, $post_type);
	$excerpt     = sanitize_text_field($input['excerpt'] ?? '');
	$status      = sanitize_text_field($input['post_status'] ?? 'draft');
	$parent      = (int) ($input['post_parent'] ?? 0);
	$menu_order  = (int) ($input['menu_order'] ?? 0);
	$slug        = sanitize_title($input['slug'] ?? '');
	$taxonomies  = $input['taxonomies'] ?? [];
	$meta        = $input['meta'] ?? [];

	// Verify the post type exists.
	if (! post_type_exists($post_type)) {
		return [
			'success' => false,
			'message' => sprintf('Post type "%s" does not exist. Use "sentinel/site-schema" to see available types.', $post_type),
		];
	}

	$post_data = [
		'post_title'   => $title,
		'post_content' => $content,
		'post_excerpt' => $excerpt,
		'post_status'  => $status,
		'post_type'    => $post_type,
		'post_author'  => get_current_user_id(),
		'post_parent'  => $parent,
		'menu_order'   => $menu_order,
	];

	if ($slug) {
		$post_data['post_name'] = $slug;
	}

	// Assign categories if post type is 'post' and category is in taxonomies.
	if ('post' === $post_type && ! empty($taxonomies['category'])) {
		$cat_ids = [];
		foreach ((array) $taxonomies['category'] as $cat_slug) {
			$cat = get_category_by_slug(sanitize_text_field($cat_slug));
			if ($cat) {
				$cat_ids[] = $cat->term_id;
			}
		}
		if ($cat_ids) {
			$post_data['post_category'] = $cat_ids;
		}
	}

	$post_id = wp_insert_post($post_data, true);

	if (is_wp_error($post_id)) {
		return [
			'success' => false,
			'message' => $post_id->get_error_message(),
		];
	}

	// Assign taxonomies.
	if (! empty($taxonomies) && is_array($taxonomies)) {
		foreach ($taxonomies as $tax_slug => $term_slugs) {
			if ('category' === $tax_slug && 'post' === $post_type) {
				continue; // Already assigned above.
			}
			if (! taxonomy_exists($tax_slug)) {
				continue;
			}
			$terms = is_array($term_slugs) ? $term_slugs : [$term_slugs];
			$terms = array_map('sanitize_text_field', $terms);
			wp_set_object_terms($post_id, $terms, $tax_slug);
		}
	}

	// Save meta fields.
	if (! empty($meta) && is_array($meta)) {
		foreach ($meta as $key => $value) {
			$key = sanitize_text_field($key);

			// If ACF is active and the field exists, use update_field.
			if (function_exists('update_field')) {
				$acf_field = acf_get_field($key);
				if ($acf_field) {
					update_field($key, $value, $post_id);
					continue;
				}
			}

			update_post_meta($post_id, $key, $value);
		}
	}

	$pt_object = get_post_type_object($post_type);
	$label     = $pt_object ? $pt_object->labels->singular_name : $post_type;

	$result = [
		'success'   => true,
		'post_id'   => $post_id,
		'post_url'  => get_permalink($post_id),
		'edit_url'  => admin_url('post.php?post=' . $post_id . '&action=edit'),
		'post_type' => $post_type,
		'message'   => sprintf(
			'%s "%s" created as %s (ID: %d).',
			$label,
			$title,
			$status,
			$post_id
		),
	];

	// Warn if content was lost during sanitisation.
	if (! empty($raw_content) && empty($content)) {
		$result['warning'] = 'Content was not empty but became empty after sanitisation. '
			. 'Use Gutenberg block markup (<!-- wp:paragraph --><p>...</p><!-- /wp:paragraph -->) '
			. 'or plain HTML. Call "sentinel/gutenberg-reference" for the block syntax guide.';
	}

	return $result;
}

/**
 * Update content universally for any post type.
 *
 * @param array $input Input data for updating the post.
 * @return array Result with success status and updated post data.
 */
function SENTINEL_universal_update(array $input): array
{

	$post_id = (int) ($input['post_id'] ?? $input['id'] ?? 0);
	$post    = get_post($post_id);

	if (! $post) {
		return [
			'success' => false,
			'message' => 'Post not found.',
		];
	}

	$update = ['ID' => $post_id];

	if (isset($input['title'])) {
		$update['post_title'] = sanitize_text_field($input['title']);
	}
	$raw_update_content = null;
	if (isset($input['content'])) {
		$raw_update_content     = $input['content'];
		$update['post_content'] = SENTINEL_sanitize_content($raw_update_content, $post->post_type);
	}
	if (isset($input['excerpt'])) {
		$update['post_excerpt'] = sanitize_text_field($input['excerpt']);
	}
	if (isset($input['post_status'])) {
		$update['post_status'] = sanitize_text_field($input['post_status']);
	}
	if (isset($input['slug'])) {
		$update['post_name'] = sanitize_title($input['slug']);
	}

	$result = wp_update_post($update, true);

	if (is_wp_error($result)) {
		return [
			'success' => false,
			'message' => $result->get_error_message(),
		];
	}

	// Update taxonomies.
	if (isset($input['taxonomies']) && is_array($input['taxonomies'])) {
		foreach ($input['taxonomies'] as $tax_slug => $term_slugs) {
			if (! taxonomy_exists($tax_slug)) {
				continue;
			}
			$terms = is_array($term_slugs) ? $term_slugs : [$term_slugs];
			$terms = array_map('sanitize_text_field', $terms);
			wp_set_object_terms($post_id, $terms, $tax_slug);
		}
	}

	// Update meta.
	if (isset($input['meta']) && is_array($input['meta'])) {
		foreach ($input['meta'] as $key => $value) {
			$key = sanitize_text_field($key);
			if (function_exists('update_field')) {
				$acf_field = acf_get_field($key);
				if ($acf_field) {
					update_field($key, $value, $post_id);
					continue;
				}
			}
			update_post_meta($post_id, $key, $value);
		}
	}

	$result = [
		'success'  => true,
		'post_id'  => $post_id,
		'post_url' => get_permalink($post_id),
		'message'  => sprintf('"%s" updated successfully.', get_the_title($post_id)),
	];

	// Warn if content was lost during sanitisation.
	if (
		null !== $raw_update_content && ! empty($raw_update_content)
		&& isset($update['post_content']) && empty($update['post_content'])
	) {
		$result['warning'] = 'Content was not empty but became empty after sanitisation. '
			. 'Use Gutenberg block markup (<!-- wp:paragraph --><p>...</p><!-- /wp:paragraph -->) '
			. 'or plain HTML. Call "sentinel/gutenberg-reference" for the block syntax guide.';
	}

	return $result;
}
