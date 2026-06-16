<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Plugins environment collector.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class Plugins_Info_Collector implements System_Info_Collector_Interface
{

    /**
     * Collect plugins data.
     *
     * @return array
     */
    public static function collect(): array
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $mu_plugins     = get_mu_plugins();
        $updates        = get_site_transient('update_plugins');

        $active_list   = array();
        $inactive_list = array();

        foreach ($all_plugins as $plugin_path => $p) {
            $info = array(
                'name'              => $p['Name'],
                'version'           => $p['Version'],
                'author'            => wp_strip_all_tags($p['Author']),
                'author_url'        => $p['AuthorURI'] ?? '',
                'plugin_url'        => $p['PluginURI'] ?? '',
                'network_activated' => is_multisite() && is_plugin_active_for_network($plugin_path),
            );

            // Check for available updates.
            if (isset($updates->response[$plugin_path])) {
                $info['version_latest']   = $updates->response[$plugin_path]->new_version;
                $info['update_available'] = true;
            } else {
                $info['version_latest']   = $p['Version'];
                $info['update_available'] = false;
            }

            if (in_array($plugin_path, $active_plugins, true)) {
                $active_list[] = $info;
            } else {
                $inactive_list[] = $info;
            }
        }

        $mu_list = array();
        foreach ($mu_plugins as $path => $p) {
            $mu_list[] = array(
                'name'    => $p['Name'],
                'version' => $p['Version'],
                'author'  => wp_strip_all_tags($p['Author'] ?? ''),
            );
        }

        // WordPress dropins.
        $dropins      = get_dropins();
        $dropin_list  = array();
        $dropin_descs = _get_dropins();
        foreach ($dropins as $file => $p) {
            $dropin_list[] = array(
                'file'        => $file,
                'name'        => ! empty($p['Name']) ? $p['Name'] : $file,
                'description' => isset($dropin_descs[$file]) ? $dropin_descs[$file][0] : '',
            );
        }

        return array(
            'active_count'     => count($active_list),
            'inactive_count'   => count($inactive_list),
            'must_use_count'   => count($mu_list),
            'dropin_count'     => count($dropin_list),
            'active_plugins'   => $active_list,
            'inactive_plugins' => $inactive_list,
            'mu_plugins'       => $mu_list,
            'dropins'          => $dropin_list,
        );
    }
}
