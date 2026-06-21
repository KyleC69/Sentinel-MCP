<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\FSE\List_Blocks_Registered_Ability;
use SentinelMCP\Abilities\FSE\List_Block_Patterns_Ability;
use SentinelMCP\Abilities\FSE\List_FSE_Templates_Ability;

/**
 * Full Site Editing read abilities (Sprint 1.4).
 *
 * Read-only inspection of registered blocks, block patterns and FSE templates.
 * Returns metadata only (never the full template content) — full template
 * editing is reserved for the Premium edition.
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
			'sentinel-fse',
			[
				'label'       => __('Full Site Editing (read-only)', 'mcp-sentinel'),
				'description' => __('Read-only inspection of block patterns and FSE templates. Editing is Premium.', 'mcp-sentinel'),
			]
		);
	}
);

Registry::register(new List_Blocks_Registered_Ability());
Registry::register(new List_Block_Patterns_Ability());
Registry::register(new List_FSE_Templates_Ability());
Registry::init();

/*
 * MCP annotations summary for this file:
 *
 *   list-blocks-registered  readOnly idempotent
 *   list-block-patterns     readOnly idempotent
 *   list-fse-templates      readOnly idempotent
 */
