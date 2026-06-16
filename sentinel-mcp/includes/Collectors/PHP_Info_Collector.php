<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * PHP environment collector.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class PHP_Info_Collector implements System_Info_Collector_Interface
{

    /**
     * Collect PHP environment data.
     *
     * @return array
     */
    public static function collect(): array
    {
        $important_extensions = array(
            'curl',
            'gd',
            'imagick',
            'intl',
            'mbstring',
            'openssl',
            'zip',
            'zlib',
            'json',
            'xml',
            'dom',
            'simplexml',
            'fileinfo',
            'exif',
            'sodium',
            'bcmath',
            'iconv',
            'soap',
        );

        $loaded   = get_loaded_extensions();
        $ext_info = array();
        foreach ($important_extensions as $ext) {
            $ext_info[$ext] = in_array($ext, $loaded, true);
        }

        return array(
            'version'             => phpversion(),
            'sapi'                => php_sapi_name(),
            'memory_limit'        => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'opcache_enabled'     => self::is_opcache_enabled(),
            'suhosin_installed'   => extension_loaded('suhosin'),
            'extensions'          => $ext_info,
            'total_extensions'    => count($loaded),
        );
    }

    /**
     * Check if OPcache is enabled without triggering warnings.
     *
     * @return bool
     */
    private static function is_opcache_enabled(): bool
    {
        if (! function_exists('opcache_get_status')) {
            return false;
        }
        if (! ini_get('opcache.enable')) {
            return false;
        }
        $status = opcache_get_status(false);
        return is_array($status) && ! empty($status['opcache_enabled']);
    }
}
