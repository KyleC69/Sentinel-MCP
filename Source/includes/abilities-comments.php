<?php
/**
 * Comment management abilities.
 *
 * Allows Claude/Cowork to list, search, moderate,
 * and reply to comments on any site post.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Category: Comments.
 */

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-comments',
			array(
				'label'       => __( 'Comment Management', 'mcp-sentinel' ),
				'description' => __( 'List, search, moderate, and reply to comments across all post types.', 'mcp-sentinel' ),
			)
		);
	}
);

/**
 * Abilities.
 */

add_action(
	'wp_abilities_api_init',
	function () {
		// LIST / SEARCH comments.

		wp_register_ability(
			'sentinel/list-comments',
			array(
				'label'               => 'List and search comments',
				'category'            => 'sentinel-comments',
				'description'         => 'All parameters optional. '
								. 'Lists site comments with flexible filters: by post, status, author, '
								. 'date range, or free text. Useful for summarizing recent comments, '
								. 'finding comments pending moderation, or analyzing reader engagement '
								. 'on a specific post.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'properties' => array(
						'post_id'      => array(
							'type'        => 'integer',
							'description' => 'Filter by specific post ID.',
						),
						'status'       => array(
							'type'        => 'string',
							'description' => 'Comment status to display.',
							'enum'        => array( 'approved', 'hold', 'spam', 'trash', 'all' ),
							'default'     => 'all',
						),
						'search'       => array(
							'type'        => 'string',
							'description' => 'Search text in comment content or author name.',
							'default'     => '',
						),
						'author_email' => array(
							'type'        => 'string',
							'description' => 'Filter by comment author email.',
							'default'     => '',
						),
						'date_after'   => array(
							'type'        => 'string',
							'description' => 'Comments after this date (YYYY-MM-DD).',
						),
						'date_before'  => array(
							'type'        => 'string',
							'description' => 'Comments before this date (YYYY-MM-DD).',
						),
						'count'        => array(
							'type'        => 'integer',
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
							'description' => 'Number of results per page (max 100). Alias: per_page is also accepted.',
						),
						'orderby'      => array(
							'type'    => 'string',
							'default' => 'comment_date_gmt',
							'enum'    => array( 'comment_date_gmt', 'comment_post_ID' ),
						),
						'order'        => array(
							'type'    => 'string',
							'default' => 'DESC',
							'enum'    => array( 'ASC', 'DESC' ),
						),
					),
				),

				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'comment_ID'   => array( 'type' => 'integer' ),
							'post_id'      => array( 'type' => 'integer' ),
							'post_title'   => array( 'type' => 'string' ),
							'author'       => array( 'type' => 'string' ),
							'author_email' => array( 'type' => 'string' ),
							'content'      => array( 'type' => 'string' ),
							'date'         => array( 'type' => 'string' ),
							'status'       => array( 'type' => 'string' ),
							'parent'       => array( 'type' => 'integer' ),
							'type'         => array( 'type' => 'string' ),
						),
					),
				),

				'execute_callback'    => function ( $input ) {
					return SENTINEL_Comment_Manager::list_comments( $input );
				},

				'permission_callback' => function () {
					return current_user_can( 'moderate_comments' );
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

		// MANAGE comment (moderate, delete, reply).

		wp_register_ability(
			'sentinel/manage-comment',
			array(
				'label'               => 'Moderate and reply to comments',
				'category'            => 'sentinel-comments',
				'description'         => 'Required: comment_id (integer), action (string). '
								. 'Manages a specific comment: approve, unapprove, mark as spam, '
								. 'trash, permanently delete, or reply. '
								. 'To reply, provide reply_content with the response text. '
								. 'Alias: id is also accepted instead of comment_id.',

				'input_schema'        => array(
					'type'       => 'object',
					'default'    => array(),
					'required'   => array( 'action' ),
					'properties' => array(
						'comment_id'    => array(
							'type'        => 'integer',
							'description' => 'ID of the comment to manage.',
						),
						'id'            => array(
							'type'        => 'integer',
							'description' => 'Alias for comment_id.',
						),
						'action'        => array(
							'type'        => 'string',
							'description' => 'Action to perform on the comment.',
							'enum'        => array( 'approve', 'unapprove', 'spam', 'trash', 'delete', 'reply' ),
						),
						'reply_content' => array(
							'type'        => 'string',
							'description' => 'HTML content of the reply. Required when action is "reply".',
							'default'     => '',
						),
					),
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'comment_id' => array( 'type' => 'integer' ),
						'new_status' => array( 'type' => 'string' ),
						'message'    => array( 'type' => 'string' ),
						'reply_id'   => array( 'type' => 'integer' ),
					),
				),

				'execute_callback'    => function ( $input ) {
					$input['comment_id'] = $input['comment_id'] ?? $input['id'] ?? 0;
					return SENTINEL_Comment_Manager::manage_comment( $input );
				},

				'permission_callback' => function () {
					return current_user_can( 'moderate_comments' );
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
