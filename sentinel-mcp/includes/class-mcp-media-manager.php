<?php

namespace SentinelMCP;

/**
 * Media Manager for MCP Content Manager.
 *
 * Handles WordPress Media Library operations:
 * listing, uploading (sideload from URL), featured image
 * assignment, and deletion.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 */

defined('ABSPATH') || exit;

if (! class_exists('SentinelMCP\SENTINEL_Media_Manager')) {

	/**
	 * Media Library operations manager.
	 */
	class SENTINEL_Media_Manager
	{

		/**
		 * Allowed MIME type prefixes for upload.
		 *
		 * @var array
		 */
		private const ALLOWED_MIME_PREFIXES = array(
			'image/',
			'video/',
			'audio/',
			'application/pdf',
			'application/zip',
			'application/x-zip-compressed',
			'application/msword',
			'application/vnd.openxmlformats-officedocument',
			'application/vnd.ms-excel',
			'application/vnd.ms-powerpoint',
			'text/csv',
			'text/plain',
		);

		/**
		 * List media attachments with filters.
		 *
		 * @param array $input Filter parameters.
		 * @return array
		 */
		public static function list_media(array $input): array
		{
			$count = min(absint($input['count'] ?? $input['per_page'] ?? 20), 100);
			$page  = max(absint($input['page'] ?? 1), 1);

			$allowed_orderby = array('date', 'title', 'modified', 'ID', 'name', 'mime_type', 'rand');
			$orderby         = sanitize_text_field($input['orderby'] ?? 'date');
			$order           = strtoupper(sanitize_text_field($input['order'] ?? 'DESC'));

			$args = array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $count,
				'paged'          => $page,
				'orderby'        => in_array($orderby, $allowed_orderby, true) ? $orderby : 'date',
				'order'          => in_array($order, array('ASC', 'DESC'), true) ? $order : 'DESC',
			);

			// Search filter.
			if (! empty($input['search'])) {
				$args['s'] = sanitize_text_field($input['search']);
			}

			// MIME type filter.
			if (! empty($input['mime_type']) && 'any' !== $input['mime_type']) {
				$mime                   = sanitize_text_field($input['mime_type']);
				$args['post_mime_type'] = $mime;
			}

			// Date filters.
			$date_query = array();
			if (! empty($input['date_after'])) {
				$date_query['after'] = sanitize_text_field($input['date_after']);
			}
			if (! empty($input['date_before'])) {
				$date_query['before'] = sanitize_text_field($input['date_before']);
			}
			if (! empty($date_query)) {
				$date_query['inclusive'] = true;
				$args['date_query']      = array($date_query);
			}

			$query = new \WP_Query($args);
			$items = array();

			foreach ($query->posts as $attachment) {
				$items[] = self::format_attachment($attachment);
			}

			return array(
				'success'     => true,
				'items'       => $items,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
				'page'        => $page,
			);
		}

		/**
		 * Upload media from a URL (sideload).
		 *
		 * @param array $input Upload parameters.
		 * @return array
		 */
		public static function upload_media(array $input): array
		{
			$url = esc_url_raw($input['url'] ?? '');

			if (empty($url)) {
				return array(
					'success' => false,
					'message' => 'URL is required.',
				);
			}

			// Validate URL.
			if (! wp_http_validate_url($url)) {
				return array(
					'success' => false,
					'message' => 'Invalid URL provided.',
				);
			}

			// Load required files for media handling.
			if (! function_exists('media_handle_sideload')) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
			}
			if (! function_exists('download_url')) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if (! function_exists('wp_generate_attachment_metadata')) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			// Download the file to a temp location.
			$tmp_file = download_url($url, 60);

			if (is_wp_error($tmp_file)) {
				return array(
					'success' => false,
					'message' => 'Failed to download file: ' . $tmp_file->get_error_message(),
				);
			}

			// Extract filename from URL.
			$url_path = wp_parse_url($url, PHP_URL_PATH);
			$filename = $url_path ? basename($url_path) : 'uploaded-file';

			// Validate file type.
			$filetype = wp_check_filetype($filename);
			if (empty($filetype['type'])) {
				// Try to detect from the downloaded file.
				$filetype = wp_check_filetype($tmp_file);
			}

			if (empty($filetype['type']) || ! self::is_allowed_mime($filetype['type'])) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink($tmp_file);
				return array(
					'success' => false,
					'message' => 'File type not allowed: ' . ($filetype['type'] ?? 'unknown'),
				);
			}

			// Prepare file array for sideload.
			$file_array = array(
				'name'     => sanitize_file_name($filename),
				'tmp_name' => $tmp_file,
			);

			$post_id = absint($input['post_id'] ?? 0);
			$desc    = sanitize_text_field($input['description'] ?? '');

			// Post data overrides.
			$post_data = array();
			if (! empty($input['title'])) {
				$post_data['post_title'] = sanitize_text_field($input['title']);
			}
			if (! empty($input['caption'])) {
				$post_data['post_excerpt'] = sanitize_text_field($input['caption']);
			}
			if (! empty($desc)) {
				$post_data['post_content'] = $desc;
			}

			// Sideload the file.
			$attachment_id = media_handle_sideload($file_array, $post_id, $desc, $post_data);

			if (is_wp_error($attachment_id)) {
				return array(
					'success' => false,
					'message' => 'Upload failed: ' . $attachment_id->get_error_message(),
				);
			}

			// Set alt text if provided.
			if (! empty($input['alt_text'])) {
				update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($input['alt_text']));
			}

			$attachment_url = wp_get_attachment_url($attachment_id);
			$metadata       = wp_get_attachment_metadata($attachment_id);

			return array(
				'success'       => true,
				'attachment_id' => $attachment_id,
				'url'           => $attachment_url,
				'mime_type'     => get_post_mime_type($attachment_id),
				'file_size'     => ! empty($metadata['filesize']) ? $metadata['filesize'] : null,
				'message'       => sprintf('Media uploaded successfully (ID: %d).', $attachment_id),
			);
		}

		/**
		 * Set or remove a post's featured image.
		 *
		 * @param array $input Parameters with post_id and attachment_id.
		 * @return array
		 */
		public static function set_featured_image(array $input): array
		{
			$post_id       = absint($input['post_id'] ?? 0);
			$attachment_id = absint($input['attachment_id'] ?? 0);

			if (! $post_id) {
				return array(
					'success' => false,
					'message' => 'post_id is required.',
				);
			}

			$post = get_post($post_id);
			if (! $post) {
				return array(
					'success' => false,
					'message' => sprintf('Post %d not found.', $post_id),
				);
			}

			if (! current_user_can('edit_post', $post_id)) {
				return array(
					'success' => false,
					'message' => 'You do not have permission to edit this post.',
				);
			}

			// Remove featured image.
			if (0 === $attachment_id) {
				delete_post_thumbnail($post_id);
				return array(
					'success'       => true,
					'post_id'       => $post_id,
					'attachment_id' => 0,
					'thumbnail_url' => '',
					'message'       => 'Featured image removed.',
				);
			}

			// Validate the attachment exists and is an image.
			$attachment = get_post($attachment_id);
			if (! $attachment || 'attachment' !== $attachment->post_type) {
				return array(
					'success' => false,
					'message' => sprintf('Attachment %d not found.', $attachment_id),
				);
			}

			$result = set_post_thumbnail($post_id, $attachment_id);

			if (false === $result) {
				return array(
					'success' => false,
					'message' => 'Failed to set featured image.',
				);
			}

			$thumb_url = get_the_post_thumbnail_url($post_id, 'medium');

			return array(
				'success'       => true,
				'post_id'       => $post_id,
				'attachment_id' => $attachment_id,
				'thumbnail_url' => $thumb_url ? (string) $thumb_url : '',
				'message'       => sprintf('Featured image set to attachment %d.', $attachment_id),
			);
		}

		/**
		 * Delete a media attachment.
		 *
		 * @param array $input Parameters with attachment_id and force.
		 * @return array
		 */
		public static function delete_media(array $input): array
		{
			$attachment_id = absint($input['attachment_id'] ?? 0);
			$force         = (bool) ($input['force'] ?? false);

			if (! $attachment_id) {
				return array(
					'success' => false,
					'message' => 'attachment_id is required.',
				);
			}

			$attachment = get_post($attachment_id);
			if (! $attachment || 'attachment' !== $attachment->post_type) {
				return array(
					'success' => false,
					'message' => sprintf('Attachment %d not found.', $attachment_id),
				);
			}

			if (! current_user_can('delete_post', $attachment_id)) {
				return array(
					'success' => false,
					'message' => 'You do not have permission to delete this attachment.',
				);
			}

			$title  = $attachment->post_title;
			$result = wp_delete_attachment($attachment_id, $force);

			if (! $result) {
				return array(
					'success' => false,
					'message' => 'Failed to delete the attachment.',
				);
			}

			return array(
				'success' => true,
				'message' => sprintf(
					'"%s" %s successfully.',
					$title,
					$force ? 'permanently deleted' : 'moved to trash'
				),
			);
		}

		/**
		 * Format an attachment post into a summary array.
		 *
		 * @param \WP_Post $attachment The attachment post object.
		 * @return array
		 */
		private static function format_attachment(\WP_Post $attachment): array
		{
			$url      = wp_get_attachment_url($attachment->ID);
			$metadata = wp_get_attachment_metadata($attachment->ID);
			$alt_text = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);

			$item = array(
				'id'        => $attachment->ID,
				'title'     => $attachment->post_title,
				'url'       => $url ? $url : '',
				'mime_type' => $attachment->post_mime_type,
				'alt_text'  => $alt_text ? $alt_text : '',
				'caption'   => $attachment->post_excerpt,
				'date'      => $attachment->post_date,
			);

			// Add dimensions for images.
			if (is_array($metadata)) {
				if (! empty($metadata['width'])) {
					$item['width']  = (int) $metadata['width'];
					$item['height'] = (int) $metadata['height'];
				}
				if (! empty($metadata['filesize'])) {
					$item['file_size'] = (int) $metadata['filesize'];
				}
			}

			// Thumbnail URL for images.
			$thumb = wp_get_attachment_image_src($attachment->ID, 'thumbnail');
			if ($thumb) {
				$item['thumbnail_url'] = $thumb[0];
			}

			return $item;
		}

		/**
		 * Check if a MIME type is in the allowed list.
		 *
		 * @param string $mime_type The MIME type to check.
		 * @return bool
		 */
		private static function is_allowed_mime(string $mime_type): bool
		{
			foreach (self::ALLOWED_MIME_PREFIXES as $prefix) {
				if (str_starts_with($mime_type, $prefix)) {
					return true;
				}
			}
			return false;
		}
	}
}
