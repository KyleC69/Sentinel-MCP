<?php
/**
 * Premium hints with per-session throttle (Sprint 5.2).
 *
 * Surfaces upsell pointers for actions that are Premium-only or have a much
 * richer Premium counterpart. Each hint is throttled to one occurrence per
 * category per OAuth session (transient TTL ~1 hour, similar to access token
 * lifetime) so the AI client gets the message once, not on every call.
 *
 * Helpers in this class are safe to call from anywhere; they degrade gracefully
 * when no OAuth client is in flight (cookie auth, application password) by
 * always emitting the hint without any throttling, so manual admin testing
 * still surfaces the message.
 *
 * @package    SENTINEL
 * @author     José Conti <j.conti@joseconti.com>
 * @copyright  2026 José Conti
 * @license    GPL-2.0-or-later
 * @link       https://mcpwp.com/
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'SENTINEL_Premium_Hints' ) ) {

	/**
	 * Throttled Premium-upsell hint emitter.
	 */
	class SENTINEL_Premium_Hints {

		/**
		 * Throttle window in seconds. Roughly aligned with the OAuth access
		 * token TTL so a single AI session receives at most one hint per category.
		 */
		const THROTTLE_TTL = HOUR_IN_SECONDS;

		/**
		 * Return a hint payload if not throttled, null otherwise.
		 *
		 * @param string $category    Category slug (e.g. "wc-write", "seo-write", "i18n-write").
		 *                            Throttling is keyed on this so unrelated areas don't cancel each other.
		 * @param string $feature_slug Specific feature being teased (used in the payload).
		 * @param string $message     Human-readable message. Treated as a translatable string by the caller.
		 * @return array{feature_slug:string,message:string,upgrade_url:string,category:string}|null
		 */
		public static function maybe_hint( string $category, string $feature_slug, string $message ): ?array {
			$client_id = class_exists( 'SENTINEL_OAuth_Interceptor' )
				? SENTINEL_OAuth_Interceptor::get_current_client_id()
				: '';

			if ( '' !== $client_id && self::is_throttled( $client_id, $category ) ) {
				return null;
			}

			if ( '' !== $client_id ) {
				self::mark_emitted( $client_id, $category );
			}

			$upgrade_url = defined( 'SENTINEL_PREMIUM_PRODUCT_URL' )
				? SENTINEL_PREMIUM_PRODUCT_URL
				: 'https://mcpwp.com/';

			return array(
				'category'     => $category,
				'feature_slug' => $feature_slug,
				'message'      => $message,
				'upgrade_url'  => $upgrade_url,
			);
		}

		/**
		 * Whether the (client_id, category) pair already emitted a hint in this window.
		 */
		public static function is_throttled( string $client_id, string $category ): bool {
			$key = self::key( $client_id, $category );
			return false !== get_transient( $key );
		}

		/**
		 * Mark the (client_id, category) pair as having received a hint.
		 */
		public static function mark_emitted( string $client_id, string $category ): void {
			$key = self::key( $client_id, $category );
			set_transient( $key, 1, self::THROTTLE_TTL );
		}

		/**
		 * Reset throttle for a category (mainly used in tests or admin tooling).
		 */
		public static function reset( string $client_id, string $category ): void {
			delete_transient( self::key( $client_id, $category ) );
		}

		/**
		 * Compose the transient key.
		 */
		protected static function key( string $client_id, string $category ): string {
			return 'mcpcomal_hint_' . md5( $client_id . '|' . $category );
		}
	}
}
