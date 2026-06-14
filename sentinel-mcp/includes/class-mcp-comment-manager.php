<?php

namespace SentinelMCP;

/**
 * Comment Manager for MCP Content Manager.
 *
 * Provides comment listing, moderation, and reply operations
 * for the WordPress Abilities API.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 */

defined('ABSPATH') || exit;

if (! class_exists('SentinelMCP\SENTINEL_Comment_Manager')) {

	/**
	 * Handles comment listing, moderation, and reply operations.
	 */
	class SENTINEL_Comment_Manager
	{

		/**
		 * List comments with flexible filters.
		 *
		 * @param array $input Ability input parameters.
		 * @return array
		 */
		public static function list_comments(array $input): array
		{
			$allowed_orderby = array('comment_date_gmt', 'comment_date', 'comment_ID', 'comment_author', 'comment_post_ID', 'comment_type');
			$orderby         = sanitize_text_field($input['orderby'] ?? 'comment_date_gmt');

			$args = array(
				'number'  => min(absint($input['count'] ?? $input['per_page'] ?? 20), 100),
				'orderby' => in_array($orderby, $allowed_orderby, true) ? $orderby : 'comment_date_gmt',
				'order'   => in_array(strtoupper($input['order'] ?? 'DESC'), array('ASC', 'DESC'), true)
					? strtoupper($input['order'] ?? 'DESC')
					: 'DESC',
			);

			// Status filter.
			$status         = sanitize_text_field($input['status'] ?? 'all');
			$status_map     = array(
				'approved' => 'approve',
				'hold'     => 'hold',
				'spam'     => 'spam',
				'trash'    => 'trash',
				'all'      => 'all',
			);
			$args['status'] = $status_map[$status] ?? 'all';

			// Optional filters.
			if (! empty($input['post_id'])) {
				$args['post_id'] = absint($input['post_id']);
			}

			if (! empty($input['search'])) {
				$args['search'] = sanitize_text_field($input['search']);
			}

			if (! empty($input['author_email'])) {
				$args['author_email'] = sanitize_email($input['author_email']);
			}

			// Date filters.
			$date_query = array();
			if (! empty($input['date_after'])) {
				$date_query[] = array(
					'after' => sanitize_text_field($input['date_after']),
				);
			}
			if (! empty($input['date_before'])) {
				$date_query[] = array(
					'before' => sanitize_text_field($input['date_before']),
				);
			}
			if (! empty($date_query)) {
				$args['date_query'] = $date_query;
			}

			$comments = get_comments($args);

			return array_map(
				function ($comment) {
					// Map internal status to readable string.
					$status = wp_get_comment_status($comment);

					return array(
						'comment_ID'   => (int) $comment->comment_ID,
						'post_id'      => (int) $comment->comment_post_ID,
						'post_title'   => get_the_title($comment->comment_post_ID),
						'author'       => $comment->comment_author,
						'author_email' => $comment->comment_author_email,
						'content'      => wp_strip_all_tags($comment->comment_content),
						'date'         => $comment->comment_date,
						'status'       => $status,
						'parent'       => (int) $comment->comment_parent,
						'type'         => $comment->comment_type ? $comment->comment_type : 'comment',
					);
				},
				$comments
			);
		}

		/**
		 * Manage a comment: moderate, delete, or reply.
		 *
		 * @param array $input Ability input parameters.
		 * @return array
		 */
		public static function manage_comment(array $input): array
		{
			$comment_id = absint($input['comment_id'] ?? 0);
			$action     = sanitize_text_field($input['action'] ?? '');

			if (! $comment_id) {
				return array(
					'success' => false,
					'message' => 'comment_id is required.',
				);
			}

			$comment = get_comment($comment_id);
			if (! $comment) {
				return array(
					'success' => false,
					'message' => "Comment #{$comment_id} not found.",
				);
			}

			switch ($action) {
				case 'approve':
					$result = wp_set_comment_status($comment_id, 'approve');
					return array(
						'success'    => (bool) $result,
						'comment_id' => $comment_id,
						'new_status' => 'approved',
						'message'    => $result ? 'Comment approved.' : 'Failed to approve comment.',
					);

				case 'unapprove':
					$result = wp_set_comment_status($comment_id, 'hold');
					return array(
						'success'    => (bool) $result,
						'comment_id' => $comment_id,
						'new_status' => 'hold',
						'message'    => $result ? 'Comment unapproved (on hold).' : 'Failed to unapprove comment.',
					);

				case 'spam':
					$result = wp_spam_comment($comment_id);
					return array(
						'success'    => (bool) $result,
						'comment_id' => $comment_id,
						'new_status' => 'spam',
						'message'    => $result ? 'Comment marked as spam.' : 'Failed to mark as spam.',
					);

				case 'trash':
					$result = wp_trash_comment($comment_id);
					return array(
						'success'    => (bool) $result,
						'comment_id' => $comment_id,
						'new_status' => 'trash',
						'message'    => $result ? 'Comment moved to trash.' : 'Failed to trash comment.',
					);

				case 'delete':
					$result = wp_delete_comment($comment_id, true);
					return array(
						'success'    => (bool) $result,
						'comment_id' => $comment_id,
						'new_status' => 'deleted',
						'message'    => $result ? 'Comment permanently deleted.' : 'Failed to delete comment.',
					);

				case 'reply':
					$reply_content = $input['reply_content'] ?? '';
					if (empty($reply_content)) {
						return array(
							'success' => false,
							'message' => 'reply_content is required when action is "reply".',
						);
					}

					$user = wp_get_current_user();

					$reply_data = array(
						'comment_post_ID'      => $comment->comment_post_ID,
						'comment_parent'       => $comment_id,
						'comment_content'      => wp_kses_post($reply_content),
						'comment_author'       => $user->display_name,
						'comment_author_email' => $user->user_email,
						'comment_author_url'   => $user->user_url,
						'user_id'              => $user->ID,
						'comment_approved'     => 1,
					);

					$reply_id = wp_new_comment($reply_data, true);

					if (is_wp_error($reply_id)) {
						return array(
							'success' => false,
							'message' => 'Failed to create reply: ' . $reply_id->get_error_message(),
						);
					}

					return array(
						'success'    => true,
						'comment_id' => $comment_id,
						'reply_id'   => (int) $reply_id,
						'new_status' => 'approved',
						'message'    => "Reply created (comment #{$reply_id}).",
					);

				default:
					return array(
						'success' => false,
						'message' => "Unknown action: {$action}. Valid actions: approve, unapprove, spam, trash, delete, reply.",
					);
			}
		}
	}
}
