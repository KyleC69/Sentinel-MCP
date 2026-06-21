<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Contract for system-info collector classes.
 *
 * Each collector gathers a single diagnostic domain
 * (e.g. WordPress, server, PHP, database).
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

interface System_Info_Collector_Interface
{

    /**
     * Collect and return diagnostic data.
     *
     * @return array
     */
    public static function collect(): array;
}
