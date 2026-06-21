<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Recovery\Site_Health_Ability;
use SentinelMCP\Abilities\Recovery\List_Plugins_Ability;
use SentinelMCP\Abilities\Recovery\Toggle_Plugin_Ability;
use SentinelMCP\Abilities\Recovery\Clear_Recovery_Ability;

/**
 * Recovery & Site Management Abilities.
 *
 * Provides abilities for site diagnostics, plugin management,
 * debug toggle, and recovery via the WordPress Abilities API.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Category: Recovery.
 */

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-recovery',
			[
				'label'       => __('Recovery and Maintenance', 'mcp-sentinel'),
				'description' => __('Site diagnostics, plugin management, and recovery tools.', 'mcp-sentinel'),
			]
		);
	}
);

Registry::register(new Site_Health_Ability());
Registry::register(new List_Plugins_Ability());
Registry::register(new Toggle_Plugin_Ability());
Registry::register(new Clear_Recovery_Ability());
Registry::init();
