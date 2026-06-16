<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * WordPress constants collector.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class Constants_Info_Collector implements System_Info_Collector_Interface
{

    /**
     * Collect WordPress constants data.
     *
     * @return array
     */
    public static function collect(): array
    {
        $constants = array(
            'WP_DEBUG',
            'WP_DEBUG_LOG',
            'WP_DEBUG_DISPLAY',
            'SCRIPT_DEBUG',
            'WP_CACHE',
            'DISABLE_WP_CRON',
            'WP_CRON_LOCK_TIMEOUT',
            'AUTOSAVE_INTERVAL',
            'WP_POST_REVISIONS',
            'EMPTY_TRASH_DAYS',
            'WP_MEMORY_LIMIT',
            'WP_MAX_MEMORY_LIMIT',
            'DISALLOW_FILE_EDIT',
            'DISALLOW_FILE_MODS',
            'FORCE_SSL_ADMIN',
            'WP_AUTO_UPDATE_CORE',
            'CONCATENATE_SCRIPTS',
            'WP_CONTENT_DIR',
            'WP_PLUGIN_DIR',
            'WPMU_PLUGIN_DIR',
            'ABSPATH',
        );

        $result = array();
        foreach ($constants as $const) {
            $result[$const] = defined($const) ? constant($const) : 'Not defined';
        }

        return $result;
    }
}
