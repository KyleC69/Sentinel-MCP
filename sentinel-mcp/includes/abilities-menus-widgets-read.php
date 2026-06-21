<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Menus_Widgets\List_Nav_Menus_Ability;
use SentinelMCP\Abilities\Menus_Widgets\List_Widgets_Ability;
use SentinelMCP\Abilities\Menus_Widgets\List_Sidebars_Ability;

/**
 * Menus, widgets and sidebars read abilities (Sprint 1.5).
 *
 * Read-only inspection of classic nav menus, widget instances per sidebar,
 * and registered sidebars. Editing menus/widgets is reserved for Premium.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-menus-widgets',
			[
				'label'       => __('Menus, widgets and sidebars (read-only)', 'mcp-sentinel'),
				'description' => __('Read-only access to classic navigation menus, widget instances and registered sidebars.', 'mcp-sentinel'),
			]
		);
	}
);

Registry::register(new List_Nav_Menus_Ability());
Registry::register(new List_Widgets_Ability());
Registry::register(new List_Sidebars_Ability());
Registry::init();

/*
 * MCP annotations summary for this file:
 *
 *   list-nav-menus    readOnly idempotent
 *   list-widgets      readOnly idempotent
 *   list-sidebars     readOnly idempotent
 */
