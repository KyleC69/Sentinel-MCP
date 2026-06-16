# Sentinel-MCP Chat: Ollama Provider Loading — Technical Findings

**Date:** 2026-06-12
**Scope:** Full-screen chat initialization, provider discovery, and Ollama model loading
**Files Analyzed:**

- `includes/chat/class-mcp-admin-chat.php`
- `includes/chat/class-mcp-chat-engine.php`
- `includes/chat/class-mcp-rest-chat.php`
- `assets/js/mcpcomal-chat.js`
- `includes/class-mcp-connectors.php`

---

## 1. Executive Summary

The chat page does **not dynamically query** the Ollama instance for its model list (e.g., `ollama list` or `GET /api/tags`). Instead, it relies on a **statically hardcoded model catalog** inside `Chat_Engine::PROVIDERS`. Whether any provider’s models appear in the dropdown is gated by a single boolean, `has_key`, which is computed server-side and passed to the browser via `wp_localize_script`. If `has_key` is `false` for Ollama, the entire provider block is filtered out in JavaScript before the dropdown is rendered.

Even when the API key is confirmed present in the WordPress Connectors UI and in the environment variable, the chat page can still fail to recognize Ollama due to **how and when** the key is resolved, and because the model list is never refreshed from the actual Ollama server.

---

## 2. Chat Startup Flow (PHP → Browser)

### 2.1 Page Render (`class-mcp-admin-chat.php`)

When an admin opens **AI Mode** (`admin.php?page=sentinel-chat`), `Admin_Chat::render_page()` executes:

1. **WordPress version check** — If WP < 7.0, it shows a hard-block message and returns early. No chat UI is rendered.
2. **Script enqueueing** — Loads `marked`, `DOMPurify`, `highlight.js`, and the main `mcpcomal-chat.js`.
3. **Provider data build** — Calls `Chat_Engine::get_available_providers()`.
4. **Key presence check** — Iterates providers to set `$has_any_key = true` if **any** provider has `has_key === true`.
5. **Localization** — Injects a global JS object `window.mcpcomalChat` via `wp_localize_script` with:
   - `hasApiKey` (boolean)
   - `providers` (array with `has_key`, `key_source`, `models`, etc.)
   - `defaultProvider`, `defaultModel`
   - REST URL, nonce, user info, i18n strings
6. **HTML injection** — Outputs a single `<div id="sentinel-chat-app" data-loading="true"></div>`.

> **Critical point:** All provider data is baked into the page at render time. There is no subsequent AJAX/REST call to refresh provider status or model lists after the page loads.

### 2.2 Browser Initialization (`mcpcomal-chat.js`)

The IIFE in `mcpcomal-chat.js` reads `window.mcpcomalChat` into `config`:

```js
var config = window.mcpcomalChat || {};
```

`ui.init()` then:

1. Removes the `data-loading` attribute.
2. If `!config.hasApiKey`, renders a "No API Key" screen and exits.
3. Otherwise builds the full layout (sidebar + welcome screen + chat view).
4. Calls `ui.cacheElements()`, which among other things sets:

   ```js
   state.selectedProvider = def.provider;
   state.selectedModel    = def.model;
   ```

5. Calls `handlers.bindAll()` to attach event listeners.
6. Calls `ui.loadConversations()` to fetch conversation history via REST.

---

## 3. How Provider Keys Are Detected

### 3.1 Server-Side Resolution (`class-mcp-chat-engine.php`)

`get_available_providers()` enriches the static `PROVIDERS` array by calling:

```php
$provider['has_key']    = self::has_api_key($id);
$provider['key_source'] = self::get_api_key_source($id);
```

#### `has_api_key(string $provider): bool`

Returns `!empty(self::get_api_key($provider))`.

#### `get_api_key(string $provider): ?string` **(private)**

Looks up the key via `WP_CONNECTOR_MAP` in this exact order:

| Step | Source | Ollama Key |
|------|--------|------------|
| 1 | `getenv($map['env_var'])` | `OLLAMA_API_KEY` |
| 2 | `defined($map['env_var'])` | `OLLAMA_API_KEY` constant |
| 3 | `get_option($map['option'])` | `connectors_ai_ollama_api_key` |
| 4 | `wp_get_connector($map['connector_id'])` → `setting_name` | `connectors_ai_ollama_api_key` (dynamic) |

> **Note:** Steps 3 and 4 both resolve to the **same option name** (`connectors_ai_ollama_api_key`) because the connector registration in `class-mcp-connectors.php` explicitly sets `setting_name` to that value. There is no separate option namespace.

#### `get_api_key_source(string $provider): string`

Duplicates the same four-step logic but returns a string label (`'env_var'`, `'constant'`, `'connectors_api'`, `'none'`) instead of the key value.

### 3.2 Why the Key Might Be "Recognized Elsewhere but Not in Chat"

Other AI pages in the plugin may:

- Use `wp_get_connector('ollama')` directly and read `$connector['authentication']['setting_name']`.
- Call `get_option('connectors_ai_ollama_api_key')` in isolation.
- Access the key via a different code path that does not go through `Chat_Engine`.

The chat engine, however, is the **only** place that combines all four checks into a single `has_key` boolean. If any one of these checks behaves differently (e.g., `getenv()` returns `false` in the web-server SAPI but `true` in CLI), the chat page will report `has_key = false` while other pages may still work.

**Known PHP SAPI discrepancy:** `getenv()` is not always populated in FastCGI/FPM contexts depending on `php.ini` (`variables_order`, `clear_env` in FPM pools). If the env var is set in the shell but not passed to the web server process, `getenv('OLLAMA_API_KEY')` returns `false` in the browser context.

---

## 4. How the Model Picker Is Built (JavaScript)

### 4.1 `getModelList()`

```js
function getModelList() {
    var list = [];
    var providers = config.providers || {};
    Object.keys(providers).forEach(function (pid) {
        var p = providers[pid];
        if (!p.has_key) {          // ← GATE: provider discarded here
            return;
        }
        var models = p.models || {};
        Object.keys(models).forEach(function (mid) {
            list.push({
                provider: pid,
                model: mid,
                providerLabel: p.label,
                modelLabel: models[mid],
                isDefault: pid === config.defaultProvider && mid === p.default,
            });
        });
    });
    return list;
}
```

**Behavior:** If `has_key` is `false`, the provider is skipped entirely. No models are collected. No fallback message is shown in the picker for that provider.

### 4.2 `buildModelDropdownItems()`

```js
Object.keys(providers).forEach(function (pid) {
    var p = providers[pid];
    // ... group header ...
    if (!p.has_key) {
        html += '<div class="sentinel-model-picker-no-key">-- No AI provider configured</div>';
        return;
    }
    // ... render models ...
});
```

**Behavior:** The provider label is still rendered as a group header, but instead of models it shows `-- No AI provider configured`.

### 4.3 `getDefaultModel()`

```js
function getDefaultModel() {
    var list = getModelList();
    for (var i = 0; i < list.length; i++) {
        if (list[i].isDefault) {
            return list[i];
        }
    }
    return list[0] || { provider: '', model: '', providerLabel: 'AI', modelLabel: 'Select model' };
}
```

**Behavior:** If `getModelList()` returns an empty array (because no provider has `has_key === true`), `getDefaultModel()` falls back to a placeholder with empty provider/model IDs. The picker button will show **"Select model"**.

---

## 5. The Static Model List Problem

### 5.1 Hardcoded Catalog

Ollama models are **not fetched live** from the Ollama API. They are defined as a PHP constant array:

```php
// class-mcp-chat-engine.php
'ollama' => array(
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
```

### 5.2 No Dynamic Discovery

There is **no REST endpoint** and **no JS function** that calls:

- `OLLAMA_HOST/api/tags` (list local models)
- `OLLAMA_HOST/api/show` (model details)
- Or any equivalent discovery API.

The chat UI assumes the administrator wants to use one of the nine pre-defined models. If the local Ollama instance has different models installed (e.g., `llama3.1`, `mistral`, `codellama`), they will **not appear** in the dropdown even if the provider is recognized.

---

## 6. The Hardcoded Endpoint Problem

### 6.1 Client Creation

```php
// class-mcp-chat-engine.php :: create_client()
'ollama' => new OpenAICompatibleClient($api_key, 'https://ollama.com/v1'),
```

This URL is **always** `https://ollama.com/v1`, regardless of whether the user is running:

- **Ollama Cloud** (the commercial hosted API at `ollama.com`)
- **Local Ollama** (typically `http://localhost:11434` or `http://host.docker.internal:11434`)

There is **no setting**, **no constant**, and **no filter** to override this base URL. The `OpenAICompatibleClient` constructor accepts a `$baseUrl` parameter, but the chat engine hardcodes it.

### 6.2 Consequence for Local Users

If a user installs Ollama locally, sets `OLLAMA_API_KEY=dummy` (or any value), and expects the chat to talk to `localhost:11434`, the request will instead be sent to `https://ollama.com/v1`, which will fail or return unauthorized.

---

## 7. REST API Endpoints Related to Providers

The chat REST controller (`class-mcp-rest-chat.php`) exposes:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/sentinel/v1/chat/providers` | GET | Returns `get_available_providers()` + default provider |
| `/sentinel/v1/chat/switch-provider` | POST | Changes provider/model on an existing conversation |

**Important:** The `/providers` endpoint also calls `get_available_providers()`, so it suffers from the same `has_key` resolution logic. There is no separate "refresh" or "test connection" endpoint.

---

## 8. Summary of Failure Modes

| Symptom | Likely Cause | Location |
|---------|--------------|----------|
| Ollama group shows "-- No AI provider configured" | `has_key === false` because `get_api_key()` returned `null` | `class-mcp-chat-engine.php` → `get_api_key()` |
| Model picker shows "Select model" | `getModelList()` returned empty array (no provider with `has_key`) | `mcpcomal-chat.js` → `getModelList()` |
| Installed local models not in list | Models are hardcoded; no dynamic fetch from Ollama | `class-mcp-chat-engine.php` → `PROVIDERS` |
| Chat requests fail with 401/404 | Hardcoded `https://ollama.com/v1` instead of local endpoint | `class-mcp-chat-engine.php` → `create_client()` |
| Other AI pages work, chat doesn't | `getenv()` may work in CLI context but not web SAPI, or other pages use a different lookup path | Environment / SAPI configuration |

---

## 9. Recommendations for Fixing

1. **Add debug logging** inside `get_api_key()` to trace which step succeeds or fails for Ollama.
2. **Support key-less local Ollama** — Local Ollama often requires no API key. The `has_key` gate should be optional for local deployments.
3. **Add a `base_url` setting** for Ollama in `WP_CONNECTOR_MAP` (or a dedicated option) so users can point to `http://localhost:11434`.
4. **Implement dynamic model discovery** — Add a REST endpoint that queries `OLLAMA_BASE_URL/api/tags` and caches the result, then populate the dropdown from that list instead of the static array.
5. **Unify key detection** — Ensure all AI pages use the same resolution logic (env → constant → option → connector) so behavior is consistent across the plugin.

---

*End of findings.*
