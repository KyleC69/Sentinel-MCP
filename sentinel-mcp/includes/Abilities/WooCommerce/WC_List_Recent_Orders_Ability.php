<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\WooCommerce;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * List recent WooCommerce orders ability.
 *
 * Lists recent orders with id, status, total, date and redacted customer info.
 */
class WC_List_Recent_Orders_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/wc-list-recent-orders';
    }

    public static function label(): string
    {
        return 'List recent WooCommerce orders';
    }

    public static function category(): string
    {
        return 'sentinel-wc-read';
    }

    public static function description(): string
    {
        return 'Read-only. Lists recent orders with id, status, currency, total, date_created and items_count. Customer name is reduced to initials and email is redacted (j***@domain.com). Paginated 1-50. Detailed customer info and full order line breakdown are Premium. Alias: count is also accepted instead of per_page.';
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
                    'type'        => 'string',
                    'description' => 'Optional WooCommerce status to filter (e.g. "processing", "completed", "on-hold"). Without prefix "wc-".',
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
        return \SentinelMCP\mcpcomal_ability_permission('manage_woocommerce');
    }

    public static function execute(array $input = array()): array
    {
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
                'id'                => (int) $order->get_id(),
                'number'            => (string) $order->get_order_number(),
                'status'            => (string) $order->get_status(),
                'currency'          => (string) $order->get_currency(),
                'total'             => (string) $order->get_total(),
                'date_created'      => $order->get_date_created() ? $order->get_date_created()->date('c') : '',
                'items_count'       => (int) $order->get_item_count(),
                'customer_initials' => \SentinelMCP\mcpcomal_wc_redact_name($full_name),
                'email_redacted'    => \SentinelMCP\mcpcomal_redact_email((string) $order->get_billing_email()),
                'payment_method'    => (string) $order->get_payment_method(),
            );
        }

        $response = array(
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => (int) $result->total,
            'total_pages' => (int) $result->max_num_pages,
            'items'       => $items,
        );

        return $response;
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
