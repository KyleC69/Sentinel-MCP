<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Theme\Get_Theme_Mod_Ability;

/**
 * Theme Mods Abilities.
 *
 * Read theme modifications (custom_logo, colors, etc.)
 * for the active WordPress theme.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

Registry::register(new Get_Theme_Mod_Ability());
Registry::init();
