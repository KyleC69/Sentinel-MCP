<?php

namespace SentinelMCP;

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
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 */

defined('ABSPATH') || exit;


/**
 * Sanitise post content with block-awareness.
 *
 * If the content already contains Gutenberg block delimiters it is
 * sanitised with filter_block_content() which preserves the block
 * structure.  Otherwise it goes through wp_kses_post() and is then
 * auto-converted to block markup via mcpcomal_content_to_blocks().
 *
 * @param string $raw       Raw content from MCP input.
 * @param string $post_type Post type slug.
 * @return string Sanitised content ready for wp_insert_post / wp_update_post.
 */
function mcpcomal_sanitize_content(string $raw, string $post_type = 'post'): string
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
	return mcpcomal_content_to_blocks(wp_kses_post($raw), $post_type);
}

/**
 * Convert raw HTML content to Gutenberg block markup.
 *
 * If the content already contains block delimiters (<!-- wp:) it is
 * returned as-is. If the post type does not use the block editor the
 * HTML is also returned unchanged.
 *
 * Supported elements: p, h1-h6, ul/ol (with list-item), blockquote, pre/code, table, hr, img.
 *
 * @param string $html      Sanitised HTML content.
 * @param string $post_type Post type slug.
 * @return string Content with Gutenberg block delimiters.
 */
function mcpcomal_content_to_blocks(string $html, string $post_type = 'post'): string
{
	// Already block content — leave untouched.
	if (str_contains($html, '<!-- wp:')) {
		return $html;
	}

	// Post type does not use block editor — keep raw HTML.
	if (
		function_exists('use_block_editor_for_post_type')
		&& ! use_block_editor_for_post_type($post_type)
	) {
		return $html;
	}

	$html = trim($html);
	if (empty($html)) {
		return '';
	}

	// Plain text without HTML tags — wrap in paragraph blocks.
	if (! preg_match('/<[a-z]/i', $html)) {
		$paragraphs = preg_split('/\n{2,}/', $html);
		$blocks     = array();
		foreach ($paragraphs as $para) {
			$para = trim($para);
			if ('' !== $para) {
				$blocks[] = "<!-- wp:paragraph -->\n<p>" . nl2br(esc_html($para)) . "</p>\n<!-- /wp:paragraph -->";
			}
		}
		return implode("\n\n", $blocks);
	}

	// --- Convert HTML elements to Gutenberg blocks ---

	$result = $html;

	// Headings h1-h6 (before paragraphs to avoid <p> inside <hN> conflicts).
	for ($i = 1; $i <= 6; $i++) {
		$attrs  = (2 === $i) ? '' : ' {"level":' . $i . '}';
		$result = preg_replace(
			'#<h' . $i . '(\s[^>]*)?>(.+?)</h' . $i . '>#si',
			'<!-- wp:heading' . $attrs . " -->\n<h" . $i . ' class="wp-block-heading">$2</h' . $i . ">\n<!-- /wp:heading -->",
			$result
		);
	}

	// Paragraphs.
	$result = preg_replace(
		'#<p(\s[^>]*)?>(.+?)</p>#si',
		"<!-- wp:paragraph -->\n<p\$1>\$2</p>\n<!-- /wp:paragraph -->",
		$result
	);

	// Unordered lists — wrap <li> in wp:list-item blocks.
	$result = preg_replace_callback(
		'#<ul(\s[^>]*)?>([\s\S]*?)</ul>#si',
		function ($m) {
			$inner = mcpcomal_wrap_list_items($m[2]);
			return "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . $inner . "</ul>\n<!-- /wp:list -->";
		},
		$result
	);

	// Ordered lists — wrap <li> in wp:list-item blocks.
	$result = preg_replace_callback(
		'#<ol(\s[^>]*)?>([\s\S]*?)</ol>#si',
		function ($m) {
			$inner = mcpcomal_wrap_list_items($m[2]);
			return "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\">" . $inner . "</ol>\n<!-- /wp:list -->";
		},
		$result
	);

	// Blockquotes.
	$result = preg_replace(
		'#<blockquote(\s[^>]*)?>([\s\S]*?)</blockquote>#si',
		"<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">\$2</blockquote>\n<!-- /wp:quote -->",
		$result
	);

	// Pre / code blocks.
	$result = preg_replace(
		'#<pre(\s[^>]*)?>([\s\S]*?)</pre>#si',
		"<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>\$2</code></pre>\n<!-- /wp:code -->",
		$result
	);

	// Tables.
	$result = preg_replace(
		'#<table(\s[^>]*)?>([\s\S]*?)</table>#si',
		"<!-- wp:table -->\n<figure class=\"wp-block-table\"><table\$1>\$2</table></figure>\n<!-- /wp:table -->",
		$result
	);

	// Horizontal rules.
	$result = preg_replace(
		'#<hr(\s[^>]*)?\s*/?\s*>#si',
		"<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->",
		$result
	);

	// Standalone images (not already inside a figure).
	$result = preg_replace(
		'#(?<!<figure[^>]*>)\s*<img(\s[^>]+?)\s*/?\s*>\s*#si',
		"\n<!-- wp:image -->\n<figure class=\"wp-block-image\"><img\$1/></figure>\n<!-- /wp:image -->\n",
		$result
	);

	return trim($result);
}

/**
 * Wrap each <li> element in wp:list-item block delimiters.
 *
 * @param string $html Inner HTML of a <ul> or <ol>.
 * @return string HTML with list items wrapped in block delimiters.
 */
function mcpcomal_wrap_list_items(string $html): string
{
	return preg_replace(
		'#<li(\s[^>]*)?>([\s\S]*?)</li>#si',
		"\n<!-- wp:list-item -->\n<li\$1>\$2</li>\n<!-- /wp:list-item -->",
		$html
	);
}

// Ensure the content category exists (defensive: abilities-discovery.php registers it too).
add_action(
	'wp_abilities_api_categories_init',
	function () {
		if (function_exists('wp_has_ability_category') && ! wp_has_ability_category('sentinel-content')) {
			wp_register_ability_category(
				'sentinel-content',
				array(
					'label'       => __('Content Management', 'mcp-sentinel'),
					'description' => __('Create, read, update, delete, and search content across all post types.', 'mcp-sentinel'),
				)
			);
		}
	}
);

add_action(
	'wp_abilities_api_init',
	function () {
		// Create content (universal).

		wp_register_ability(
			'sentinel/create-content',
			array(
				'label'               => 'Create content in any post type',
				'category'            => 'sentinel-content',
				'description'         => 'Required: title (string). '
					. 'Creates content in any post type (post, page, or custom CPTs). '
					. 'Supports title, content, excerpt, status, taxonomies (categories, tags, custom), '
					. 'meta fields (standard and ACF), parent page (for hierarchical), and menu order. '
					. 'IMPORTANT: Content MUST use Gutenberg block markup (<!-- wp:paragraph --><p>text</p>'
					. '<!-- /wp:paragraph -->). Plain HTML without block delimiters may be emptied by '
					. 'sanitization. Call "sentinel/gutenberg-reference" FIRST to get the correct block syntax. '
					. 'Use "sentinel/inspect-post-type" to discover available fields for the CPT.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'required'   => array('title'),
					'properties' => array(
						'post_type'   => array(
							'type'        => 'string',
							'description' => 'Post type slug. E.g.: "post", "page", "guide", "doc". Defaults to "post".',
							'default'     => 'post',
						),
						'title'       => array(
							'type'        => 'string',
							'description' => 'Content title.',
						),
						'content'     => array(
							'type'        => 'string',
							'description' => 'Post content in Gutenberg block markup format. '
								. 'Use <!-- wp:paragraph --><p>text</p><!-- /wp:paragraph --> delimiters. '
								. 'Plain HTML without block delimiters may be emptied by sanitization. '
								. 'Call "sentinel/gutenberg-reference" FIRST to get the correct block syntax.',
							'default'     => '',
						),
						'excerpt'     => array(
							'type'        => 'string',
							'description' => 'Short excerpt or summary.',
							'default'     => '',
						),
						'post_status' => array(
							'type'        => 'string',
							'description' => 'Status: "draft", "publish", "pending", "private".',
							'enum'        => array('draft', 'publish', 'pending', 'private'),
							'default'     => 'draft',
						),
						'post_parent' => array(
							'type'        => 'integer',
							'description' => 'Parent page/post ID (for hierarchical CPTs).',
							'default'     => 0,
						),
						'menu_order'  => array(
							'type'        => 'integer',
							'description' => 'Menu order (for hierarchical CPTs).',
							'default'     => 0,
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => 'Custom URL slug. If omitted, generated from title.',
							'default'     => '',
						),
						'taxonomies'  => array(
							'type'                 => 'object',
							'description'          => 'Taxonomies to assign. Object where each key is the taxonomy slug '
								. 'and value is an array of term slugs. '
								. 'E.g.: {"category": ["news"], "post_tag": ["redsys", "woocommerce"], '
								. '"doc_category": ["redsys-guides"]}.',
							'additionalProperties' => true,
							'default'              => array(),
						),
						'meta'        => array(
							'type'                 => 'object',
							'description'          => 'Meta fields to save. Object where each key is the meta_key '
								. 'and value is the meta_value. Works with standard and ACF fields. '
								. 'E.g.: {"price": "49.99", "difficulty_level": "intermediate"}.',
							'additionalProperties' => true,
							'default'              => array(),
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array('type' => 'boolean'),
						'post_id'   => array('type' => 'integer'),
						'post_url'  => array('type' => 'string'),
						'edit_url'  => array('type' => 'string'),
						'post_type' => array('type' => 'string'),
						'message'   => array('type' => 'string'),
					),
				),

				'execute_callback'    => function ($input) {
					return mcpcomal_universal_create($input);
				},

				'permission_callback' => function () {
					return current_user_can('publish_posts');
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => false,
							'destructiveHint' => false,
							'idempotentHint'  => false,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);

		// Read content.

		wp_register_ability(
			'sentinel/read-content',
			array(
				'label'               => 'Read content from any post',
				'category'            => 'sentinel-content',
				'description'         => 'Required: post_id (integer). '
					. 'Reads the full content of any post by its ID. '
					. 'Includes title, content, meta, taxonomies with terms, and post type. '
					. 'Alias: id is also accepted instead of post_id.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the post to read.',
						),
						'id'      => array(
							'type'        => 'integer',
							'description' => 'Alias for post_id.',
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'ID'         => array('type' => 'integer'),
						'title'      => array('type' => 'string'),
						'content'    => array('type' => 'string'),
						'excerpt'    => array('type' => 'string'),
						'status'     => array('type' => 'string'),
						'post_type'  => array('type' => 'string'),
						'date'       => array('type' => 'string'),
						'url'        => array('type' => 'string'),
						'parent'     => array('type' => 'integer'),
						'slug'       => array('type' => 'string'),
						'taxonomies' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'meta'       => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				),

				'execute_callback'    => function ($input) {
					$post = get_post((int) ($input['post_id'] ?? $input['id'] ?? 0));
					if (! $post) {
						return array(
							'success' => false,
							'message' => 'Post not found.',
						);
					}

					// Get all taxonomies and their assigned terms.
					$taxonomies    = get_object_taxonomies($post->post_type, 'objects');
					$tax_data      = array();
					foreach ($taxonomies as $tax) {
						$terms = wp_get_object_terms($post->ID, $tax->name);
						if (! is_wp_error($terms) && ! empty($terms)) {
							$tax_data[$tax->name] = array_map(
								function ($t) {
									return array(
										'id'   => $t->term_id,
										'name' => $t->name,
										'slug' => $t->slug,
									);
								},
								$terms
							);
						}
					}

					// Get meta (excluding WP internal keys).
					$all_meta  = get_post_meta($post->ID);
					$meta_data = array();
					foreach ($all_meta as $key => $values) {
						if (str_starts_with($key, '_edit_') || str_starts_with($key, '_wp_')) {
							continue;
						}
						// ACF: If it is a field reference, skip it.
						if (str_starts_with($key, '_') && isset($all_meta['_' . $key])) {
							continue;
						}
						$meta_data[$key] = count($values) === 1 ? $values[0] : $values;
					}

					return array(
						'ID'         => $post->ID,
						'title'      => $post->post_title,
						'content'    => $post->post_content,
						'excerpt'    => $post->post_excerpt,
						'status'     => $post->post_status,
						'post_type'  => $post->post_type,
						'date'       => $post->post_date,
						'url'        => get_permalink($post->ID),
						'parent'     => $post->post_parent,
						'slug'       => $post->post_name,
						'taxonomies' => $tax_data,
						'meta'       => $meta_data,
					);
				},

				'permission_callback' => function () {
					return current_user_can('read');
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

		// Update content.

		wp_register_ability(
			'sentinel/update-content',
			array(
				'label'               => 'Update existing content',
				'category'            => 'sentinel-content',
				'description'         => 'Required: post_id (integer). '
					. 'Updates any field of an existing post: title, content, excerpt, '
					. 'status, taxonomies, meta fields. Only provided fields are updated. '
					. 'IMPORTANT: Content MUST use Gutenberg block markup (<!-- wp:paragraph --><p>text</p>'
					. '<!-- /wp:paragraph -->). Plain HTML without block delimiters may be emptied by '
					. 'sanitization. Call "sentinel/gutenberg-reference" FIRST to get the correct block syntax. '
					. 'Alias: id is also accepted instead of post_id.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'post_id'     => array(
							'type'        => 'integer',
							'description' => 'ID of the post to update.',
						),
						'id'          => array(
							'type'        => 'integer',
							'description' => 'Alias for post_id.',
						),
						'title'       => array('type' => 'string'),
						'content'     => array(
							'type'        => 'string',
							'description' => 'Content as HTML or Gutenberg block markup. '
								. 'Plain HTML is auto-converted to blocks. '
								. 'For advanced layouts (columns, buttons, cover, groups, etc.) '
								. 'send Gutenberg block markup with <!-- wp:blockname --> delimiters. '
								. 'Call "sentinel/gutenberg-reference" first to see the block syntax guide.',
						),
						'excerpt'     => array('type' => 'string'),
						'post_status' => array(
							'type' => 'string',
							'enum' => array('draft', 'publish', 'pending', 'private'),
						),
						'slug'        => array('type' => 'string'),
						'taxonomies'  => array(
							'type'                 => 'object',
							'description'          => 'Taxonomies to update (replaces existing terms).',
							'additionalProperties' => true,
						),
						'meta'        => array(
							'type'                 => 'object',
							'description'          => 'Meta fields to update.',
							'additionalProperties' => true,
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array('type' => 'boolean'),
						'post_id'  => array('type' => 'integer'),
						'post_url' => array('type' => 'string'),
						'message'  => array('type' => 'string'),
					),
				),

				'execute_callback'    => function ($input) {
					return mcpcomal_universal_update($input);
				},

				'permission_callback' => function () {
					return current_user_can('edit_posts');
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => false,
							'destructiveHint' => false,
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);

		// Search content.

		wp_register_ability(
			'sentinel/search-content',
			array(
				'label'               => 'Search content',
				'category'            => 'sentinel-content',
				'description'         => 'All parameters optional. '
					. 'Searches content across any post type by text, category, custom taxonomy, '
					. 'meta field, date range, or any combination of filters. '
					. 'Supports publish date and modified date filters '
					. 'to find outdated or recently updated content.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'search'          => array(
							'type'        => 'string',
							'description' => 'Search text (searches in title and content).',
							'default'     => '',
						),
						'post_type'       => array(
							'type'        => 'string',
							'description' => 'Post type slug. Use "any" for all types.',
							'default'     => 'any',
						),
						'taxonomy_filter' => array(
							'type'                 => 'object',
							'description'          => 'Taxonomy filter. E.g.: {"category": "news"} or {"doc_category": "guides"}.',
							'additionalProperties' => true,
							'default'              => array(),
						),
						'meta_filter'     => array(
							'type'                 => 'object',
							'description'          => 'Meta filter. E.g.: {"level": "advanced"}. Each key is meta_key, value is meta_value.',
							'additionalProperties' => true,
							'default'              => array(),
						),
						'post_status'     => array(
							'type'        => 'string',
							'default'     => 'any',
							'description' => '"any", "publish", "draft", etc.',
						),
						'count'           => array(
							'type'        => 'integer',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 50,
							'description' => 'Number of results per page (max 50). Alias: per_page is also accepted.',
						),
						'orderby'         => array(
							'type'    => 'string',
							'default' => 'date',
							'enum'    => array('date', 'title', 'modified', 'menu_order', 'rand'),
						),
						'order'           => array(
							'type'    => 'string',
							'default' => 'DESC',
							'enum'    => array('ASC', 'DESC'),
						),
						'date_after'      => array(
							'type'        => 'string',
							'description' => 'Posts published after this date (YYYY-MM-DD).',
						),
						'date_before'     => array(
							'type'        => 'string',
							'description' => 'Posts published before this date (YYYY-MM-DD).',
						),
						'modified_after'  => array(
							'type'        => 'string',
							'description' => 'Posts modified after this date (YYYY-MM-DD). Useful to find recently updated content.',
						),
						'modified_before' => array(
							'type'        => 'string',
							'description' => 'Posts modified before this date (YYYY-MM-DD). Useful to find outdated content.',
						),
					),
				),

				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'ID'         => array('type' => 'integer'),
							'title'      => array('type' => 'string'),
							'url'        => array('type' => 'string'),
							'date'       => array('type' => 'string'),
							'modified'   => array('type' => 'string'),
							'status'     => array('type' => 'string'),
							'post_type'  => array('type' => 'string'),
							'excerpt'    => array('type' => 'string'),
							'word_count' => array('type' => 'integer'),
						),
					),
				),

				'execute_callback'    => function ($input) {
					$allowed_orderby = array('date', 'title', 'modified', 'ID', 'name', 'author', 'rand', 'comment_count', 'menu_order', 'none');
					$orderby         = sanitize_text_field($input['orderby'] ?? 'date');
					$order           = strtoupper(sanitize_text_field($input['order'] ?? 'DESC'));

					$args = array(
						'numberposts' => min(absint($input['count'] ?? $input['per_page'] ?? 10), 100),
						'post_type'   => sanitize_text_field($input['post_type'] ?? 'any'),
						'post_status' => sanitize_text_field($input['post_status'] ?? 'any'),
						'orderby'     => in_array($orderby, $allowed_orderby, true) ? $orderby : 'date',
						'order'       => in_array($order, array('ASC', 'DESC'), true) ? $order : 'DESC',
					);

					if ('any' === $args['post_status']) {
						$args['post_status'] = array('publish', 'draft', 'pending', 'private');
					}

					if (! empty($input['search'])) {
						$args['s'] = sanitize_text_field($input['search']);
					}

					// Taxonomy filter.
					if (! empty($input['taxonomy_filter']) && is_array($input['taxonomy_filter'])) {
						$tax_query = array();
						foreach ($input['taxonomy_filter'] as $tax => $term_slug) {
							$tax_query[] = array(
								'taxonomy' => sanitize_text_field($tax),
								'field'    => 'slug',
								'terms'    => sanitize_text_field($term_slug),
							);
						}
						$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for taxonomy filtering.
					}

					// Meta filter.
					if (! empty($input['meta_filter']) && is_array($input['meta_filter'])) {
						$meta_query = array();
						foreach ($input['meta_filter'] as $key => $value) {
							$meta_query[] = array(
								'key'   => sanitize_text_field($key),
								'value' => sanitize_text_field($value),
							);
						}
						$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for meta filtering.
					}

					// Date filters (published and modified).
					$date_query = array();
					if (! empty($input['date_after'])) {
						$date_query[] = array(
							'column' => 'post_date',
							'after'  => sanitize_text_field($input['date_after']),
						);
					}
					if (! empty($input['date_before'])) {
						$date_query[] = array(
							'column' => 'post_date',
							'before' => sanitize_text_field($input['date_before']),
						);
					}
					if (! empty($input['modified_after'])) {
						$date_query[] = array(
							'column' => 'post_modified',
							'after'  => sanitize_text_field($input['modified_after']),
						);
					}
					if (! empty($input['modified_before'])) {
						$date_query[] = array(
							'column' => 'post_modified',
							'before' => sanitize_text_field($input['modified_before']),
						);
					}
					if (! empty($date_query)) {
						$args['date_query'] = $date_query;
					}

					$posts = get_posts($args);

					return array_map(
						function ($post) {
							return array(
								'ID'         => $post->ID,
								'title'      => $post->post_title,
								'url'        => get_permalink($post),
								'date'       => $post->post_date,
								'modified'   => $post->post_modified,
								'status'     => $post->post_status,
								'post_type'  => $post->post_type,
								'excerpt'    => wp_trim_words($post->post_content, 30),
								'word_count' => str_word_count(wp_strip_all_tags($post->post_content)),
							);
						},
						$posts
					);
				},

				'permission_callback' => function () {
					return current_user_can('read');
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

		// Delete content.

		wp_register_ability(
			'sentinel/delete-content',
			array(
				'label'               => 'Delete content',
				'category'            => 'sentinel-content',
				'description'         => 'Required: post_id (integer). '
					. 'Moves a post to the trash or permanently deletes it. '
					. 'Alias: id is also accepted instead of post_id.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the post to delete.',
						),
						'id'      => array(
							'type'        => 'integer',
							'description' => 'Alias for post_id.',
						),
						'force'   => array(
							'type'        => 'boolean',
							'description' => 'true = permanently delete, false = move to trash.',
							'default'     => false,
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array('type' => 'boolean'),
						'message' => array('type' => 'string'),
					),
				),

				'execute_callback'    => function ($input) {
					$post_id = (int) ($input['post_id'] ?? $input['id'] ?? 0);
					$post    = get_post($post_id);

					if (! $post) {
						return array(
							'success' => false,
							'message' => 'Post not found.',
						);
					}

					$title = $post->post_title;
					$force = (bool) ($input['force'] ?? false);

					$result = wp_delete_post($post_id, $force);

					if (! $result) {
						return array(
							'success' => false,
							'message' => 'Failed to delete the post.',
						);
					}

					return array(
						'success' => true,
						'message' => sprintf(
							'"%s" %s successfully.',
							$title,
							$force ? 'permanently deleted' : 'moved to trash'
						),
					);
				},

				'permission_callback' => function () {
					return current_user_can('delete_posts');
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => false,
							'destructiveHint' => true,
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);
	}
);

/**
 * Create content universally for any post type.
 *
 * @param array $input Input data for creating the post.
 * @return array Result with success status and post data.
 */
function mcpcomal_universal_create(array $input): array
{

	$post_type   = sanitize_text_field($input['post_type'] ?? 'post');
	$title       = sanitize_text_field($input['title']);
	$raw_content = $input['content'] ?? '';
	$content     = mcpcomal_sanitize_content($raw_content, $post_type);
	$excerpt     = sanitize_text_field($input['excerpt'] ?? '');
	$status      = sanitize_text_field($input['post_status'] ?? 'draft');
	$parent      = (int) ($input['post_parent'] ?? 0);
	$menu_order  = (int) ($input['menu_order'] ?? 0);
	$slug        = sanitize_title($input['slug'] ?? '');
	$taxonomies  = $input['taxonomies'] ?? array();
	$meta        = $input['meta'] ?? array();

	// Verify the post type exists.
	if (! post_type_exists($post_type)) {
		return array(
			'success' => false,
			'message' => sprintf('Post type "%s" does not exist. Use "sentinel/site-schema" to see available types.', $post_type),
		);
	}

	$post_data = array(
		'post_title'   => $title,
		'post_content' => $content,
		'post_excerpt' => $excerpt,
		'post_status'  => $status,
		'post_type'    => $post_type,
		'post_author'  => get_current_user_id(),
		'post_parent'  => $parent,
		'menu_order'   => $menu_order,
	);

	if ($slug) {
		$post_data['post_name'] = $slug;
	}

	// Assign categories if post type is 'post' and category is in taxonomies.
	if ('post' === $post_type && ! empty($taxonomies['category'])) {
		$cat_ids = array();
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
		return array(
			'success' => false,
			'message' => $post_id->get_error_message(),
		);
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
			$terms = is_array($term_slugs) ? $term_slugs : array($term_slugs);
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

	$result = array(
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
	);

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
function mcpcomal_universal_update(array $input): array
{

	$post_id = (int) ($input['post_id'] ?? $input['id'] ?? 0);
	$post    = get_post($post_id);

	if (! $post) {
		return array(
			'success' => false,
			'message' => 'Post not found.',
		);
	}

	$update = array('ID' => $post_id);

	if (isset($input['title'])) {
		$update['post_title'] = sanitize_text_field($input['title']);
	}
	$raw_update_content = null;
	if (isset($input['content'])) {
		$raw_update_content     = $input['content'];
		$update['post_content'] = mcpcomal_sanitize_content($raw_update_content, $post->post_type);
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
		return array(
			'success' => false,
			'message' => $result->get_error_message(),
		);
	}

	// Update taxonomies.
	if (isset($input['taxonomies']) && is_array($input['taxonomies'])) {
		foreach ($input['taxonomies'] as $tax_slug => $term_slugs) {
			if (! taxonomy_exists($tax_slug)) {
				continue;
			}
			$terms = is_array($term_slugs) ? $term_slugs : array($term_slugs);
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

	$result = array(
		'success'  => true,
		'post_id'  => $post_id,
		'post_url' => get_permalink($post_id),
		'message'  => sprintf('"%s" updated successfully.', get_the_title($post_id)),
	);

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
