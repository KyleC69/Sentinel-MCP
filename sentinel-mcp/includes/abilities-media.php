<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Media\List_Media_Ability;
use SentinelMCP\Abilities\Media\Upload_Media_Ability;
use SentinelMCP\Abilities\Media\Set_Featured_Image_Ability;
use SentinelMCP\Abilities\Media\Delete_Media_Ability;

/**
 * Media Library Abilities.
 *
 * Upload, list, manage, and assign media files in the
 * WordPress media library via MCP.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
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
			'sentinel-media',
			array(
				'label'       => __('Media Library', 'mcp-sentinel'),
				'description' => __('Upload, list, manage, and assign media files in the WordPress media library.', 'mcp-sentinel'),
			)
		);
	}
);

/*
 * Abilities
 * ─────────────────────────────────────────
 */

Registry::register(new List_Media_Ability());
Registry::register(new Upload_Media_Ability());
Registry::register(new Set_Featured_Image_Ability());
Registry::register(new Delete_Media_Ability());
Registry::init();
