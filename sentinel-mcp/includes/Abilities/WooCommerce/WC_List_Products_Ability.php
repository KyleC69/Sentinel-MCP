<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\WooCommerce;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * List WooCommerce products ability.
 *
 * Lists products with id, name, type, status, sku, price, stock status and image.
 */
class WC_List_Products_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/wc-list-products';
    }

    public static function label(): string
    {
        return 'List WooCommerce products';
    }

    public static function category(): string
    {
        return 'sentinel-wc-read';
    }

    public static function description(): string
    {
        return 'Read-only. Lists products with id, name, type (simple/variable/grouped/external), status, sku, price, regular_price, sale_price, stock_status, permalink and featured image URL. Paginated 1-50. Alias: count is also accepted instead of per_page.';
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
        return \SentinelMCP\SENTINEL_ability_permission('read');
    }

    public static function execute(array $input = array()): array
    {
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
                'id'            => (int) $product->get_id(),
                'name'          => (string) $product->get_name(),
                'type'          => (string) $product->get_type(),
                'status'        => (string) $product->get_status(),
                'sku'           => (string) $product->get_sku(),
                'price'         => (string) $product->get_price(),
                'regular_price' => (string) $product->get_regular_price(),
                'sale_price'    => (string) $product->get_sale_price(),
                'stock_status'  => (string) $product->get_stock_status(),
                'featured'      => (bool) $product->get_featured(),
                'permalink'     => (string) $product->get_permalink(),
                'image_url'     => $image_url,
            );
        }

        return array(
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => (int) $result->total,
            'total_pages' => (int) $result->max_num_pages,
            'items'       => $items,
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
