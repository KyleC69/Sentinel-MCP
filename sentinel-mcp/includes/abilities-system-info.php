<?php

namespace SentinelMCP;

/**
 * System Information Ability.
 *
 * Provides comprehensive server diagnostics similar to
 * WooCommerce System Status, but works independently.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 */

defined('ABSPATH') || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-system',
			array(
				'label'       => __('System Information', 'mcp-sentinel'),
				'description' => __('Server, PHP, database, and WordPress environment diagnostics.', 'mcp-sentinel'),
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	function () {

		wp_register_ability(
			'sentinel/system-info',
			array(
				'label'               => 'System information',
				'category'            => 'sentinel-system',
				'description'         => 'All parameters optional. Returns comprehensive server environment information equivalent to WooCommerce System Status. '
					. 'Works with or without WooCommerce. Includes: WordPress version and config, PHP version '
					. 'and extensions, database details with per-table sizes, web server with remote connectivity '
					. 'tests, active/inactive plugins with update status, theme with WC template overrides, '
					. 'security configuration, WordPress constants, WooCommerce store settings (currency, HPOS, '
					. 'gateways, pages, features), post type counts, and logging status. '
					. 'Ideal for diagnosing issues, checking compatibility, and recommending improvements.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'sections' => array(
							'type'        => 'array',
							'description' => 'Sections to include. All by default. The "woocommerce" section only returns data when WooCommerce is active.',
							'items'       => array(
								'type' => 'string',
								'enum' => array('wordpress', 'server', 'php', 'database', 'theme', 'plugins', 'security', 'constants', 'woocommerce', 'post_type_counts', 'logging'),
							),
							'default'     => array('wordpress', 'server', 'php', 'database', 'theme', 'plugins', 'security', 'constants', 'woocommerce', 'post_type_counts', 'logging'),
						),
					),
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ($input) {
					return SENTINEL_System_Info::get_info($input);
				},

				'permission_callback' => function () {
					return current_user_can('manage_options');
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => true,
							'destructiveHint' => false,
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);
	}
);
