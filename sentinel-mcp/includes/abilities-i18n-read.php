<?php

declare(strict_types=1);

namespace SentinelMCP;

use SentinelMCP\Abilities\Registry;
use SentinelMCP\Abilities\I18n\List_Languages_Ability;
use SentinelMCP\Abilities\I18n\List_Translations_For_Post_Ability;
use SentinelMCP\Abilities\I18n\Get_Post_In_Language_Ability;
use SentinelMCP\Abilities\I18n\List_String_Translations_Ability;

/**
 * I18n read abilities (Sprint 4.1).
 *
 * Four read-only abilities that work transparently across Polylang, WPML and
 * TranslatePress. Translation creation/sync are reserved for the Premium edition.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-i18n',
			array(
				'label'       => __('Multilingual (read-only)', 'mcp-sentinel'),
				'description' => __('Read languages, post translations and string translations from Polylang, WPML or TranslatePress. Writing translations is Premium.', 'mcp-sentinel'),
			)
		);
	}
);

Registry::register(new List_Languages_Ability());
Registry::register(new List_Translations_For_Post_Ability());
Registry::register(new Get_Post_In_Language_Ability());
Registry::register(new List_String_Translations_Ability());
Registry::init();

/*
 * MCP annotations summary for this file:
 *
 *   i18n-list-languages              readOnly idempotent
 *   i18n-list-translations-for-post  readOnly idempotent
 *   i18n-get-post-in-language        readOnly idempotent
 *   i18n-list-string-translations    readOnly idempotent
 */
