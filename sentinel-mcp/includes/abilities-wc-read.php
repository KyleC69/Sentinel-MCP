<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\WooCommerce\WC_Get_Store_Info_Ability;
use SentinelMCP\Abilities\WooCommerce\WC_List_Products_Ability;
use SentinelMCP\Abilities\WooCommerce\WC_List_Recent_Orders_Ability;
use SentinelMCP\Abilities\WooCommerce\WC_List_Coupons_Ability;

/**
 * WooCommerce read-only abilities (Sprint 1.6).
 *
 * Conditional on WooCommerce being active. Loaded only via the conditional
 * require_once in mcp-sentinel.php — but we still guard at the
 * top of the file with class_exists() so the file is harmless if loaded
 * directly. Editing WooCommerce data is reserved for the Premium edition.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

if (! function_exists('SENTINEL_wc_redact_name')) {
	/**
	 * Redact a person name to initials: "Kyle L Crowder" → "J. C.".
	 *
	 * @param string $name Full name.
	 * @return string
	 */
	function SENTINEL_wc_redact_name(string $name): string
	{
		$parts = preg_split('/\s+/', trim($name));
		if (! is_array($parts)) {
			return '';
		}
		$initials = [];
		foreach ($parts as $part) {
			if ('' === $part) {
				continue;
			}
			$initials[] = mb_strtoupper(mb_substr($part, 0, 1)) . '.';
		}
		return implode(' ', $initials);
	}
}

add_action(
	'wp_abilities_api_categories_init',
	function () {
		if (! class_exists('WooCommerce')) {
			return;
		}
		wp_register_ability_category(
			'sentinel-wc-read',
			[
				'label'       => __('WooCommerce (read-only)', 'mcp-sentinel'),
				'description' => __('Read-only access to WooCommerce store info, products, recent orders and coupons. Editing WooCommerce data is Premium.', 'mcp-sentinel'),
			]
		);
	}
);

add_action(
	'wp_abilities_api_init',
	function () {
		if (! class_exists('WooCommerce')) {
			return;
		}

		Registry::register(new WC_Get_Store_Info_Ability());
		Registry::register(new WC_List_Products_Ability());
		Registry::register(new WC_List_Recent_Orders_Ability());
		Registry::register(new WC_List_Coupons_Ability());
		Registry::init();
	}
);

/*
 * MCP annotations summary for this file:
 *
 *   wc-get-store-info       readOnly idempotent
 *   wc-list-products        readOnly idempotent
 *   wc-list-recent-orders   readOnly idempotent (data redacted)
 *   wc-list-coupons         readOnly idempotent
 */
