<?php

namespace SentinelMCP;

/**
 * I18n read abilities (Sprint 4.1).
 *
 * Four read-only abilities that work transparently across Polylang, WPML and
 * TranslatePress. Translation creation/sync are reserved for the Premium edition.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-i18n',
			array(
				'label'       => __( 'Multilingual (read-only)', 'mcp-sentinel' ),
				'description' => __( 'Read languages, post translations and string translations from Polylang, WPML or TranslatePress. Writing translations is Premium.', 'mcp-sentinel' ),
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	function () {

		// L1. List languages.

		wp_register_ability(
			'sentinel/i18n-list-languages',
			array(
				'label'               => 'List site languages',
				'category'            => 'sentinel-i18n',
				'description'         => 'Read-only. Returns the languages active on the site (code, name, locale, flag URL, default flag) using whichever multilingual plugin is detected (Polylang, WPML, TranslatePress). Returns an empty list with a "no_plugin" hint if no multilingual plugin is active.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function () {
					$adapter = SENTINEL_I18n_Adapter::active();
					if ( '' === $adapter ) {
						return array(
							'plugin'    => '',
							'languages' => array(),
							'note'      => 'no_plugin: no multilingual plugin detected (Polylang, WPML, TranslatePress).',
						);
					}
					return array(
						'plugin'    => $adapter::slug(),
						'languages' => $adapter::list_languages(),
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'read' );
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

		// L2. List translations for a post.

		wp_register_ability(
			'sentinel/i18n-list-translations-for-post',
			array(
				'label'               => 'List translations of a post',
				'category'            => 'sentinel-i18n',
				'description'         => 'Read-only. For a given post_id, returns the translation map across languages: each entry has language code, translated post_id, status and title. Adapters that translate inline (TranslatePress) return a single ID per language with a note.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'required'             => array( 'post_id' ),
					'properties'           => array(
						'post_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input ) {
					$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
					if ( ! $post_id || ! get_post( $post_id ) ) {
						return array(
							'success' => false,
							'message' => 'Post not found.',
						);
					}
					$adapter = SENTINEL_I18n_Adapter::active();
					if ( '' === $adapter ) {
						return array(
							'plugin'       => '',
							'translations' => array(),
							'note'         => 'no_plugin: no multilingual plugin detected.',
						);
					}
					return array(
						'plugin'       => $adapter::slug(),
						'post_id'      => $post_id,
						'translations' => $adapter::list_translations_for_post( $post_id ),
					);
				},

				'permission_callback' => function ( $input ) {
					$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
					return $post_id ? current_user_can( 'read_post', $post_id ) : false;
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

		// L3. Get post in another language.

		wp_register_ability(
			'sentinel/i18n-get-post-in-language',
			array(
				'label'               => 'Get post in another language',
				'category'            => 'sentinel-i18n',
				'description'         => 'Read-only. Resolves the post ID in the target language and returns its full content (shortcut to read-content for the translated ID). Returns null if no translation exists. Alias: lang is also accepted instead of language.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'required'             => array( 'post_id' ),
					'properties'           => array(
						'post_id'  => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'language' => array(
							'type'        => 'string',
							'description' => 'Target language code (e.g. "es", "en", "fr"). Required (or pass "lang" as alias).',
						),
						'lang'     => array(
							'type'        => 'string',
							'description' => 'Alias for language.',
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input ) {
					$post_id      = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
					$language_raw = $input['language'] ?? $input['lang'] ?? '';
					$language     = sanitize_key( (string) $language_raw );

					if ( ! $post_id || ! get_post( $post_id ) ) {
						return array(
							'success' => false,
							'message' => 'Post not found.',
						);
					}
					if ( '' === $language ) {
						return array(
							'success' => false,
							'message' => 'Language code is required.',
						);
					}

					$adapter = SENTINEL_I18n_Adapter::active();
					if ( '' === $adapter ) {
						return array(
							'success' => false,
							'message' => 'no_plugin: no multilingual plugin detected.',
						);
					}

					$translated_id = $adapter::get_post_in_language( $post_id, $language );
					if ( null === $translated_id ) {
						return array(
							'success' => false,
							'plugin'  => $adapter::slug(),
							'message' => 'No translation found for that language.',
						);
					}

					$post = get_post( $translated_id );
					return array(
						'success'  => true,
						'plugin'   => $adapter::slug(),
						'language' => $language,
						'post_id'  => $translated_id,
						'title'    => $post ? (string) $post->post_title : '',
						'status'   => $post ? (string) $post->post_status : '',
						'content'  => $post ? (string) $post->post_content : '',
						'permalink' => $post ? (string) get_permalink( $post ) : '',
					);
				},

				'permission_callback' => function ( $input ) {
					$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
					return $post_id ? current_user_can( 'read_post', $post_id ) : false;
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

		// L4. List string translations.

		wp_register_ability(
			'sentinel/i18n-list-string-translations',
			array(
				'label'               => 'List string translations',
				'category'            => 'sentinel-i18n',
				'description'         => 'Read-only. Lists translated theme/plugin strings, when the active multilingual plugin exposes them (WPML icl_strings, TranslatePress dictionary). Polylang has no public enumeration API and returns a partial hint.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 50,
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input = null ) {
					$page     = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
					$per_page = isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 50;

					$adapter = SENTINEL_I18n_Adapter::active();
					if ( '' === $adapter ) {
						return array(
							'plugin' => '',
							'items'  => array(),
							'note'   => 'no_plugin: no multilingual plugin detected.',
						);
					}
					$result = $adapter::list_string_translations( $page, $per_page );
					$result['plugin'] = $adapter::slug();
					return $result;
				},

				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
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

/*
 * MCP annotations summary for this file:
 *
 *   i18n-list-languages              readOnly idempotent
 *   i18n-list-translations-for-post  readOnly idempotent
 *   i18n-get-post-in-language        readOnly idempotent
 *   i18n-list-string-translations    readOnly idempotent
 */
