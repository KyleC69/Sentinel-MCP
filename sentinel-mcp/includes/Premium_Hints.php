<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Premium hints (stub).
 *
 * The Lite edition does not surface premium upsell hints. This class is kept
 * as a placeholder so any existing call sites remain valid and simply receive
 * no hint in the Lite edition.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Premium-upsell hint emitter (no-op in Lite edition).
 */
class Premium_Hints
{

	/**
	 * Return a hint payload if not throttled, null otherwise.
	 *
	 * In the Lite edition this always returns null so no upsell hints are
	 * surfaced to AI clients.
	 *
	 * @param string $category     Category slug (unused).
	 * @param string $feature_slug Specific feature being teased (unused).
	 * @param string $message      Human-readable message (unused).
	 * @return null
	 */
	public static function maybe_hint(string $category, string $feature_slug, string $message): ?array
	{
		return null;
	}

	/**
	 * Whether the (client_id, category) pair already emitted a hint in this window.
	 *
	 * Always false in the Lite edition.
	 *
	 * @param string $client_id OAuth client id (unused).
	 * @param string $category  Category slug (unused).
	 * @return bool
	 */
	public static function is_throttled(string $client_id, string $category): bool
	{
		return false;
	}

	/**
	 * Mark the (client_id, category) pair as having received a hint.
	 *
	 * No-op in the Lite edition.
	 *
	 * @param string $client_id OAuth client id (unused).
	 * @param string $category  Category slug (unused).
	 * @return void
	 */
	public static function mark_emitted(string $client_id, string $category): void
	{
		// No-op in Lite edition.
	}

	/**
	 * Reset throttle for a category (mainly used in tests or admin tooling).
	 *
	 * No-op in the Lite edition.
	 *
	 * @param string $client_id OAuth client id (unused).
	 * @param string $category  Category slug (unused).
	 * @return void
	 */
	public static function reset(string $client_id, string $category): void
	{
		// No-op in Lite edition.
	}

	/**
	 * Compose the transient key.
	 *
	 * Kept for API compatibility; not used in the Lite edition.
	 *
	 * @param string $client_id OAuth client id.
	 * @param string $category  Category slug.
	 * @return string
	 */
	protected static function key(string $client_id, string $category): string
	{
		return 'SENTINEL_hint_' . md5($client_id . '|' . $category);
	}
}
