<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * WordPress environment collector.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class WordPress_Info_Collector implements System_Info_Collector_Interface
{

    /**
     * Collect WordPress environment data.
     *
     * @return array
     */
    public static function collect(): array
    {
        global $wp_version;

        $uploads_dir = wp_upload_dir();
        $permalink   = get_option('permalink_structure');

        return array(
            'version'             => $wp_version,
            'multisite'           => is_multisite(),
            'site_url'            => site_url(),
            'home_url'            => home_url(),
            'permalink_structure' => $permalink ? $permalink : 'Plain',
            'is_ssl'              => is_ssl(),
            'language'            => get_locale(),
            'timezone'            => wp_timezone_string(),
            'memory_limit'        => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'Not defined',
            'max_memory_limit'    => defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : 'Not defined',
            'debug_mode'          => defined('WP_DEBUG') && WP_DEBUG,
            'debug_log'           => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'debug_display'       => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            'cron_enabled'        => ! (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
            'object_cache'        => wp_using_ext_object_cache() ? 'External (persistent)' : 'Default (non-persistent)',
            'uploads_dir'         => $uploads_dir['basedir'],
            'uploads_writable'    => wp_is_writable($uploads_dir['basedir']),
            'wp_content_writable' => wp_is_writable(dirname($uploads_dir['basedir'])),
            'environment_type'    => function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'Not available',
        );
    }
}
