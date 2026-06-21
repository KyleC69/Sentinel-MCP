<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\WooCommerce;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * Get WooCommerce store info ability.
 *
 * Returns the WooCommerce store basics: name, address, currency, units, tax settings.
 */
class WC_Get_Store_Info_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/wc-get-store-info';
    }

    public static function label(): string
    {
        return 'Get WooCommerce store info';
    }

    public static function category(): string
    {
        return 'sentinel-wc-read';
    }

    public static function description(): string
    {
        return 'Read-only. Returns the WooCommerce store basics: store name, address, base country/state, currency code and symbol, weight and dimension units, prices including or excluding tax, catalog mode flag and active language.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'properties'           => array(),
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
        return static function () {
            return current_user_can('manage_woocommerce') || current_user_can('read');
        };
    }

    public static function execute(array $input = array()): array
    {
        $base_country_state = function_exists('wc_get_base_location') ? wc_get_base_location() : array();

        return array(
            'store_name'         => (string) get_option('blogname', ''),
            'store_address_1'    => (string) get_option('woocommerce_store_address', ''),
            'store_address_2'    => (string) get_option('woocommerce_store_address_2', ''),
            'store_city'         => (string) get_option('woocommerce_store_city', ''),
            'store_postcode'     => (string) get_option('woocommerce_store_postcode', ''),
            'base_country'       => isset($base_country_state['country']) ? (string) $base_country_state['country'] : '',
            'base_state'         => isset($base_country_state['state']) ? (string) $base_country_state['state'] : '',
            'currency_code'      => function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '',
            'currency_symbol'    => function_exists('get_woocommerce_currency_symbol') ? (string) get_woocommerce_currency_symbol() : '',
            'weight_unit'        => (string) get_option('woocommerce_weight_unit', ''),
            'dimension_unit'     => (string) get_option('woocommerce_dimension_unit', ''),
            'prices_include_tax' => 'yes' === get_option('woocommerce_prices_include_tax', 'no'),
            'tax_enabled'        => 'yes' === get_option('woocommerce_calc_taxes', 'no'),
            'language'           => (string) get_locale(),
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\SENTINEL_ability_meta();
    }
}
