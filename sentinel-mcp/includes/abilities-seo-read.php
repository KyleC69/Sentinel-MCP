<?php
/**
 * SEO read ability (Sprint 1.7).
 *
 * Single read-only ability that returns unified SEO meta for a given post,
 * regardless of which SEO plugin is active. Detection and reading are
 * delegated to SENTINEL_SEO_Adapter.
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
			'sentinel-seo',
			array(
				'label'       => __( 'SEO (read-only)', 'mcp-sentinel' ),
				'description' => __( 'Read SEO meta from any active SEO plugin (Yoast, Rank Math, AIOSEO, The SEO Framework, SureRank, SEOPress, Slim SEO, Squirrly). Bulk write SEO is Premium.', 'mcp-sentinel' ),
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	function () {

		wp_register_ability(
			'sentinel/seo-read-meta',
			array(
				'label'               => 'Read SEO meta for a post',
				'category'            => 'sentinel-seo',
				'description'         => 'Read-only. Returns the SEO metadata of a post (title, description, focus keyword, canonical, robots_noindex) detected from the active SEO plugin. Supports Yoast, Rank Math, AIOSEO, The SEO Framework, SureRank, SEOPress, Slim SEO and Squirrly. If multiple SEO plugins are active, returns one entry per plugin so the caller can compare. Bulk SEO rewriting is reserved for the Premium edition.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'required'             => array( 'post_id' ),
					'properties'           => array(
						'post_id' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post whose SEO meta to read.',
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

					$detected = SENTINEL_SEO_Adapter::detect_active_plugins();
					$entries  = SENTINEL_SEO_Adapter::read_for_post( $post_id );

					$response = array(
						'post_id'          => $post_id,
						'detected_plugins' => $detected,
						'entries'          => $entries,
					);

					if ( ! empty( $entries ) ) {
						$hint = SENTINEL_Premium_Hints::maybe_hint(
							'seo-write',
							'seo-bulk-write',
							__( 'To rewrite SEO meta in bulk across many posts (Yoast, Rank Math, AIOSEO and others), see Premium.', 'mcp-sentinel' )
						);
						if ( null !== $hint ) {
							$response['_premium_hint'] = $hint;
						}
					}

					return $response;
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
	}
);

/*
 * MCP annotations summary for this file:
 *
 *   seo-read-meta  readOnly idempotent
 */
