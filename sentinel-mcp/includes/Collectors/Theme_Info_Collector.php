<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Active theme collector.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class Theme_Info_Collector implements System_Info_Collector_Interface
{

    /**
     * Collect active theme data.
     *
     * @return array
     */
    public static function collect(): array
    {
        $theme        = wp_get_theme();
        $parent_theme = $theme->parent();

        $data = array(
            'name'           => $theme->get('Name'),
            'version'        => $theme->get('Version'),
            'author'         => $theme->get('Author'),
            'author_url'     => $theme->get('AuthorURI'),
            'template'       => $theme->get_template(),
            'stylesheet'     => $theme->get_stylesheet(),
            'is_child_theme' => (bool) $parent_theme,
            'parent_theme'   => $parent_theme ? $parent_theme->get('Name') : null,
            'is_block_theme' => $theme->is_block_theme(),
        );

        // WooCommerce theme support.
        if (class_exists('WooCommerce')) {
            $data['has_woocommerce_support'] = current_theme_supports('woocommerce');
            $data['has_woocommerce_file']    = file_exists(get_template_directory() . '/woocommerce.php');
            $data['wc_template_overrides']   = self::get_wc_template_overrides();
        }

        // Parent theme details.
        if ($parent_theme) {
            $data['parent_version']    = $parent_theme->get('Version');
            $data['parent_author_url'] = $parent_theme->get('AuthorURI');
        }

        // Latest version available (from WordPress.org).
        $updates = get_site_transient('update_themes');
        if (isset($updates->response[$theme->get_stylesheet()])) {
            $data['version_latest']   = $updates->response[$theme->get_stylesheet()]['new_version'];
            $data['update_available'] = true;
        } else {
            $data['version_latest']   = $theme->get('Version');
            $data['update_available'] = false;
        }

        return $data;
    }

    /**
     * Get WooCommerce template overrides in the active theme.
     *
     * @return array
     */
    private static function get_wc_template_overrides(): array
    {
        if (! function_exists('WC')) {
            return array();
        }

        $template_path = WC()->plugin_path() . '/templates/';
        $theme_root    = get_stylesheet_directory() . '/woocommerce/';
        $parent_root   = get_template_directory() . '/woocommerce/';
        $overrides     = array();
        $has_outdated  = false;

        // Check child theme overrides.
        if (is_dir($theme_root)) {
            $overrides = self::scan_template_overrides($theme_root, $template_path, 'child');
        }

        // Check parent theme overrides (only if different from child).
        if (is_child_theme() && is_dir($parent_root)) {
            $parent_overrides = self::scan_template_overrides($parent_root, $template_path, 'parent');
            $overrides        = array_merge($overrides, $parent_overrides);
        }

        foreach ($overrides as $override) {
            if (! empty($override['outdated'])) {
                $has_outdated = true;
                break;
            }
        }

        return array(
            'overrides'       => $overrides,
            'has_outdated'    => $has_outdated,
            'total_overrides' => count($overrides),
        );
    }

    /**
     * Scan a directory for WooCommerce template overrides.
     *
     * @param string $theme_dir    Theme templates directory.
     * @param string $template_dir WooCommerce templates directory.
     * @param string $source       Source label (child/parent).
     * @return array
     */
    private static function scan_template_overrides(string $theme_dir, string $template_dir, string $source): array
    {
        $overrides = array();

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($theme_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (! $file->isFile() || 'php' !== pathinfo($file->getFilename(), PATHINFO_EXTENSION)) {
                continue;
            }

            $relative  = str_replace($theme_dir, '', $file->getPathname());
            $core_file = $template_dir . $relative;
            $override  = array(
                'file'   => 'woocommerce/' . $relative,
                'source' => $source,
            );

            // Compare versions if core file exists.
            if (file_exists($core_file)) {
                $theme_version = self::get_template_version($file->getPathname());
                $core_version  = self::get_template_version($core_file);

                $override['version']      = $theme_version;
                $override['core_version'] = $core_version;
                $override['outdated']     = $theme_version && $core_version && version_compare($theme_version, $core_version, '<');
            }

            $overrides[] = $override;
        }

        return $overrides;
    }

    /**
     * Extract @version tag from a template file header.
     *
     * @param string $file File path.
     * @return string Version string or empty.
     */
    private static function get_template_version(string $file): string
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading first 8KB of local file for version tag extraction.
        $content = file_get_contents($file, false, null, 0, 8192);
        if ($content && preg_match('/@version\s+(\d+\.\d+(\.\d+)?)/', $content, $matches)) {
            return $matches[1];
        }
        return '';
    }
}
