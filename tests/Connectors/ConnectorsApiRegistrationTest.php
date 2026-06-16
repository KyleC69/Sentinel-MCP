<?php

/**
 * Unit tests for the WordPress 7.0 Connectors API registration.
 *
 * @package Sentinel-MCP
 */

use PHPUnit\Framework\TestCase;
use SentinelMCP\SENTINEL_Chat_Engine;

/**
 * Tests connector registration via wp_connectors_init and the
 * SENTINEL_Chat_Engine connector mapping.
 */
class ConnectorsApiRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        reset_wp_state();
        // Re-register the connectors hook after reset clears actions.
        // Use include (not require_once) so the file re-executes each test.
        include __DIR__ . '/../../sentinel-mcp/includes/class-mcp-connectors.php';
    }

    // ─── Action registration tests ─────────────────────────────────

    /** @test */
    public function connectors_file_registers_wp_connectors_init_action(): void
    {
        global $_wp_actions;

        // The file was loaded in bootstrap; verify the hook exists.
        $this->assertArrayHasKey('wp_connectors_init', $_wp_actions);
        $this->assertCount(1, $_wp_actions['wp_connectors_init']);
    }

    /** @test */
    public function wp_connectors_init_callback_skips_when_all_already_registered(): void
    {
        global $_wp_mock_connector_registered;
        $_wp_mock_connector_registered = ['ollama' => true, 'openrouter' => true];

        $registry = new class {
            public array $registered = [];
            public function register(string $id, array $config): void
            {
                $this->registered[$id] = $config;
            }
        };

        do_action('wp_connectors_init', $registry);

        $this->assertEmpty($registry->registered);
    }

    /** @test */
    public function wp_connectors_init_callback_registers_ollama_when_api_available(): void
    {
        global $_wp_actions, $_wp_mock_connector_registered;

        // Ensure mock registry is empty so wp_is_connector_registered returns false.
        $_wp_mock_connector_registered = [];

        $registry = new class {
            public array $registered = [];
            public function register(string $id, array $config): void
            {
                $this->registered[$id] = $config;
            }
        };

        do_action('wp_connectors_init', $registry);

        $this->assertArrayHasKey('ollama', $registry->registered, 'Keys: ' . implode(', ', array_keys($registry->registered)));
        $this->assertEquals('Ollama Cloud', $registry->registered['ollama']['name']);
        $this->assertEquals('ai_provider', $registry->registered['ollama']['type']);
        $this->assertEquals('mcp-sentinel', $registry->registered['ollama']['plugin']);
    }

    /** @test */
    public function wp_connectors_init_callback_registers_openrouter_when_api_available(): void
    {
        global $_wp_mock_connector_registered;
        $_wp_mock_connector_registered = [];

        $registry = new class {
            public array $registered = [];
            public function register(string $id, array $config): void
            {
                $this->registered[$id] = $config;
            }
        };

        do_action('wp_connectors_init', $registry);

        $this->assertArrayHasKey('openrouter', $registry->registered);
        $this->assertEquals('OpenRouter', $registry->registered['openrouter']['name']);
        $this->assertEquals('ai_provider', $registry->registered['openrouter']['type']);
    }

    /** @test */
    public function wp_connectors_init_callback_skips_already_registered_connectors(): void
    {
        global $_wp_mock_connector_registered;
        $_wp_mock_connector_registered = ['ollama' => true];

        $registry = new class {
            public array $registered = [];
            public function register(string $id, array $config): void
            {
                $this->registered[$id] = $config;
            }
        };

        do_action('wp_connectors_init', $registry);

        $this->assertArrayNotHasKey('ollama', $registry->registered);
        $this->assertArrayHasKey('openrouter', $registry->registered);
    }

    // ─── Authentication config tests ─────────────────────────────────

    /** @test */
    public function ollama_connector_has_api_key_auth_config(): void
    {
        global $_wp_mock_connector_registered;
        $_wp_mock_connector_registered = [];

        $registry = new class {
            public array $registered = [];
            public function register(string $id, array $config): void
            {
                $this->registered[$id] = $config;
            }
        };

        do_action('wp_connectors_init', $registry);
        $auth = $registry->registered['ollama']['authentication'];

        $this->assertEquals('api_key', $auth['method']);
        $this->assertEquals('connectors_ai_ollama_api_key', $auth['setting_name']);
        $this->assertEquals('OLLAMA_API_KEY', $auth['env_var_name']);
        $this->assertEquals('OLLAMA_API_KEY', $auth['constant_name']);
    }

    /** @test */
    public function openrouter_connector_has_api_key_auth_config(): void
    {
        global $_wp_mock_connector_registered;
        $_wp_mock_connector_registered = [];

        $registry = new class {
            public array $registered = [];
            public function register(string $id, array $config): void
            {
                $this->registered[$id] = $config;
            }
        };

        do_action('wp_connectors_init', $registry);
        $auth = $registry->registered['openrouter']['authentication'];

        $this->assertEquals('api_key', $auth['method']);
        $this->assertEquals('database', $auth['credentials_location']);
        $this->assertEquals('connectors_ai_openrouter_api_key', $auth['setting_name']);
    }

    // ─── Logo URL tests ────────────────────────────────────────────

    /** @test */
    public function registered_connectors_include_logo_url(): void
    {
        global $_wp_mock_connector_registered;
        $_wp_mock_connector_registered = [];

        $registry = new class {
            public array $registered = [];
            public function register(string $id, array $config): void
            {
                $this->registered[$id] = $config;
            }
        };

        do_action('wp_connectors_init', $registry);

        $this->assertStringContainsString(
            'assets/images/ollama-black.webp',
            $registry->registered['ollama']['logo_url']
        );
        $this->assertStringContainsString(
            'assets/images/openrouter-black.webp',
            $registry->registered['openrouter']['logo_url']
        );
    }

    // ─── Engine-to-connector mapping tests ───────────────────────────

    /** @test */
    public function engine_provider_slugs_map_to_connector_ids(): void
    {
        $map = (new ReflectionClass(SENTINEL_Chat_Engine::class))
            ->getConstant('WP_CONNECTOR_MAP');

        // These are the IDs the Connectors API registers.
        $this->assertEquals('anthropic', $map['anthropic']['connector_id']);
        $this->assertEquals('openai', $map['openai']['connector_id']);
        $this->assertEquals('google', $map['gemini']['connector_id']);
        $this->assertEquals('openrouter', $map['openrouter']['connector_id']);
        $this->assertEquals('ollama', $map['ollama']['connector_id']);
    }

    /** @test */
    public function engine_env_var_names_match_connector_auth(): void
    {
        $map = (new ReflectionClass(SENTINEL_Chat_Engine::class))
            ->getConstant('WP_CONNECTOR_MAP');

        $this->assertEquals('ANTHROPIC_API_KEY', $map['anthropic']['env_var']);
        $this->assertEquals('OPENAI_API_KEY', $map['openai']['env_var']);
        $this->assertEquals('GOOGLE_API_KEY', $map['gemini']['env_var']);
        $this->assertEquals('OPENROUTER_API_KEY', $map['openrouter']['env_var']);
        $this->assertEquals('OLLAMA_API_KEY', $map['ollama']['env_var']);
    }

    /** @test */
    public function engine_option_names_match_connector_setting_names(): void
    {
        $map = (new ReflectionClass(SENTINEL_Chat_Engine::class))
            ->getConstant('WP_CONNECTOR_MAP');

        $this->assertEquals('connectors_ai_anthropic_api_key', $map['anthropic']['option']);
        $this->assertEquals('connectors_ai_openai_api_key', $map['openai']['option']);
        $this->assertEquals('connectors_ai_google_api_key', $map['gemini']['option']);
        $this->assertEquals('connectors_ai_openrouter_api_key', $map['openrouter']['option']);
        $this->assertEquals('connectors_ai_ollama_api_key', $map['ollama']['option']);
    }
}
