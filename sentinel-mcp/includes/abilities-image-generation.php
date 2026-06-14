<?php

namespace SentinelMCP;

/**
 * AI image generation abilities (Lite minimal).
 *
 * Two read/write abilities backed by SENTINEL_Image_Generator:
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

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-images',
			array(
				'label'       => __( 'AI Image Generation', 'mcp-sentinel' ),
				'description' => __( 'Generate images via Google Gemini and save them to the Media Library. Multiple aspect ratios, sizes and image editing are Premium.', 'mcp-sentinel' ),
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	function () {

		// 1. Generate one or more images and save to Media Library.

		wp_register_ability(
			'sentinel/generate-image',
			array(
				'label'               => 'Generate AI image(s)',
				'category'            => 'sentinel-images',
				'description'         => 'Generate 1 to 3 images for a prompt using Google Gemini and save each one to the Media Library. Returns attachment IDs and URLs. The output is square-ish PNG at the model native size; aspect ratio selection, 2K/4K, Imagen API and image editing are Premium. Optional: attach the generated image(s) to a specific post via attach_to_post.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'required'             => array( 'prompt' ),
					'properties'           => array(
						'prompt'         => array(
							'type'        => 'string',
							'minLength'   => 3,
							'maxLength'   => 2000,
							'description' => 'Description of the image to generate.',
						),
						'count'          => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 3,
							'default'     => 1,
							'description' => 'How many images to generate (1-3).',
						),
						'attach_to_post' => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'default'     => 0,
							'description' => 'Optional post ID to attach the new attachments to.',
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input ) {
					$prompt         = isset( $input['prompt'] ) ? sanitize_textarea_field( (string) $input['prompt'] ) : '';
					$count          = isset( $input['count'] ) ? (int) $input['count'] : 1;
					$attach_to_post = isset( $input['attach_to_post'] ) ? absint( $input['attach_to_post'] ) : 0;

					$result = SENTINEL_Image_Generator::generate( $prompt, $count, $attach_to_post );
					if ( ! $result['ok'] ) {
						return array(
							'success'       => false,
							'message'       => $result['message'] ?? 'Image generation failed.',
							'partial_items' => $result['items'] ?? array(),
						);
					}

					return array(
						'success' => true,
						'count'   => count( $result['items'] ),
						'items'   => $result['items'],
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'upload_files' );
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => false,
							'destructiveHint' => false,
							'idempotentHint'  => false,
							'openWorldHint'   => true,
						),
					),
				),
			)
		);

		// 2. Generate one image from a prompt and set it as featured of a post.

		wp_register_ability(
			'sentinel/set-featured-from-prompt',
			array(
				'label'               => 'Generate image and set as featured',
				'category'            => 'sentinel-images',
				'description'         => 'Shortcut: generate one image with Gemini for the given prompt, save to Media Library and assign it as the featured image of a target post. Returns the attachment_id, url and post_id.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'required'             => array( 'prompt', 'post_id' ),
					'properties'           => array(
						'prompt'  => array(
							'type'        => 'string',
							'minLength'   => 3,
							'maxLength'   => 2000,
							'description' => 'Description of the image to generate.',
						),
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
					$prompt  = isset( $input['prompt'] ) ? sanitize_textarea_field( (string) $input['prompt'] ) : '';
					$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

					if ( ! $post_id || ! get_post( $post_id ) ) {
						return array(
							'success' => false,
							'message' => 'Post not found.',
						);
					}

					$result = SENTINEL_Image_Generator::generate( $prompt, 1, $post_id );
					if ( ! $result['ok'] || empty( $result['items'][0]['attachment_id'] ) ) {
						return array(
							'success' => false,
							'message' => $result['message'] ?? 'Image generation failed.',
						);
					}

					$attachment_id = (int) $result['items'][0]['attachment_id'];
					$ok            = set_post_thumbnail( $post_id, $attachment_id );
					if ( ! $ok ) {
						return array(
							'success'       => false,
							'message'       => 'Image generated but failed to set as featured.',
							'attachment_id' => $attachment_id,
							'post_id'       => $post_id,
						);
					}

					return array(
						'success'       => true,
						'post_id'       => $post_id,
						'attachment_id' => $attachment_id,
						'url'           => (string) ( $result['items'][0]['url'] ?? '' ),
					);
				},

				'permission_callback' => function ( $input ) {
					$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
					if ( ! $post_id ) {
						return false;
					}
					return current_user_can( 'edit_post', $post_id ) && current_user_can( 'upload_files' );
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => false,
							'destructiveHint' => false,
							'idempotentHint'  => false,
							'openWorldHint'   => true,
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
 *   generate-image              writes (creates attachments), openWorldHint=true (calls Gemini API).
 *   set-featured-from-prompt    writes (creates attachment + sets thumbnail), openWorldHint=true.
 */
