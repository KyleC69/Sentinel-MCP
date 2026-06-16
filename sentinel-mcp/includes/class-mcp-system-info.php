<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * System Information for MCP Content Manager.
 *
 * Provides comprehensive server, PHP, database, and WordPress
 * diagnostics similar to WooCommerce System Status, but works
 * independently without requiring WooCommerce. When WooCommerce
 * is active, includes WC-specific diagnostics.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Provides comprehensive server, PHP, database, and WordPress diagnostics.
 */
class System_Info
{

	/**
	 * Registry mapping section keys to collector class names.
	 *
	 * @var array
	 */
	private const COLLECTORS = array(
		'wordpress'         => 'SentinelMCP\WordPress_Info_Collector',
		'server'            => 'SentinelMCP\Server_Info_Collector',
		'php'               => 'SentinelMCP\PHP_Info_Collector',
		'database'          => 'SentinelMCP\Database_Info_Collector',
		'theme'             => 'SentinelMCP\Theme_Info_Collector',
		'plugins'           => 'SentinelMCP\Plugins_Info_Collector',
		'security'          => 'SentinelMCP\Security_Info_Collector',
		'constants'         => 'SentinelMCP\Constants_Info_Collector',
		'woocommerce'       => 'SentinelMCP\WooCommerce_Info_Collector',
		'post_type_counts'  => 'SentinelMCP\Post_Type_Counts_Info_Collector',
		'logging'           => 'SentinelMCP\Logging_Info_Collector',
	);

	/**
	 * Get system information.
	 *
	 * @param array $input Ability input parameters.
	 * @return array
	 */
	public static function get_info(array $input): array
	{
		$all_sections = array_keys(self::COLLECTORS);
		$sections     = $input['sections'] ?? $all_sections;

		if (! is_array($sections)) {
			$sections = array($sections);
		}

		$result = array();

		foreach ($sections as $section) {
			$key = sanitize_key($section);
			if (isset(self::COLLECTORS[$key])) {
				$class = self::COLLECTORS[$key];
				if (is_a($class, System_Info_Collector_Interface::class, true)) {
					$result[$key] = $class::collect();
				}
			}
		}

		return $result;
	}
}
