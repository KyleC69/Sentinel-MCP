<?php

/**
 * Stub for wp-admin/includes/upgrade.php — prevents fatal errors in unit tests.
 */

if (! function_exists('dbDelta')) {
    function dbDelta(string $queries, bool $execute = true): array
    {
        return [];
    }
}
