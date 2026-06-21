<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Gutenberg Block Reference Ability.
 *
 * Provides Cowork/Claude with a complete Gutenberg block markup
 * reference so it can create rich, layout-aware content using
 * the sentinel/create-content and sentinel/update-content abilities.
 *
 * Two sections:
 *  - "guide"    : curated markup examples for common core blocks.
 *  - "registry" : dynamic list of all blocks registered on this site.
 *
 * @package    SENTINEL
 * @author     Kyle  ??  ??
 * @copyright  ??  ??
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

\SentinelMCP\Abilities\Registry::register(new \SentinelMCP\Abilities\Gutenberg\Gutenberg_Reference_Ability());
\SentinelMCP\Abilities\Registry::init();

/**
 * Execute the gutenberg-reference ability.
 *
 * @param array $input Ability input with optional 'section' key.
 * @return array Reference data.
 */
function SENTINEL_gutenberg_reference_execute(array $input): array
{
	$section = sanitize_text_field($input['section'] ?? 'all');
	$result  = ['success' => true];

	if ('guide' === $section || 'all' === $section) {
		$result['syntax_rules'] = SENTINEL_gutenberg_syntax_rules();
		$result['blocks']       = SENTINEL_gutenberg_block_examples();
	}

	if ('registry' === $section || 'all' === $section) {
		$result['registered_blocks'] = SENTINEL_gutenberg_registered_blocks();
	}

	return $result;
}

/**
 * General syntax rules for Gutenberg block markup.
 *
 * @return array Rules as strings.
 */
function SENTINEL_gutenberg_syntax_rules(): array
{
	return [
		'Block delimiters are HTML comments: <!-- wp:blockname {"attr":"value"} --> content <!-- /wp:blockname -->',
		'Self-closing blocks (no content): <!-- wp:blockname {"attr":"value"} /-->',
		'Attributes are a JSON object inside the opening comment. Omit if no attributes needed.',
		'Core blocks omit the "core/" namespace: use "wp:paragraph", not "wp:core/paragraph".',
		'Inline formatting goes directly in the HTML: <strong>bold</strong>, <em>italic</em>, <a href="...">link</a>, <code>inline code</code>, <s>strikethrough</s>.',
		'Nested/inner blocks are placed between the parent opening and closing delimiters.',
		'CSS classes like wp-block-heading, wp-block-list are required on the HTML elements.',
		'Colors use preset names: has-{name}-background-color, has-{name}-color. Custom colors use style attribute in JSON.',
		'Alignment: "align" attribute produces alignleft/aligncenter/alignright/alignwide/alignfull on the wrapper element.',
		'Font size: "fontSize" attribute produces has-{size}-font-size class (small, medium, large, x-large).',
	];
}

/**
 * Curated examples of core Gutenberg blocks.
 *
 * Each entry: name, description, example markup, and optionally a
 * variant with attributes for more advanced usage.
 *
 * @return array Block examples.
 */
function SENTINEL_gutenberg_block_examples(): array
{
	return [

		// --- TEXT BLOCKS ---

		[
			'name'        => 'paragraph',
			'description' => 'Text paragraph. Supports alignment, drop cap, font size, colors.',
			'example'     => "<!-- wp:paragraph -->\n<p>Your text here with <strong>bold</strong>, <em>italic</em> and <a href=\"https://example.com\">links</a>.</p>\n<!-- /wp:paragraph -->",
			'with_attrs'  => "<!-- wp:paragraph {\"align\":\"center\",\"dropCap\":true,\"fontSize\":\"large\"} -->\n<p class=\"has-drop-cap has-text-align-center has-large-font-size\">Large centered text with drop cap.</p>\n<!-- /wp:paragraph -->",
		],

		[
			'name'        => 'heading',
			'description' => 'Heading h2-h6. Default level is 2 (no attribute needed). Use {"level":N} for others.',
			'example'     => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">H2 heading (default)</h2>\n<!-- /wp:heading -->",
			'with_attrs'  => "<!-- wp:heading {\"level\":3,\"textAlign\":\"center\"} -->\n<h3 class=\"wp-block-heading has-text-align-center\">Centered H3</h3>\n<!-- /wp:heading -->",
		],

		[
			'name'        => 'list + list-item',
			'description' => 'List block wraps <ul> or <ol>. Each <li> must be inside a wp:list-item block.',
			'example'     => "<!-- wp:list -->\n<ul class=\"wp-block-list\">\n<!-- wp:list-item -->\n<li>First item</li>\n<!-- /wp:list-item -->\n<!-- wp:list-item -->\n<li>Second item</li>\n<!-- /wp:list-item -->\n</ul>\n<!-- /wp:list -->",
			'with_attrs'  => "<!-- wp:list {\"ordered\":true,\"start\":3} -->\n<ol start=\"3\" class=\"wp-block-list\">\n<!-- wp:list-item -->\n<li>Third item</li>\n<!-- /wp:list-item -->\n</ol>\n<!-- /wp:list -->",
		],

		[
			'name'        => 'quote',
			'description' => 'Blockquote with optional citation. Inner content uses paragraph blocks.',
			'example'     => "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">\n<!-- wp:paragraph -->\n<p>The quoted text.</p>\n<!-- /wp:paragraph -->\n<cite>Author Name</cite>\n</blockquote>\n<!-- /wp:quote -->",
		],

		[
			'name'        => 'pullquote',
			'description' => 'Styled pull quote for visual emphasis. Wrapped in <figure>.',
			'example'     => "<!-- wp:pullquote -->\n<figure class=\"wp-block-pullquote\"><blockquote><p>Important quote text.</p><cite>Source</cite></blockquote></figure>\n<!-- /wp:pullquote -->",
		],

		[
			'name'        => 'code',
			'description' => 'Code block for displaying source code.',
			'example'     => "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>function hello() {\n    return \"world\";\n}</code></pre>\n<!-- /wp:code -->",
		],

		[
			'name'        => 'preformatted',
			'description' => 'Preformatted text with preserved whitespace (not code).',
			'example'     => "<!-- wp:preformatted -->\n<pre class=\"wp-block-preformatted\">Text with    preserved    spacing.</pre>\n<!-- /wp:preformatted -->",
		],

		[
			'name'        => 'details',
			'description' => 'Expandable details/summary (accordion). Inner content uses blocks.',
			'example'     => "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>Click to expand</summary>\n<!-- wp:paragraph -->\n<p>Hidden content revealed when expanded.</p>\n<!-- /wp:paragraph -->\n</details>\n<!-- /wp:details -->",
		],

		// --- MEDIA BLOCKS ---

		[
			'name'        => 'image',
			'description' => 'Image block. Use sizeSlug for size, id for media library attachment.',
			'example'     => "<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"https://example.com/photo.jpg\" alt=\"Description\"/></figure>\n<!-- /wp:image -->",
			'with_attrs'  => "<!-- wp:image {\"id\":42,\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"https://example.com/photo-1024x768.jpg\" alt=\"Description\" class=\"wp-image-42\"/><figcaption class=\"wp-element-caption\">Caption text.</figcaption></figure>\n<!-- /wp:image -->",
		],

		[
			'name'        => 'gallery',
			'description' => 'Image gallery. Contains wp:image inner blocks.',
			'example'     => "<!-- wp:gallery {\"linkTo\":\"none\",\"columns\":3} -->\n<figure class=\"wp-block-gallery has-nested-images columns-3 is-cropped\">\n<!-- wp:image {\"id\":1,\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"image1.jpg\" alt=\"\" class=\"wp-image-1\"/></figure>\n<!-- /wp:image -->\n<!-- wp:image {\"id\":2,\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"image2.jpg\" alt=\"\" class=\"wp-image-2\"/></figure>\n<!-- /wp:image -->\n</figure>\n<!-- /wp:gallery -->",
		],

		[
			'name'        => 'video',
			'description' => 'Video block with HTML5 video player.',
			'example'     => "<!-- wp:video -->\n<figure class=\"wp-block-video\"><video controls src=\"https://example.com/video.mp4\"></video></figure>\n<!-- /wp:video -->",
		],

		[
			'name'        => 'audio',
			'description' => 'Audio block with HTML5 audio player.',
			'example'     => "<!-- wp:audio -->\n<figure class=\"wp-block-audio\"><audio controls src=\"https://example.com/audio.mp3\"></audio></figure>\n<!-- /wp:audio -->",
		],

		[
			'name'        => 'embed',
			'description' => 'Embed external content (YouTube, Twitter, etc.). Just put the URL inside.',
			'example'     => "<!-- wp:embed {\"url\":\"https://www.youtube.com/watch?v=EXAMPLE\",\"type\":\"video\",\"providerNameSlug\":\"youtube\",\"responsive\":true,\"className\":\"wp-embed-aspect-16-9 wp-has-aspect-ratio\"} -->\n<figure class=\"wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio\"><div class=\"wp-block-embed__wrapper\">\nhttps://www.youtube.com/watch?v=EXAMPLE\n</div></figure>\n<!-- /wp:embed -->",
		],

		// --- LAYOUT BLOCKS ---

		[
			'name'        => 'columns + column',
			'description' => 'Multi-column layout. Each column is a wp:column block containing inner blocks. Default is equal width.',
			'example'     => "<!-- wp:columns -->\n<div class=\"wp-block-columns\">\n<!-- wp:column -->\n<div class=\"wp-block-column\">\n<!-- wp:paragraph -->\n<p>Left column.</p>\n<!-- /wp:paragraph -->\n</div>\n<!-- /wp:column -->\n<!-- wp:column -->\n<div class=\"wp-block-column\">\n<!-- wp:paragraph -->\n<p>Right column.</p>\n<!-- /wp:paragraph -->\n</div>\n<!-- /wp:column -->\n</div>\n<!-- /wp:columns -->",
			'with_attrs'  => "<!-- wp:columns -->\n<div class=\"wp-block-columns\">\n<!-- wp:column {\"width\":\"66.66%\"} -->\n<div class=\"wp-block-column\" style=\"flex-basis:66.66%\">\n<!-- wp:paragraph -->\n<p>Two-thirds column.</p>\n<!-- /wp:paragraph -->\n</div>\n<!-- /wp:column -->\n<!-- wp:column {\"width\":\"33.33%\"} -->\n<div class=\"wp-block-column\" style=\"flex-basis:33.33%\">\n<!-- wp:paragraph -->\n<p>One-third column.</p>\n<!-- /wp:paragraph -->\n</div>\n<!-- /wp:column -->\n</div>\n<!-- /wp:columns -->",
		],

		[
			'name'        => 'group',
			'description' => 'Container/wrapper block. Use layout type: "constrained" (centered), "flex" (row), or vertical stack.',
			'example'     => "<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\">\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Group Title</h2>\n<!-- /wp:heading -->\n<!-- wp:paragraph -->\n<p>Grouped content.</p>\n<!-- /wp:paragraph -->\n</div>\n<!-- /wp:group -->",
			'with_attrs'  => "<!-- wp:group {\"backgroundColor\":\"contrast\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"20px\",\"bottom\":\"20px\",\"left\":\"20px\",\"right\":\"20px\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group has-contrast-background-color has-background\" style=\"padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px\">\n<!-- wp:paragraph -->\n<p>Content inside styled group.</p>\n<!-- /wp:paragraph -->\n</div>\n<!-- /wp:group -->",
		],

		[
			'name'        => 'buttons + button',
			'description' => 'Button group. Each button is a wp:button block inside wp:buttons. Supports outline style.',
			'example'     => "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">\n<!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"https://example.com\">Click me</a></div>\n<!-- /wp:button -->\n</div>\n<!-- /wp:buttons -->",
			'with_attrs'  => "<!-- wp:buttons {\"layout\":{\"type\":\"flex\",\"justifyContent\":\"center\"}} -->\n<div class=\"wp-block-buttons\">\n<!-- wp:button {\"backgroundColor\":\"vivid-cyan-blue\"} -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link has-vivid-cyan-blue-background-color has-background wp-element-button\" href=\"https://example.com\">Primary</a></div>\n<!-- /wp:button -->\n<!-- wp:button {\"className\":\"is-style-outline\"} -->\n<div class=\"wp-block-button is-style-outline\"><a class=\"wp-block-button__link wp-element-button\" href=\"https://example.com\">Outline</a></div>\n<!-- /wp:button -->\n</div>\n<!-- /wp:buttons -->",
		],

		[
			'name'        => 'cover',
			'description' => 'Background image/color with overlay text. Great for hero sections and banners.',
			'example'     => "<!-- wp:cover {\"url\":\"https://example.com/bg.jpg\",\"dimRatio\":50} -->\n<div class=\"wp-block-cover\"><span aria-hidden=\"true\" class=\"wp-block-cover__background has-background-dim\"></span><img class=\"wp-block-cover__image-background\" src=\"https://example.com/bg.jpg\" alt=\"\"/><div class=\"wp-block-cover__inner-container\">\n<!-- wp:paragraph {\"align\":\"center\",\"fontSize\":\"large\"} -->\n<p class=\"has-text-align-center has-large-font-size\">Hero Title</p>\n<!-- /wp:paragraph -->\n</div></div>\n<!-- /wp:cover -->",
		],

		[
			'name'        => 'media-text',
			'description' => 'Side-by-side media + text layout. Great for feature sections.',
			'example'     => "<!-- wp:media-text {\"mediaType\":\"image\"} -->\n<div class=\"wp-block-media-text is-stacked-on-mobile\">\n<figure class=\"wp-block-media-text__media\"><img src=\"https://example.com/photo.jpg\" alt=\"\"/></figure>\n<div class=\"wp-block-media-text__content\">\n<!-- wp:paragraph -->\n<p>Text content next to the image.</p>\n<!-- /wp:paragraph -->\n</div>\n</div>\n<!-- /wp:media-text -->",
		],

		[
			'name'        => 'separator',
			'description' => 'Horizontal divider. Styles: default (short), is-style-wide, is-style-dots.',
			'example'     => "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->",
			'with_attrs'  => "<!-- wp:separator {\"className\":\"is-style-wide\"} -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity is-style-wide\"/>\n<!-- /wp:separator -->",
		],

		[
			'name'        => 'spacer',
			'description' => 'Vertical space. Use height attribute for custom spacing.',
			'example'     => "<!-- wp:spacer {\"height\":\"50px\"} -->\n<div style=\"height:50px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->",
		],

		// --- OTHER BLOCKS ---

		[
			'name'        => 'table',
			'description' => 'Table block wrapped in <figure>. Supports header and footer rows.',
			'example'     => "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\"><thead><tr><th>Header 1</th><th>Header 2</th></tr></thead><tbody><tr><td>Cell 1</td><td>Cell 2</td></tr><tr><td>Cell 3</td><td>Cell 4</td></tr></tbody></table></figure>\n<!-- /wp:table -->",
		],

		[
			'name'        => 'html',
			'description' => 'Custom HTML block for raw HTML that does not fit any standard block.',
			'example'     => "<!-- wp:html -->\n<div class=\"custom-widget\">Any raw HTML here</div>\n<!-- /wp:html -->",
		],

		[
			'name'        => 'shortcode',
			'description' => 'Embed a WordPress shortcode.',
			'example'     => "<!-- wp:shortcode -->\n[contact-form-7 id=\"123\" title=\"Contact\"]\n<!-- /wp:shortcode -->",
		],
	];
}

/**
 * Get a compact summary of all blocks registered on this WordPress site.
 *
 * @return array List of registered blocks with name, title, category and attributes.
 */
function SENTINEL_gutenberg_registered_blocks(): array
{
	if (! class_exists('WP_Block_Type_Registry')) {
		return ['error' => 'Block registry not available.'];
	}

	$registry = WP_Block_Type_Registry::get_instance();
	$blocks   = $registry->get_all_registered();
	$result   = [];

	foreach ($blocks as $name => $block_type) {
		$entry = [
			'name'     => $name,
			'title'    => $block_type->title ?? '',
			'category' => $block_type->category ?? '',
		];

		if (! empty($block_type->description)) {
			$entry['description'] = $block_type->description;
		}

		if (! empty($block_type->parent)) {
			$entry['parent'] = $block_type->parent;
		}

		// Include attribute names only (not full schemas) to keep response compact.
		if (! empty($block_type->attributes)) {
			$entry['attributes'] = array_keys($block_type->attributes);
		}

		// Include non-false supports.
		if (! empty($block_type->supports) && is_array($block_type->supports)) {
			$active = array_keys(
				array_filter(
					$block_type->supports,
					function ($v) {
						return false !== $v;
					}
				)
			);
			if (! empty($active)) {
				$entry['supports'] = $active;
			}
		}

		$result[] = $entry;
	}

	return $result;
}
