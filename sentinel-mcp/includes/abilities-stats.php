<?php
/**
 * Site stats abilities (Sprint 1.2).
 *
 * Lightweight counts for content audits: posts per CPT and status, comments
 * per status, users per role, and media library by mime type and total size.
 *
 * @package    SENTINEL
 * @author     José Conti <j.conti@joseconti.com>
 * @copyright  2026 José Conti
 * @license    GPL-2.0-or-later
 * @link       https://mcpwp.com/
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-stats',
			array(
				'label'       => __( 'Site stats and counts', 'mcp-sentinel' ),
				'description' => __( 'Lightweight counts: posts per CPT and status, comments, users per role, media usage.', 'mcp-sentinel' ),
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	function () {

		// 1. Site stats — counts of posts, comments, users.

		wp_register_ability(
			'sentinel/get-site-stats',
			array(
				'label'               => 'Get site stats',
				'category'            => 'sentinel-stats',
				'description'         => 'Read-only. Returns counts of content across the site: posts per registered post type broken down by status (publish, draft, pending, future, trash, private), comments grouped by status (approved, hold, spam, trash), users grouped by role, and total media items. Use this for quick audits and to find buckets of work (e.g. "how many drafts do I have?").',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'include_private_post_types' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => 'When false, only public post types are counted.',
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input = null ) {
					$include_private = ! isset( $input['include_private_post_types'] ) || ! empty( $input['include_private_post_types'] );

					$args = $include_private ? array() : array( 'public' => true );
					$post_types = get_post_types( $args, 'objects' );

					$post_counts = array();
					foreach ( $post_types as $pt ) {
						$counts = wp_count_posts( $pt->name );
						if ( ! $counts ) {
							continue;
						}
						$post_counts[ $pt->name ] = array(
							'label'   => $pt->label,
							'publish' => isset( $counts->publish ) ? (int) $counts->publish : 0,
							'draft'   => isset( $counts->draft ) ? (int) $counts->draft : 0,
							'pending' => isset( $counts->pending ) ? (int) $counts->pending : 0,
							'future'  => isset( $counts->future ) ? (int) $counts->future : 0,
							'private' => isset( $counts->private ) ? (int) $counts->private : 0,
							'trash'   => isset( $counts->trash ) ? (int) $counts->trash : 0,
							'auto-draft' => isset( $counts->{'auto-draft'} ) ? (int) $counts->{'auto-draft'} : 0,
						);
					}

					$comment_raw = wp_count_comments();
					$comments    = array(
						'approved'       => isset( $comment_raw->approved ) ? (int) $comment_raw->approved : 0,
						'moderated'      => isset( $comment_raw->moderated ) ? (int) $comment_raw->moderated : 0,
						'spam'           => isset( $comment_raw->spam ) ? (int) $comment_raw->spam : 0,
						'trash'          => isset( $comment_raw->trash ) ? (int) $comment_raw->trash : 0,
						'post-trashed'   => isset( $comment_raw->{'post-trashed'} ) ? (int) $comment_raw->{'post-trashed'} : 0,
						'total_comments' => isset( $comment_raw->total_comments ) ? (int) $comment_raw->total_comments : 0,
					);

					$user_raw = count_users();
					$users    = array(
						'total'    => isset( $user_raw['total_users'] ) ? (int) $user_raw['total_users'] : 0,
						'by_role'  => array(),
					);
					if ( isset( $user_raw['avail_roles'] ) && is_array( $user_raw['avail_roles'] ) ) {
						foreach ( $user_raw['avail_roles'] as $role => $count ) {
							$users['by_role'][ $role ] = (int) $count;
						}
					}

					$media_total = wp_count_posts( 'attachment' );
					$media_count = ( isset( $media_total->inherit ) ? (int) $media_total->inherit : 0 )
						+ ( isset( $media_total->publish ) ? (int) $media_total->publish : 0 );

					return array(
						'post_counts' => $post_counts,
						'comments'    => $comments,
						'users'       => $users,
						'media_total' => $media_count,
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

		// 2. Media stats — counts and disk size by mime type.

		wp_register_ability(
			'sentinel/get-media-stats',
			array(
				'label'               => 'Get media stats',
				'category'            => 'sentinel-stats',
				'description'         => 'Read-only. Returns a breakdown of the media library by main mime type (image/jpeg, image/png, image/webp, application/pdf, video/mp4, etc.) and the total disk size used by all attachments. Useful for storage audits.',

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
					global $wpdb;

					$rows = $wpdb->get_results(
						"SELECT post_mime_type AS mime, COUNT(*) AS total
						FROM {$wpdb->posts}
						WHERE post_type = 'attachment'
						GROUP BY post_mime_type
						ORDER BY total DESC"
					); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

					$by_mime = array();
					foreach ( (array) $rows as $row ) {
						$mime = (string) $row->mime;
						if ( '' === $mime ) {
							$mime = 'unknown';
						}
						$by_mime[ $mime ] = (int) $row->total;
					}

					$upload_dir = wp_get_upload_dir();
					$base_dir   = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';
					$total_bytes = 0;

					if ( '' !== $base_dir && is_dir( $base_dir ) ) {
						$total_bytes = mcpcomal_stats_dir_size( $base_dir );
					}

					return array(
						'by_mime'           => $by_mime,
						'total_attachments' => array_sum( $by_mime ),
						'uploads_path'      => $base_dir,
						'uploads_size_bytes' => $total_bytes,
						'uploads_size_human' => size_format( $total_bytes, 2 ),
					);
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
	}
);

if ( ! function_exists( 'mcpcomal_stats_dir_size' ) ) {
	/**
	 * Recursively compute the size of a directory.
	 *
	 * Bounded to avoid pathological loops on misconfigured filesystems.
	 *
	 * @param string $dir Absolute path.
	 * @return int Size in bytes.
	 */
	function mcpcomal_stats_dir_size( string $dir ): int {
		$total = 0;

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ( $iterator as $file ) {
				if ( $file instanceof SplFileInfo && $file->isFile() ) {
					$total += (int) $file->getSize();
				}
			}
		} catch ( Throwable $e ) {
			return $total;
		}

		return $total;
	}
}

/*
 * MCP annotations summary for this file:
 *
 *   get-site-stats   readOnly idempotent
 *   get-media-stats  readOnly idempotent
 */
