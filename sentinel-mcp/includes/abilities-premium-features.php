<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Premium\List_Premium_Features_Ability;

/**
 * Premium features discovery ability (stub).
 *
 * The Lite edition does not expose a premium upsell ability. This file is kept
 * as a placeholder so the ability registration and class reference remain
 * valid if a future edition needs to restore premium discovery.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

// Premium catalog helpers intentionally removed per architecture review (YAGNI).
// TODO: Restore catalog loading/filtering if premium edition returns.

/**
 * Register the discovery category for the Premium-features ability.
 */
add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-premium-info',
			array(
				'label'       => __('Premium Information', 'mcp-sentinel'),
				'description' => __('Read-only catalog of capabilities available in the Premium edition. Used only when the user asks what else can be done.', 'mcp-sentinel'),
			)
		);
	}
);

Registry::register(new List_Premium_Features_Ability());
Registry::init();
