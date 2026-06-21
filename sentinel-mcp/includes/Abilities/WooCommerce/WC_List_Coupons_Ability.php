<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\WooCommerce;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * List WooCommerce coupons ability.
 *
 * Lists shop_coupon entries with code, discount type, amount, usage and expiry.
 */
class WC_List_Coupons_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/wc-list-coupons';
    }

    public static function label(): string
    {
        return 'List WooCommerce coupons';
    }

    public static function category(): string
    {
        return 'sentinel-wc-read';
    }

    public static function description(): string
    {
        return 'Read-only. Lists shop_coupon entries with code, discount_type, amount, usage_count, usage_limit and expiry_date.';
    }

    public static function input_schema(): array
    {
        return array(
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
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => true,
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\SENTINEL_ability_permission('manage_woocommerce');
    }

    public static function execute(array $input = array()): array
    {
        $per_page = isset($input['per_page']) ? max(1, min(100, (int) $input['per_page'])) : 25;
        $page     = isset($input['page']) ? max(1, (int) $input['page']) : 1;

        $query = new \WP_Query(
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
                $coupon = new \WC_Coupon($post->ID);
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
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
