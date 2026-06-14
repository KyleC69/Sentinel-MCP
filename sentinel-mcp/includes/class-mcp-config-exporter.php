<?php

namespace SentinelMCP;

/**
 * MCP client configuration exporter (Sprint 3.2).
 *
 * Generates ready-to-paste configuration snippets for the most common MCP
 * clients (Claude Desktop, ChatGPT, Cursor, Windsurf, Continue, JetBrains AI),
 * plus a curl debugging snippet.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://mcpwp.com/
 */

defined('ABSPATH') || exit;

if (! class_exists('SentinelMCP\SENTINEL_Config_Exporter')) {

	/**
	 * Builds client-specific config snippets pointing at this site's MCP server.
	 */
	class SENTINEL_Config_Exporter
	{

		/**
		 * Server name used in client configs.
		 */
		const SERVER_NAME = 'mcp-content-manager';

		/**
		 * URL of this site's MCP endpoint.
		 */
		public static function endpoint_url(): string
		{
			return (string) rest_url('mcp/mcp-adapter-default-server');
		}

		/**
		 * Available format keys.
		 *
		 * @return array<string,string> Map of slug => display label.
		 */
		public static function clients(): array
		{
			return array(
				'claude_desktop' => 'Claude Desktop',
				'chatgpt'        => 'ChatGPT',
				'cursor'         => 'Cursor',
				'windsurf'       => 'Windsurf',
				'continue'       => 'Continue',
				'jetbrains'      => 'JetBrains AI',
				'curl'           => 'curl (debug)',
			);
		}

		/**
		 * Generate a config snippet for the given client.
		 *
		 * @param string $client Slug from clients().
		 * @return array{format:string,content:string,instructions:string}
		 */
		public static function for_client(string $client): array
		{
			switch ($client) {
				case 'claude_desktop':
					return self::for_claude_desktop();
				case 'chatgpt':
					return self::for_chatgpt();
				case 'cursor':
					return self::for_cursor();
				case 'windsurf':
					return self::for_windsurf();
				case 'continue':
					return self::for_continue();
				case 'jetbrains':
					return self::for_jetbrains();
				case 'curl':
					return self::for_curl();
				default:
					return array(
						'format'       => 'text',
						'content'      => '',
						'instructions' => 'Unknown client.',
					);
			}
		}

		/**
		 * Claude Desktop / Claude Code remote MCP server config.
		 */
		public static function for_claude_desktop(): array
		{
			$payload = array(
				'mcpServers' => array(
					self::SERVER_NAME => array(
						'url' => self::endpoint_url(),
					),
				),
			);
			return array(
				'format'       => 'json',
				'content'      => self::pretty_json($payload),
				'instructions' => 'In Claude Desktop, open Settings > MCP Servers > Edit config. Paste this object inside the existing "mcpServers" block. Authenticate via the OAuth browser flow on first connect.',
			);
		}

		/**
		 * ChatGPT remote MCP server config.
		 */
		public static function for_chatgpt(): array
		{
			$payload = array(
				'name' => self::SERVER_NAME,
				'url'  => self::endpoint_url(),
			);
			return array(
				'format'       => 'json',
				'content'      => self::pretty_json($payload),
				'instructions' => 'Enable Developer Mode in ChatGPT. In Settings > MCP Servers, add a new remote server and paste this URL. ChatGPT handles OAuth registration automatically.',
			);
		}

		/**
		 * Cursor remote MCP server config.
		 */
		public static function for_cursor(): array
		{
			$payload = array(
				'mcpServers' => array(
					self::SERVER_NAME => array(
						'url' => self::endpoint_url(),
					),
				),
			);
			return array(
				'format'       => 'json',
				'content'      => self::pretty_json($payload),
				'instructions' => 'In Cursor, open Settings > MCP > Edit config and paste this object. Authenticate via the OAuth browser flow on first connect.',
			);
		}

		/**
		 * Windsurf remote MCP server config.
		 */
		public static function for_windsurf(): array
		{
			$payload = array(
				'mcpServers' => array(
					self::SERVER_NAME => array(
						'serverUrl' => self::endpoint_url(),
					),
				),
			);
			return array(
				'format'       => 'json',
				'content'      => self::pretty_json($payload),
				'instructions' => 'In Windsurf, open Settings > MCP and paste this object. Authenticate via the OAuth browser flow on first connect.',
			);
		}

		/**
		 * Continue (continue.dev) MCP server config (YAML).
		 */
		public static function for_continue(): array
		{
			$yaml  = "mcpServers:\n";
			$yaml .= '  - name: ' . self::SERVER_NAME . "\n";
			$yaml .= '    url: ' . self::endpoint_url() . "\n";
			return array(
				'format'       => 'yaml',
				'content'      => $yaml,
				'instructions' => 'Add this snippet to your Continue config.yaml under the existing "mcpServers" array.',
			);
		}

		/**
		 * JetBrains AI MCP server config.
		 */
		public static function for_jetbrains(): array
		{
			$payload = array(
				'name' => self::SERVER_NAME,
				'url'  => self::endpoint_url(),
			);
			return array(
				'format'       => 'json',
				'content'      => self::pretty_json($payload),
				'instructions' => 'In JetBrains AI Assistant settings, add a new remote MCP server and paste this URL. Authenticate via the OAuth browser flow on first connect.',
			);
		}

		/**
		 * curl one-liner for debugging.
		 */
		public static function for_curl(): array
		{
			$cmd = sprintf(
				'curl -i -X POST -H "Content-Type: application/json" -H "Authorization: Bearer YOUR_ACCESS_TOKEN" %s -d \'{"jsonrpc":"2.0","id":1,"method":"tools/list"}\'',
				escapeshellarg(self::endpoint_url())
			);
			return array(
				'format'       => 'shell',
				'content'      => $cmd,
				'instructions' => 'Replace YOUR_ACCESS_TOKEN with a valid OAuth access token. Useful to verify the endpoint responds and to list registered tools.',
			);
		}

		/**
		 * Pretty-print JSON without escaping forward slashes or unicode.
		 *
		 * @param mixed $value Value to encode.
		 */
		protected static function pretty_json($value): string
		{
			$encoded = wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			return is_string($encoded) ? $encoded : '';
		}
	}
}
