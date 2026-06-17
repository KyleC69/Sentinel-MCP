# Sentinel-MCP REST Endpoint Inventory

**Plugin Version:** 2.0.2
**Generated:** 2026-06-15
**Scope:** All REST API routes registered by the Sentinel-MCP plugin and its bundled `wordpress/mcp-adapter` dependency.

---

## Summary

| Namespace | Source | Endpoint Count |
|-----------|--------|----------------|
| `sentinel/v1` | Plugin (`includes/`) | 7 |
| `sentinel-auth/v1` | Plugin (`includes/oauth/`) | 5 |
| `mcp` | Bundled `wordpress/mcp-adapter` | 1 |
| `.well-known` (non-REST handlers) | Plugin (`includes/oauth/`) | 2 |
| **Total** | | **15** |

---

## 1. Plugin REST Endpoints — `sentinel/v1`

Registered by `\SentinelMCP\REST_Chat` (`includes/chat/Rest_Chat.php`) and `\SentinelMCP\Health_Endpoint` (`includes/Health_Endpoint.php`).

### 1.1 Health

| Method | Route | Permission | Handler | Purpose |
|--------|-------|------------|---------|---------|
| `GET` | `/sentinel/v1/health` | `__return_true` (public) | `Health_Endpoint::handle` | Public health probe returning plugin, WordPress, PHP, and OAuth table status. |

### 1.2 Chat AI

| Method(s) | Route | Permission | Handler | Purpose |
|-----------|-------|------------|---------|---------|
| `POST` | `/sentinel/v1/chat/send` | `manage_options` | `REST_Chat::handle_send` | Send a message to the AI for a conversation. |
| `GET` | `/sentinel/v1/chat/conversations` | `manage_options` | `REST_Chat::handle_list_conversations` | List conversations for the current user. |
| `POST` | `/sentinel/v1/chat/conversations` | `manage_options` | `REST_Chat::handle_create_conversation` | Create a new conversation. |
| `GET` | `/sentinel/v1/chat/conversations/(?P<id>\d+)` | `manage_options` | `REST_Chat::handle_get_conversation` | Retrieve a single conversation. |
| `DELETE` | `/sentinel/v1/chat/conversations/(?P<id>\d+)` | `manage_options` | `REST_Chat::handle_delete_conversation` | Delete a conversation. |
| `PATCH` | `/sentinel/v1/chat/conversations/(?P<id>\d+)` | `manage_options` | `REST_Chat::handle_rename_conversation` | Rename a conversation. |
| `GET` | `/sentinel/v1/chat/search` | `manage_options` | `REST_Chat::handle_search` | Search conversations. |
| `GET` | `/sentinel/v1/chat/providers` | `manage_options` | `REST_Chat::handle_providers` | List available AI providers/models. |
| `POST` | `/sentinel/v1/chat/switch-provider` | `manage_options` | `REST_Chat::handle_switch_provider` | Switch provider/model on a conversation. |

> **Note:** The `/chat/conversations` and `/chat/conversations/(?P<id>\d+)` routes are registered as multi-method routes, so they each count as one REST route definition but expose multiple HTTP methods.

---

## 2. OAuth 2.1 Server Endpoints — `sentinel-auth/v1`

Registered by `\SentinelMCP\OAuth_Server` (`includes/oauth/Oauth_Server.php`).

| Method(s) | Route | Permission | Handler | Purpose |
|-----------|-------|------------|---------|---------|
| `POST` | `/sentinel-auth/v1/register` | `__return_true` (public, RFC 7591) | `OAuth_Server::handle_register` | Dynamic Client Registration (DCR). |
| `GET` | `/sentinel-auth/v1/authorize` | `__return_true` (login enforced in callback) | `OAuth_Authorize::handle_get` | Display OAuth consent page. |
| `POST` | `/sentinel-auth/v1/authorize` | `__return_true` (nonce + login in callback) | `OAuth_Authorize::handle_post` | Submit OAuth authorization. |
| `POST` | `/sentinel-auth/v1/token` | `__return_true` (public, RFC 6749) | `OAuth_Token::handle` | Exchange authorization code + PKCE for tokens. |
| `POST` | `/sentinel-auth/v1/revoke` | `__return_true` (public, RFC 7009) | `OAuth_Token::handle_revoke` | Revoke an access/refresh token. |
| `POST` | `/sentinel-auth/v1/debug-probe` | `manage_options` | `OAuth_Server::handle_debug_probe` | Admin-only HTTP probe for troubleshooting OAuth endpoints. |

> **Note:** `/authorize` is registered as a single route with two method definitions (`GET` and `POST`).

---

## 3. Bundled MCP Adapter Endpoint — `mcp`

Registered by `WP\MCP\Transport\HttpTransport` (`vendor/wordpress/mcp-adapter/includes/Transport/HttpTransport.php`), instantiated by the default MCP server factory.

| Method(s) | Route | Permission | Handler | Purpose |
|-----------|-------|------------|---------|---------|
| `POST`, `GET`, `DELETE` | `/mcp/mcp-adapter-default-server` | Configurable; defaults to `current_user_can('read')` | `HttpTransport::handle_request` | MCP protocol endpoint for tool/resource/prompt discovery and execution. `GET` is reserved for future SSE streaming; `DELETE` terminates sessions. |

The route namespace and path are defined in `WP\MCP\Servers\DefaultServerFactory` with defaults:

- `server_route_namespace`: `mcp`
- `server_route`: `mcp-adapter-default-server`

These can be filtered via `mcp_adapter_default_server_config`.

---

## 4. Non-REST `.well-known` Handlers

Registered by `\SentinelMCP\OAuth_Server::handle_well_known()` on the `init` hook (priority `1`). These are not WordPress REST routes, but they are public HTTP endpoints exposed by the plugin and are documented here for completeness.

| Path | Handler | Purpose |
|------|---------|---------|
| `/.well-known/oauth-protected-resource` | `OAuth_Server::send_protected_resource_metadata` | OAuth protected resource metadata (RFC 9728). |
| `/.well-known/oauth-authorization-server` | `OAuth_Server::send_authorization_server_metadata` | OAuth authorization server metadata (RFC 8414). |

---

## 5. Related Admin-Ajax Endpoint

The plugin also registers one admin-ajax action that is part of the OAuth callback flow. It is not a REST endpoint, but it is closely related to the OAuth routes above.

| Action | Route | Permission | Handler | Purpose |
|--------|-------|------------|---------|---------|
| `mcp_oauth_callback` | `/wp-admin/admin-ajax.php?action=mcp_oauth_callback` | `wp_ajax_*` (logged-in users) | `OAuth_Manager::handle_callback` | Handle OAuth callback redirect after authorization. |

---

## 6. How to Access the Endpoints

All REST routes are served from the WordPress REST API base URL:

```
https://www.bitbybitforensics.com/wp-json/
```

Append the route path to that base. For example, the health endpoint is reachable at:

```
GET https://www.bitbybitforensics.com/wp-json/sentinel/v1/health
```

### 6.1 Authentication

| Endpoint Group | Required Authentication |
|----------------|-------------------------|
| `/sentinel/v1/health` | None (public). |
| `/sentinel/v1/chat/*` | WordPress cookie or Application Password with `manage_options` capability. |
| `/sentinel-auth/v1/register`, `/token`, `/revoke`, `/authorize` | Public OAuth 2.1 endpoints (see below). |
| `/sentinel-auth/v1/debug-probe` | WordPress cookie or Application Password with `manage_options`. |
| `/mcp/mcp-adapter-default-server` | Configurable; defaults to any authenticated user with `read` capability. |

### 6.2 Calling with Application Passwords

For admin-scoped endpoints, use WordPress Application Passwords with Basic Auth:

```bash
# Health (public)
curl -s https://www.bitbybitforensics.com/wp-json/sentinel/v1/health | jq .

# List chat conversations (requires manage_options)
curl -s -u "admin_user:application_password" \
  https://www.bitbybitforensics.com/wp-json/sentinel/v1/chat/conversations | jq .

# Send a chat message
curl -s -X POST -u "admin_user:application_password" \
  -H "Content-Type: application/json" \
  -d '{"conversation_id": 1, "message": "Hello AI"}' \
  https://www.bitbybitforensics.com/wp-json/sentinel/v1/chat/send | jq .
```

### 6.3 Calling the OAuth 2.1 Endpoints

The OAuth endpoints implement the standard OAuth 2.1 / PKCE flow.

#### 1. Dynamic Client Registration

```bash
curl -s -X POST https://www.bitbybitforensics.com/wp-json/sentinel-auth/v1/register \
  -H "Content-Type: application/json" \
  -d '{
    "client_name": "My MCP Client",
    "redirect_uris": ["https://client.www.bitbybitforensics.com/callback"],
    "grant_types": ["authorization_code"],
    "response_types": ["code"],
    "token_endpoint_auth_method": "none",
    "scope": "mcp:tools mcp:read mcp:write"
  }' | jq .
```

Store the returned `client_id` for the next steps.

#### 2. Authorization Request (PKCE)

Generate a PKCE code verifier and challenge, then redirect the user to:

```
https://www.bitbybitforensics.com/wp-json/sentinel-auth/v1/authorize
  ?client_id=<client_id>
  &response_type=code
  &redirect_uri=https://client.www.bitbybitforensics.com/callback
  &scope=mcp:tools
  &state=<random_state>
  &code_challenge=<base64url_sha256_of_verifier>
  &code_challenge_method=S256
```

The user logs in, approves the client, and is redirected back with an authorization `code`.

#### 3. Exchange Code for Token

```bash
curl -s -X POST https://www.bitbybitforensics.com/wp-json/sentinel-auth/v1/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "authorization_code",
    "client_id": "<client_id>",
    "code": "<authorization_code>",
    "redirect_uri": "https://client.www.bitbybitforensics.com/callback",
    "code_verifier": "<pkce_code_verifier>"
  }' | jq .
```

#### 4. Use the Access Token

```bash
curl -s -X POST https://www.bitbybitforensics.com/wp-json/mcp/mcp-adapter-default-server \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list"
  }' | jq .
```

#### 5. Revoke a Token

```bash
curl -s -X POST https://www.bitbybitforensics.com/wp-json/sentinel-auth/v1/revoke \
  -H "Content-Type: application/json" \
  -d '{"token": "<access_token_or_refresh_token>"}'
```

### 6.4 Calling the MCP Endpoint

The MCP endpoint speaks JSON-RPC and supports the standard MCP methods such as `initialize`, `tools/list`, `tools/call`, `resources/list`, `prompts/list`, etc.

**Example — list available tools:**

```bash
curl -s -X POST https://www.bitbybitforensics.com/wp-json/mcp/mcp-adapter-default-server \
  -u "admin_user:application_password" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list"
  }' | jq .
```

**Example — call a tool:**

```bash
curl -s -X POST https://www.bitbybitforensics.com/wp-json/mcp/mcp-adapter-default-server \
  -u "admin_user:application_password" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "tools/call",
    "params": {
      "name": "sentinel/discovery/site-info",
      "arguments": {}
    }
  }' | jq .
```

### 6.5 Calling `.well-known` Endpoints

These are plain HTTP GET endpoints at the site root:

```bash
curl -s https://www.bitbybitforensics.com/.well-known/oauth-authorization-server | jq .
curl -s https://www.bitbybitforensics.com/.well-known/oauth-protected-resource | jq .
```

### 6.6 Calling the Admin-Ajax OAuth Callback

This endpoint is used by the plugin's own OAuth manager; third-party clients normally do not call it directly.

```bash
curl -s "https://www.bitbybitforensics.com/wp-admin/admin-ajax.php?action=mcp_oauth_callback" \
  -H "Cookie: wordpress_logged_in_<hash>=<cookie>"
```

---

## 7. How to Verify

You can dump all registered REST routes at runtime with the following WP-CLI command or snippet:

```php
add_action('rest_api_init', function () {
    $routes = rest_get_server()->get_routes();
    foreach ($routes as $route => $handlers) {
        if (str_starts_with($route, 'sentinel/') || str_starts_with($route, 'sentinel-auth/') || str_starts_with($route, 'mcp/')) {
            error_log($route);
        }
    }
}, 100);
```

Or via WP-CLI:

```bash
wp rest list
```

---

## 8. Source Files

| File | Responsibility |
|------|--------------|
| `sentinel-mcp/includes/Health_Endpoint.php` | `/sentinel/v1/health` |
| `sentinel-mcp/includes/chat/Rest_Chat.php` | `/sentinel/v1/chat/*` |
| `sentinel-mcp/includes/oauth/Oauth_Server.php` | `/sentinel-auth/v1/*` and `.well-known` handlers |
| `sentinel-mcp/vendor/wordpress/mcp-adapter/includes/Transport/HttpTransport.php` | `/mcp/mcp-adapter-default-server` |
| `sentinel-mcp/vendor/wordpress/mcp-adapter/includes/Servers/DefaultServerFactory.php` | Default MCP server configuration |
| `sentinel-mcp/vendor/wordpress/mcp-adapter/includes/Core/McpAdapter.php` | MCP adapter initialization on `rest_api_init` |

---

*Document generated for the Sentinel-MCP plugin. Keep this inventory updated when adding, removing, or renaming REST routes.*
