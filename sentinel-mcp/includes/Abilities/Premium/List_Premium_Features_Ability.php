<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Premium;

use SentinelMCP\Abilities\Ability;

defined('ABSPATH') || exit;

/**
 * List Premium features ability (stub).
 *
 * The Lite edition does not expose a premium upsell ability. This class is
 * kept as a placeholder so the ability registration and interface contract
 * remain valid if a future edition needs to restore premium discovery.
 */
class List_Premium_Features_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/list-premium-features';
    }

    public static function label(): string
    {
        return 'List Premium features';
    }

    public static function category(): string
    {
        return 'sentinel-premium-info';
    }

    public static function description(): string
    {
        return 'Read-only. Returns an empty catalog. Premium feature listing is not available in the Lite edition.';
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
            'properties'           => array(
                'total_features' => array('type' => 'integer'),
                'categories'     => array('type' => 'array'),
                'note'           => array('type' => 'string'),
            ),
            'additionalProperties' => false,
        );
    }

    public static function permission_callback(): callable
    {
        return \SentinelMCP\mcpcomal_ability_permission('read');
    }

    public static function execute(array $input = array()): array
    {
        return array(
            'total_features' => 0,
            'categories'     => array(),
            'note'           => 'Premium feature listing is not available in the Lite edition.',
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta();
    }
}
