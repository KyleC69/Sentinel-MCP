<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * WooCommerce-specific collector.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */
class WooCommerce_Info_Collector implements System_Info_Collector_Interface
{

    /**
     * Collect WooCommerce data.
     *
     * @return array
     */
    public static function collect(): array
    {
        if (! class_exists('WooCommerce')) {
            return array(
                'active' => false,
                'note'   => 'WooCommerce is not installed or not active.',
            );
        }

        $data = array(
            'active'  => true,
            'version' => WC()->version,
        );

        // Store settings.
        $data['store'] = array(
            'currency'           => get_woocommerce_currency(),
            'currency_symbol'    => get_woocommerce_currency_symbol(),
            'currency_position'  => get_option('woocommerce_currency_pos', 'left'),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'decimal_separator'  => wc_get_price_decimal_separator(),
            'number_of_decimals' => wc_get_price_decimals(),
            'store_address'      => get_option('woocommerce_store_address', ''),
            'store_city'         => get_option('woocommerce_store_city', ''),
            'default_country'    => get_option('woocommerce_default_country', ''),
            'store_postcode'     => get_option('woocommerce_store_postcode', ''),
            'tax_enabled'        => wc_tax_enabled(),
            'shipping_enabled'   => wc_shipping_enabled(),
            'coupons_enabled'    => wc_coupons_enabled(),
        );

        // HPOS (High-Performance Order Storage).
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            $data['hpos_enabled'] = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }

        // WC database version.
        $data['db_version'] = get_option('woocommerce_db_version', '');

        // Payment gateways.
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gw_list  = array();
        foreach ($gateways as $gw) {
            if ('yes' === $gw->enabled) {
                $gw_list[] = array(
                    'id'    => $gw->id,
                    'title' => $gw->get_title(),
                );
            }
        }
        $data['active_payment_gateways'] = $gw_list;

        // Shipping methods.
        $shipping_methods = WC()->shipping()->get_shipping_methods();
        $sm_list          = array();
        foreach ($shipping_methods as $sm) {
            if ('yes' === ($sm->enabled ?? '') || (method_exists($sm, 'is_enabled') && $sm->is_enabled())) {
                $sm_list[] = array(
                    'id'    => $sm->id,
                    'title' => $sm->get_method_title(),
                );
            }
        }
        $data['active_shipping_methods'] = $sm_list;

        // WooCommerce pages.
        $wc_pages = array(
            'shop'      => array(
                'option' => 'woocommerce_shop_page_id',
                'label'  => 'Shop base',
            ),
            'cart'      => array(
                'option' => 'woocommerce_cart_page_id',
                'label'  => 'Cart',
            ),
            'checkout'  => array(
                'option' => 'woocommerce_checkout_page_id',
                'label'  => 'Checkout',
            ),
            'myaccount' => array(
                'option' => 'woocommerce_myaccount_page_id',
                'label'  => 'My account',
            ),
            'terms'     => array(
                'option' => 'woocommerce_terms_page_id',
                'label'  => 'Terms and conditions',
            ),
        );

        $pages_data = array();
        foreach ($wc_pages as $key => $page_config) {
            $page_id   = (int) get_option($page_config['option'], 0);
            $page_info = array(
                'page_name'    => $page_config['label'],
                'page_id'      => $page_id,
                'page_set'     => $page_id > 0,
                'page_exists'  => false,
                'page_visible' => false,
            );

            if ($page_id > 0) {
                $page = get_post($page_id);
                if ($page) {
                    $page_info['page_exists']  = true;
                    $page_info['page_visible'] = 'publish' === $page->post_status;
                }
            }

            $pages_data[$key] = $page_info;
        }
        $data['pages'] = $pages_data;

        // Enabled features (WC 8.0+).
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            $feature_list = array();
            $features     = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_features(true);
            foreach ($features as $slug => $feature) {
                $feature_list[$slug] = $feature['is_enabled'] ?? false;
            }
            $data['enabled_features'] = $feature_list;
        }

        // Geolocation.
        $data['geolocation_enabled'] = 'geolocation' === get_option('woocommerce_default_customer_address')
            || 'geolocation_ajax' === get_option('woocommerce_default_customer_address');

        // Logging.
        $data['log_directory'] = WC_LOG_DIR;
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Read-only check for diagnostics, WP_Filesystem not needed.
        $data['log_directory_writable'] = is_writable(WC_LOG_DIR);

        return $data;
    }
}
