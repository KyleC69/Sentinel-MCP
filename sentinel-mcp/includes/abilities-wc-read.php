<?php

/**
 * WooCommerce read-only abilities (Sprint 1.6).
 *
 * Conditional on WooCommerce being active. Loaded only via the conditional
 * require_once in mcp-sentinel.php — but we still guard at the
 * top of the file with class_exists() so the file is harmless if loaded
 * directly. Editing WooCommerce data is reserved for the Premium edition.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://mcpwp.com/
 */

defined('ABSPATH') || exit;

if (! function_exists('mcpcomal_wc_redact_email')) {
	/**
	 * Redact an email address: jose@example.com → j***@example.com.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	function mcpcomal_wc_redact_email(string $email): string
	{
		if ('' === $email || false === strpos($email, '@')) {
			return '';
		}
		list($local, $domain) = explode('@', $email, 2);
		return ('' !== $local ? mb_substr($local, 0, 1) . '***' : '***') . '@' . $domain;
	}
}

if (! function_exists('mcpcomal_wc_redact_name')) {
	/**
	 * Redact a person name to initials: "Jose Conti" → "J. C.".
	 *
	 * @param string $name Full name.
	 * @return string
	 */
	function mcpcomal_wc_redact_name(string $name): string
	{
		$parts = preg_split('/\s+/', trim($name));
		if (! is_array($parts)) {
			return '';
		}
		$initials = array();
		foreach ($parts as $part) {
			if ('' === $part) {
				continue;
			}
			$initials[] = mb_strtoupper(mb_substr($part, 0, 1)) . '.';
		}
		return implode(' ', $initials);
	}
}

add_action(
	'wp_abilities_api_categories_init',
	function () {
		if (! class_exists('WooCommerce')) {
			return;
		}
		wp_register_ability_category(
			'sentinel-wc-read',
			array(
				'label'       => __('WooCommerce (read-only)', 'mcp-sentinel'),
				'description' => __('Read-only access to WooCommerce store info, products, recent orders and coupons. Editing WooCommerce data is Premium.', 'mcp-sentinel'),
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	function () {
		if (! class_exists('WooCommerce')) {
			return;
		}

		// 1. Store info.

		wp_register_ability(
			'sentinel/wc-get-store-info',
			array(
				'label'               => 'Get WooCommerce store info',
				'category'            => 'sentinel-wc-read',
				'description'         => 'Read-only. Returns the WooCommerce store basics: store name, address, base country/state, currency code and symbol, weight and dimension units, prices including or excluding tax, catalog mode flag and active language.',

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
					$base_country_state = function_exists('wc_get_base_location') ? wc_get_base_location() : array();

					return array(
						'store_name'        => (string) get_option('blogname', ''),
						'store_address_1'   => (string) get_option('woocommerce_store_address', ''),
						'store_address_2'   => (string) get_option('woocommerce_store_address_2', ''),
						'store_city'        => (string) get_option('woocommerce_store_city', ''),
						'store_postcode'    => (string) get_option('woocommerce_store_postcode', ''),
						'base_country'      => isset($base_country_state['country']) ? (string) $base_country_state['country'] : '',
						'base_state'        => isset($base_country_state['state']) ? (string) $base_country_state['state'] : '',
						'currency_code'     => function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '',
						'currency_symbol'   => function_exists('get_woocommerce_currency_symbol') ? (string) get_woocommerce_currency_symbol() : '',
						'weight_unit'       => (string) get_option('woocommerce_weight_unit', ''),
						'dimension_unit'    => (string) get_option('woocommerce_dimension_unit', ''),
						'prices_include_tax' => 'yes' === get_option('woocommerce_prices_include_tax', 'no'),
						'tax_enabled'       => 'yes' === get_option('woocommerce_calc_taxes', 'no'),
						'language'          => (string) get_locale(),
					);
				},

				'permission_callback' => function () {
					return current_user_can('manage_woocommerce') || current_user_can('read');
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

		// 2. List products.

		wp_register_ability(
			'sentinel/wc-list-products',
			array(
				'label'               => 'List WooCommerce products',
				'category'            => 'sentinel-wc-read',
				'description'         => 'Read-only. Lists products with id, name, type (simple/variable/grouped/external), status, sku, price, regular_price, sale_price, stock_status, permalink and featured image URL. Paginated 1-50. Alias: count is also accepted instead of per_page.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 20,
						),
						'count'    => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 50,
							'description' => 'Alias for per_page.',
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'status'   => array(
							'type'    => 'string',
							'default' => 'publish',
						),
						'search'   => array(
							'type'        => 'string',
							'description' => 'Optional substring to match in product name.',
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ($input = null) {
					if (! function_exists('wc_get_products')) {
						return array(
							'success' => false,
							'message' => 'WooCommerce is not active.',
						);
					}

					$per_page_raw = $input['per_page'] ?? $input['count'] ?? 20;
					$per_page     = max(1, min(50, (int) $per_page_raw));
					$page         = isset($input['page']) ? max(1, (int) $input['page']) : 1;
					$status       = isset($input['status']) ? sanitize_text_field((string) $input['status']) : 'publish';
					$search       = isset($input['search']) ? sanitize_text_field((string) $input['search']) : '';

					$args = array(
						'limit'    => $per_page,
						'page'     => $page,
						'status'   => $status,
						'paginate' => true,
					);
					if ('' !== $search) {
						$args['s'] = $search;
					}

					$result = wc_get_products($args);

					$items = array();
					foreach ($result->products as $product) {
						$image_id  = (int) $product->get_image_id();
						$image_url = $image_id ? (string) wp_get_attachment_image_url($image_id, 'full') : '';

						$items[] = array(
							'id'           => (int) $product->get_id(),
							'name'         => (string) $product->get_name(),
							'type'         => (string) $product->get_type(),
							'status'       => (string) $product->get_status(),
							'sku'          => (string) $product->get_sku(),
							'price'        => (string) $product->get_price(),
							'regular_price' => (string) $product->get_regular_price(),
							'sale_price'   => (string) $product->get_sale_price(),
							'stock_status' => (string) $product->get_stock_status(),
							'featured'     => (bool) $product->get_featured(),
							'permalink'    => (string) $product->get_permalink(),
							'image_url'    => $image_url,
						);
					}

					return array(
						'page'        => $page,
						'per_page'    => $per_page,
						'total'       => (int) $result->total,
						'total_pages' => (int) $result->max_num_pages,
						'items'       => $items,
					);
				},

				'permission_callback' => function () {
					return current_user_can('read');
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

		// 3. List recent orders (with redaction).

		wp_register_ability(
			'sentinel/wc-list-recent-orders',
			array(
				'label'               => 'List recent WooCommerce orders',
				'category'            => 'sentinel-wc-read',
				'description'         => 'Read-only. Lists recent orders with id, status, currency, total, date_created and items_count. Customer name is reduced to initials and email is redacted (j***@domain.com). Paginated 1-50. Detailed customer info and full order line breakdown are Premium. Alias: count is also accepted instead of per_page.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 20,
						),
						'count'    => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 50,
							'description' => 'Alias for per_page.',
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'status'   => array(
							'type'        => 'string',
							'description' => 'Optional WooCommerce status to filter (e.g. "processing", "completed", "on-hold"). Without prefix "wc-".',
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ($input = null) {
					if (! function_exists('wc_get_orders')) {
						return array(
							'success' => false,
							'message' => 'WooCommerce is not active.',
						);
					}

					$per_page_raw = $input['per_page'] ?? $input['count'] ?? 20;
					$per_page     = max(1, min(50, (int) $per_page_raw));
					$page         = isset($input['page']) ? max(1, (int) $input['page']) : 1;
					$status       = isset($input['status']) ? sanitize_text_field((string) $input['status']) : '';

					$args = array(
						'limit'    => $per_page,
						'page'     => $page,
						'paginate' => true,
						'orderby'  => 'date',
						'order'    => 'DESC',
					);
					if ('' !== $status) {
						$args['status'] = $status;
					}

					$result = wc_get_orders($args);
					$items  = array();

					foreach ($result->orders as $order) {
						$first_name = (string) $order->get_billing_first_name();
						$last_name  = (string) $order->get_billing_last_name();
						$full_name  = trim($first_name . ' ' . $last_name);

						$items[] = array(
							'id'             => (int) $order->get_id(),
							'number'         => (string) $order->get_order_number(),
							'status'         => (string) $order->get_status(),
							'currency'       => (string) $order->get_currency(),
							'total'          => (string) $order->get_total(),
							'date_created'   => $order->get_date_created() ? $order->get_date_created()->date('c') : '',
							'items_count'    => (int) $order->get_item_count(),
							'customer_initials' => mcpcomal_wc_redact_name($full_name),
							'email_redacted' => mcpcomal_wc_redact_email((string) $order->get_billing_email()),
							'payment_method' => (string) $order->get_payment_method(),
						);
					}

					$response = array(
						'page'        => $page,
						'per_page'    => $per_page,
						'total'       => (int) $result->total,
						'total_pages' => (int) $result->max_num_pages,
						'items'       => $items,
					);

					$hint = SENTINEL_Premium_Hints::maybe_hint(
						'wc-orders-extended',
						'wc-orders-full-details',
						__('Full customer details, line items, addresses, refunds and CSV export are available in Premium.', 'mcp-sentinel')
					);
					if (null !== $hint) {
						$response['_premium_hint'] = $hint;
					}

					return $response;
				},

				'permission_callback' => function () {
					return current_user_can('manage_woocommerce');
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

		// 4. List coupons.

		wp_register_ability(
			'sentinel/wc-list-coupons',
			array(
				'label'               => 'List WooCommerce coupons',
				'category'            => 'sentinel-wc-read',
				'description'         => 'Read-only. Lists shop_coupon entries with code, discount_type, amount, usage_count, usage_limit and expiry_date.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 25,
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),

				'execute_callback'    => function ($input = null) {
					$per_page = isset($input['per_page']) ? max(1, min(100, (int) $input['per_page'])) : 25;
					$page     = isset($input['page']) ? max(1, (int) $input['page']) : 1;

					$query = new WP_Query(
						array(
							'post_type'      => 'shop_coupon',
							'posts_per_page' => $per_page,
							'paged'          => $page,
							'post_status'    => 'publish',
							'orderby'        => 'date',
							'order'          => 'DESC',
						)
					);

					$items = array();
					foreach ($query->posts as $post) {
						if (function_exists('wc_get_coupon_id_by_code') && function_exists('WC') && class_exists('WC_Coupon')) {
							$coupon = new WC_Coupon($post->ID);
							$expiry = $coupon->get_date_expires();
							$items[] = array(
								'id'            => (int) $coupon->get_id(),
								'code'          => (string) $coupon->get_code(),
								'discount_type' => (string) $coupon->get_discount_type(),
								'amount'        => (string) $coupon->get_amount(),
								'usage_count'   => (int) $coupon->get_usage_count(),
								'usage_limit'   => (int) $coupon->get_usage_limit(),
								'expiry_date'   => $expiry ? $expiry->date('c') : '',
							);
						}
					}

					return array(
						'page'        => $page,
						'per_page'    => $per_page,
						'total'       => (int) $query->found_posts,
						'total_pages' => (int) $query->max_num_pages,
						'items'       => $items,
					);
				},

				'permission_callback' => function () {
					return current_user_can('manage_woocommerce');
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

/*
 * MCP annotations summary for this file:
 *
 *   wc-get-store-info       readOnly idempotent
 *   wc-list-products        readOnly idempotent
 *   wc-list-recent-orders   readOnly idempotent (data redacted)
 *   wc-list-coupons         readOnly idempotent
 */
