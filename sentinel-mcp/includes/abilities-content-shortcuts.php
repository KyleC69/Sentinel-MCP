<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Content shortcuts abilities (Sprint 1.3).
 *
 * Convenience read-only abilities so the AI client doesn't have to learn
 * search-content filters from scratch. They reuse existing categories
 * (sentinel-content, sentinel-comments).
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

if (! function_exists('SENTINEL_format_post_summary')) {
	/**
	 * Format a single post for content-shortcuts output.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	function SENTINEL_format_post_summary(\WP_Post $post): array
	{
		$excerpt = $post->post_excerpt ? $post->post_excerpt : wp_strip_all_tags((string) $post->post_content);
		$excerpt = trim(preg_replace('/\s+/', ' ', (string) $excerpt));
		if (strlen($excerpt) > 200) {
			$excerpt = mb_substr($excerpt, 0, 200) . '...';
		}

		return [
			'id'        => (int) $post->ID,
			'title'     => (string) $post->post_title,
			'status'    => (string) $post->post_status,
			'type'      => (string) $post->post_type,
			'date'      => (string) $post->post_date,
			'modified'  => (string) $post->post_modified,
			'author_id' => (int) $post->post_author,
			'permalink' => (string) get_permalink($post),
			'excerpt'   => $excerpt,
		];
	}
}

if (! function_exists('SENTINEL_query_posts_by_status')) {
	/**
	 * Run a \WP_Query and format results.
	 *
	 * @param string $post_type Post type slug.
	 * @param string|array $status Status or list of statuses.
	 * @param int    $per_page Results per page (1-50).
	 * @param int    $page Page number (>=1).
	 * @param string $orderby Field to order by.
	 * @param string $order ASC or DESC.
	 * @return array
	 */
	function SENTINEL_query_posts_by_status(string $post_type, $status, int $per_page, int $page, string $orderby = 'date', string $order = 'DESC'): array
	{
		$query = new \WP_Query(
			[
				'post_type'              => $post_type,
				'post_status'            => $status,
				'posts_per_page'         => max(1, min(50, $per_page)),
				'paged'                  => max(1, $page),
				'orderby'                => $orderby,
				'order'                  => 'ASC' === strtoupper($order) ? 'ASC' : 'DESC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		$items = [];
		foreach ($query->posts as $post) {
			$items[] = SENTINEL_format_post_summary($post);
		}

		return [
			'page'        => max(1, $page),
			'per_page'    => max(1, min(50, $per_page)),
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'items'       => $items,
		];
	}
}

add_action(
	'wp_abilities_api_init',
	function () {

		// 1. List recent posts.

		wp_register_ability(
			'sentinel/list-recent-posts',
			[
				'label'               => 'List recent posts',
				'category'            => 'sentinel-content',
				'description'         => 'Read-only. Lists the most recent posts of a given post type ordered by date DESC. Defaults: post type "post", 10 results, page 1. Returns id, title, status, type, dates, author_id, permalink and a 200-char excerpt for each. Alias: count is also accepted instead of per_page.',

				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [
						'post_type' => [
							'type'        => 'string',
							'default'     => 'post',
							'description' => 'Post type slug. Defaults to "post".',
						],
						'per_page'  => [
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 10,
						],
						'count'     => [
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 50,
							'description' => 'Alias for per_page.',
						],
						'page'      => [
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						],
						'status'    => [
							'type'        => 'string',
							'default'     => 'publish',
							'description' => 'Post status to filter by. Use "any" for everything except trash/auto-draft.',
						],
					],
					'additionalProperties' => false,
				],

				'output_schema'       => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],

				'execute_callback'    => function (array $input = null) {
					$post_type = isset($input['post_type']) ? sanitize_key((string) $input['post_type']) : 'post';
					$per_page  = isset($input['per_page']) ? (int) $input['per_page'] : (isset($input['count']) ? (int) $input['count'] : 10);
					$page      = isset($input['page']) ? (int) $input['page'] : 1;
					$status    = isset($input['status']) ? sanitize_text_field((string) $input['status']) : 'publish';

					if (! post_type_exists($post_type)) {
						return [
							'success' => false,
							'message' => sprintf('Post type "%s" not found.', $post_type),
						];
					}

					return SENTINEL_query_posts_by_status($post_type, $status, $per_page, $page);
				},

				'permission_callback' => SENTINEL_ability_permission('read'),

				'meta'                => SENTINEL_ability_meta(),
			]
		);

		// 2. List pending comments.

		wp_register_ability(
			'sentinel/list-pending-comments',
			[
				'label'               => 'List pending comments',
				'category'            => 'sentinel-comments',
				'description'         => 'Read-only shortcut to list comments awaiting moderation (status = "hold"). Paginated. Returns id, post_id, author, author_email (redacted), date, content excerpt.',

				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [
						'per_page' => [
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 25,
						],
						'page'     => [
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						],
					],
					'additionalProperties' => false,
				],

				'output_schema'       => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],

				'execute_callback'    => function (array $input = null) {
					$per_page = isset($input['per_page']) ? max(1, min(100, (int) $input['per_page'])) : 25;
					$page     = isset($input['page']) ? max(1, (int) $input['page']) : 1;

					$query    = new WP_Comment_Query();
					$comments = $query->query(
						[
							'status'  => 'hold',
							'number'  => $per_page,
							'offset'  => ($page - 1) * $per_page,
							'orderby' => 'comment_date_gmt',
							'order'   => 'DESC',
						]
					);

					$total = (int) get_comments(
						[
							'status' => 'hold',
							'count'  => true,
						]
					);

					$items = [];
					foreach ((array) $comments as $comment) {
						$redacted = SENTINEL_redact_email((string) $comment->comment_author_email);

						$content = wp_strip_all_tags((string) $comment->comment_content);
						if (strlen($content) > 200) {
							$content = mb_substr($content, 0, 200) . '...';
						}

						$items[] = [
							'id'                   => (int) $comment->comment_ID,
							'post_id'              => (int) $comment->comment_post_ID,
							'author'               => (string) $comment->comment_author,
							'author_email_redacted' => $redacted,
							'date'                 => (string) $comment->comment_date,
							'content'              => $content,
						];
					}

					return [
						'page'     => $page,
						'per_page' => $per_page,
						'total'    => $total,
						'items'    => $items,
					];
				},

				'permission_callback' => SENTINEL_ability_permission('edit_posts'),

				'meta'                => SENTINEL_ability_meta(),
			]
		);

		// 3. List scheduled posts.

		wp_register_ability(
			'sentinel/list-scheduled-posts',
			[
				'label'               => 'List scheduled posts',
				'category'            => 'sentinel-content',
				'description'         => 'Read-only. Lists posts with status "future" (scheduled for future publication). Defaults: post type "post", 25 results.',

				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [
						'post_type' => [
							'type'    => 'string',
							'default' => 'post',
						],
						'per_page'  => [
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 25,
						],
						'page'      => [
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						],
					],
					'additionalProperties' => false,
				],

				'output_schema'       => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],

				'execute_callback'    => function (array $input = null) {
					$post_type = isset($input['post_type']) ? sanitize_key((string) $input['post_type']) : 'post';
					$per_page  = isset($input['per_page']) ? (int) $input['per_page'] : 25;
					$page      = isset($input['page']) ? (int) $input['page'] : 1;

					if (! post_type_exists($post_type)) {
						return [
							'success' => false,
							'message' => sprintf('Post type "%s" not found.', $post_type),
						];
					}

					return SENTINEL_query_posts_by_status($post_type, 'future', $per_page, $page, 'date', 'ASC');
				},

				'permission_callback' => SENTINEL_ability_permission('edit_posts'),

				'meta'                => SENTINEL_ability_meta(),
			]
		);

		// 4. List trashed posts.

		wp_register_ability(
			'sentinel/list-trashed-posts',
			[
				'label'               => 'List trashed posts',
				'category'            => 'sentinel-content',
				'description'         => 'Read-only. Lists posts in the trash. Useful before purging or restoring. Defaults: post type "post", 25 results.',

				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [
						'post_type' => [
							'type'    => 'string',
							'default' => 'post',
						],
						'per_page'  => [
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 25,
						],
						'page'      => [
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						],
					],
					'additionalProperties' => false,
				],

				'output_schema'       => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],

				'execute_callback'    => function (array $input = null) {
					$post_type = isset($input['post_type']) ? sanitize_key((string) $input['post_type']) : 'post';
					$per_page  = isset($input['per_page']) ? (int) $input['per_page'] : 25;
					$page      = isset($input['page']) ? (int) $input['page'] : 1;

					if (! post_type_exists($post_type)) {
						return [
							'success' => false,
							'message' => sprintf('Post type "%s" not found.', $post_type),
						];
					}

					return SENTINEL_query_posts_by_status($post_type, 'trash', $per_page, $page);
				},

				'permission_callback' => SENTINEL_ability_permission('edit_posts'),

				'meta'                => SENTINEL_ability_meta(),
			]
		);

		// 5. List post revisions.

		wp_register_ability(
			'sentinel/list-post-revisions',
			[
				'label'               => 'List post revisions',
				'category'            => 'sentinel-content',
				'description'         => 'Read-only. Lists all revisions of a given post (autosaves and saved revisions). Returns id, parent post_id, author_id, dates and a content length indicator. The actual restore action is Premium.',

				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'required'             => ['post_id'],
					'properties'           => [
						'post_id' => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'Parent post ID whose revisions to list.',
						],
					],
					'additionalProperties' => false,
				],

				'output_schema'       => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],

				'execute_callback'    => function (array $input) {
					$post_id = isset($input['post_id']) ? absint($input['post_id']) : 0;
					if (! $post_id || ! get_post($post_id)) {
						return [
							'success' => false,
							'message' => 'Post not found.',
						];
					}

					$revisions = wp_get_post_revisions($post_id, ['order' => 'DESC']);
					$items     = [];

					foreach ($revisions as $rev) {
						$items[] = [
							'id'              => (int) $rev->ID,
							'parent_post_id'  => (int) $post_id,
							'author_id'       => (int) $rev->post_author,
							'date'            => (string) $rev->post_date,
							'modified'        => (string) $rev->post_modified,
							'is_autosave'     => wp_is_post_autosave($rev) ? true : false,
							'title_length'    => mb_strlen((string) $rev->post_title),
							'content_length'  => mb_strlen((string) $rev->post_content),
						];
					}

					return [
						'parent_post_id' => $post_id,
						'count'          => count($items),
						'items'          => $items,
					];
				},

				'permission_callback' => function ($input) {
					$post_id = isset($input['post_id']) ? absint($input['post_id']) : 0;
					return $post_id ? current_user_can('read_post', $post_id) : false;
				},

				'meta'                => [
					'mcp' => [
						'public'      => true,
						'annotations' => [
							'readOnlyHint'    => true,
							'destructiveHint' => false,
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						],
					],
				],
			]
		);
	}
);

/*
 * MCP annotations summary for this file:
 *
 *   list-recent-posts      readOnly idempotent
 *   list-pending-comments  readOnly idempotent
 *   list-scheduled-posts   readOnly idempotent
 *   list-trashed-posts     readOnly idempotent
 *   list-post-revisions    readOnly idempotent
 */
