<?php

/**
 * Unit tests for REST_Chat provider endpoints.
 *
 * @package Sentinel-MCP
 */

use PHPUnit\Framework\TestCase;
use SentinelMCP\REST_Chat;
use SentinelMCP\Chat_Engine;

/**
 * Tests REST chat provider listing, switching, and conversation creation.
 */
class RestChatProviderEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        reset_wp_state();
    }

    // ─── handle_providers tests ────────────────────────────────────

    /** @test */
    public function handle_providers_returns_success(): void
    {
        $response = REST_Chat::handle_providers();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('providers', $data);
        $this->assertArrayHasKey('default', $data);
    }

    /** @test */
    public function handle_providers_includes_all_providers(): void
    {
        $response = REST_Chat::handle_providers();
        $data     = $response->get_data();
        $providers = $data['providers'];

        $this->assertArrayHasKey('anthropic', $providers);
        $this->assertArrayHasKey('openai', $providers);
        $this->assertArrayHasKey('gemini', $providers);
        $this->assertArrayHasKey('openrouter', $providers);
        $this->assertArrayHasKey('ollama', $providers);
    }

    /** @test */
    public function handle_providers_default_matches_engine_default(): void
    {
        $response = REST_Chat::handle_providers();
        $data     = $response->get_data();

        $this->assertEquals(Chat_Engine::get_default_provider(), $data['default']);
    }

    /** @test */
    public function handle_providers_reflects_key_configuration(): void
    {
        update_option('connectors_ai_openrouter_api_key', 'sk-or-test');

        $response  = REST_Chat::handle_providers();
        $providers = $response->get_data()['providers'];

        $this->assertTrue($providers['openrouter']['has_key']);
        $this->assertEquals('connectors_api', $providers['openrouter']['key_source']);
    }

    // ─── handle_create_conversation tests ──────────────────────────

    /** @test */
    public function handle_create_conversation_uses_default_provider_and_model(): void
    {
        global $_wp_current_user_id;
        $_wp_current_user_id = 42;

        $request = $this->create_request([]);
        $response = REST_Chat::handle_create_conversation($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('conversation', $data);
        $this->assertEquals(Chat_Engine::get_default_provider(), $data['conversation']['provider']);
    }

    /** @test */
    public function handle_create_conversation_allows_explicit_provider(): void
    {
        global $_wp_current_user_id;
        $_wp_current_user_id = 42;

        $request = $this->create_request(['provider' => 'ollama', 'model' => 'qwen3.5:4b']);
        $response = REST_Chat::handle_create_conversation($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertEquals('ollama', $data['conversation']['provider']);
        $this->assertEquals('qwen3.5:4b', $data['conversation']['model']);
    }

    /** @test */
    public function handle_create_conversation_uses_default_model_for_provider(): void
    {
        global $_wp_current_user_id;
        $_wp_current_user_id = 42;

        $request = $this->create_request(['provider' => 'openai']);
        $response = REST_Chat::handle_create_conversation($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertEquals('openai', $data['conversation']['provider']);
        $this->assertEquals('gpt-4o', $data['conversation']['model']);
    }

    // ─── handle_switch_provider tests ──────────────────────────────

    /** @test */
    public function handle_switch_provider_rejects_unknown_provider(): void
    {
        global $_wp_current_user_id;
        $_wp_current_user_id = 42;

        // Create a conversation first.
        $createReq = $this->create_request([]);
        $createRes = REST_Chat::handle_create_conversation($createReq);
        $convId    = $createRes->get_data()['conversation']['id'];

        $request = $this->create_request([
            'conversation_id' => $convId,
            'provider'        => 'unknown-provider',
            'model'           => 'some-model',
        ]);
        $response = REST_Chat::handle_switch_provider($request);
        $data = $response->get_data();

        $this->assertFalse($data['success']);
        $this->assertEquals(400, $response->get_status());
    }

    /** @test */
    public function handle_switch_provider_updates_existing_conversation(): void
    {
        global $_wp_current_user_id;
        $_wp_current_user_id = 42;

        $createReq = $this->create_request(['provider' => 'anthropic']);
        $createRes = REST_Chat::handle_create_conversation($createReq);
        $convId    = $createRes->get_data()['conversation']['id'];

        $request = $this->create_request([
            'conversation_id' => $convId,
            'provider'        => 'gemini',
            'model'           => 'gemini-2.5-pro',
        ]);
        $response = REST_Chat::handle_switch_provider($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);

        // Verify via GET.
        $getReq  = $this->create_request(['id' => $convId]);
        $getRes  = REST_Chat::handle_get_conversation($getReq);
        $conv    = $getRes->get_data()['conversation'];

        $this->assertEquals('gemini', $conv['provider']);
        $this->assertEquals('gemini-2.5-pro', $conv['model']);
    }

    // ─── handle_get_conversation tests ─────────────────────────────

    /** @test */
    public function handle_get_conversation_returns_404_for_missing(): void
    {
        $request  = $this->create_request(['id' => 99999]);
        $response = REST_Chat::handle_get_conversation($request);

        $this->assertEquals(404, $response->get_status());
        $this->assertFalse($response->get_data()['success']);
    }

    // ─── Permission tests ──────────────────────────────────────────

    /** @test */
    public function check_permissions_returns_true_for_manage_options(): void
    {
        global $_wp_current_user_caps;
        $_wp_current_user_caps = ['manage_options' => true];

        $this->assertTrue(REST_Chat::check_permissions());
    }

    /** @test */
    public function check_permissions_returns_false_without_manage_options(): void
    {
        global $_wp_current_user_caps;
        $_wp_current_user_caps = ['manage_options' => false];

        $this->assertFalse(REST_Chat::check_permissions());
    }

    // ─── Helper ──────────────────────────────────────────────────────

    /**
     * Build a mock WP_REST_Request with the given parameters.
     *
     * @param array $params Request parameters.
     * @return \WP_REST_Request
     */
    private function create_request(array $params): \WP_REST_Request
    {
        return new class($params) extends \WP_REST_Request {
            private array $mock_params;
            public function __construct(array $params)
            {
                $this->mock_params = $params;
            }
            public function get_param(string $key)
            {
                return $this->mock_params[$key] ?? null;
            }
        };
    }
}
