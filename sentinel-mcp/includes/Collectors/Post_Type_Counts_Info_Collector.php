<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Post type counts collector.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class Post_Type_Counts_Info_Collector implements System_Info_Collector_Interface
{

    /**
     * Collect post type counts.
     *
     * @return array
     */
    public static function collect(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate count grouped by type, no WP API equivalent.
        $results = $wpdb->get_results(
            "SELECT post_type, COUNT(*) AS count FROM {$wpdb->posts} GROUP BY post_type ORDER BY count DESC",
            ARRAY_A
        );

        $counts = array();
        foreach ($results as $row) {
            $counts[$row['post_type']] = (int) $row['count'];
        }

        return $counts;
    }
}
