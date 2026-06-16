<?php

/**
 * Unit tests for SENTINEL_Chat_Engine connector discovery and provider helpers.
 *
 * @package Sentinel-MCP
 */

use PHPUnit\Framework\TestCase;
use SentinelMCP\SENTINEL_Chat_Engine;

/**
 * Tests provider discovery, API key resolution, and tool-mode selection.
 */
class ChatEngineConnectorDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        reset_wp_state();
    }

    // ─── Provider list tests ───────────────────────────────────────

    /** @test */
    public function get_available_providers_returns_all_providers(): void
    {
        $providers = SENTINEL_Chat_Engine::get_available_providers();

        $this->assertArrayHasKey('anthropic', $providers);
        $this->assertArrayHasKey('openai', $providers);
        $this->assertArrayHasKey('gemini', $providers);
        $this->assertArrayHasKey('openrouter', $providers);
        $this->assertArrayHasKey('ollama', $providers);
    }

    /** @test */
    public function get_available_providers_includes_models_and_labels(): void
    {
        $providers = SENTINEL_Chat_Engine::get_available_providers();

        $this->assertEquals('Anthropic Claude', $providers['anthropic']['label']);
        $this->assertArrayHasKey('claude-sonnet-4-6', $providers['anthropic']['models']);
        $this->assertEquals('Claude Sonnet 4.6', $providers['anthropic']['models']['claude-sonnet-4-6']);
    }

    /** @test */
    public function get_available_providers_marks_has_key_false_when_unconfigured(): void
    {
        $providers = SENTINEL_Chat_Engine::get_available_providers();

        foreach ($providers as $id => $provider) {
            $this->assertFalse($provider['has_key'], "Provider {$id} should not have a key");
            $this->assertEquals('none', $provider['key_source']);
        }
    }

    /** @test */
    public function get_available_providers_detects_env_var_key(): void
    {
        putenv('ANTHROPIC_API_KEY=sk-ant-test123');

        $providers = SENTINEL_Chat_Engine::get_available_providers();

        $this->assertTrue($providers['anthropic']['has_key']);
        $this->assertEquals('env_var', $providers['anthropic']['key_source']);

        putenv('ANTHROPIC_API_KEY');
    }

    /** @test */
    public function get_available_providers_detects_option_key(): void
    {
        update_option('connectors_ai_openai_api_key', 'sk-openai-test456');

        $providers = SENTINEL_Chat_Engine::get_available_providers();

        $this->assertTrue($providers['openai']['has_key']);
        $this->assertEquals('connectors_api', $providers['openai']['key_source']);
    }

    // ─── Default provider / model tests ────────────────────────────

    /** @test */
    public function get_default_provider_returns_stored_option(): void
    {
        update_option('mcpcomal_chat_default_provider', 'gemini');

        $this->assertEquals('gemini', SENTINEL_Chat_Engine::get_default_provider());
    }

    /** @test */
    public function get_default_provider_falls_back_to_anthropic(): void
    {
        $this->assertEquals('anthropic', SENTINEL_Chat_Engine::get_default_provider());
    }

    /** @test */
    public function get_default_model_returns_provider_default(): void
    {
        $this->assertEquals('gpt-4o', SENTINEL_Chat_Engine::get_default_model('openai'));
        $this->assertEquals('qwen3.5:4b', SENTINEL_Chat_Engine::get_default_model('ollama'));
    }

    /** @test */
    public function get_default_model_returns_fallback_for_unknown_provider(): void
    {
        $this->assertEquals('claude-sonnet-4-6', SENTINEL_Chat_Engine::get_default_model('nonexistent'));
    }

    // ─── Discovery mode tests ──────────────────────────────────────

    /** @test */
    public function uses_discovery_mode_returns_true_for_openai(): void
    {
        $this->assertTrue(SENTINEL_Chat_Engine::uses_discovery_mode('openai'));
    }

    /** @test */
    public function uses_discovery_mode_returns_true_for_gemini(): void
    {
        $this->assertTrue(SENTINEL_Chat_Engine::uses_discovery_mode('gemini'));
    }

    /** @test */
    public function uses_discovery_mode_returns_true_for_openrouter(): void
    {
        $this->assertTrue(SENTINEL_Chat_Engine::uses_discovery_mode('openrouter'));
    }

    /** @test */
    public function uses_discovery_mode_returns_false_for_anthropic(): void
    {
        $this->assertFalse(SENTINEL_Chat_Engine::uses_discovery_mode('anthropic'));
    }

    /** @test */
    public function uses_discovery_mode_returns_false_for_ollama(): void
    {
        $this->assertFalse(SENTINEL_Chat_Engine::uses_discovery_mode('ollama'));
    }

    /** @test */
    public function uses_discovery_mode_returns_true_for_unknown_provider(): void
    {
        // Unknown providers default to 0 limit = discovery mode.
        $this->assertTrue(SENTINEL_Chat_Engine::uses_discovery_mode('unknown-provider'));
    }

    // ─── API key source priority tests ─────────────────────────────

    /** @test */
    public function env_var_takes_priority_over_option(): void
    {
        putenv('ANTHROPIC_API_KEY=sk-env');
        update_option('connectors_ai_anthropic_api_key', 'sk-option');

        $providers = SENTINEL_Chat_Engine::get_available_providers();

        $this->assertTrue($providers['anthropic']['has_key']);
        $this->assertEquals('env_var', $providers['anthropic']['key_source']);

        putenv('ANTHROPIC_API_KEY');
    }

    /** @test */
    public function constant_takes_priority_over_option_when_env_missing(): void
    {
        if (! defined('ANTHROPIC_API_KEY')) {
            define('ANTHROPIC_API_KEY', 'sk-const');
        }
        update_option('connectors_ai_anthropic_api_key', 'sk-option');

        $providers = SENTINEL_Chat_Engine::get_available_providers();

        $this->assertTrue($providers['anthropic']['has_key']);
        $this->assertEquals('constant', $providers['anthropic']['key_source']);
    }

    /** @test */
    public function has_api_key_returns_false_for_unmapped_provider(): void
    {
        $this->assertFalse(SENTINEL_Chat_Engine::has_api_key('nonexistent'));
    }

    /** @test */
    public function has_api_key_uses_connector_authentication_when_available(): void
    {
        global $_wp_options;
        $_wp_options['connectors_ai_google_api_key'] = 'sk-gemini';

        $this->assertTrue(SENTINEL_Chat_Engine::has_api_key('gemini'));
    }

    // ─── WP_CONNECTOR_MAP completeness tests ─────────────────────────

    /** @test */
    public function wp_connector_map_contains_all_providers(): void
    {
        $map = (new ReflectionClass(SENTINEL_Chat_Engine::class))
            ->getConstant('WP_CONNECTOR_MAP');

        $this->assertArrayHasKey('anthropic', $map);
        $this->assertArrayHasKey('openai', $map);
        $this->assertArrayHasKey('gemini', $map);
        $this->assertArrayHasKey('openrouter', $map);
        $this->assertArrayHasKey('ollama', $map);
    }

    /** @test */
    public function wp_connector_map_has_required_fields(): void
    {
        $map = (new ReflectionClass(SENTINEL_Chat_Engine::class))
            ->getConstant('WP_CONNECTOR_MAP');

        foreach ($map as $provider => $config) {
            $this->assertArrayHasKey('connector_id', $config, "{$provider} missing connector_id");
            $this->assertArrayHasKey('env_var', $config, "{$provider} missing env_var");
            $this->assertArrayHasKey('option', $config, "{$provider} missing option");
        }
    }

    /** @test */
    public function gemini_connector_id_maps_to_google(): void
    {
        $map = (new ReflectionClass(SENTINEL_Chat_Engine::class))
            ->getConstant('WP_CONNECTOR_MAP');

        $this->assertEquals('google', $map['gemini']['connector_id']);
    }

    // ─── Provider tool limits tests ────────────────────────────────

    /** @test */
    public function provider_tool_limits_match_expected_values(): void
    {
        $limits = (new ReflectionClass(SENTINEL_Chat_Engine::class))
            ->getConstant('PROVIDER_TOOL_LIMITS');

        $this->assertEquals(128, $limits['anthropic']);
        $this->assertEquals(0, $limits['openai']);
        $this->assertEquals(0, $limits['gemini']);
        $this->assertEquals(0, $limits['openrouter']);
        $this->assertEquals(115, $limits['ollama']);
    }
}
