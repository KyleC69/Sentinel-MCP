# Sentinel-MCP

> Turn your WordPress site into a first-class **Model Context Protocol (MCP)** server.

**Sentinel-MCP** lets AI assistants like Claude, ChatGPT, GitHub Copilot, Cursor, Windsurf, Continue, and JetBrains AI manage your WordPress content using natural language.

[alt text](Sentinel-MCP.png)*



It is **not** a wrapper around the REST API. The plugin **auto-discovers*!
every Custom Post Type, taxonomy, ACF group, custom field, and registered meta on your site and exposes them as MCP abilities — so your AI client instantly understands the structure of your specific install without configuration files, code, or YAML.

---

## Why Sentinel-MCP?

Most WordPress MCP plugins ship 10–20 hardcoded tools that wrap a fixed list of REST endpoints. Sentinel-MCP ships **60+ abilities** out of the box, plus trust, onboarding, and observability features that the rest of the ecosystem treats as paid extras:

- **Auto-discovery, not hardcoded tools.** Your AI client sees your real schema — CPTs from CPT UI, ACF, JetEngine, Pods, Toolset; taxonomies; statuses; shortcodes; permalink structure — generated at runtime.
- **OAuth 2.1 with PKCE built in.** No application passwords. The plugin runs its own OAuth 2.1 server with Dynamic Client Registration so Claude, ChatGPT, Cursor, and Copilot connect with one click.
- **Granular per-client allowlists.** Each OAuth client has its own `allowed_abilities` list, editable visually. Give Claude full access, give a webhook integration only `read-post` and `list-recent-posts`.
- **AI image generation with Google Gemini.** Generate up to three images per call, save them to the Media Library, and set as featured image.
- **Rate limiting per client_id**, **Activity Log** with 30-day retention, **MCP annotations** on every ability, and a public **`/health`** endpoint for uptime monitoring.
- **Get Started wizard** + **Connect tab** with copy/paste config for Claude Desktop, ChatGPT, Cursor, Windsurf, Continue, and JetBrains AI.
- **Universal SEO read** across Yoast, Rank Math, AIOSEO, The SEO Framework, SureRank, SEOPress, Slim SEO, and Squirrly.
- **Universal multilingual read** across Polylang, WPML, and TranslatePress.
- **WooCommerce read-only** when WooCommerce is active.
- **Spanish translation bundled.** Coexistence with Premium with no conflicts.

---

## What You Can Do

Just talk to your AI assistant naturally:
After Installing and activating the plugin there are several admin panels explaining how to accomplish several tasks.

- *"Create a draft blog post titled 'Summer Travel Guide' with an introduction and three headings."*
- *"Show me all draft posts from the last month and publish the ones tagged 'ready'."*
- *"Find posts in the 'Tutorials' category without a featured image."*
- *"Create a new category called 'Case Studies' under 'Resources'."*
- *"Approve all pending comments from the last 24 hours."*
- *"Upload this image from a URL and set it as the featured image for post 42."*
- *"Generate a hero image of a sunset over the ocean and set it as the featured image of post 123."* (Gemini)
- *"What languages does this site have configured in Polylang? Fetch the Spanish version of the 'About' page."*
- *"Read the SEO meta of the homepage and list the meta description of every post in the 'Services' category."*
- *"Show me the last 20 paid WooCommerce orders and which coupons are active."*
- *"Show me a complete system diagnostics report and which plugins are currently active."*
- *"What content types does this site have? Show me the custom fields registered for the 'product' post type."*

---

## Compatible MCP Clients

The plugin works with any MCP client that supports remote servers and OAuth 2.1:

- **Claude Desktop, Claude Code, Cowork** (Anthropic) — full native OAuth 2.1
- **ChatGPT** (OpenAI) — full native OAuth 2.1 with Dynamic Client Registration
- **GitHub Copilot in VS Code** — native OAuth (VS Code 1.101+)
- **Cursor AI** — OAuth (some servers may need the mcp-remote bridge)
- **Windsurf** (Codeium) — OAuth for remote MCP servers
- **Continue** — full native OAuth 2.1 in VS Code and IntelliJ
- **Augment Code** — native OAuth with one-click approval
- **JetBrains AI Assistant** — MCP support in JetBrains IDEs (2025.2+)

Any application that implements the Model Context Protocol with remote server support should work.

---

## Requirements

- **WordPress:** 7.0 or higher
- **PHP:** 8.3 or higher
- **Dependencies:**
  - WordPress Abilities API (`wordpress/abilities-api`)
  - WordPress MCP Adapter (`wordpress/mcp-adapter`)

---

## Installation

1. Download the plugin ZIP from the [Releases](../../releases) page.
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Activate **Sentinel-MCP**.
4. Go to **Settings → Sentinel-MCP** and run the **Get Started** wizard.
5. Copy your MCP server URL from the **Connect** tab and paste it into your AI client.

---

## Features Included in Lite (60+ Abilities)

### Auto-Discovery & Schema Introspection

`list-post-types`, `list-taxonomies`, `list-post-statuses`, `list-shortcodes`, `get-permalink-structure`, `list-blocks-registered`, `list-block-patterns`, `list-fse-templates`. ACF groups, JetEngine fields, and registered meta detected automatically.

### Universal Content CRUD

Create, read, update, search, and delete posts, pages, and any custom post type. Full Gutenberg block markup with a built-in block reference. Search filters by status, author, date range, taxonomy, and meta. Custom fields and ACF fields read/write. Featured image from URL or Media Library. Bulk-friendly endpoints for agentic workflows.

### Site Stats & Content Shortcuts

`get-site-stats`, `get-media-stats`, `list-recent-posts`, `list-pending-comments`, `list-scheduled-posts`, `list-trashed-posts`, `list-post-revisions`.

### Taxonomies

List, create, update, and delete terms in any taxonomy with full hierarchy support.

### Comments

List, read, create, reply, approve, mark as spam, send to trash, and delete. Bulk moderation friendly.

### Media Library

List attachments with mime/size filtering, upload from local files or remote URL, set featured image, attach to posts.

### Users (Read-Only)

Browse users by role with detailed profile view.

### Menus, Widgets & Sidebars (Read-Only)

`list-nav-menus`, `list-widgets`, `list-sidebars`.

### WooCommerce Read-Only (Conditional)

When WooCommerce is active: `wc-get-store-info`, `wc-list-products`, `wc-list-recent-orders` (with email/name redaction), `wc-list-coupons`.

### SEO Read (Universal, 8 Plugins)

`seo-read-meta` with adapters for Yoast, Rank Math, AIOSEO, The SEO Framework, SureRank, SEOPress, Slim SEO, and Squirrly.

### Multilingual Read (Universal, 3 Plugins)

`i18n-list-languages`, `i18n-list-translations-for-post`, `i18n-get-post-in-language`, `i18n-list-string-translations` for Polylang, WPML, and TranslatePress.

### WordPress Options (Read-Only)

Site title, URL, admin email, timezone, date/time format, language, posts per page, permalink structure, reading and writing settings.

### Theme Customizations (Read-Only)

Custom logo, site icon, colors, header text, background.

### System Diagnostics

Complete environment report, `list-cron-events`, `list-user-roles`.

### Plugin Management

List every installed plugin and activate or deactivate them.

### Recovery & Debug

`site-health` summary, `clear-recovery`, debug logging toggle.

### AI Image Generation (Gemini)

`generate-image` (1–3 images per prompt) and `set-featured-from-prompt` when a Google Gemini API key is configured.

### Trust, Observability & Rate Limiting

- Activity Log with 30-day retention and paginated admin viewer
- Hourly and daily rate limits per `client_id`
- MCP annotations on every ability
- Public `/wp-json/sentinel/v1/health` endpoint

### Onboarding & Connection

- Get Started wizard
- Connect tab with config exporter for 6+ AI clients
- Prompts gallery (30+ curated prompts)
- "Detected on this site" badges in Status
- Spanish translation bundled

---

## AI Mode (Built-In Chat)

When running WordPress 7.0+, Sentinel-MCP includes a **fullscreen AI chat** directly in the WordPress admin. Talk to your site’s AI assistant without leaving the dashboard. Supports multiple providers via the WordPress Connectors API:

- Anthropic Claude
- OpenAI
- Google Gemini
- OpenRouter
- **Ollama Cloud** (local or hosted)

---

## Who Is This For?

- **Content creators** who want to draft, schedule, and update posts using natural language.
- **Site administrators** who want quick diagnostics, log inspection, and bulk content oversight.
- **Agencies** managing dozens of WordPress sites from a single AI assistant.
- **WooCommerce store owners** who want to inspect their catalog, orders, and coupons via chat.
- **Multilingual site owners** running Polylang, WPML, or TranslatePress.
- **Developers** building agentic workflows on top of WordPress.

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for details.

---

*Sentinel-MCP — Manage WordPress from any MCP client.*
