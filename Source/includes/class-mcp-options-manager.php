<?php
/**
 * Options Manager for MCP Content Manager.
 *
 * Provides safe read/write access to WordPress options
 * through a hardcoded whitelist of allowed option names.
 *
 * @package    SENTINEL
 * @author     José Conti <j.conti@joseconti.com>
 * @copyright  2026 José Conti
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'SENTINEL_Options_Manager' ) ) {

	/**
	 * WordPress options manager with security whitelist.
	 */
	class SENTINEL_Options_Manager {

		/**
		 * Options that can be read.
		 *
		 * @var array
		 */
		private const READABLE_OPTIONS = array(
			// General.
			'blogname',
			'blogdescription',
			'siteurl',
			'home',
			'admin_email',
			'date_format',
			'time_format',
			'timezone_string',
			'gmt_offset',
			'WPLANG',
			'blog_charset',
			'start_of_week',
			// Writing.
			'default_category',
			'default_post_format',
			'use_smilies',
			'ping_sites',
			// Reading.
			'posts_per_page',
			'posts_per_rss',
			'rss_use_excerpt',
			'show_on_front',
			'page_on_front',
			'page_for_posts',
			'blog_public',
			// Discussion.
			'default_comment_status',
			'default_pingback_flag',
			'default_ping_status',
			'require_name_email',
			'comment_moderation',
			'comment_previously_approved',
			'moderation_notify',
			'comments_notify',
			'thread_comments',
			'thread_comments_depth',
			'close_comments_for_old_posts',
			'close_comments_days_old',
			'page_comments',
			'comments_per_page',
			'default_comments_page',
			'comment_order',
			'comment_max_links',
			'show_avatars',
			'avatar_rating',
			'avatar_default',
			'show_comments_cookies_opt_in',
			// Media.
			'thumbnail_size_w',
			'thumbnail_size_h',
			'medium_size_w',
			'medium_size_h',
			'large_size_w',
			'large_size_h',
			'uploads_use_yearmonth_folders',
			// Permalinks.
			'permalink_structure',
			'category_base',
			'tag_base',
			// Privacy.
			'wp_page_for_privacy_policy',
			// Site identity.
			'site_icon',
			// Theme & Plugins.
			'active_plugins',
			'template',
			'stylesheet',
			'current_theme',
			// Users.
			'default_role',
			'users_can_register',
			// WooCommerce — General / Store.
			'woocommerce_currency',
			'woocommerce_default_country',
			'woocommerce_store_address',
			'woocommerce_store_address_2',
			'woocommerce_store_city',
			'woocommerce_store_postcode',
			'woocommerce_allowed_countries',
			'woocommerce_specific_allowed_countries',
			'woocommerce_all_except_countries',
			'woocommerce_default_customer_address',
			'woocommerce_enable_coupons',
			'woocommerce_calc_discounts_sequentially',
			'woocommerce_currency_pos',
			'woocommerce_price_thousand_sep',
			'woocommerce_price_decimal_sep',
			'woocommerce_price_num_decimals',
			// WooCommerce — Tax.
			'woocommerce_calc_taxes',
			'woocommerce_prices_include_tax',
			'woocommerce_tax_based_on',
			'woocommerce_shipping_tax_class',
			'woocommerce_tax_round_at_subtotal',
			'woocommerce_tax_classes',
			'woocommerce_tax_display_shop',
			'woocommerce_tax_display_cart',
			'woocommerce_price_display_suffix',
			'woocommerce_tax_total_display',
			// WooCommerce — Products.
			'woocommerce_weight_unit',
			'woocommerce_dimension_unit',
			'woocommerce_manage_stock',
			'woocommerce_notify_low_stock',
			'woocommerce_notify_no_stock',
			'woocommerce_stock_email_recipient',
			'woocommerce_low_stock_amount',
			'woocommerce_shop_page_id',
			'woocommerce_cart_redirect_after_add',
			'woocommerce_enable_ajax_add_to_cart',
			'woocommerce_enable_reviews',
			'woocommerce_review_rating_verification_label',
			'woocommerce_review_rating_verification_required',
			'woocommerce_enable_review_rating',
			'woocommerce_review_rating_required',
			'woocommerce_hold_stock_minutes',
			'woocommerce_hide_out_of_stock_items',
			'woocommerce_stock_format',
			'woocommerce_file_download_method',
			// WooCommerce — Shipping.
			'woocommerce_ship_to_countries',
			'woocommerce_ship_to_destination',
			'woocommerce_enable_shipping_calc',
			'woocommerce_shipping_cost_requires_address',
			// WooCommerce — Accounts & Privacy.
			'woocommerce_enable_guest_checkout',
			'woocommerce_enable_checkout_login_reminder',
			'woocommerce_enable_signup_and_login_from_checkout',
			'woocommerce_enable_myaccount_registration',
			'woocommerce_registration_generate_password',
			'woocommerce_registration_generate_username',
			'woocommerce_erasure_request_removes_order_data',
			'woocommerce_erasure_request_removes_download_data',
			'woocommerce_registration_privacy_policy_text',
			'woocommerce_checkout_privacy_policy_text',
			// WooCommerce — Emails.
			'woocommerce_email_from_name',
			'woocommerce_email_from_address',
			'woocommerce_email_header_image',
			'woocommerce_email_footer_text',
			'woocommerce_email_base_color',
			// WooCommerce — Pages.
			'woocommerce_cart_page_id',
			'woocommerce_checkout_page_id',
			'woocommerce_myaccount_page_id',
			'woocommerce_terms_page_id',
		);

		/**
		 * Options that can be written (safe subset of readable).
		 *
		 * @var array
		 */
		private const WRITABLE_OPTIONS = array(
			// General.
			'blogname',
			'blogdescription',
			'admin_email',
			'date_format',
			'time_format',
			'timezone_string',
			'gmt_offset',
			'WPLANG',
			'start_of_week',
			// Writing.
			'default_category',
			'default_post_format',
			'use_smilies',
			'ping_sites',
			// Reading.
			'posts_per_page',
			'posts_per_rss',
			'rss_use_excerpt',
			'show_on_front',
			'page_on_front',
			'page_for_posts',
			'blog_public',
			// Discussion.
			'default_comment_status',
			'default_pingback_flag',
			'default_ping_status',
			'require_name_email',
			'comment_moderation',
			'comment_previously_approved',
			'moderation_notify',
			'comments_notify',
			'thread_comments',
			'thread_comments_depth',
			'close_comments_for_old_posts',
			'close_comments_days_old',
			'page_comments',
			'comments_per_page',
			'default_comments_page',
			'comment_order',
			'comment_max_links',
			'show_avatars',
			'avatar_rating',
			'avatar_default',
			'show_comments_cookies_opt_in',
			// Media.
			'thumbnail_size_w',
			'thumbnail_size_h',
			'medium_size_w',
			'medium_size_h',
			'large_size_w',
			'large_size_h',
			'uploads_use_yearmonth_folders',
			// Permalinks.
			'permalink_structure',
			'category_base',
			'tag_base',
			// Privacy.
			'wp_page_for_privacy_policy',
			// Site identity.
			'site_icon',
			// Users.
			'default_role',
			'users_can_register',
			// WooCommerce — General / Store.
			'woocommerce_currency',
			'woocommerce_default_country',
			'woocommerce_store_address',
			'woocommerce_store_address_2',
			'woocommerce_store_city',
			'woocommerce_store_postcode',
			'woocommerce_allowed_countries',
			'woocommerce_specific_allowed_countries',
			'woocommerce_all_except_countries',
			'woocommerce_default_customer_address',
			'woocommerce_enable_coupons',
			'woocommerce_calc_discounts_sequentially',
			'woocommerce_currency_pos',
			'woocommerce_price_thousand_sep',
			'woocommerce_price_decimal_sep',
			'woocommerce_price_num_decimals',
			// WooCommerce — Tax.
			'woocommerce_calc_taxes',
			'woocommerce_prices_include_tax',
			'woocommerce_tax_based_on',
			'woocommerce_shipping_tax_class',
			'woocommerce_tax_round_at_subtotal',
			'woocommerce_tax_classes',
			'woocommerce_tax_display_shop',
			'woocommerce_tax_display_cart',
			'woocommerce_price_display_suffix',
			'woocommerce_tax_total_display',
			// WooCommerce — Products.
			'woocommerce_weight_unit',
			'woocommerce_dimension_unit',
			'woocommerce_manage_stock',
			'woocommerce_notify_low_stock',
			'woocommerce_notify_no_stock',
			'woocommerce_stock_email_recipient',
			'woocommerce_low_stock_amount',
			'woocommerce_shop_page_id',
			'woocommerce_cart_redirect_after_add',
			'woocommerce_enable_ajax_add_to_cart',
			'woocommerce_enable_reviews',
			'woocommerce_review_rating_verification_label',
			'woocommerce_review_rating_verification_required',
			'woocommerce_enable_review_rating',
			'woocommerce_review_rating_required',
			'woocommerce_hold_stock_minutes',
			'woocommerce_hide_out_of_stock_items',
			'woocommerce_stock_format',
			'woocommerce_file_download_method',
			// WooCommerce — Shipping.
			'woocommerce_ship_to_countries',
			'woocommerce_enable_shipping_calc',
			'woocommerce_shipping_cost_requires_address',
			'woocommerce_ship_to_destination',
			// WooCommerce — Accounts & Privacy.
			'woocommerce_enable_guest_checkout',
			'woocommerce_enable_checkout_login_reminder',
			'woocommerce_enable_signup_and_login_from_checkout',
			'woocommerce_enable_myaccount_registration',
			'woocommerce_registration_generate_password',
			'woocommerce_registration_generate_username',
			'woocommerce_erasure_request_removes_order_data',
			'woocommerce_erasure_request_removes_download_data',
			'woocommerce_registration_privacy_policy_text',
			'woocommerce_checkout_privacy_policy_text',
			// WooCommerce — Emails.
			'woocommerce_email_from_name',
			'woocommerce_email_from_address',
			'woocommerce_email_header_image',
			'woocommerce_email_footer_text',
			'woocommerce_email_base_color',
			// WooCommerce — Pages.
			'woocommerce_cart_page_id',
			'woocommerce_checkout_page_id',
			'woocommerce_myaccount_page_id',
			'woocommerce_terms_page_id',
		);

		/**
		 * Get one or all whitelisted options.
		 *
		 * @param array $input Ability input parameters.
		 * @return array
		 */
		public static function get_option( array $input ): array {
			$name = sanitize_text_field( $input['name'] ?? '' );

			// Return a specific option.
			if ( ! empty( $name ) ) {
				if ( ! in_array( $name, self::READABLE_OPTIONS, true ) ) {
					return array(
						'success'           => false,
						'message'           => sprintf( 'Option "%s" is not in the readable whitelist.', $name ),
						'available_options' => self::READABLE_OPTIONS,
					);
				}

				return array(
					'success' => true,
					'options' => array( $name => get_option( $name ) ),
				);
			}

			// Return all whitelisted options.
			$options = array();
			foreach ( self::READABLE_OPTIONS as $option_name ) {
				$value = get_option( $option_name, '__SENTINEL_NOT_SET__' );
				if ( '__SENTINEL_NOT_SET__' !== $value ) {
					$options[ $option_name ] = $value;
				}
			}

			return array(
				'success' => true,
				'options' => $options,
			);
		}

		/**
		 * Update a whitelisted option.
		 *
		 * @param array $input Ability input parameters.
		 * @return array
		 */
		public static function update_option( array $input ): array {
			$name = sanitize_text_field( $input['name'] ?? '' );

			if ( empty( $name ) ) {
				return array(
					'success' => false,
					'message' => 'Option name is required.',
				);
			}

			if ( ! in_array( $name, self::WRITABLE_OPTIONS, true ) ) {
				$reason = in_array( $name, self::READABLE_OPTIONS, true )
				? 'This option is read-only for safety (changing it could break the site).'
				: 'This option is not in the whitelist.';

				return array(
					'success'          => false,
					'message'          => sprintf( 'Cannot write option "%s". %s', $name, $reason ),
					'writable_options' => self::WRITABLE_OPTIONS,
				);
			}

			if ( ! array_key_exists( 'value', $input ) ) {
				return array(
					'success' => false,
					'message' => 'Option value is required.',
				);
			}

			$old_value = get_option( $name );
			$new_value = $input['value'];

			// Sanitize based on expected type.
			if ( is_string( $new_value ) ) {
				$new_value = sanitize_text_field( $new_value );
			} elseif ( is_numeric( $new_value ) ) {
				$new_value = is_int( $new_value ) ? (int) $new_value : (float) $new_value;
			}

			update_option( $name, $new_value );

			// Flush rewrite rules when permalink structure changes.
			if ( 'permalink_structure' === $name ) {
				flush_rewrite_rules();
			}

			return array(
				'success'   => true,
				'name'      => $name,
				'old_value' => $old_value,
				'new_value' => $new_value,
				'message'   => sprintf( 'Option "%s" updated.', $name ),
			);
		}
	}

}
