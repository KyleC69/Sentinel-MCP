<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Premium admin tab (stub).
 *
 * The Lite edition does not expose a premium upsell tab. This file is kept as
 * a placeholder so the class reference remains valid if a future edition needs
 * to restore premium discovery UI.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Premium admin tab placeholder.
 *
 * @todo Restore premium feature catalog rendering if premium edition returns.
 */
class Admin_Tab_Premium extends Admin_Tab
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		parent::__construct('premium', __('Premium', 'mcp-sentinel'));
	}

	/**
	 * Render the tab content.
	 *
	 * Currently a no-op in the Lite edition.
	 *
	 * @return void
	 */
	public function render(): void
	{
		// Premium upsell UI intentionally removed per architecture review (YAGNI).
	}
}
