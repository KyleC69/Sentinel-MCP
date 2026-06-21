
# **Sentinel‑MCP REST Endpoint Inventory (AI‑Ready Specification)**

**Plugin Version:** 2.0.2
**Generated:** 2026‑06‑15
**Scope:** All REST API routes registered by the Sentinel‑MCP plugin and its bundled `wordpress/mcp-adapter` dependency.

---

# **0. Summary**

| Namespace | Source | Count |
|----------|--------|-------|
| `sentinel/v1` | Plugin (`includes/`) | 7 |
| `sentinel-auth/v1` | Plugin (`includes/oauth/`) | 5 |
| `mcp` | Bundled MCP adapter | 1 |
| `.well-known` | Plugin (`includes/oauth/`) | 2 |
| **Total** |  | **15** |

---

# **1. Sentinel REST Endpoints — `sentinel/v1`**

Registered by:

- `\SentinelMCP\REST_Chat`
- `\SentinelMCP\Health_Endpoint`

## **1.1 Health**

| Method | Route | Permission | Handler | Purpose |
|--------|--------|-------------|----------|----------|
| GET | `/sentinel/v1/health` | public | `Health_Endpoint::handle` | Health probe: plugin, WordPress, PHP, OAuth table status |

---

## **1.2 Chat AI Endpoints**

All require: **WordPress cookie OR Application Password** with `manage_options`.

| Method(s) | Route | Handler | Purpose |
|-----------|--------|----------|----------|
| POST | `/sentinel/v1/chat/send` | `REST_Chat::handle_send` | Send a message to the AI |
| GET | `/sentinel/v1/chat/conversations` | `REST_Chat::handle_list_conversations` | List conversations |
| POST | `/sentinel/v1/chat/conversations` | `REST_Chat::handle_create_conversation` | Create conversation |
| GET | `/sentinel/v1/chat/conversations/{id}` | `REST_Chat::handle_get_conversation` | Retrieve conversation |
| DELETE | `/sentinel/v1/chat/conversations/{id}` | `REST_Chat::handle_delete_conversation` | Delete conversation |
| PATCH | `/sentinel/v1/chat/conversations/{id}` | `REST_Chat::handle_rename_conversation` | Rename conversation |
| GET | `/sentinel/v1/chat/search` | `REST_Chat::handle_search` | Search conversations |
| GET | `/sentinel/v1/chat/providers` | `REST_Chat::handle_providers` | List AI providers |
| POST | `/sentinel/v1/chat/switch-provider` | `REST_Chat::handle_switch_provider` | Switch provider |

---

# **2. OAuth 2.1 Server Endpoints — `sentinel-auth/v1` (Strict Contract)**

Registered by `\SentinelMCP\OAuth_Server`.

This section defines **exact**, **non‑negotiable** OAuth behavior.
LLMs must treat these rules as authoritative.

---

## **2.1 Supported OAuth Features**

### ✔ Supported

- Authorization Code Flow **with PKCE**
- **Refresh Token Grant**
- Dynamic Client Registration (RFC 7591)
- Token Revocation (RFC 7009)
- Opaque access tokens
- Opaque refresh tokens
- `.well-known` metadata endpoints (RFC 8414, RFC 9728)

### ❌ Forbidden (MUST NOT be invented)

- implicit flow
- password grant
- client credentials grant
- device code flow
- OpenID Connect
- `/userinfo`
- ID tokens
- JWKS
- JWT access tokens
- discovery beyond the two `.well-known` endpoints

---

# **2.2 Endpoint Definitions**

## **POST /sentinel-auth/v1/register**

**Permission:** public
**Purpose:** Dynamic Client Registration (RFC 7591)

Validates:

- HTTPS redirect URIs
- grant_types
- response_types
- token_endpoint_auth_method

**Response:**
Returns a `client_id`.

---

## **GET /sentinel-auth/v1/authorize**

**Permission:** public (login enforced via redirect)
**Handler:** `OAuth_Authorize::handle_get`
**Purpose:** Display consent page.

### **Login Redirect Rules (Hard Contract)**

If user is **not logged in**, server MUST redirect to:

```
/wp-login.php?redirect_to=<current_authorize_url>
```

This is the **only** login redirect strategy.
No alternate login URLs.
No OIDC login_hint.
No prompt=login.

### If user *is* logged in

Render consent page.

---

## **POST /sentinel-auth/v1/authorize**

**Permission:** public
**Handler:** `OAuth_Authorize::handle_post`
**Purpose:** Submit authorization.

### **POST Validation Requirements**

- nonce
- client_id
- redirect_uri
- PKCE code_challenge
- scopes

### **Successful Authorization Redirect**

Server MUST redirect to:

```
<redirect_uri>?code=<authorization_code>&state=<state>
```

No other redirect patterns allowed.

---

## **POST /sentinel-auth/v1/token**

**Permission:** public
**Handler:** `OAuth_Token::handle`
**Purpose:** Token exchange OR refresh.

---

### **A. Authorization Code Exchange**

**Required fields:**

- `grant_type=authorization_code`
- `client_id`
- `code`
- `redirect_uri`
- `code_verifier`

**Response:**

```json
{
  "access_token": "<opaque>",
  "refresh_token": "<opaque>",
  "token_type": "Bearer",
  "expires_in": 3600,
  "scope": "<scopes>"
}
```

---

### **B. Refresh Token Grant**

**Required fields:**

- `grant_type=refresh_token`
- `refresh_token`
- `client_id`

**Rules:**

- Refresh tokens **rotate**
- Scope cannot expand
- New access + refresh tokens returned

**Response:**

```json
{
  "access_token": "<new opaque>",
  "refresh_token": "<new opaque>",
  "token_type": "Bearer",
  "expires_in": 3600,
  "scope": "<scopes>"
}
```

---

## **POST /sentinel-auth/v1/revoke**

**Permission:** public
**Handler:** `OAuth_Token::handle_revoke`
**Purpose:** Revoke access or refresh tokens.

Rules:

- Always returns 200
- Revoking a refresh token invalidates all derived access tokens

---

## **POST /sentinel-auth/v1/debug-probe**

**Permission:** `manage_options`
**Purpose:** Admin-only diagnostics.

---

# **3. Bundled MCP Adapter Endpoint — `mcp`**

Registered by `WP\MCP\Transport\HttpTransport`.

| Method(s) | Route | Permission | Handler | Purpose |
|-----------|--------|-------------|----------|----------|
| POST, GET, DELETE | `/mcp/mcp-adapter-default-server` | defaults to `read` | `HttpTransport::handle_request` | MCP JSON‑RPC endpoint |

### Notes

- SERVES AS UNIVERSAL REGISTRATION ENDPOINT FOR CLIENTS. - AUTH FLOW BEGINS HERE.
- GET **IMPLEMENTED** Provides registration over SSE for picky clients such as VS Code.
- POST is generally used to intiate auth flow from many clients.
- DELETE terminates sessions

---

# **4. Non‑REST `.well-known` Endpoints**

Registered by `OAuth_Server::handle_well_known()`.

| Path | Handler | Purpose |
|------|----------|----------|
| `/.well-known/oauth-protected-resource` | `send_protected_resource_metadata` | Protected resource metadata (RFC 9728) |
| `/.well-known/oauth-authorization-server` | `send_authorization_server_metadata` | Authorization server metadata (RFC 8414) |

---

# **5. Admin‑Ajax OAuth Callback**

| Action | Route | Permission | Handler | Purpose |
|--------|--------|-------------|----------|----------|
| `mcp_oauth_callback` | `/wp-admin/admin-ajax.php?action=mcp_oauth_callback` | logged‑in users | `OAuth_Manager::handle_callback` | OAuth redirect handler |

---

# **6. Authentication Requirements**

| Endpoint Group | Required Authentication |
|----------------|--------------------------|
| `/sentinel/v1/health` | None |
| `/sentinel/v1/chat/*` | WP cookie or App Password (`manage_options`) |
| `/sentinel-auth/v1/*` | Public (OAuth handles security) |
| `/sentinel-auth/v1/debug-probe` | WP admin |
| `/mcp/mcp-adapter-default-server` | Any WP user with `read` |

---

# **7. Token Format Contract**

### **Access Tokens**

- Opaque
- Short-lived
- Stored hashed
- Used only for MCP endpoint

### **Refresh Tokens**

- Opaque
- Long-lived
- Stored hashed
- Rotated on each use
- Bound to client + user + scope

---

# **8. WWW‑Authenticate Header Contract**

### No token

```
WWW-Authenticate: Bearer resource_metadata="https://<site>/.well-known/oauth-protected-resource"
```

### Invalid token

```
WWW-Authenticate: Bearer error="invalid_token", error_description="Token expired"
```

---

# **9. How to Access Endpoints**

Base URL:

```
https://www.bitbybitforensics.com/wp-json/
```

Append route paths accordingly.

---

# **10. Source Files**

| File | Responsibility |
|------|----------------|
| `includes/Health_Endpoint.php` | `/sentinel/v1/health` |
| `includes/chat/Rest_Chat.php` | `/sentinel/v1/chat/*` |
| `includes/oauth/Oauth_Server.php` | OAuth endpoints + `.well-known` |
| `vendor/wordpress/mcp-adapter/...` | MCP endpoint |

---

# **This is now a complete, AI‑safe, hallucination‑proof specification**

If you want, I can also generate:

- a matching **OpenAPI 3.1 spec**
- a **developer quickstart** version
- a **client SDK contract**
- a **machine‑readable JSON spec**

Just tell me what format you want next.
