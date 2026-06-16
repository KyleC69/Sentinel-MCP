<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Server environment collector.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class Server_Info_Collector implements System_Info_Collector_Interface
{

    /**
     * Collect server environment data.
     *
     * @return array
     */
    public static function collect(): array
    {
        $data = array(
            'software'                  => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown',
            'os'                        => php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m'),
            'architecture'              => PHP_INT_SIZE === 8 ? '64-bit' : '32-bit',
            'hostname'                  => php_uname('n'),
            'document_root'             => isset($_SERVER['DOCUMENT_ROOT']) ? sanitize_text_field(wp_unslash($_SERVER['DOCUMENT_ROOT'])) : 'Unknown',
            'max_upload_size'           => size_format(wp_max_upload_size()),
            'max_post_size'             => ini_get('post_max_size'),
            'max_input_vars'            => (int) ini_get('max_input_vars'),
            'max_execution_time'        => (int) ini_get('max_execution_time'),
            'default_timezone'          => date_default_timezone_get(),
            'curl_version'              => function_exists('curl_version') ? curl_version()['version'] : 'Not available',
            'curl_ssl_version'          => function_exists('curl_version') ? curl_version()['ssl_version'] : 'Not available',
            'fsockopen_or_curl_enabled' => function_exists('fsockopen') || function_exists('curl_init'),
            'soapclient_enabled'        => class_exists('SoapClient'),
            'domdocument_enabled'       => class_exists('DOMDocument'),
            'gzip_enabled'              => is_callable('gzopen'),
            'mbstring_enabled'          => extension_loaded('mbstring'),
        );

        $data['remote_post'] = self::test_remote_post();
        $data['remote_get']  = self::test_remote_get();

        return $data;
    }

    /**
     * Test remote POST to WordPress.org.
     *
     * @return array
     */
    private static function test_remote_post(): array
    {
        $response = wp_safe_remote_post(
            'https://api.wordpress.org/core/version-check/1.7/',
            array(
                'timeout' => 10,
                'body'    => array(
                    'version' => get_bloginfo('version'),
                ),
            )
        );

        if (is_wp_error($response)) {
            return array(
                'successful' => false,
                'error'      => $response->get_error_message(),
            );
        }

        return array(
            'successful'    => true,
            'response_code' => wp_remote_retrieve_response_code($response),
        );
    }

    /**
     * Test remote GET to WordPress.org.
     *
     * @return array
     */
    private static function test_remote_get(): array
    {
        $response = wp_safe_remote_get(
            'https://api.wordpress.org/core/version-check/1.7/',
            array('timeout' => 10)
        );

        if (is_wp_error($response)) {
            return array(
                'successful' => false,
                'error'      => $response->get_error_message(),
            );
        }

        return array(
            'successful'    => true,
            'response_code' => wp_remote_retrieve_response_code($response),
        );
    }
}
