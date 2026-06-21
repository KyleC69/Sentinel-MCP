<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities;

defined('ABSPATH') || exit;

class Registry
{
    /** @var Ability[] */
    private static array $abilities = [];

    public static function register(Ability $ability): void
    {
        self::$abilities[] = $ability;
    }

    public static function init(): void
    {
        add_action('wp_abilities_api_init', [self::class, 'do_register']);
    }

    public static function do_register(): void
    {
        foreach (self::$abilities as $ability) {
            wp_register_ability(
                $ability::slug(),
                array(
                    'label'               => $ability::label(),
                    'category'            => $ability::category(),
                    'description'         => $ability::description(),
                    'input_schema'        => $ability::input_schema(),
                    'output_schema'       => $ability::output_schema(),
                    'execute_callback'    => static function ($input = null) use ($ability) {
                        return $ability::execute($input ?? array());
                    },
                    'permission_callback' => $ability::permission_callback(),
                    'meta'                => $ability::meta(),
                )
            );
        }
    }
}
