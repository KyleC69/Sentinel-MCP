<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Chat Provider Registry — centralizes AI provider metadata.
 *
 * Provides a filterable registry of chat AI providers, their models,
 * WordPress connector mappings, and tool limits. Other plugins can
 * extend the list via the `sentinel_chat_providers` filter.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @since      2.0.2
 */

defined('ABSPATH') || exit;

/**
 * Chat Provider Registry.
 */
class Chat_Provider_Registry
{

    /**
     * Get the available AI providers with their models.
     *
     * @return array Filtered provider metadata.
     */
    public static function get_providers(): array
    {
        $providers = array(
            'anthropic'  => array(
                'label'   => 'Anthropic Claude',
                'models'  => array(
                    'claude-sonnet-4-6'  => 'Claude Sonnet 4.6',
                    'claude-opus-4-6'    => 'Claude Opus 4.6',
                    'claude-haiku-4-5'   => 'Claude Haiku 4.5',
                ),
                'default' => 'claude-sonnet-4-6',
            ),
            'openai'     => array(
                'label'   => 'OpenAI',
                'models'  => array(
                    'gpt-4o'      => 'GPT-4o',
                    'gpt-4o-mini' => 'GPT-4o Mini',
                    'o3'          => 'o3',
                    'o4-mini'     => 'o4 Mini',
                ),
                'default' => 'gpt-4o',
            ),
            'gemini'     => array(
                'label'   => 'Google Gemini',
                'models'  => array(
                    'gemini-2.5-pro'   => 'Gemini 2.5 Pro',
                    'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                ),
                'default' => 'gemini-2.5-flash',
            ),
            'openrouter' => array(
                'label'   => 'OpenRouter',
                'models'  => array(
                    'anthropic/claude-sonnet-4'    => 'Claude Sonnet 4 (OpenRouter)',
                    'openai/gpt-4o'               => 'GPT-4o (OpenRouter)',
                    'google/gemini-2.5-flash'     => 'Gemini 2.5 Flash (OpenRouter)',
                    'meta-llama/llama-4-maverick' => 'Llama 4 Maverick',
                ),
                'default' => 'anthropic/claude-sonnet-4',
            ),
            'ollama'     => array(
                'label'   => 'Ollama Cloud',
                'models'  => array(
                    'ministral-3:3b'        => 'Ministral 3 3B — Free',
                    'qwen3.5:4b'            => 'Qwen 3.5 4B — Free',
                    'nemotron-3-nano:4b'    => 'Nemotron 3 Nano 4B — Free',
                    'ministral-3:8b'        => 'Ministral 3 8B — Free',
                    'qwen3.5:9b'            => 'Qwen 3.5 9B — Free',
                    'devstral-small:24b'    => 'Devstral Small 24B',
                    'gemma4:27b'            => 'Gemma 4 27B',
                    'qwen3.5:32b'           => 'Qwen 3.5 32B',
                    'nemotron-3-super:120b' => 'Nemotron 3 Super 120B — Premium',
                ),
                'default' => 'qwen3.5:4b',
            ),
        );

        /**
         * Filter the available chat AI providers.
         *
         * @since 2.0.2
         *
         * @param array $providers Provider metadata keyed by slug.
         */
        return apply_filters('sentinel_chat_providers', $providers);
    }

    /**
     * Get the WordPress connector map for providers.
     *
     * @return array Filtered connector metadata.
     */
    public static function get_connector_map(): array
    {
        $map = array(
            'anthropic'  => array(
                'connector_id' => 'anthropic',
                'env_var'      => 'ANTHROPIC_API_KEY',
                'option'       => 'connectors_ai_anthropic_api_key',
            ),
            'openai'     => array(
                'connector_id' => 'openai',
                'env_var'      => 'OPENAI_API_KEY',
                'option'       => 'connectors_ai_openai_api_key',
            ),
            'gemini'     => array(
                'connector_id' => 'google',
                'env_var'      => 'GOOGLE_API_KEY',
                'option'       => 'connectors_ai_google_api_key',
            ),
            'openrouter' => array(
                'connector_id' => 'openrouter',
                'env_var'      => 'OPENROUTER_API_KEY',
                'option'       => 'connectors_ai_openrouter_api_key',
            ),
            'ollama'     => array(
                'connector_id' => 'ollama',
                'env_var'      => 'OLLAMA_API_KEY',
                'option'       => 'connectors_ai_ollama_api_key',
            ),
        );

        /**
         * Filter the WordPress connector map for chat providers.
         *
         * @since 2.0.2
         *
         * @param array $map Connector metadata keyed by provider slug.
         */
        return apply_filters('sentinel_chat_connector_map', $map);
    }

    /**
     * Get the maximum number of direct tools allowed per provider.
     *
     * Anthropic has generous TPM limits and supports many tools natively.
     * OpenAI/Gemini/OpenRouter have strict TPM limits on lower-tier plans,
     * so we use "discovery mode" (2 meta-tools) instead of sending all tools.
     *
     * @return array Filtered tool limits keyed by provider slug.
     */
    public static function get_provider_tool_limits(): array
    {
        $limits = array(
            'anthropic'  => 128,
            'openai'     => 0,  // Discovery mode: use meta-tools.
            'gemini'     => 0,  // Discovery mode: use meta-tools.
            'openrouter' => 0,  // Discovery mode: use meta-tools.
            'ollama'     => 115,
        );

        /**
         * Filter the provider tool limits.
         *
         * @since 2.0.2
         *
         * @param array $limits Tool limits keyed by provider slug.
         */
        return apply_filters('sentinel_chat_provider_tool_limits', $limits);
    }
}
