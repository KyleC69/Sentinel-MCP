<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Structured file logger for Sentinel-MCP **DEBUG** output.
 *
 * Writes to a protected log file under wp-content/uploads/sentinel-logs/
 * instead of the server error_log.  Log files are rotated daily and
 * retained for 7 days.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class Logger
{

    /**
     * Maximum number of days to retain log files.
     */
    const RETENTION_DAYS = 7;

    /**
     * Cached enabled state.
     *
     * @var bool|null
     */
    private static ?bool $enabled = null;

    /**
     * Tracks whether old logs have been purged in the current request.
     *
     * @var bool
     */
    private static bool $purged = false;

    /**
     * Write a debug message to the protected log file.
     *
     * @param string $message Log message.
     * @return void
     */
    public static function debug(string $message): void
    {
        if (null === self::$enabled) {
            self::$enabled = (bool) get_option('sentinel_debug_logging', false);
        }

        if (! self::$enabled) {
            return;
        }

        $dir  = self::log_dir();
        $file = $dir . '/sentinel-' . gmdate('Y-m-d') . '.log';

        if (! is_dir($dir)) {
            if (false === wp_mkdir_p($dir)) {
                error_log('Sentinel-MCP Logger: failed to create log directory ' . $dir);
                return;
            }
            // Protect directory from web access.
            self::write_protection_files($dir);
        }

        $line = sprintf(
            "[%s] %s\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            str_replace(array("\r", "\n"), ' ', $message)
        );

        if (! self::put_contents($file, $line)) {
            return;
        }

        if (! self::$purged) {
            self::$purged = true;
            self::purge_old_logs($dir);
        }
    }

    /**
     * Get the log directory path.
     *
     * @return string Absolute path to the log directory.
     */
    private static function log_dir(): string
    {
        $upload = wp_upload_dir();
        return $upload['basedir'] . '/sentinel-logs';
    }

    /**
     * Write contents to a file.
     *
     * Uses native file_put_contents() first. If direct writes are not
     * possible (e.g. restrictive permissions), falls back to the WordPress
     * filesystem API after requesting credentials.
     *
     * @param string $file Absolute file path.
     * @param string $line Line to append.
     * @return bool True on success, false on failure.
     */
    private static function put_contents(string $file, string $line): bool
    {
        // Primary path: direct file append. Works on most standard hosts.
        if (false !== file_put_contents($file, $line, FILE_APPEND | LOCK_EX)) {
            return true;
        }

        // Fallback: WordPress filesystem API for hosts requiring credentials.
        if (! function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

 

        global $wp_filesystem;
        if (! is_object($wp_filesystem) || is_wp_error($wp_filesystem) || ! method_exists($wp_filesystem, 'put_contents')) {
            error_log('Sentinel-MCP Logger: WP_Filesystem unavailable for ' . $file);
            return false;
        }

        $existing = '';
        if ($wp_filesystem->is_file($file)) {
            $existing = $wp_filesystem->get_contents($file);
            if (false === $existing) {
                $existing = '';
            }
        }

        if (false === $wp_filesystem->put_contents($file, $existing . $line, FS_CHMOD_FILE)) {
            error_log('Sentinel-MCP Logger: WP_Filesystem failed to write ' . $file);
            return false;
        }

        return true;
    }

    /**
     * Write .htaccess and index.php protection files.
     *
     * @param string $dir Log directory.
     * @return void
     */
    private static function write_protection_files(string $dir): void
    {
        $htaccess = $dir . '/.htaccess';
        $index    = $dir . '/index.php';

        if (! file_exists($htaccess)) {
            self::put_contents($htaccess, "Order deny,allow\nDeny from all\n");
        }
        if (! file_exists($index)) {
            self::put_contents($index, "<?php // Silence is golden.\n");
        }
    }

    /**
     * Remove log files older than RETENTION_DAYS.
     *
     * @param string $dir Log directory.
     * @return void
     */
    private static function purge_old_logs(string $dir): void
    {
        $cutoff = time() - (self::RETENTION_DAYS * DAY_IN_SECONDS);

        $files = glob($dir . '/sentinel-*.log');
        if (! is_array($files)) {
            return;
        }

        foreach ($files as $path) {
            if (! is_file($path)) {
                continue;
            }

            $mtime = filemtime($path);
            if (false === $mtime) {
                continue;
            }

            if ($mtime < $cutoff) {
                @unlink($path);
            }
        }
    }
}
