<?php
/**
 * WordPress 7.0 Connectors API — register Ollama Cloud and OpenRouter as AI provider connectors.
 *
 * This file is safe to load on WordPress < 7.0: the wp_connectors_init action
 * simply never fires, and all Connectors API functions are guarded with
 * function_exists() checks to prevent fatal errors.
 *
 * @package    SENTINEL
 * @author     Jose Conti <j.conti@joseconti.com>
 * @copyright  2026 Jose Conti
 * @license    GPL-2.0-or-later
 * @since      1.1.0
 *
 * @see https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register custom AI provider connectors with the WordPress Connectors API.
 *
 * The wp_connectors_init action is only available in WordPress 7.0+.
 * On older versions this callback is never executed.
 */
add_action(
	'wp_connectors_init',
	function ( $registry ) {

		// Double-check: the Connectors API functions must exist (WordPress 7.0+).
		if ( ! function_exists( 'wp_is_connector_registered' ) ) {
			return;
		}

		// ── Ollama Cloud ────────────────────────────────────────────
		if ( ! wp_is_connector_registered( 'ollama' ) ) {
			$registry->register(
				'ollama',
				array(
					'name'           => __( 'Ollama Cloud', 'mcp-sentinel' ),
					'description'    => __( 'Open-source AI models via Ollama Cloud. Includes free models (Qwen, Ministral, Nemotron, Gemma) and premium models with an OpenAI-compatible API.', 'mcp-sentinel' ),
					'logo_url'       => SENTINEL_URL . 'assets/images/ollama-black.webp',
					'type'           => 'ai_provider',
					'authentication' => array(
						'method'               => 'api_key',
						'credentials_location' => 'database',
						'setting_name'         => 'connectors_ai_ollama_api_key',
					),
					'plugin'         => 'mcp-sentinel',
				)
			);
		}

		// ── OpenRouter ──────────────────────────────────────────────
		if ( ! wp_is_connector_registered( 'openrouter' ) ) {
			$registry->register(
				'openrouter',
				array(
					'name'           => __( 'OpenRouter', 'mcp-sentinel' ),
					'description'    => __( 'Access AI models from multiple providers (Anthropic, OpenAI, Google, Meta) through a single unified API.', 'mcp-sentinel' ),
					'logo_url'       => SENTINEL_URL . 'assets/images/openrouter-black.webp',
					'type'           => 'ai_provider',
					'authentication' => array(
						'method'               => 'api_key',
						'credentials_location' => 'database',
						'setting_name'         => 'connectors_ai_openrouter_api_key',
					),
					'plugin'         => 'mcp-sentinel',
				)
			);
		}
	}
);
