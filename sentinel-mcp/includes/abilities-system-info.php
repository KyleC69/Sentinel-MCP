<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\System_Info\System_Info_Ability;

/**
 * System Information Ability.
 *
 * Provides comprehensive server diagnostics similar to
 * WooCommerce System Status, but works independently.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		\wp_register_ability_category(
			'sentinel-system',
			[
				'label'       => \__('System Information', 'mcp-sentinel'),
				'description' => \__('Server, PHP, database, and WordPress environment diagnostics.', 'mcp-sentinel'),
			]
		);
	}
);

Registry::register(new System_Info_Ability());
Registry::init();
