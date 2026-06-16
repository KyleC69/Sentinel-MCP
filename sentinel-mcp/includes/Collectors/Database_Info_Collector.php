<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Database environment collector.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class Database_Info_Collector implements System_Info_Collector_Interface
{

    /**
     * Collect database environment data.
     *
     * @return array
     */
    public static function collect(): array
    {
        global $wpdb;

        $server_info = $wpdb->db_server_info();
        $is_mariadb  = stripos($server_info, 'mariadb') !== false;

        // Total tables and DB size.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL command, cannot be prepared or cached.
        $tables       = $wpdb->get_results('SHOW TABLE STATUS', ARRAY_A);
        $total_size   = 0;
        $total_tables = 0;
        $table_list   = array();
        if (is_array($tables)) {
            $total_tables = count($tables);
            foreach ($tables as $table) {
                $data_len    = (int) ($table['Data_length'] ?? 0);
                $index_len   = (int) ($table['Index_length'] ?? 0);
                $total_size += $data_len + $index_len;

                $table_list[$table['Name']] = array(
                    'data'   => round($data_len / 1048576, 2),
                    'index'  => round($index_len / 1048576, 2),
                    'rows'   => (int) ($table['Rows'] ?? 0),
                    'engine' => $table['Engine'] ?? 'Unknown',
                );
            }
        }

        // Autoloaded options size (WP 6.6+ changed 'yes' to 'on'/'auto-on').
        $autoload_values = function_exists('wp_autoload_values_to_autoload')
            ? wp_autoload_values_to_autoload()
            : array('yes', 'on', 'auto-on');
        $al_placeholders = implode(',', array_fill(0, count($autoload_values), '%s'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate query, no WP API equivalent.
        $autoloaded_size = (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN() placeholders, all values passed through prepare().
                "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ({$al_placeholders})",
                ...$autoload_values
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate query, no WP API equivalent.
        $autoloaded_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN() placeholders, all values passed through prepare().
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload IN ({$al_placeholders})",
                ...$autoload_values
            )
        );

        $result = array(
            'type'                     => $is_mariadb ? 'MariaDB' : 'MySQL',
            'version'                  => $wpdb->db_version(),
            'server_info'              => $server_info,
            'charset'                  => $wpdb->charset,
            'collation'                => $wpdb->collate,
            'prefix'                   => $wpdb->prefix,
            'total_tables'             => $total_tables,
            'total_db_size'            => size_format($total_size),
            'total_db_size_bytes'      => $total_size,
            'autoloaded_options_size'  => size_format($autoloaded_size),
            'autoloaded_options_count' => $autoloaded_count,
            'autoloaded_warning'       => $autoloaded_size > 1048576,
            'database_tables'          => $table_list,
        );

        // WooCommerce database version if available.
        if (class_exists('WooCommerce')) {
            $result['wc_database_version'] = get_option('woocommerce_db_version', '');
        }

        return $result;
    }
}
