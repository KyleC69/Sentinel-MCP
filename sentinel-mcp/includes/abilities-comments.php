<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Comments\List_Comments_Ability;
use SentinelMCP\Abilities\Comments\Manage_Comment_Ability;

/**
 * Comment management abilities.
 *
 * Allows Claude/Cowork to list, search, moderate,
 * and reply to comments on any site post.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Category: Comments.
 */

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-comments',
			array(
				'label'       => __('Comment Management', 'mcp-sentinel'),
				'description' => __('List, search, moderate, and reply to comments across all post types.', 'mcp-sentinel'),
			)
		);
	}
);

/**
 * Abilities.
 */

Registry::register(new List_Comments_Ability());
Registry::register(new Manage_Comment_Ability());
Registry::init();
