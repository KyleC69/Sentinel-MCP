<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Discovery_Extended\List_Post_Types_Ability;
use SentinelMCP\Abilities\Discovery_Extended\List_Taxonomies_Ability;
use SentinelMCP\Abilities\Discovery_Extended\List_Post_Statuses_Ability;
use SentinelMCP\Abilities\Discovery_Extended\List_Shortcodes_Ability;
use SentinelMCP\Abilities\Discovery_Extended\Get_Permalink_Structure_Ability;

/**
 * Extended discovery abilities (Sprint 1.1).
 *
 * Read-only abilities that round out site discovery: full lists of post types,
 * taxonomies, post statuses, registered shortcodes, and permalink structure.
 * They reuse the existing "sentinel-discovery" category registered in
 * abilities-discovery.php.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

Registry::register(new List_Post_Types_Ability());
Registry::register(new List_Taxonomies_Ability());
Registry::register(new List_Post_Statuses_Ability());
Registry::register(new List_Shortcodes_Ability());
Registry::register(new Get_Permalink_Structure_Ability());
Registry::init();

/*
 * MCP annotations summary for this file:
 *
 *   list-post-types         readOnly idempotent  (no destructive)
 *   list-taxonomies         readOnly idempotent
 *   list-post-statuses      readOnly idempotent
 *   list-shortcodes         readOnly idempotent
 *   get-permalink-structure readOnly idempotent
 */
