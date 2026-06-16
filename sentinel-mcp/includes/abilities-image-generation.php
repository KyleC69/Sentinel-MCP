<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Images\Generate_Image_Ability;
use SentinelMCP\Abilities\Images\Set_Featured_From_Prompt_Ability;

/**
 * AI image generation abilities (Lite minimal).
 *
 * Two read/write abilities backed by Image_Generator:
 *   - sentinel/generate-image
 *   - sentinel/set-featured-from-prompt
 *
 * Heavier features (Imagen API, multiple aspect ratios, 2K/4K, image edit
 * with prompts, safety controls) are reserved for the Premium edition.
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
			'sentinel-images',
			array(
				'label'       => __('AI Image Generation', 'mcp-sentinel'),
				'description' => __('Generate images via Google Gemini and save them to the Media Library. Multiple aspect ratios, sizes and image editing are Premium.', 'mcp-sentinel'),
			)
		);
	}
);

Registry::register(new Generate_Image_Ability());
Registry::register(new Set_Featured_From_Prompt_Ability());
Registry::init();

/*
 * MCP annotations summary for this file:
 *
 *   generate-image              writes (creates attachments), openWorldHint=true (calls Gemini API).
 *   set-featured-from-prompt    writes (creates attachment + sets thumbnail), openWorldHint=true.
 */
