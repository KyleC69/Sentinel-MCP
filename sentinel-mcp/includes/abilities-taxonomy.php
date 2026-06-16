<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Taxonomy\Create_Term_Ability;
use SentinelMCP\Abilities\Taxonomy\Update_Term_Ability;
use SentinelMCP\Abilities\Taxonomy\Delete_Term_Ability;

/**
 * Taxonomy CRUD Abilities.
 *
 * Create, update, and delete terms in any taxonomy.
 * Complements the existing sentinel/list-terms ability in abilities-discovery.php.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

Registry::register(new Create_Term_Ability());
Registry::register(new Update_Term_Ability());
Registry::register(new Delete_Term_Ability());
Registry::init();
