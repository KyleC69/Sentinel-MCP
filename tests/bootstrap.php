<?php

/**
 * PHPUnit bootstrap for Sentinel-MCP tests.
 *
 * Loads a minimal WordPress mock layer so plugin classes can be
 * instantiated and unit-tested without a full WordPress installation.
 */

// Base constants.
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
if (! defined('SENTINEL_URL')) {
    define('SENTINEL_URL', 'https://example.com/wp-content/plugins/sentinel-mcp/');
}
if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

// ─── Minimal WordPress function stubs ─────────────────────────────

if (! function_exists('add_action')) {
    function add_action(string $tag, callable $function_to_add, int $priority = 10, int $accepted_args = 1): void
    {
        global $_wp_actions;
        if (! isset($_wp_actions)) {
            $_wp_actions = [];
        }
        $_wp_actions[$tag][] = [
            'callback'      => $function_to_add,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $tag, $value, ...$args)
    {
        return $value;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $tag, ...$args): void
    {
        global $_wp_actions;
        if (empty($_wp_actions[$tag])) {
            return;
        }
        foreach ($_wp_actions[$tag] as $hook) {
            ($hook['callback'])(...$args);
        }
    }
}

if (! function_exists('get_option')) {
    function get_option(string $option, $default = false)
    {
        global $_wp_options;
        return $_wp_options[$option] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $option, $value, $autoload = null): bool
    {
        global $_wp_options;
        $_wp_options[$option] = $value;
        return true;
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        global $_wp_options;
        unset($_wp_options[$option]);
        return true;
    }
}

if (! function_exists('get_transient')) {
    function get_transient(string $transient)
    {
        global $_wp_transients;
        return $_wp_transients[$transient] ?? false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient(string $transient, $value, int $expiration = 0): bool
    {
        global $_wp_transients;
        $_wp_transients[$transient] = $value;
        return true;
    }
}

if (! function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = '')
    {
        $map = [
            'name'    => 'Test Blog',
            'version' => '7.0.0',
        ];
        return $map[$show] ?? '';
    }
}

if (! function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://example.com' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (! function_exists('get_locale')) {
    function get_locale(): string
    {
        return 'en_US';
    }
}

if (! function_exists('wp_get_theme')) {
    function wp_get_theme()
    {
        return new class {
            public function get(string $header): string
            {
                return $header === 'Name' ? 'Twenty Twenty-Six' : '';
            }
        };
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        global $_wp_current_user_caps;
        return $_wp_current_user_caps[$capability] ?? false;
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        global $_wp_current_user_id;
        return $_wp_current_user_id ?? 1;
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return strip_tags(trim($str));
    }
}

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $str): string
    {
        return sanitize_text_field($str);
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0, int $depth = 512): string
    {
        return json_encode($data, $options, $depth);
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        private string $message;
        public function __construct(string $code, string $message)
        {
            $this->message = $message;
        }
        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (! function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args = [], bool $override = false): bool
    {
        global $_wp_rest_routes;
        if (! isset($_wp_rest_routes)) {
            $_wp_rest_routes = [];
        }
        $_wp_rest_routes[$namespace][$route] = $args;
        return true;
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('wp_die')) {
    function wp_die(string $message = '', string $title = '', array $args = []): void
    {
        throw new RuntimeException("wp_die: {$message}");
    }
}

if (! function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        public function get_param(string $key)
        {
            return null;
        }
    }
}

if (! class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        private array $data;
        private int $status;
        public function __construct($data = [], int $status = 200)
        {
            $this->data   = is_array($data) ? $data : [];
            $this->status = $status;
        }
        public function get_data(): array
        {
            return $this->data;
        }
        public function get_status(): int
        {
            return $this->status;
        }
    }
}

if (! function_exists('wp_add_inline_style')) {
    function wp_add_inline_style(string $handle, string $data): bool
    {
        global $_wp_inline_styles;
        $_wp_inline_styles[$handle] = $data;
        return true;
    }
}

if (! function_exists('wp_get_abilities')) {
    function wp_get_abilities(): array
    {
        global $_wp_abilities;
        return $_wp_abilities ?? [];
    }
}

if (! function_exists('wp_get_ability')) {
    function wp_get_ability(string $name)
    {
        global $_wp_abilities;
        return $_wp_abilities[$name] ?? null;
    }
}

// ─── WordPress AI Client registry stub ──────────────────────────
// Sentinel-MCP queries AiClient::defaultRegistry() for provider discovery.

if (! class_exists('\WordPress\AiClient\AiClient')) {
    class AiClient_Mock_Registry
    {
        public function hasProvider(string $id): bool
        {
            return true;
        }

        public function isProviderConfigured(string $id): bool
        {
            global $_wp_mock_ai_registry, $_wp_options;
            // If explicit mock entry exists, use it.
            if (isset($_wp_mock_ai_registry[$id]['configured'])) {
                return ! empty($_wp_mock_ai_registry[$id]['configured']);
            }
            // Otherwise infer from available credentials in the mock environment.
            $class_name = $this->getProviderClassName($id);
            if (! $class_name) {
                return false;
            }
            $provider_id = 'mock';
            if (str_contains($class_name, '::')) {
                [$class_name, $provider_id] = explode('::', $class_name, 2);
            }
            if (! class_exists($class_name)) {
                return false;
            }
            $provider = new $class_name([], $provider_id);
            if (! method_exists($provider, 'getAuthentication')) {
                return false;
            }
            $auth = $provider->getAuthentication();
            if (! is_array($auth) || ($auth['method'] ?? '') !== 'api_key') {
                return false;
            }
            $env_var_name  = $auth['env_var_name'] ?? '';
            $constant_name = $auth['constant_name'] ?? '';
            $setting_name  = $auth['setting_name'] ?? '';
            if ('' !== $env_var_name) {
                $env_value = getenv($env_var_name);
                if (false !== $env_value && '' !== $env_value) {
                    return true;
                }
            }
            if ('' !== $constant_name && defined($constant_name)) {
                $const_value = constant($constant_name);
                if (is_string($const_value) && '' !== $const_value) {
                    return true;
                }
            }
            if ('' !== $setting_name) {
                $db_value = get_option($setting_name, '');
                if ('' !== $db_value) {
                    return true;
                }
            }
            return false;
        }

        public function getRegisteredProviderIds(): array
        {
            global $_wp_mock_ai_registry;
            return array_keys($_wp_mock_ai_registry ?? []);
        }

        public function getProviderClassName(string $id): ?string
        {
            global $_wp_mock_ai_registry;
            return $_wp_mock_ai_registry[$id]['class_name'] ?? "AiClient_Mock_Provider::{$id}";
        }
    }

    class AiClient_Mock_Provider
    {
        private array $auth;
        private string $provider_id;

        public function __construct(array $auth = [], string $provider_id = 'mock')
        {
            $this->auth        = $auth;
            $this->provider_id = $provider_id;
        }

        public function getAuthentication(): array
        {
            if (empty($this->auth)) {
                $slug = $this->provider_id;
                return [
                    'method'        => 'api_key',
                    'setting_name'  => 'connectors_ai_' . strtolower($slug) . '_api_key',
                    'env_var_name'  => strtoupper($slug) . '_API_KEY',
                    'constant_name' => strtoupper($slug) . '_API_KEY',
                ];
            }
            return $this->auth;
        }
    }

    class WP_Mock_AiClient
    {
        public static function defaultRegistry(): AiClient_Mock_Registry
        {
            return new AiClient_Mock_Registry();
        }
    }

    class_alias('WP_Mock_AiClient', '\WordPress\AiClient\AiClient');
    class_alias('AiClient_Mock_Provider', '\WordPress\AiClient\MockProvider');
    class_alias('AiClient_Mock_Provider', '\WordPress\AiClient\AnthropicProvider');
    class_alias('AiClient_Mock_Provider', '\WordPress\AiClient\OpenAIProvider');
    class_alias('AiClient_Mock_Provider', '\WordPress\AiClient\GoogleProvider');
    class_alias('AiClient_Mock_Provider', '\WordPress\AiClient\OpenRouterProvider');
    class_alias('AiClient_Mock_Provider', '\WordPress\AiClient\OllamaProvider');

    // Seed the mock registry so Chat_Engine can resolve provider authentication.
    $_wp_mock_ai_registry = [
        'anthropic'  => ['configured' => false, 'class_name' => 'AiClient_Mock_Provider::anthropic'],
        'openai'     => ['configured' => false, 'class_name' => 'AiClient_Mock_Provider::openai'],
        'google'     => ['configured' => false, 'class_name' => 'AiClient_Mock_Provider::google'],
        'openrouter' => ['configured' => false, 'class_name' => 'AiClient_Mock_Provider::openrouter'],
        'ollama'     => ['configured' => false, 'class_name' => 'AiClient_Mock_Provider::ollama'],
    ];

    // Map provider slugs to connector IDs used by Chat_Provider_Registry.
    $_wp_mock_connector_registry = [
        'anthropic' => [
            'id'            => 'anthropic',
            'name'          => 'Anthropic',
            'type'          => 'ai_provider',
            'authentication' => [
                'method'        => 'api_key',
                'setting_name'  => 'connectors_ai_anthropic_api_key',
                'env_var_name'  => 'ANTHROPIC_API_KEY',
                'constant_name' => 'ANTHROPIC_API_KEY',
            ],
        ],
        'openai' => [
            'id'            => 'openai',
            'name'          => 'OpenAI',
            'type'          => 'ai_provider',
            'authentication' => [
                'method'        => 'api_key',
                'setting_name'  => 'connectors_ai_openai_api_key',
                'env_var_name'  => 'OPENAI_API_KEY',
                'constant_name' => 'OPENAI_API_KEY',
            ],
        ],
        'google' => [
            'id'            => 'google',
            'name'          => 'Google',
            'type'          => 'ai_provider',
            'authentication' => [
                'method'        => 'api_key',
                'setting_name'  => 'connectors_ai_google_api_key',
                'env_var_name'  => 'GOOGLE_API_KEY',
                'constant_name' => 'GOOGLE_API_KEY',
            ],
        ],
        'openrouter' => [
            'id'            => 'openrouter',
            'name'          => 'OpenRouter',
            'type'          => 'ai_provider',
            'authentication' => [
                'method'        => 'api_key',
                'setting_name'  => 'connectors_ai_openrouter_api_key',
                'env_var_name'  => 'OPENROUTER_API_KEY',
                'constant_name' => 'OPENROUTER_API_KEY',
            ],
        ],
        'ollama' => [
            'id'            => 'ollama',
            'name'          => 'Ollama',
            'type'          => 'ai_provider',
            'authentication' => [
                'method'        => 'api_key',
                'setting_name'  => 'connectors_ai_ollama_api_key',
                'env_var_name'  => 'OLLAMA_API_KEY',
                'constant_name' => 'OLLAMA_API_KEY',
            ],
        ],
    ];
}

// ─── Sentinel-MCP local connector helpers ─────────────────────────
if (! function_exists('mcpcomal_get_ai_connectors')) {
    function mcpcomal_get_ai_connectors(): array
    {
        if (! class_exists('\WordPress\AiClient\AiClient')) {
            return [];
        }

        $registry = \WordPress\AiClient\AiClient::defaultRegistry();
        return $registry->getRegisteredProviderIds();
    }
}

if (! function_exists('mcpcomal_is_connector_configured')) {
    function mcpcomal_is_connector_configured(string $connector_id): bool
    {
        if (! class_exists('\WordPress\AiClient\AiClient')) {
            return false;
        }

        $registry = \WordPress\AiClient\AiClient::defaultRegistry();
        return $registry->hasProvider($connector_id) && $registry->isProviderConfigured($connector_id);
    }
}

if (! function_exists('mcpcomal_has_connector_authentication')) {
    function mcpcomal_has_connector_authentication(string $connector_id): bool
    {
        if (! class_exists('\WordPress\AiClient\AiClient')) {
            return false;
        }

        $registry = \WordPress\AiClient\AiClient::defaultRegistry();
        if (! $registry->hasProvider($connector_id)) {
            return false;
        }

        $class_name = $registry->getProviderClassName($connector_id);
        if (! $class_name || ! class_exists($class_name)) {
            return false;
        }

        $provider = new $class_name();
        if (! method_exists($provider, 'getAuthentication')) {
            return false;
        }

        $auth = $provider->getAuthentication();
        if (! is_array($auth) || ($auth['method'] ?? '') !== 'api_key') {
            return false;
        }

        $setting_name = $auth['setting_name'] ?? '';
        if (! is_string($setting_name) || '' === $setting_name) {
            return false;
        }

        return 'none' !== mcpcomal_get_connector_api_key_source(
            $setting_name,
            $auth['env_var_name'] ?? '',
            $auth['constant_name'] ?? ''
        );
    }
}

if (! function_exists('mcpcomal_get_connector_api_key_source')) {
    function mcpcomal_get_connector_api_key_source(string $setting_name, string $env_var_name = '', string $constant_name = ''): string
    {
        if ('' !== $env_var_name) {
            $env_value = getenv($env_var_name);
            if (false !== $env_value && '' !== $env_value) {
                return 'env_var';
            }
        }

        if ('' !== $constant_name && defined($constant_name)) {
            $const_value = constant($constant_name);
            if (is_string($const_value) && '' !== $const_value) {
                return 'constant';
            }
        }

        $db_value = get_option($setting_name, '');
        if ('' !== $db_value) {
            return 'connectors_api';
        }

        return 'none';
    }
}

if (! function_exists('mcpcomal_has_ai_credentials')) {
    function mcpcomal_has_ai_credentials(): bool
    {
        $connectors = mcpcomal_get_ai_connectors();
        foreach ($connectors as $connector_id) {
            if (mcpcomal_has_connector_authentication($connector_id)) {
                return true;
            }
        }
        return false;
    }
}

if (! function_exists('wp_is_connector_registered')) {
    function wp_is_connector_registered(string $connector_id): bool
    {
        global $_wp_mock_connector_registry;
        return isset($_wp_mock_connector_registry[$connector_id]);
    }
}

if (! function_exists('wp_get_connector')) {
    function wp_get_connector(string $connector_id): ?array
    {
        global $_wp_mock_connector_registry;
        return $_wp_mock_connector_registry[$connector_id] ?? null;
    }
}

if (! function_exists('wp_get_connectors')) {
    function wp_get_connectors(): array
    {
        global $_wp_mock_connector_registry;
        return $_wp_mock_connector_registry ?? [];
    }
}

// Stub dbDelta so Chat DB table creation doesn't fatal.
if (! function_exists('dbDelta')) {
    function dbDelta(string $queries, bool $execute = true): array
    {
        return [];
    }
}

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (! defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

// ─── Mock $wpdb ───────────────────────────────────────────────────
if (! isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public string $prefix = 'wp_';
        public int $insert_id = 0;
        private array $_inserted_rows = [];
        public function get_charset_collate(): string
        {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
        public function get_var(string $query)
        {
            return null;
        }
        public function get_row(string $query, string $output = OBJECT)
        {
            global $_wp_last_insert_id, $_wp_conversation_provider, $_wp_conversation_model;
            // Return the most recently inserted row as a mock conversation.
            if (str_contains($query, 'mcpcomal_chat_conversations')) {
                // Extract ID from query like "WHERE id = 'X'"
                if (preg_match("/id\s*=\s*'?(\d+)'?/", $query, $m)) {
                    $requestedId = (int) $m[1];
                    if ($requestedId !== ($_wp_last_insert_id ?? 0)) {
                        return null;
                    }
                }
                $id = $_wp_last_insert_id ?? 1;
                return [
                    'id'         => $id,
                    'user_id'    => 42,
                    'title'      => 'New conversation',
                    'provider'   => $_wp_conversation_provider ?? 'anthropic',
                    'model'      => $_wp_conversation_model ?? 'claude-sonnet-4-6',
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ];
            }
            return null;
        }
        public function get_results(string $query, string $output = OBJECT)
        {
            return [];
        }
        public function insert(string $table, array $data, array $format = []): int|false
        {
            global $_wp_last_insert_id, $_wp_conversation_provider, $_wp_conversation_model;
            $_wp_last_insert_id = ($_wp_last_insert_id ?? 0) + 1;
            $this->insert_id = $_wp_last_insert_id;
            $this->_inserted_rows[$_wp_last_insert_id] = $data;
            if (isset($data['provider'])) {
                $_wp_conversation_provider = $data['provider'];
            }
            if (isset($data['model'])) {
                $_wp_conversation_model = $data['model'];
            }
            return 1;
        }
        public function update(string $table, array $data, array $where, array $format = [], array $where_format = []): int|false
        {
            global $_wp_conversation_provider, $_wp_conversation_model;
            if (isset($data['provider'])) {
                $_wp_conversation_provider = $data['provider'];
            }
            if (isset($data['model'])) {
                $_wp_conversation_model = $data['model'];
            }
            return 1;
        }
        public function delete(string $table, array $where, array $where_format = []): int|false
        {
            return 1;
        }
        public function query(string $query)
        {
            return true;
        }
        public function prepare(string $query, ...$args): string
        {
            // Naive placeholder replacement for %i, %d, %s.
            $i = 0;
            return preg_replace_callback('/%[ids]/', function () use (&$args, &$i) {
                $val = $args[$i++] ?? '';
                return is_int($val) ? (string) $val : "'" . addslashes((string) $val) . "'";
            }, $query);
        }
    };
}

// ─── Reset helpers ────────────────────────────────────────────────

function reset_wp_state(): void
{
    global $_wp_actions, $_wp_options, $_wp_transients, $_wp_rest_routes,
        $_wp_current_user_caps, $_wp_current_user_id, $_wp_inline_styles,
        $_wp_abilities, $_wp_connector_registry, $_wp_connector_auth,
        $_wp_last_insert_id, $_wp_conversation_provider, $_wp_conversation_model,
        $_wp_mock_ai_registry, $_wp_mock_connector_registered, $_wp_mock_connector_registry,
        $_wp_mock_users, $_wp_mock_users_by_id, $_wp_mock_user_meta, $_wp_last_mock_user_id;

    $_wp_actions          = [];
    $_wp_options          = [];
    $_wp_transients       = [];
    $_wp_rest_routes      = [];
    $_wp_current_user_caps = ['manage_options' => true];
    $_wp_current_user_id  = 1;
    $_wp_inline_styles    = [];
    $_wp_abilities        = [];
    $_wp_connector_registry = [];
    $_wp_connector_auth   = [];
    $_wp_last_insert_id   = 0;
    $_wp_conversation_provider = null;
    $_wp_conversation_model = null;
    $_wp_mock_ai_registry = [];
    $_wp_mock_connector_registered = [];
    $_wp_mock_connector_registry = [];
    $_wp_mock_users       = [];
    $_wp_mock_users_by_id = [];
    $_wp_mock_user_meta   = [];
    $_wp_last_mock_user_id = 0;
}

// NOTE: Vendor dependencies require PHP 8.3, but the host has 8.2.
//       We skip the Composer autoloader for unit tests and instead
//       stub any vendor classes that are referenced by type-hints.
// $vendorAutoload = __DIR__ . '/../sentinel-mcp/vendor/autoload.php';
// if (file_exists($vendorAutoload)) {
//     require_once $vendorAutoload;
// }

// Stub vendor classes referenced in type-hints (not instantiated in tests).
if (! class_exists('Soukicz\Llm\Message\LLMMessage')) {
    class LLMMessage
    {
        public static function createFromSystemString(string $text)
        {
            return new self();
        }
        public static function createFromUserString(string $text)
        {
            return new self();
        }
        public static function createFromAssistantString(string $text)
        {
            return new self();
        }
    }
}
if (! class_exists('Soukicz\Llm\Message\LLMMessageContents')) {
    class LLMMessageContents
    {
        public static function fromString(string $text)
        {
            return new self();
        }
        public static function fromErrorString(string $text)
        {
            return new self();
        }
    }
}
if (! class_exists('Soukicz\Llm\Tool\CallbackToolDefinition')) {
    class CallbackToolDefinition
    {
        public string $name;
        public string $description;
        public array $inputSchema;
        public $handler;
        public function __construct(string $name, string $description, array $inputSchema, callable $handler)
        {
            $this->name = $name;
            $this->description = $description;
            $this->inputSchema = $inputSchema;
            $this->handler = $handler;
        }
        public function getName(): string
        {
            return $this->name;
        }
    }
}
if (! class_exists('Soukicz\Llm\LLMConversation')) {
    class LLMConversation
    {
        public function __construct(array $messages) {}
    }
}
if (! class_exists('Soukicz\Llm\LLMRequest')) {
    class LLMRequest
    {
        public function __construct($model, $conversation, float $temperature, int $maxTokens, array $tools) {}
    }
}
if (! class_exists('Soukicz\Llm\LLMResponse')) {
    class LLMResponse
    {
        public function getLastText(): ?string
        {
            return null;
        }
        public function getInputTokens(): int
        {
            return 0;
        }
        public function getOutputTokens(): int
        {
            return 0;
        }
    }
}
if (! class_exists('Soukicz\Llm\Client\LLMClient')) {
    class LLMClient {}
}
if (! class_exists('Soukicz\Llm\Client\LLMAgentClient')) {
    class LLMAgentClient
    {
        public function run(LLMClient $client, LLMRequest $request): LLMResponse
        {
            return new LLMResponse();
        }
    }
}
if (! class_exists('Soukicz\Llm\Client\Anthropic\AnthropicClient')) {
    class AnthropicClient extends LLMClient
    {
        public function __construct(string $apiKey) {}
    }
}
if (! class_exists('Soukicz\Llm\Client\OpenAI\OpenAIClient')) {
    class OpenAIClient extends LLMClient
    {
        public function __construct(string $apiKey, string $org) {}
    }
}
if (! class_exists('Soukicz\Llm\Client\OpenAI\OpenAICompatibleClient')) {
    class OpenAICompatibleClient extends LLMClient
    {
        public function __construct(string $apiKey, string $baseUrl) {}
    }
}
if (! class_exists('Soukicz\Llm\Client\Gemini\GeminiClient')) {
    class GeminiClient extends LLMClient
    {
        public function __construct(string $apiKey) {}
    }
}
if (! class_exists('Soukicz\Llm\Client\Universal\LocalModel')) {
    class LocalModel
    {
        public function __construct(string $modelId) {}
    }
}

// ─── Additional WordPress stubs for manager classes ───────────────

if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}
if (! defined('FS_CHMOD_FILE')) {
    define('FS_CHMOD_FILE', 0644);
}

if (! function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array
    {
        return [
            'basedir' => WP_CONTENT_DIR . '/uploads',
            'baseurl' => 'https://example.com/wp-content/uploads',
        ];
    }
}

if (! function_exists('wp_normalize_path')) {
    function wp_normalize_path(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

if (! function_exists('validate_file')) {
    function validate_file(string $file, array $allowed_files = []): int
    {
        if (str_contains($file, '..')) {
            return 1;
        }
        if (str_contains($file, ':')) {
            return 2;
        }
        return 0;
    }
}

if (! function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $dir): bool
    {
        if (! is_dir($dir)) {
            return mkdir($dir, 0755, true);
        }
        return true;
    }
}

if (! function_exists('absint')) {
    function absint($maybeint): int
    {
        return abs((int) $maybeint);
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }
}

if (! function_exists('sanitize_user')) {
    function sanitize_user(string $username): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
    }
}

if (! function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
}

if (! function_exists('is_email')) {
    function is_email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (! function_exists('username_exists')) {
    function username_exists(string $username): bool
    {
        global $_wp_mock_users;
        return isset($_wp_mock_users[$username]);
    }
}

if (! function_exists('email_exists')) {
    function email_exists(string $email): bool
    {
        global $_wp_mock_users;
        foreach ($_wp_mock_users ?? [] as $user) {
            if (($user['email'] ?? '') === $email) {
                return true;
            }
        }
        return false;
    }
}

if (! function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $special = true, bool $extra = true): string
    {
        return bin2hex(random_bytes($length));
    }
}

if (! function_exists('count_users')) {
    function count_users(): array
    {
        return [
            'avail_roles' => [
                'administrator' => 1,
                'subscriber'  => 5,
            ],
            'total_users' => 6,
        ];
    }
}

if (! function_exists('get_userdata')) {
    function get_userdata(int $user_id)
    {
        global $_wp_mock_users_by_id;
        return $_wp_mock_users_by_id[$user_id] ?? false;
    }
}

if (! function_exists('count_user_posts')) {
    function count_user_posts(int $user_id): int
    {
        return 0;
    }
}

if (! function_exists('get_user_meta')) {
    function get_user_meta(int $user_id, string $key = '', bool $single = false)
    {
        global $_wp_mock_user_meta;
        if ($key === '') {
            return $_wp_mock_user_meta[$user_id] ?? [];
        }
        $val = $_wp_mock_user_meta[$user_id][$key] ?? [];
        return $single ? ($val[0] ?? '') : $val;
    }
}

if (! function_exists('update_user_meta')) {
    function update_user_meta(int $user_id, string $key, $value): int
    {
        global $_wp_mock_user_meta;
        $_wp_mock_user_meta[$user_id][$key] = [$value];
        return 1;
    }
}

if (! function_exists('wp_insert_user')) {
    function wp_insert_user(array $userdata)
    {
        global $_wp_mock_users, $_wp_mock_users_by_id, $_wp_last_mock_user_id;
        if (isset($userdata['user_login']) && username_exists($userdata['user_login'])) {
            return new WP_Error('existing_user_login', 'Username already exists.');
        }
        $_wp_last_mock_user_id = ($_wp_last_mock_user_id ?? 0) + 1;
        $id = $_wp_last_mock_user_id;
        $user = (object) array_merge($userdata, ['ID' => $id, 'roles' => [$userdata['role'] ?? 'subscriber']]);
        $_wp_mock_users[$userdata['user_login']] = ['id' => $id, 'email' => $userdata['user_email']];
        $_wp_mock_users_by_id[$id] = $user;
        return $id;
    }
}

if (! function_exists('wp_update_user')) {
    function wp_update_user(array $userdata)
    {
        global $_wp_mock_users_by_id;
        $id = $userdata['ID'] ?? 0;
        if (! isset($_wp_mock_users_by_id[$id])) {
            return new WP_Error('invalid_user', 'User not found.');
        }
        foreach ($userdata as $key => $value) {
            $_wp_mock_users_by_id[$id]->$key = $value;
        }
        return $id;
    }
}

if (! function_exists('wp_new_user_notification')) {
    function wp_new_user_notification(int $user_id, ?string $notify = null, string $notification = 'both'): void
    {
        // No-op for tests.
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash(string $text): string
    {
        return stripslashes($text);
    }
}

// ─── Load plugin files under test ─────────────────────────────────
// helpers.php bails early if ABSPATH is not defined, so define it first.
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
require_once __DIR__ . '/../sentinel-mcp/includes/helpers.php';
require_once __DIR__ . '/../sentinel-mcp/includes/chat/Chat_Provider_Registry.php';
require_once __DIR__ . '/../sentinel-mcp/includes/chat/Chat_Engine.php';
require_once __DIR__ . '/../sentinel-mcp/includes/chat/Rest_Chat.php';
require_once __DIR__ . '/../sentinel-mcp/includes/chat/Chat_Db.php';
require_once __DIR__ . '/../sentinel-mcp/includes/Comment_Manager.php';
require_once __DIR__ . '/../sentinel-mcp/includes/File_Manager.php';
require_once __DIR__ . '/../sentinel-mcp/includes/Media_Manager.php';
require_once __DIR__ . '/../sentinel-mcp/includes/User_Manager.php';
