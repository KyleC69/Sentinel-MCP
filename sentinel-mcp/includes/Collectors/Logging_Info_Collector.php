<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Logging environment collector.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class Logging_Info_Collector implements System_Info_Collector_Interface
{

    /**
     * Collect logging environment data.
     *
     * @return array
     */
    public static function collect(): array
    {
        $data = array(
            'wp_debug'     => defined('WP_DEBUG') && WP_DEBUG,
            'wp_debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
        );

        // WP debug log location.
        $content_base = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
        if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG)) {
            $data['wp_debug_log_path'] = WP_DEBUG_LOG;
        } elseif (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $data['wp_debug_log_path'] = $content_base . '/debug.log';
        }

        // Debug log file size.
        $log_path = $data['wp_debug_log_path'] ?? ($content_base . '/debug.log');
        if (file_exists($log_path)) {
            $data['debug_log_size'] = size_format(filesize($log_path));
        }

        // WooCommerce logging.
        if (defined('WC_LOG_DIR') && is_dir(WC_LOG_DIR)) {
            $data['wc_log_directory'] = WC_LOG_DIR;
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Read-only check for diagnostics, WP_Filesystem not needed.
            $data['wc_log_writable'] = is_writable(WC_LOG_DIR);

            // Calculate log directory size.
            $log_size = 0;
            $files    = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(WC_LOG_DIR, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $log_size += $file->getSize();
                }
            }
            $data['wc_log_directory_size'] = size_format($log_size);
        }

        return $data;
    }
}
