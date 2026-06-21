<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Convert raw HTML content to Gutenberg block markup.
 *
 * Supports: p, h1-h6, ul/ol (with list-item), blockquote, pre/code,
 * table, hr, img. Already-block content and non-block-editor post types
 * are returned unchanged.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class HTML_To_Blocks_Converter
{

    /**
     * Convert HTML to Gutenberg block markup.
     *
     * @param string $html      Sanitised HTML content.
     * @param string $post_type Post type slug.
     * @return string Content with Gutenberg block delimiters.
     */
    public static function convert(string $html, string $post_type = 'post'): string
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
            return self::wrap_plain_text($html);
        }

        return self::convert_html_elements($html);
    }

    /**
     * Wrap plain text (no HTML tags) in paragraph blocks.
     *
     * @param string $text Plain text.
     * @return string
     */
    private static function wrap_plain_text(string $text): string
    {
        $paragraphs = preg_split('/\n{2,}/', $text);
        $blocks     = array();

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ('' !== $para) {
                $blocks[] = "<!-- wp:paragraph -->\n<p>" . nl2br(esc_html($para)) . "</p>\n<!-- /wp:paragraph -->";
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Convert HTML elements to Gutenberg blocks.
     *
     * @param string $html HTML content.
     * @return string
     */
    private static function convert_html_elements(string $html): string
    {
        $result = $html;

        // Headings h1-h6 (before paragraphs to avoid <p> inside <hN> conflicts).
        $result = self::convert_headings($result);

        // Paragraphs.
        $result = self::convert_paragraphs($result);

        // Lists.
        $result = self::convert_unordered_lists($result);
        $result = self::convert_ordered_lists($result);

        // Blockquotes.
        $result = self::convert_blockquotes($result);

        // Pre / code blocks.
        $result = self::convert_pre_blocks($result);

        // Tables.
        $result = self::convert_tables($result);

        // Horizontal rules.
        $result = self::convert_hr($result);

        // Standalone images.
        $result = self::convert_images($result);

        return trim($result);
    }

    /**
     * Convert heading tags to heading blocks.
     *
     * @param string $html HTML content.
     * @return string
     */
    private static function convert_headings(string $html): string
    {
        for ($i = 1; $i <= 6; $i++) {
            $attrs  = (2 === $i) ? '' : ' {"level":' . $i . '}';
            $html   = preg_replace(
                '#<h' . $i . '(\s[^>]*)?>(.+?)</h' . $i . '>#si',
                '<!-- wp:heading' . $attrs . " -->\n<h" . $i . ' class="wp-block-heading">$2</h' . $i . ">\n<!-- /wp:heading -->",
                $html
            );
        }
        return $html;
    }

    /**
     * Convert paragraph tags to paragraph blocks.
     *
     * @param string $html HTML content.
     * @return string
     */
    private static function convert_paragraphs(string $html): string
    {
        return preg_replace(
            '#<p(\s[^>]*)?>(.+?)</p>#si',
            "<!-- wp:paragraph -->\n<p\$1>\$2</p>\n<!-- /wp:paragraph -->",
            $html
        );
    }

    /**
     * Convert unordered lists to list blocks.
     *
     * @param string $html HTML content.
     * @return string
     */
    private static function convert_unordered_lists(string $html): string
    {
        return preg_replace_callback(
            '#<ul(\s[^>]*)?>([\s\S]*?)</ul>#si',
            function (array $m): string {
                $inner = self::wrap_list_items($m[2]);
                return "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . $inner . "</ul>\n<!-- /wp:list -->";
            },
            $html
        );
    }

    /**
     * Convert ordered lists to list blocks.
     *
     * @param string $html HTML content.
     * @return string
     */
    private static function convert_ordered_lists(string $html): string
    {
        return preg_replace_callback(
            '#<ol(\s[^>]*)?>([\s\S]*?)</ol>#si',
            function (array $m): string {
                $inner = self::wrap_list_items($m[2]);
                return "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\">" . $inner . "</ol>\n<!-- /wp:list -->";
            },
            $html
        );
    }

    /**
     * Convert blockquote tags to quote blocks.
     *
     * @param string $html HTML content.
     * @return string
     */
    private static function convert_blockquotes(string $html): string
    {
        return preg_replace(
            '#<blockquote(\s[^>]*)?>([\s\S]*?)</blockquote>#si',
            "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">\$2</blockquote>\n<!-- /wp:quote -->",
            $html
        );
    }

    /**
     * Convert pre tags to code blocks.
     *
     * @param string $html HTML content.
     * @return string
     */
    private static function convert_pre_blocks(string $html): string
    {
        return preg_replace(
            '#<pre(\s[^>]*)?>([\s\S]*?)</pre>#si',
            "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>\$2</code></pre>\n<!-- /wp:code -->",
            $html
        );
    }

    /**
     * Convert table tags to table blocks.
     *
     * @param string $html HTML content.
     * @return string
     */
    private static function convert_tables(string $html): string
    {
        return preg_replace(
            '#<table(\s[^>]*)?>([\s\S]*?)</table>#si',
            "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table\$1>\$2</table></figure>\n<!-- /wp:table -->",
            $html
        );
    }

    /**
     * Convert hr tags to separator blocks.
     *
     * @param string $html HTML content.
     * @return string
     */
    private static function convert_hr(string $html): string
    {
        return preg_replace(
            '#<hr(\s[^>]*)?\s*/?\s*>#si',
            "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/\u003e\n<!-- /wp:separator -->",
            $html
        );
    }

    /**
     * Convert standalone img tags to image blocks.
     *
     * @param string $html HTML content.
     * @return string
     */
    private static function convert_images(string $html): string
    {
        return preg_replace(
            '#<img(\s[^>]+?)\s*/?\s*>\s*#si',
            "\n<!-- wp:image -->\n<figure class=\"wp-block-image\"><img\$1/></figure>\n<!-- /wp:image -->\n",
            $html
        );
    }

    /**
     * Wrap each <li> element in wp:list-item block delimiters.
     *
     * @param string $html Inner HTML of a <ul> or <ol>.
     * @return string HTML with list items wrapped in block delimiters.
     */
    private static function wrap_list_items(string $html): string
    {
        return preg_replace(
            '#<li(\s[^>]*)?>([\s\S]*?)</li>#si',
            "\n<!-- wp:list-item -->\n<li\$1>\$2</li>\n<!-- /wp:list-item -->",
            $html
        );
    }
}
