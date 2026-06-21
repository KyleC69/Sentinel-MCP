<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\System\List_Cron_Events_Ability;
use SentinelMCP\Abilities\System\List_User_Roles_Ability;

/**
 * System / cron read abilities (Sprint 1.8).
 *
 * Exposes a read-only view of WP-Cron events and the registered user roles.
 * Cancellation and scheduling of cron events are reserved for Premium.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

Registry::register(new List_Cron_Events_Ability());
Registry::register(new List_User_Roles_Ability());
Registry::init();

/*
 * MCP annotations summary for this file:
 *
 *   list-cron-events  readOnly idempotent
 *   list-user-roles   readOnly idempotent
 */
