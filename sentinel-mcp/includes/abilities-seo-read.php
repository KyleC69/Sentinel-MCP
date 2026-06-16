<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\SEO\Read_SEO_Meta_Ability;

/**
 * SEO read ability (Sprint 1.7).
 *
 * Single read-only ability that returns unified SEO meta for a given post,
 * regardless of which SEO plugin is active. Detection and reading are
 * delegated to SEO_Adapter.
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
			'sentinel-seo',
			array(
				'label'       => __('SEO (read-only)', 'mcp-sentinel'),
				'description' => __('Read SEO meta from any active SEO plugin (Yoast, Rank Math, AIOSEO, The SEO Framework, SureRank, SEOPress, Slim SEO, Squirrly). Bulk write SEO is Premium.', 'mcp-sentinel'),
			)
		);
	}
);

Registry::register(new Read_SEO_Meta_Ability());
Registry::init();

/*
 * MCP annotations summary for this file:
 *
 *   seo-read-meta  readOnly idempotent
 */
