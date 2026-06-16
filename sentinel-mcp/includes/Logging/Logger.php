<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Structured file logger for Sentinel-MCP debug output.
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
     * Write a debug message to the protected log file.
     *
     * @param string $message Log message.
     * @return void
     */
    public static function debug(string $message): void
    {
        if (null === self::$enabled) {
            self::$enabled = (bool) get_option('mcpcomal_debug_logging', false);
        }

        if (! self::$enabled) {
            return;
        }

        $dir  = self::log_dir();
        $file = $dir . '/sentinel-' . gmdate('Y-m-d') . '.log';

        if (! is_dir($dir)) {
            wp_mkdir_p($dir);
            // Protect directory from web access.
            if (! file_exists($dir . '/.htaccess')) {
                file_put_contents($dir . '/.htaccess', "Order deny,allow\nDeny from all\n");
            }
            if (! file_exists($dir . '/index.php')) {
                file_put_contents($dir . '/index.php', "<?php // Silence is golden.\n");
            }
        }

        $line = sprintf(
            "[%s] %s\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            str_replace(array("\r", "\n"), ' ', $message)
        );

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        self::purge_old_logs($dir);
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
     * Remove log files older than RETENTION_DAYS.
     *
     * @param string $dir Log directory.
     * @return void
     */
    private static function purge_old_logs(string $dir): void
    {
        $cutoff = time() - (self::RETENTION_DAYS * DAY_IN_SECONDS);

        foreach (glob($dir . '/sentinel-*.log') as $path) {
            if (is_file($path) && filemtime($path) < $cutoff) {
                unlink($path);
            }
        }
    }
}
