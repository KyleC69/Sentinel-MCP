<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities;

defined('ABSPATH') || exit;

interface Ability
{
    public static function slug(): string;
    public static function label(): string;
    public static function category(): string;
    public static function description(): string;
    public static function input_schema(): array;
    public static function output_schema(): array;
    public static function permission_callback(): callable;
    public static function execute(array $input = []): array;
    public static function meta(): array;
}
