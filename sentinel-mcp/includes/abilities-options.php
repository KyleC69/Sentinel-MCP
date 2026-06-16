<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Options\Get_Option_Ability;
use SentinelMCP\Abilities\Options\List_Options_By_Prefix_Ability;
use SentinelMCP\Abilities\Options\List_Registered_Settings_Ability;

/**
 * WordPress Options Abilities.
 *
 * Read WordPress options through a security whitelist.
 * Dangerous options (siteurl, home, active_plugins) are read-only.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

Registry::register(new Get_Option_Ability());
Registry::register(new List_Options_By_Prefix_Ability());
Registry::register(new List_Registered_Settings_Ability());
Registry::init();
