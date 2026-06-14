<?php

/**
 * Gemini image generator (Lite minimal).
 *
 * Wraps Google's Gemini generateContent endpoint to produce a single 1024px
 * PNG per call and side-loads the result into the Media Library. Designed to
 * be small, defensive and free of heavy dependencies — Imagen API, multiple
 * resolutions, aspect ratio selection, image editing, safety controls and
 * person generation are all reserved for the Premium edition.
 *
 * Settings:
 *   option `mcpcomal_gemini_api_key`  — API key (autoload off).
 *   option `mcpcomal_gemini_model`    — model id, default "gemini-2.0-flash-exp".
 *   filter `mcpcomal_gemini_api_key`  — runtime override.
 *   filter `mcpcomal_gemini_model`    — runtime override.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://mcpwp.com/
 */

defined('ABSPATH') || exit;

if (! class_exists('SENTINEL_Image_Generator')) {

	/**
	 * Minimal Gemini-based image generator with Media Library side-loading.
	 */
	class SENTINEL_Image_Generator
	{

		const DEFAULT_MODEL = 'gemini-2.0-flash-exp';
		const ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

		/**
		 * Get the configured API key (option + filter).
		 */
		public static function api_key(): string
		{
			$key = (string) get_option('mcpcomal_gemini_api_key', '');
			$key = (string) apply_filters('mcpcomal_gemini_api_key', $key);
			return trim($key);
		}

		/**
		 * Get the configured model id (option + filter).
		 */
		public static function model(): string
		{
			$model = (string) get_option('mcpcomal_gemini_model', self::DEFAULT_MODEL);
			$model = (string) apply_filters('mcpcomal_gemini_model', $model);
			return '' !== $model ? $model : self::DEFAULT_MODEL;
		}

		/**
		 * Whether the generator is configured.
		 */
		public static function is_configured(): bool
		{
			return '' !== self::api_key();
		}

		/**
		 * Generate N images for the same prompt, side-load each one into the
		 * Media Library and return their attachment IDs and URLs.
		 *
		 * @param string $prompt User prompt (1-2000 chars).
		 * @param int    $count  Number of images to generate (1-3).
		 * @param int    $attach_to_post Optional post ID to attach the new attachments to.
		 * @return array{ok:bool, message?:string, items:array<int,array<string,mixed>>}
		 */
		public static function generate(string $prompt, int $count = 1, int $attach_to_post = 0): array
		{
			$prompt = trim($prompt);
			if ('' === $prompt) {
				return array(
					'ok'      => false,
					'message' => 'Prompt is empty.',
					'items'   => array(),
				);
			}
			if (! self::is_configured()) {
				return array(
					'ok'      => false,
					'message' => 'Gemini API key is not configured. Set it in Settings → Sentinel-MCP → Settings.',
					'items'   => array(),
				);
			}

			$count = max(1, min(3, $count));
			$items = array();

			for ($i = 0; $i < $count; $i++) {
				$image = self::call_gemini_once($prompt);
				if (is_wp_error($image)) {
					return array(
						'ok'      => false,
						'message' => $image->get_error_message(),
						'items'   => $items,
					);
				}

				$attachment = self::sideload_image_bytes(
					(string) $image['bytes'],
					(string) $image['mime'],
					$prompt,
					$attach_to_post
				);
				if (is_wp_error($attachment)) {
					return array(
						'ok'      => false,
						'message' => $attachment->get_error_message(),
						'items'   => $items,
					);
				}

				$items[] = $attachment;
			}

			return array(
				'ok'    => true,
				'items' => $items,
			);
		}

		/**
		 * Single Gemini generateContent call configured to return an image.
		 *
		 * @param string $prompt Prompt.
		 * @return array{bytes:string,mime:string}|WP_Error
		 */
		protected static function call_gemini_once(string $prompt)
		{
			$endpoint = self::ENDPOINT_BASE . rawurlencode(self::model()) . ':generateContent';
			$body     = wp_json_encode(
				array(
					'contents'         => array(
						array(
							'role'  => 'user',
							'parts' => array(
								array('text' => $prompt),
							),
						),
					),
					'generationConfig' => array(
						'responseModalities' => array('TEXT', 'IMAGE'),
					),
				)
			);

			$response = wp_remote_post(
				add_query_arg('key', self::api_key(), $endpoint),
				array(
					'timeout' => 60,
					'headers' => array('Content-Type' => 'application/json'),
					'body'    => $body,
				)
			);

			if (is_wp_error($response)) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code($response);
			$raw  = (string) wp_remote_retrieve_body($response);
			if ($code < 200 || $code >= 300) {
				return new WP_Error(
					'mcpcomal_gemini_http_error',
					sprintf('Gemini API returned HTTP %d: %s', $code, mb_substr($raw, 0, 300))
				);
			}

			$decoded = json_decode($raw, true);
			if (! is_array($decoded)) {
				return new WP_Error('mcpcomal_gemini_invalid_json', 'Gemini API response is not valid JSON.');
			}

			$candidates = $decoded['candidates'] ?? array();
			foreach ((array) $candidates as $candidate) {
				$parts = isset($candidate['content']['parts']) ? (array) $candidate['content']['parts'] : array();
				foreach ($parts as $part) {
					if (isset($part['inlineData']['data']) && isset($part['inlineData']['mimeType'])) {
						$bytes = base64_decode((string) $part['inlineData']['data'], true); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
						if (false === $bytes) {
							return new WP_Error('mcpcomal_gemini_invalid_b64', 'Gemini returned an inlineData part that is not valid base64.');
						}
						return array(
							'bytes' => $bytes,
							'mime'  => (string) $part['inlineData']['mimeType'],
						);
					}
				}
			}

			return new WP_Error('mcpcomal_gemini_no_image', 'Gemini did not return an inline image.');
		}

		/**
		 * Save raw bytes as a Media Library attachment.
		 *
		 * @param string $bytes  Image bytes.
		 * @param string $mime   Mime type ("image/png", "image/jpeg").
		 * @param string $prompt Prompt to record as meta (and as alt-text fallback).
		 * @param int    $attach_to_post Optional post id.
		 * @return array<string,mixed>|WP_Error
		 */
		protected static function sideload_image_bytes(string $bytes, string $mime, string $prompt, int $attach_to_post)
		{
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$ext = 'png';
			if ('image/jpeg' === $mime || 'image/jpg' === $mime) {
				$ext = 'jpg';
			} elseif ('image/webp' === $mime) {
				$ext = 'webp';
			}

			$slug   = 'gemini-' . wp_generate_password(8, false, false) . '.' . $ext;
			$upload = wp_upload_bits($slug, null, $bytes);
			if (! empty($upload['error'])) {
				return new WP_Error('mcpcomal_upload_failed', (string) $upload['error']);
			}

			$file_path = (string) $upload['file'];
			$file_url  = (string) $upload['url'];

			$filetype = wp_check_filetype($file_path);
			$attach_id = wp_insert_attachment(
				array(
					'post_mime_type' => $filetype['type'] ? $filetype['type'] : $mime,
					'post_title'     => sanitize_text_field(wp_trim_words($prompt, 8, '')),
					'post_content'   => '',
					'post_status'    => 'inherit',
					'post_excerpt'   => $prompt,
				),
				$file_path,
				$attach_to_post > 0 ? $attach_to_post : 0
			);

			if (is_wp_error($attach_id)) {
				return $attach_id;
			}

			$metadata = wp_generate_attachment_metadata($attach_id, $file_path);
			wp_update_attachment_metadata($attach_id, $metadata);

			update_post_meta($attach_id, '_mcpcomal_gemini_prompt', $prompt);
			update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field(wp_trim_words($prompt, 20, '')));

			return array(
				'attachment_id' => (int) $attach_id,
				'url'           => $file_url,
				'mime'          => $mime,
				'alt'           => sanitize_text_field(wp_trim_words($prompt, 20, '')),
			);
		}
	}
}
