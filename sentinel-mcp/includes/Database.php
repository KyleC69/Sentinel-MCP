<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Thin wrapper around WordPress $wpdb for testability.
 *
 * Encapsulates direct $wpdb access so that unit tests can mock
 * database operations without relying on the global.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class Database
{

    /**
     * Get the global $wpdb instance.
     *
     * @return \wpdb
     */
    public static function wpdb(): \wpdb
    {
        global $wpdb;
        return $wpdb;
    }

    /**
     * Retrieve distinct meta keys from the latest posts of a given post type.
     *
     * @param string $post_type Post type slug.
     * @return array<string> List of meta key names.
     */
    public static function get_sample_meta_keys(string $post_type): array
    {
        $wpdb = self::wpdb();

        $like_underscore = $wpdb->esc_like('_') . '%';
        $like_field      = $wpdb->esc_like('field_') . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Meta key discovery query, results vary per post type.
        $keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.meta_key
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE p.post_type = %s
				   AND pm.meta_key NOT LIKE %s
				   AND pm.meta_key NOT LIKE %s
				 ORDER BY pm.meta_key
				 LIMIT 50",
                $post_type,
                $like_underscore,
                $like_field
            )
        );

        return $keys ?: [];
    }

    /**
     * Retrieve all option names from the options table.
     *
     * @return array<string> List of option names.
     */
    public static function get_all_option_names(): array
    {
        $wpdb = self::wpdb();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Full option list required for discovery.
        return $wpdb->get_col("SELECT option_name FROM {$wpdb->options}");
    }
}
