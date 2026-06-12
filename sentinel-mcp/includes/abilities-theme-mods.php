<?php

/**
 * Theme Mods Abilities.
 *
 * Read theme modifications (custom_logo, colors, etc.)
 * for the active WordPress theme.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 */

defined('ABSPATH') || exit;

add_action(
	'wp_abilities_api_init',
	function () {
		/*
		 * GET THEME MOD
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/get-theme-mod',
			array(
				'label'               => 'Read theme modification',
				'category'            => 'sentinel-system',
				'description'         => 'All parameters optional. '
					. 'Reads theme modifications (theme_mods) for the active theme. '
					. 'Pass a specific key to get one value, or omit to get all theme_mods. '
					. 'Common keys: custom_logo (attachment ID), background_color, header_textcolor, '
					. 'header_image, nav_menu_locations. Keys are theme-specific.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'key' => array(
							'type'        => 'string',
							'description' => 'Theme mod key to read. Omit to return all theme mods. '
								. 'Alias: name is also accepted.',
						),
					),
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ($input) {
					$input = is_array($input) ? $input : array();
					$key   = sanitize_text_field($input['key'] ?? $input['name'] ?? '');

					if (! empty($key)) {
						$value = get_theme_mod($key, '__SENTINEL_NOT_SET__');
						if ('__SENTINEL_NOT_SET__' === $value) {
							return array(
								'success' => false,
								'message' => sprintf('Theme mod "%s" is not set.', $key),
							);
						}

						return array(
							'success' => true,
							'theme'   => get_stylesheet(),
							'mods'    => array($key => $value),
						);
					}

					// Return all theme mods.
					$mods = get_theme_mods();
					if (! is_array($mods)) {
						$mods = array();
					}

					// Remove internal keys.
					unset($mods[0]);

					return array(
						'success' => true,
						'theme'   => get_stylesheet(),
						'count'   => count($mods),
						'mods'    => $mods,
					);
				},

				'permission_callback' => function () {
					return current_user_can('edit_theme_options');
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
