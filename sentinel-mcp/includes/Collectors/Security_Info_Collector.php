<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Security settings collector.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class Security_Info_Collector implements System_Info_Collector_Interface
{

    /**
     * Collect security settings data.
     *
     * @return array
     */
    public static function collect(): array
    {
        return array(
            'secure_connection'     => is_ssl(),
            'hide_errors'           => ! (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY),
            'file_editing_disabled' => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT,
            'file_mods_disabled'    => defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS,
            'force_ssl_admin'       => defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN,
            'auth_keys_defined'     => defined('SECURE_AUTH_KEY') && '' !== SECURE_AUTH_KEY
                && defined('AUTH_KEY') && '' !== AUTH_KEY
                && defined('LOGGED_IN_KEY') && '' !== LOGGED_IN_KEY
                && defined('NONCE_KEY') && '' !== NONCE_KEY,
        );
    }
}
