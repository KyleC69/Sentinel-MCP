<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Chat AI Engine — orchestrates AI conversations using soukicz/llm SDK.
 *
 * Handles system prompt construction, ability → tool conversion,
 * and the agentic loop (tool execution + retry) via LLMAgentClient.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @since      1.1.0
 */

defined('ABSPATH') || exit;

use Soukicz\Llm\Client\LLMClient;
use Soukicz\Llm\Client\LLMAgentClient;
use Soukicz\Llm\Client\Anthropic\AnthropicClient;
use Soukicz\Llm\Client\OpenAI\OpenAIClient;
use Soukicz\Llm\Client\OpenAI\OpenAICompatibleClient;
use Soukicz\Llm\Client\Gemini\GeminiClient;
use Soukicz\Llm\Client\Universal\LocalModel;
use Soukicz\Llm\LLMRequest;
use Soukicz\Llm\LLMResponse;
use Soukicz\Llm\LLMConversation;
use Soukicz\Llm\Message\LLMMessage;
use Soukicz\Llm\Message\LLMMessageContents;
use Soukicz\Llm\Tool\CallbackToolDefinition;

/**
 * Chat AI Engine.
 */
class Chat_Engine
{



	/**
	 * Get available providers with their models.
	 *
	 * @return array
	 */
	public static function get_available_providers(): array
	{
		$providers = Chat_Provider_Registry::get_providers();

		// Mark which ones have API keys configured and their source.
		foreach ($providers as $id => &$provider) {
			$provider['has_key']    = self::has_api_key($id);
			$provider['key_source'] = self::get_api_key_source($id);
		}

		return $providers;
	}

	/**
	 * Get the default provider slug.
	 *
	 * @return string
	 */
	public static function get_default_provider(): string
	{
		return get_option('SENTINEL_chat_default_provider', 'anthropic');
	}

	/**
	 * Get the default model for a provider.
	 *
	 * @param string $provider Provider slug.
	 * @return string Model ID.
	 */
	public static function get_default_model(string $provider): string
	{
		return Chat_Provider_Registry::get_providers()[$provider]['default'] ?? 'claude-sonnet-4-6';
	}





	public static function get_connector_id(string $provider): string
	{
		return Chat_Provider_Registry::get_connector_map()[$provider]['connector_id'] ?? $provider;
	}

	/**
	 * Check if a provider is configured and available.
	 *
	 * Queries the AiClient registry via is_connector_configured().
	 *
	 * @param string $provider Provider slug.
	 * @return bool
	 */
	public static function has_api_key(string $provider): bool
	{
		$connector_id = self::get_connector_id($provider);

		return is_connector_configured($connector_id);
	}

	/**
	 * Get the API key for a provider.
	 *
	 * Queries the AiClient registry for the provider's authentication
	 * configuration and returns the resolved key from env, constant, or option.
	 *
	 * @param string $provider Provider slug.
	 * @return string|null
	 */
	private static function get_api_key(string $provider): ?string
	{
		$connector_id = self::get_connector_id($provider);

		if (! class_exists('\WordPress\AiClient\AiClient')) {
			return null;
		}

		$registry = \WordPress\AiClient\AiClient::defaultRegistry();

		if (! $registry->hasProvider($connector_id)) {
			return null;
		}

		$class_name = $registry->getProviderClassName($connector_id);
		if (! $class_name || ! class_exists($class_name)) {
			return null;
		}

		$provider_instance = new $class_name();
		if (! method_exists($provider_instance, 'getAuthentication')) {
			return null;
		}

		$auth = $provider_instance->getAuthentication();
		if (! is_array($auth) || ($auth['method'] ?? '') !== 'api_key') {
			return null;
		}

		$setting_name = $auth['setting_name'] ?? '';
		$env_var_name = $auth['env_var_name'] ?? '';
		$constant_name = $auth['constant_name'] ?? '';

		if ('' !== $env_var_name) {
			$env_value = getenv($env_var_name);
			if (false !== $env_value && '' !== $env_value) {
				return $env_value;
			}
		}

		if ('' !== $constant_name && defined($constant_name)) {
			$const_value = constant($constant_name);
			if (is_string($const_value) && '' !== $const_value) {
				return $const_value;
			}
		}

		if ('' !== $setting_name) {
			$db_value = get_option($setting_name, '');
			if ('' !== $db_value) {
				return $db_value;
			}
		}

		return null;
	}

	/**
	 * Get the source of the API key for a provider.
	 *
	 * Queries the AiClient registry for the provider's authentication
	 * configuration and delegates to get_connector_api_key_source().
	 *
	 * @param string $provider Provider slug.
	 * @return string 'env_var', 'constant', 'connectors_api', or 'none'.
	 */
	public static function get_api_key_source(string $provider): string
	{
		$connector_id = self::get_connector_id($provider);

		if (! class_exists('\WordPress\AiClient\AiClient')) {
			return 'none';
		}

		$registry = \WordPress\AiClient\AiClient::defaultRegistry();

		if (! $registry->hasProvider($connector_id)) {
			return 'none';
		}

		$class_name = $registry->getProviderClassName($connector_id);
		if (! $class_name || ! class_exists($class_name)) {
			return 'none';
		}

		$provider_instance = new $class_name();
		if (! method_exists($provider_instance, 'getAuthentication')) {
			return 'none';
		}

		$auth = $provider_instance->getAuthentication();
		if (! is_array($auth) || ($auth['method'] ?? '') !== 'api_key') {
			return 'none';
		}

		return get_connector_api_key_source(
			$auth['setting_name'] ?? '',
			$auth['env_var_name'] ?? '',
			$auth['constant_name'] ?? ''
		);
	}

	// ─── Client Creation ─────────────────────────────────────────────

	/**
	 * Create an LLM client for the given provider.
	 *
	 * @param string $provider_id Provider slug.
	 * @param string $api_key     API key.
	 * @return LLMClient
	 * @throws \InvalidArgumentException If unknown provider.
	 */
	private static function create_client(string $provider_id, string $api_key): LLMClient
	{
		return match ($provider_id) {
			'anthropic'  => new AnthropicClient($api_key),
			'openai'     => new OpenAIClient($api_key, ''),
			'gemini'     => new GeminiClient($api_key),
			'openrouter' => new OpenAICompatibleClient($api_key, 'https://openrouter.ai/api/v1'),
			'ollama'     => new OpenAICompatibleClient($api_key, 'https://ollama.com/v1'),
			default      => throw new \InvalidArgumentException("Unknown AI provider: {$provider_id}"),
		};
	}

	// ─── Main Processing ─────────────────────────────────────────────

	/**
	 * Process a user message and return the AI response.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $user_message    The user's message text.
	 * @param int    $user_id         WordPress user ID.
	 * @return array{success: bool, message?: array, error?: string}
	 */
	public static function process_message(int $conversation_id, string $user_message, int $user_id): array
	{
		// 0. Verify the LLM SDK is available (vendor/ must be bundled).
		if (! class_exists(LLMMessage::class)) {
			return array(
				'success' => false,
				'error'   => 'The AI SDK (soukicz/llm) is not available. The vendor/ directory may not have been deployed correctly.',
			);
		}

		// 1. Verify conversation exists and belongs to user.
		$conversation_row = Chat_DB::get_conversation($conversation_id, $user_id);
		if (! $conversation_row) {
			return array('success' => false, 'error' => 'Conversation not found.');
		}

		$provider_id = $conversation_row['provider'];
		$model_id    = $conversation_row['model'];

		// 2. Get API key.
		$api_key = self::get_api_key($provider_id);
		if (! $api_key) {
			return array(
				'success' => false,
				'error'   => sprintf('No API key configured for %s. Go to Settings to add one.', $provider_id),
			);
		}

		// 3. Rate limiting.
		$usage_key = 'SENTINEL_chat_usage_' . $user_id . '_' . gmdate('Y-m-d');
		$daily_count = (int) get_transient($usage_key);
		$daily_limit = (int) get_option('SENTINEL_chat_daily_limit', 100);

		if ($daily_count >= $daily_limit) {
			return array(
				'success' => false,
				'error'   => sprintf('Daily message limit reached (%d). Try again tomorrow.', $daily_limit),
			);
		}

		// 4. Save user message to DB.
		Chat_DB::add_message($conversation_id, 'user', $user_message);

		// 5. Load message history and build LLM conversation.
		$db_messages = Chat_DB::get_messages($conversation_id, $user_id);
		$llm_messages = self::build_llm_messages($db_messages);

		// 6. Build system prompt and prepend as first message.
		$system_prompt = self::build_system_prompt($provider_id);
		array_unshift($llm_messages, LLMMessage::createFromSystemString($system_prompt));

		$conversation_obj = new LLMConversation($llm_messages);

		// 7. Convert abilities to SDK tools.
		$tool_executions = array();
		$sdk_tools = self::convert_tools($user_id, $tool_executions, $provider_id);

		// 8. Create client and request.
		try {
			$client = self::create_client($provider_id, $api_key);
		} catch (\InvalidArgumentException $e) {
			return array('success' => false, 'error' => $e->getMessage());
		}

		$request = new LLMRequest(
			model: new LocalModel($model_id),
			conversation: $conversation_obj,
			temperature: 0.0,
			maxTokens: 4096,
			tools: $sdk_tools,
		);

		// 9. Run agentic loop.
		try {
			set_time_limit(300);

			$agent    = new LLMAgentClient();
			$response = $agent->run($client, $request);

			$assistant_text = $response->getLastText() ?? '';
			$tokens_in      = $response->getInputTokens();
			$tokens_out     = $response->getOutputTokens();
		} catch (\Throwable $e) {
			$error_message = self::parse_error($e);

			// Save error as assistant message.
			Chat_DB::add_message(
				$conversation_id,
				'assistant',
				'I encountered an error: ' . $error_message
			);

			return array('success' => false, 'error' => $error_message);
		}

		// 10. Save assistant response to DB.
		Chat_DB::add_message(
			$conversation_id,
			'assistant',
			$assistant_text,
			! empty($tool_executions) ? $tool_executions : null,
			null,
			$tokens_in,
			$tokens_out
		);

		// 11. Update rate limiting.
		set_transient($usage_key, $daily_count + 1, DAY_IN_SECONDS);

		// 12. Auto-generate title on first user message.
		if ('New conversation' === $conversation_row['title']) {
			$auto_title = mb_substr($user_message, 0, 60);
			if (mb_strlen($user_message) > 60) {
				$auto_title .= '...';
			}
			Chat_DB::update_title($conversation_id, $user_id, $auto_title);
		}

		return array(
			'success' => true,
			'message' => array(
				'role'       => 'assistant',
				'content'    => $assistant_text,
				'tool_calls' => $tool_executions,
				'tokens_in'  => $tokens_in,
				'tokens_out' => $tokens_out,
			),
		);
	}

	// ─── Conversation History → LLM Messages ─────────────────────────

	/**
	 * Convert DB messages to LLMMessage objects.
	 *
	 * @param array $db_messages Array of DB message rows.
	 * @return LLMMessage[]
	 */
	private static function build_llm_messages(array $db_messages): array
	{
		$messages = array();

		foreach ($db_messages as $row) {
			switch ($row['role']) {
				case 'user':
					$messages[] = LLMMessage::createFromUserString($row['content']);
					break;
				case 'assistant':
					$messages[] = LLMMessage::createFromAssistantString($row['content']);
					break;
				case 'system':
					$messages[] = LLMMessage::createFromSystemString($row['content']);
					break;
			}
		}

		return $messages;
	}

	// ─── System Prompt ───────────────────────────────────────────────

	/**
	 * Build the system prompt for the AI assistant.
	 *
	 * @return string
	 */
	/**
	 * Whether to use discovery mode (meta-tools) for a provider.
	 *
	 * @param string $provider_id Provider slug.
	 * @return bool
	 */
	public static function uses_discovery_mode(string $provider_id): bool
	{
		return (Chat_Provider_Registry::get_provider_tool_limits()[$provider_id] ?? 0) === 0;
	}

	private static function build_system_prompt(string $provider_id = 'anthropic'): string
	{
		$site_name  = get_bloginfo('name');
		$site_url   = home_url();
		$wp_version = get_bloginfo('version');
		$theme      = wp_get_theme();
		$theme_name = $theme->get('Name');
		$locale     = get_locale();

		$prompt = <<<PROMPT
You are an AI assistant that manages the WordPress site "{$site_name}" ({$site_url}).

## Site Information
- WordPress version: {$wp_version}
- Active theme: {$theme_name}
- Locale: {$locale}

## Your Role
You help the site administrator manage every aspect of their WordPress site through natural language conversation.

## Guidelines
1. **Explain before acting**: Always tell the user what you plan to do before executing destructive or irreversible operations.
2. **Be conservative**: Prefer read-only operations first. Only modify data when explicitly asked.
3. **One step at a time**: For complex tasks, break them into steps and explain your progress.
4. **Error handling**: If a tool call fails, explain what went wrong and suggest alternatives.
5. **Language**: Respond in the same language the user writes in.

## Important
- Never fabricate information. If you don't know something, use the available tools to look it up.
- Never expose API keys, passwords, or sensitive credentials in your responses.
- When creating or editing content, ask for confirmation on important details before proceeding.
PROMPT;

		// In discovery mode, embed the abilities catalog directly in the prompt
		// so the AI already knows what's available without an extra tool call.
		if (self::uses_discovery_mode($provider_id)) {
			$catalog = self::build_abilities_catalog();
			$prompt .= <<<DISCOVERY


## Available Abilities
You have access to ALL of the following WordPress abilities via the `execute_ability` tool.
To use one, call `execute_ability` with the ability name and parameters.
If you need the full input schema for an ability, call `list_abilities` with its name.

{$catalog}

## How to use your tools
- **execute_ability** — Execute any ability from the list above by name, passing the required parameters.
- **list_abilities** — Call with a specific ability_name to get its full input schema before executing, if you're unsure about the parameters.
DISCOVERY;
		}

		return $prompt;
	}

	/**
	 * Build a compact text catalog of all registered abilities.
	 *
	 * @return string
	 */
	private static function build_abilities_catalog(): string
	{
		if (! function_exists('wp_get_abilities')) {
			return 'No abilities registered.';
		}

		$abilities    = wp_get_abilities();
		$by_category  = array();

		foreach ($abilities as $ability) {
			$meta = $ability->get_meta();
			if (! ($meta['mcp']['public'] ?? false)) {
				continue;
			}

			$category = $meta['category'] ?? 'other';
			$name     = $ability->get_name();
			$desc     = mb_substr($ability->get_description(), 0, 100);

			if (! isset($by_category[$category])) {
				$by_category[$category] = array();
			}
			$by_category[$category][] = "- `{$name}`: {$desc}";
		}

		$lines = array();
		foreach ($by_category as $cat => $items) {
			$lines[] = "### {$cat}";
			foreach ($items as $item) {
				$lines[] = $item;
			}
		}

		return implode("\n", $lines);
	}

	// ─── Abilities → SDK Tools ───────────────────────────────────────

	/**
	 * Convert registered abilities to SDK CallbackToolDefinition objects.
	 *
	 * Uses two modes:
	 * - **Direct mode** (Anthropic): sends all abilities as individual tools.
	 * - **Discovery mode** (OpenAI/Gemini/OpenRouter): sends 2 lightweight
	 *   meta-tools (list_abilities + execute_ability) that give access to ALL
	 *   abilities without exceeding TPM limits.
	 *
	 * @param int    $user_id         WordPress user ID.
	 * @param array  &$tool_executions Reference to collect tool execution logs.
	 * @param string $provider_id     Provider slug for tool limit lookup.
	 * @return CallbackToolDefinition[]
	 */
	private static function convert_tools(int $user_id, array &$tool_executions, string $provider_id = 'openai'): array
	{
		if (! function_exists('wp_get_abilities')) {
			return array();
		}

		// Discovery mode: 2 meta-tools that give access to ALL abilities.
		if (self::uses_discovery_mode($provider_id)) {
			return self::build_discovery_tools($user_id, $tool_executions);
		}

		// Direct mode: send each ability as its own tool.
		return self::build_direct_tools($user_id, $tool_executions, $provider_id);
	}

	/**
	 * Build 2 lightweight meta-tools for discovery mode.
	 *
	 * This keeps the request under ~3K tokens while giving the AI
	 * access to ALL registered abilities via list + execute.
	 *
	 * @param int   $user_id         WordPress user ID.
	 * @param array &$tool_executions Reference to collect tool execution logs.
	 * @return CallbackToolDefinition[]
	 */
	private static function build_discovery_tools(int $user_id, array &$tool_executions): array
	{
		$tools = array();

		// 1. list_abilities — returns names, descriptions, and optionally full schema.
		$tools[] = new CallbackToolDefinition(
			name: 'list_abilities',
			description: 'List all available WordPress abilities. Call with no parameters to get all abilities with short descriptions. Pass an ability_name to get its full input schema. Pass a category to filter.',
			inputSchema: array(
				'type'       => 'object',
				'properties' => array(
					'ability_name' => array(
						'type'        => 'string',
						'description' => 'Optional. Pass a specific ability name to get its full input schema and detailed description.',
					),
					'category' => array(
						'type'        => 'string',
						'description' => 'Optional. Filter abilities by category (e.g. "mcp-content", "mcp-media", "mcp-settings").',
					),
				),
			),
			handler: function (array $input) use (&$tool_executions): LLMMessageContents {
				$start_time  = microtime(true);
				$abilities   = wp_get_abilities();
				$filter_name = $input['ability_name'] ?? '';
				$filter_cat  = $input['category'] ?? '';

				$result_list = array();

				foreach ($abilities as $ability) {
					$meta = $ability->get_meta();
					if (! ($meta['mcp']['public'] ?? false)) {
						continue;
					}

					$name     = $ability->get_name();
					$category = $meta['category'] ?? '';

					// Filter by specific ability name — return full schema.
					if (! empty($filter_name) && $name === $filter_name) {
						$schema = $ability->get_input_schema();
						$result = array(
							'name'         => $name,
							'description'  => $ability->get_description(),
							'category'     => $category,
							'input_schema' => $schema,
						);

						$elapsed = round(microtime(true) - $start_time, 3);
						$tool_executions[] = array(
							'tool'    => 'list_abilities',
							'input'   => $input,
							'output'  => $result,
							'success' => true,
							'time'    => $elapsed,
						);
						return LLMMessageContents::fromString(wp_json_encode($result));
					}

					// Filter by category.
					if (! empty($filter_cat) && $category !== $filter_cat) {
						continue;
					}

					$result_list[] = array(
						'name'        => $name,
						'description' => mb_substr($ability->get_description(), 0, 120),
						'category'    => $category,
					);
				}

				// If a specific name was requested but not found.
				if (! empty($filter_name)) {
					$elapsed = round(microtime(true) - $start_time, 3);
					$tool_executions[] = array(
						'tool'    => 'list_abilities',
						'input'   => $input,
						'output'  => array('error' => 'Ability not found.'),
						'success' => false,
						'time'    => $elapsed,
					);
					return LLMMessageContents::fromErrorString(sprintf('Ability "%s" not found.', $filter_name));
				}

				$result = array(
					'total'     => count($result_list),
					'abilities' => $result_list,
				);

				$elapsed = round(microtime(true) - $start_time, 3);
				$tool_executions[] = array(
					'tool'    => 'list_abilities',
					'input'   => $input,
					'output'  => $result,
					'success' => true,
					'time'    => $elapsed,
				);
				return LLMMessageContents::fromString(wp_json_encode($result));
			}
		);

		// 2. execute_ability — execute any ability by name with parameters.
		$tools[] = new CallbackToolDefinition(
			name: 'execute_ability',
			description: 'Execute a WordPress ability by name. Use list_abilities first to discover available abilities and their parameters.',
			inputSchema: array(
				'type'       => 'object',
				'properties' => array(
					'ability_name' => array(
						'type'        => 'string',
						'description' => 'The full ability name (e.g. "sentinel/search-content", "sentinel/list-plugins").',
					),
					'params' => array(
						'type'        => 'object',
						'description' => 'The input parameters for the ability, as a JSON object. Use list_abilities with the ability_name to see the expected schema.',
					),
				),
				'required' => array('ability_name'),
			),
			handler: function (array $input) use ($user_id, &$tool_executions): LLMMessageContents {
				$start_time   = microtime(true);
				$ability_name = $input['ability_name'] ?? '';
				$params       = $input['params'] ?? array();

				if (empty($ability_name)) {
					return LLMMessageContents::fromErrorString('ability_name is required.');
				}

				try {
					$ability_obj = wp_get_ability($ability_name);
					if (! $ability_obj) {
						throw new \RuntimeException(sprintf('Ability "%s" not found. Use list_abilities to see available abilities.', $ability_name));
					}

					$result  = $ability_obj->execute($params);
					$success = ! is_wp_error($result);

					if ($success) {
						$text = is_string($result) ? $result : wp_json_encode($result);
					} else {
						$text = $result->get_error_message();
					}
				} catch (\Throwable $e) {
					$success = false;
					$text    = 'Error: ' . $e->getMessage();
					$result  = array('error' => $e->getMessage());
				}

				// Normalize output for the tool call log.
				$output_for_log = $result;
				if (is_string($result)) {
					$decoded = json_decode($result, true);
					$output_for_log = (null !== $decoded) ? $decoded : array('result' => mb_substr($result, 0, 500));
				} elseif (is_wp_error($result)) {
					$output_for_log = array('error' => $result->get_error_message());
				}

				$elapsed = round(microtime(true) - $start_time, 3);
				$tool_executions[] = array(
					'tool'    => $ability_name,
					'input'   => $params,
					'output'  => $output_for_log,
					'success' => $success,
					'time'    => $elapsed,
				);

				return $success
					? LLMMessageContents::fromString($text)
					: LLMMessageContents::fromErrorString($text);
			}
		);

		return $tools;
	}

	/**
	 * Build direct tools (one per ability) for providers with high TPM limits.
	 *
	 * @param int    $user_id         WordPress user ID.
	 * @param array  &$tool_executions Reference to collect tool execution logs.
	 * @param string $provider_id     Provider slug.
	 * @return CallbackToolDefinition[]
	 */
	private static function build_direct_tools(int $user_id, array &$tool_executions, string $provider_id): array
	{
		$abilities      = wp_get_abilities();
		$max_tools      = Chat_Provider_Registry::get_provider_tool_limits()[$provider_id] ?? 128;
		$needs_sanitize = in_array($provider_id, array('openai', 'gemini', 'openrouter'), true);
		$sdk_tools      = array();

		foreach ($abilities as $ability) {
			$meta = $ability->get_meta();

			if (! ($meta['mcp']['public'] ?? false)) {
				continue;
			}

			$ability_name = $ability->get_name();
			$description  = $ability->get_description();
			$schema       = $ability->get_input_schema();

			$tool_name = $needs_sanitize
				? preg_replace('/[^a-zA-Z0-9_-]/', '_', $ability_name)
				: $ability_name;

			if (! is_array($schema)) {
				$schema = array(
					'type'       => 'object',
					'properties' => (object) array(),
				);
			} else {
				$schema = self::sanitize_schema($schema);
			}

			$sdk_tools[] = new CallbackToolDefinition(
				name: $tool_name,
				description: $description,
				inputSchema: $schema,
				handler: function (array $input) use ($ability_name, $user_id, &$tool_executions): LLMMessageContents {
					$start_time = microtime(true);

					try {
						$ability_obj = wp_get_ability($ability_name);
						if (! $ability_obj) {
							throw new \RuntimeException(sprintf('Ability "%s" not found.', $ability_name));
						}
						$result  = $ability_obj->execute($input);
						$success = ! is_wp_error($result);

						if ($success) {
							$text = is_string($result) ? $result : wp_json_encode($result);
						} else {
							$text = $result->get_error_message();
						}
					} catch (\Throwable $e) {
						$success = false;
						$text    = 'Error: ' . $e->getMessage();
						$result  = array('error' => $e->getMessage());
					}

					$elapsed = round(microtime(true) - $start_time, 3);
					$tool_executions[] = array(
						'tool'    => $ability_name,
						'input'   => $input,
						'output'  => $result,
						'success' => $success,
						'time'    => $elapsed,
					);

					return $success
						? LLMMessageContents::fromString($text)
						: LLMMessageContents::fromErrorString($text);
				}
			);
		}

		if (count($sdk_tools) > $max_tools) {
			$sdk_tools = self::prioritize_tools($sdk_tools, $abilities, $max_tools);
		}

		return $sdk_tools;
	}

	// ─── Schema Sanitization ────────────────────────────────────

	/**
	 * Recursively sanitize a JSON Schema array for strict providers (OpenAI, Gemini).
	 *
	 * @param array $schema The schema array to sanitize.
	 * @return array The sanitized schema.
	 */
	private static function sanitize_schema(array $schema): array
	{
		static $object_keys = array(
			'properties',
			'patternProperties',
		);

		// Remove 'default' — not valid in OpenAI function schemas.
		unset($schema['default']);

		// OpenAI requires 'items' for type:array.
		if (isset($schema['type']) && 'array' === $schema['type'] && ! isset($schema['items'])) {
			$schema['items'] = array('type' => 'string');
		}

		// Ensure type:object has 'properties' defined.
		if (isset($schema['type']) && 'object' === $schema['type'] && ! isset($schema['properties'])) {
			$schema['properties'] = (object) array();
		}

		// Force object-type keys to serialize as {} when empty.
		foreach ($object_keys as $key) {
			if (! array_key_exists($key, $schema)) {
				continue;
			}

			$value = $schema[$key];

			if (is_array($value)) {
				if (empty($value)) {
					$schema[$key] = (object) array();
				} else {
					foreach ($value as $prop_name => $prop_schema) {
						if (is_array($prop_schema)) {
							$value[$prop_name] = self::sanitize_schema($prop_schema);
						}
					}
					$schema[$key] = $value;
				}
			}
		}

		// Handle additionalProperties.
		if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
			if (empty($schema['additionalProperties'])) {
				$schema['additionalProperties'] = (object) array();
			} else {
				$schema['additionalProperties'] = self::sanitize_schema($schema['additionalProperties']);
			}
		}

		// Recurse into 'items'.
		if (isset($schema['items']) && is_array($schema['items'])) {
			$schema['items'] = self::sanitize_schema($schema['items']);
		}

		// Recurse into anyOf, oneOf, allOf.
		foreach (array('anyOf', 'oneOf', 'allOf') as $combo_key) {
			if (isset($schema[$combo_key]) && is_array($schema[$combo_key])) {
				foreach ($schema[$combo_key] as $i => $sub_schema) {
					if (is_array($sub_schema)) {
						$schema[$combo_key][$i] = self::sanitize_schema($sub_schema);
					}
				}
			}
		}

		return $schema;
	}

	// ─── Tool Prioritization ────────────────────────────────────

	/**
	 * Category priority for tool selection when exceeding provider limits.
	 *
	 * @var array
	 */
	const CATEGORY_PRIORITY = array(
		'mcp-content'     => 1,
		'mcp-media'       => 2,
		'mcp-settings'    => 3,
		'mcp-users'       => 5,
		'mcp-roles'       => 5,
		'mcp-themes'      => 6,
		'mcp-plugins'     => 7,
		'mcp-menus'       => 8,
		'mcp-cache'       => 9,
		'mcp-security'    => 10,
		'mcp-diagnostics' => 12,
		'mcp-templates'   => 15,
		'mcp-theme-fse'   => 15,
		'mcp-blocks'      => 18,
		'mcp-database'    => 22,
	);

	/**
	 * Prioritize tools when provider limit is exceeded.
	 *
	 * @param CallbackToolDefinition[] $sdk_tools  Built tool definitions.
	 * @param array                    $abilities  Original abilities from wp_get_abilities().
	 * @param int                      $max_tools  Maximum tools allowed.
	 * @return CallbackToolDefinition[]
	 */
	private static function prioritize_tools(array $sdk_tools, array $abilities, int $max_tools): array
	{
		$category_map = array();
		foreach ($abilities as $ability) {
			$meta = $ability->get_meta();
			$category_map[$ability->get_name()] = $meta['category'] ?? '';
		}

		usort($sdk_tools, function ($a, $b) use ($category_map) {
			$cat_a = $category_map[$a->getName()] ?? '';
			$cat_b = $category_map[$b->getName()] ?? '';
			$pri_a = self::CATEGORY_PRIORITY[$cat_a] ?? 50;
			$pri_b = self::CATEGORY_PRIORITY[$cat_b] ?? 50;
			return $pri_a <=> $pri_b;
		});

		return array_slice($sdk_tools, 0, $max_tools);
	}

	// ─── Error Parsing ───────────────────────────────────────────────

	/**
	 * Parse a thrown exception into a user-friendly error message.
	 *
	 * @param \Throwable $e The exception.
	 * @return string
	 */
	private static function parse_error(\Throwable $e): string
	{
		$message = $e->getMessage();

		// GuzzleHttp HTTP errors.
		if ($e instanceof \GuzzleHttp\Exception\ClientException || $e instanceof \GuzzleHttp\Exception\ServerException) {
			$status = $e->getResponse()->getStatusCode();

			// Extract the provider's error detail from the response body.
			$body_raw = (string) $e->getResponse()->getBody();
			$body     = json_decode($body_raw, true);
			$detail   = '';

			if (is_array($body)) {
				// Anthropic: { "error": { "type": "...", "message": "..." } }
				if (! empty($body['error']['message'])) {
					$detail = $body['error']['message'];
				}
				// OpenAI / Gemini: { "error": { "message": "..." } }
				elseif (! empty($body['message'])) {
					$detail = $body['message'];
				}
			}

			return match ($status) {
				401     => 'Invalid API key. Please check your API key in Settings.',
				403     => 'Access denied by the AI provider. Check your API key permissions.',
				429     => 'Rate limited by the AI provider' . ($detail ? ': ' . $detail : '. Please wait a moment and try again.'),
				402     => 'Insufficient credits at the AI provider. Please check your account balance.',
				529     => 'The AI provider is temporarily overloaded. Please try again in a moment.',
				default => "AI provider returned HTTP {$status}" . ($detail ? ': ' . $detail : ': ' . $message),
			};
		}

		if ($e instanceof \GuzzleHttp\Exception\ConnectException) {
			return 'Could not connect to the AI provider. Please check your server\'s internet connection.';
		}

		return 'Unexpected error: ' . $message;
	}
}
