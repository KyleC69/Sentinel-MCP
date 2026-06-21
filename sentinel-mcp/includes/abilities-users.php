<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Users\List_Users_Ability;
use SentinelMCP\Abilities\Users\Read_User_Ability;
use SentinelMCP\Abilities\Users\List_User_Meta_Keys_Ability;

/**
 * User Management Abilities.
 *
 * List and read WordPress users.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/*
 * Category
 * ─────────────────────────────────────────
 */

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-users',
			[
				'label'       => __('User Management', 'mcp-sentinel'),
				'description' => __('List and read WordPress users.', 'mcp-sentinel'),
			]
		);
	}
);

/*
 * Abilities
 * ─────────────────────────────────────────
 */

Registry::register(new List_Users_Ability());
Registry::register(new Read_User_Ability());
Registry::register(new List_User_Meta_Keys_Ability());
Registry::init();
