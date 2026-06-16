<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\Stats\Get_Site_Stats_Ability;
use SentinelMCP\Abilities\Stats\Get_Media_Stats_Ability;

/**
 * Site stats abilities (Sprint 1.2).
 *
 * Lightweight counts for content audits: posts per CPT and status, comments
 * per status, users per role, and media library by mime type and total size.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-stats',
			array(
				'label'       => __('Site stats and counts', 'mcp-sentinel'),
				'description' => __('Lightweight counts: posts per CPT and status, comments, users per role, media usage.', 'mcp-sentinel'),
			)
		);
	}
);

Registry::register(new Get_Site_Stats_Ability());
Registry::register(new Get_Media_Stats_Ability());
Registry::init();

if (! function_exists('mcpcomal_stats_dir_size')) {
	/**
	 * Recursively compute the size of a directory.
	 *
	 * Bounded to avoid pathological loops on misconfigured filesystems.
	 *
	 * @param string $dir Absolute path.
	 * @return int Size in bytes.
	 */
	function mcpcomal_stats_dir_size(string $dir): int
	{
		$total = 0;

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ($iterator as $file) {
				if ($file instanceof SplFileInfo && $file->isFile()) {
					$total += (int) $file->getSize();
				}
			}
		} catch (Throwable $e) {
			return $total;
		}

		return $total;
	}
}

/*
 * MCP annotations summary for this file:
 *
 *   get-site-stats   readOnly idempotent
 *   get-media-stats  readOnly idempotent
 */
