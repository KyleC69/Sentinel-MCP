<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Discovery\Site_Schema_Ability;
use SentinelMCP\Abilities\Discovery\Inspect_Post_Type_Ability;
use SentinelMCP\Abilities\Discovery\List_Terms_Ability;

/**
 * Discovery abilities.
 *
 * Allow Claude/Cowork to explore the complete structure
 * of the WordPress site dynamically:
 *  - List all CPTs with detail
 *  - Inspect a specific CPT (taxonomies, meta, etc.)
 *  - View site summary
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Register ability categories.
 */

add_action(
	'wp_abilities_api_categories_init',
	function () {

		wp_register_ability_category(
			'sentinel-discovery',
			[
				'label'       => __('Content Discovery', 'mcp-sentinel'),
				'description' => __('Explore site structure: post types, taxonomies, custom fields, and terms.', 'mcp-sentinel'),
			]
		);

		wp_register_ability_category(
			'sentinel-content',
			[
				'label'       => __('Content Management', 'mcp-sentinel'),
				'description' => __('Create, read, update, delete, and search content across all post types.', 'mcp-sentinel'),
			]
		);
	}
);

Registry::register(new Site_Schema_Ability());
Registry::register(new Inspect_Post_Type_Ability());
Registry::register(new List_Terms_Ability());
Registry::init();
