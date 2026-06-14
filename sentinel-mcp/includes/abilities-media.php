<?php

namespace SentinelMCP;

/**
 * Media Library Abilities.
 *
 * Upload, list, manage, and assign media files in the
 * WordPress media library via MCP.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined( 'ABSPATH' ) || exit;

/*
 * Category
 * ─────────────────────────────────────────
 */

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-media',
			array(
				'label'       => __( 'Media Library', 'mcp-sentinel' ),
				'description' => __( 'Upload, list, manage, and assign media files in the WordPress media library.', 'mcp-sentinel' ),
			)
		);
	}
);

/*
 * Abilities
 * ─────────────────────────────────────────
 */

add_action(
	'wp_abilities_api_init',
	function () {
		/*
		 * LIST MEDIA
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/list-media',
			array(
				'label'               => 'List media attachments',
				'category'            => 'sentinel-media',
				'description'         => 'All parameters optional. '
								. 'Lists media items from the WordPress Media Library with filters for MIME type, '
								. 'date range, and search. Returns ID, title, URL, dimensions, alt text, and thumbnail. '
								. 'Use this to find images before assigning them as featured images.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'search'      => array(
							'type'        => 'string',
							'description' => 'Search in attachment title and description.',
						),
						'mime_type'   => array(
							'type'        => 'string',
							'description' => 'Filter by MIME type prefix. E.g.: "image", "video", "audio", "application/pdf", or "any" for all.',
							'default'     => 'any',
						),
						'date_after'  => array(
							'type'        => 'string',
							'description' => 'Only media uploaded after this date (YYYY-MM-DD).',
						),
						'date_before' => array(
							'type'        => 'string',
							'description' => 'Only media uploaded before this date (YYYY-MM-DD).',
						),
						'count'       => array(
							'type'        => 'integer',
							'description' => 'Number of results per page (max 100). Alias: per_page is also accepted.',
							'default'     => 20,
						),
						'page'        => array(
							'type'        => 'integer',
							'description' => 'Page number for pagination.',
							'default'     => 1,
						),
						'orderby'     => array(
							'type'        => 'string',
							'description' => 'Order by field: "date", "title", or "modified".',
							'default'     => 'date',
							'enum'        => array( 'date', 'title', 'modified' ),
						),
						'order'       => array(
							'type'        => 'string',
							'description' => 'Sort direction: "ASC" or "DESC".',
							'default'     => 'DESC',
							'enum'        => array( 'ASC', 'DESC' ),
						),
					),
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input ) {
					return SENTINEL_Media_Manager::list_media( $input );
				},

				'permission_callback' => function () {
					return current_user_can( 'upload_files' );
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

		/*
		 * UPLOAD MEDIA
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/upload-media',
			array(
				'label'               => 'Upload media from URL',
				'category'            => 'sentinel-media',
				'description'         => 'Required: url (string). '
								. 'Downloads a file from a URL and adds it to the WordPress Media Library. '
								. 'Supports images, videos, audio, PDFs, and common document types. '
								. 'Optionally set title, alt text, caption, and attach to a post. '
								. 'After uploading, use sentinel/set-featured-image to assign it as a featured image.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'required'   => array( 'url' ),
					'properties' => array(
						'url'         => array(
							'type'        => 'string',
							'description' => 'Full URL of the file to download and upload to the media library.',
						),
						'title'       => array(
							'type'        => 'string',
							'description' => 'Override the media title. If omitted, extracted from the filename.',
						),
						'alt_text'    => array(
							'type'        => 'string',
							'description' => 'Alt text for images (for accessibility and SEO).',
						),
						'caption'     => array(
							'type'        => 'string',
							'description' => 'Media caption.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Media description.',
						),
						'post_id'     => array(
							'type'        => 'integer',
							'description' => 'Attach the media to this post ID. 0 = unattached.',
							'default'     => 0,
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'attachment_id' => array( 'type' => 'integer' ),
						'url'           => array( 'type' => 'string' ),
						'mime_type'     => array( 'type' => 'string' ),
						'message'       => array( 'type' => 'string' ),
					),
				),

				'execute_callback'    => function ( $input ) {
					return SENTINEL_Media_Manager::upload_media( $input );
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
							'openWorldHint'   => false,
						),
					),
				),
			)
		);

		/*
		 * SET FEATURED IMAGE
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/set-featured-image',
			array(
				'label'               => 'Set featured image',
				'category'            => 'sentinel-media',
				'description'         => 'Required: post_id (integer). '
								. 'Sets or removes the featured image (post thumbnail) for any post, page, or product. '
								. 'Pass attachment_id to set, or 0 / omit to remove the current featured image. '
								. 'Use sentinel/list-media or sentinel/upload-media first to get the attachment ID. '
								. 'Alias: id is also accepted instead of post_id.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'post_id'       => array(
							'type'        => 'integer',
							'description' => 'The post/page/product ID to set the featured image on.',
						),
						'id'            => array(
							'type'        => 'integer',
							'description' => 'Alias for post_id.',
						),
						'attachment_id' => array(
							'type'        => 'integer',
							'description' => 'The media attachment ID to use as featured image. Pass 0 or omit to remove.',
							'default'     => 0,
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'post_id'       => array( 'type' => 'integer' ),
						'attachment_id' => array( 'type' => 'integer' ),
						'thumbnail_url' => array( 'type' => 'string' ),
						'message'       => array( 'type' => 'string' ),
					),
				),

				'execute_callback'    => function ( $input ) {
					$input['post_id'] = $input['post_id'] ?? $input['id'] ?? 0;
					return SENTINEL_Media_Manager::set_featured_image( $input );
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
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);

		/*
		 * DELETE MEDIA
		 * ─────────────────────────────────────────
		 */

		wp_register_ability(
			'sentinel/delete-media',
			array(
				'label'               => 'Delete media attachment',
				'category'            => 'sentinel-media',
				'description'         => 'Required: attachment_id (integer). '
								. 'Deletes a media attachment from the WordPress Media Library. '
								. 'By default moves to trash. Set force=true for permanent deletion '
								. '(removes the file from disk). Use sentinel/list-media to find the attachment ID. '
								. 'Alias: id is also accepted instead of attachment_id.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'attachment_id' => array(
							'type'        => 'integer',
							'description' => 'The attachment ID to delete.',
						),
						'id'            => array(
							'type'        => 'integer',
							'description' => 'Alias for attachment_id.',
						),
						'force'         => array(
							'type'        => 'boolean',
							'description' => 'true = permanent deletion (removes files from disk). false = move to trash.',
							'default'     => false,
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
				),

				'execute_callback'    => function ( $input ) {
					$input['attachment_id'] = $input['attachment_id'] ?? $input['id'] ?? 0;
					return SENTINEL_Media_Manager::delete_media( $input );
				},

				'permission_callback' => function () {
					return current_user_can( 'upload_files' );
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => false,
							'destructiveHint' => true,
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);
	}
);
