=== Sentinel-MCP ===
Contributors: Kyle Crowder
Tags: mcp, ai, claude, chatgpt, woocommerce
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 2.0.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage WordPress from any MCP client. 60+ abilities, OAuth 2.1, allowlists, activity log, SEO ready.

== Description ==

**Sentinel-MCP** turns your WordPress site into a first-class Model Context Protocol (MCP) server so AI assistants like Claude, ChatGPT, GitHub Copilot, Cursor, Windsurf, Continue, JetBrains AI Assistant or Cowork can manage your content using natural language.

It is **not** a wrapper around the REST API. The plugin **auto-discovers** every CPT, taxonomy, ACF group, custom field and registered meta on your site and exposes them as MCP abilities, so your AI client instantly understands the structure of your specific install — without configuration files, without code, without YAML.

= Why Sentinel-MCP over other MCP plugins =

Most WordPress MCP plugins ship 10–20 hardcoded tools that wrap a fixed list of REST endpoints. Sentinel-MCP ships **60+ abilities** out of the box, plus trust, onboarding and observability features that the rest of the ecosystem treats as paid extras:

* **Auto-discovery, not hardcoded tools.** Your AI client sees your real schema — CPTs from CPT UI, ACF, JetEngine, Pods, Toolset; taxonomies; statuses; shortcodes; permalink structure — generated at runtime. No YAML files to maintain.
* **OAuth 2.1 with PKCE built in.** No application passwords. The plugin runs its own OAuth 2.1 server with Dynamic Client Registration so Claude, ChatGPT, Cursor and Copilot connect with one click.
* **Granular per-client allowlists.** Each OAuth client has its own `allowed_abilities` list, editable visually. Give Claude full access, give a webhook integration only `read-post` and `list-recent-posts`.
* **AI image generation with Google Gemini.** Generate up to three images per call, save them to the Media Library, set as featured image. Imagen, multiple aspect ratios, 2K/4K and image editing remain Premium.
* **Rate limiting per client_id**, **Activity Log** with 30-day retention, **MCP annotations** on every ability and a public **/health endpoint** for uptime monitoring.
* **Get Started wizard** + **Connect tab** with copy/paste config for Claude Desktop, ChatGPT, Cursor, Windsurf, Continue and JetBrains AI.
* **Universal SEO read** across Yoast, Rank Math, AIOSEO, The SEO Framework, SureRank, SEOPress, Slim SEO and Squirrly. One ability, eight plugins.
* **Universal multilingual read** across Polylang, WPML and TranslatePress.
* **WooCommerce read-only** when WooCommerce is active.
* **Spanish translation bundled.** Coexistence with Premium with no conflicts.

No code. No configuration. Install, activate, run the wizard, paste the URL into your MCP client.

= Everything Included in Lite (60+ abilities) =

**Auto-discovery and schema introspection.** `list-post-types`, `list-taxonomies`, `list-post-statuses`, `list-shortcodes`, `get-permalink-structure`, `list-blocks-registered`, `list-block-patterns`, `list-fse-templates`. ACF groups, JetEngine fields and registered meta detected automatically.

**Universal content CRUD.** Create, read, update, search and delete posts, pages and any custom post type. Full Gutenberg block markup with a built-in block reference so the AI generates clean blocks. Search filters by status, author, date range, taxonomy, meta. Custom fields and ACF fields read/write. Featured image from URL or Media Library. Bulk-friendly endpoints for agentic workflows.

**Site stats and content shortcuts.** `get-site-stats`, `get-media-stats`, `list-recent-posts`, `list-pending-comments`, `list-scheduled-posts`, `list-trashed-posts`, `list-post-revisions`.

**Taxonomies.** List, create, update and delete terms in any taxonomy with full hierarchy support.

**Comments.** List, read, create, reply, approve, mark as spam, send to trash and delete. Bulk moderation friendly.

**Media library.** List attachments with mime/size filtering, upload from local files or remote URL, set featured image, attach to posts.

**Users (read-only).** Browse users by role with detailed profile view.

**Menus, widgets and sidebars (read-only).** `list-nav-menus`, `list-widgets`, `list-sidebars`.

**WooCommerce read-only (conditional).** When WooCommerce is active, four abilities appear: `wc-get-store-info`, `wc-list-products`, `wc-list-recent-orders` (with email/name redaction by default) and `wc-list-coupons`.

**SEO read (universal, 8 plugins).** `seo-read-meta` with adapters for Yoast, Rank Math, AIOSEO, The SEO Framework, SureRank, SEOPress, Slim SEO and Squirrly. Title, description, canonical, robots, OpenGraph and Twitter where the source plugin exposes them.

**Multilingual read (universal, 3 plugins).** `i18n-list-languages`, `i18n-list-translations-for-post`, `i18n-get-post-in-language`, `i18n-list-string-translations`. Adapters for Polylang, WPML and TranslatePress.

**WordPress options (read-only, security whitelist).** Site title, URL, admin email, timezone, date/time format, language, posts per page, permalink structure, reading and writing settings.

**Theme customizations (read-only).** Custom logo, site icon, colors, header text, background.

**System diagnostics.** Complete environment report (WordPress, PHP, database, theme, active plugins, server, memory, SSL, REST base, security indicators), `list-cron-events`, `list-user-roles`.

**Plugin management.** List every installed plugin and activate or deactivate them.

**Recovery and debug.** `site-health` summary, `clear-recovery` for recovery-mode flags, debug logging toggle without editing wp-config.php.

**AI image generation (Gemini).** When a Google Gemini API key is configured in Settings: `generate-image` (1–3 images per prompt, saved to Media Library, optional post attachment) and `set-featured-from-prompt` (generate and assign as featured image in one call). Imagen, multiple aspect ratios, 2K/4K and image editing remain Premium.

**OAuth 2.1 server.** Authorization Code flow with PKCE, Dynamic Client Registration (RFC 7591), per-client `allowed_abilities` allowlist with a visual editor, token revocation, refresh tokens, scope management.

**Trust, observability and rate limiting.** Activity Log (30-day retention, daily cron purge, paginated admin viewer). Hourly and daily rate limits per `client_id` (filterable). MCP annotations on every ability. Public `/wp-json/sentinel/v1/health` endpoint for uptime monitoring.

**Onboarding and connection.** Get Started wizard (environment check, OAuth client creation, AI client picker, live connection test). Connect tab with copy/paste config exporter for Claude Desktop, ChatGPT, Cursor, Windsurf, Continue, JetBrains AI and a curl debug snippet. Prompts gallery (30+ curated prompts). "Detected on this site" badges in Status. Navigable Premium catalog inside the Go Premium tab. Spanish translation bundled.

= Example Requests You Can Make =

Just talk to your AI assistant naturally:

* "Create a draft blog post titled 'Summer Travel Guide' with an introduction and three headings."
* "Show me all draft posts from the last month and publish the ones tagged 'ready'."
* "Find posts in the 'Tutorials' category without a featured image."
* "Create a new category called 'Case Studies' under 'Resources'."
* "Approve all pending comments from the last 24 hours."
* "Upload this image from a URL and set it as the featured image for post 42."
* "Generate a hero image of a sunset over the ocean and set it as the featured image of post 123." (Gemini)
* "What languages does this site have configured in Polylang? Fetch the Spanish version of the 'About' page."
* "Read the SEO meta of the homepage and list the meta description of every post in the 'Services' category."
* "Show me the last 20 paid WooCommerce orders and which coupons are active."
* "Show me a complete system diagnostics report and which plugins are currently active."
* "What content types does this site have? Show me the custom fields registered for the 'product' post type."

= Compatible MCP Clients =

The plugin works with any MCP client that supports remote servers and OAuth 2.1. Confirmed clients:

* **Claude Desktop, Claude Code, Cowork** (Anthropic) — full native OAuth 2.1.
* **ChatGPT** (OpenAI) — full native OAuth 2.1 with Dynamic Client Registration.
* **GitHub Copilot in VS Code** — native OAuth (VS Code 1.101+).
* **Cursor AI** — OAuth (some servers may need the mcp-remote bridge).
* **Windsurf** (Codeium) — OAuth for remote MCP servers.
* **Continue** — full native OAuth 2.1 in VS Code and IntelliJ.
* **Augment Code** — native OAuth with one-click approval.
* **JetBrains AI Assistant** — MCP support in JetBrains IDEs (2025.2+).

Any application that implements the Model Context Protocol with remote server support should work. There are 500+ MCP clients today and the protocol is the same everywhere.

= Who Is This For? =

* **Content creators** who want to draft, schedule and update posts using natural language.
* **Site administrators** who want quick diagnostics, log inspection and bulk content oversight.
* **Agencies** managing dozens of WordPress sites from a single AI assistant.
* **WooCommerce store owners** who want to inspect their catalog, orders and coupons via chat.
* **Multilingual site owners** running Polylang, WPML or TranslatePress.
* **Developers** building agentic workflows on top of WordPress.

= Requirements =

* WordPress 6.9 or later (AI Mode requires WordPress 7.0+ — see FAQ).
* PHP 8.3 or later.
* The [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin (the Lite plugin can also load the bundled adapter automatically if no other source provides it).
* An MCP-compatible client.

* **WooCommerce write (8 modules):** products with variations and attributes, orders edit, refunds, customers CRUD, sales analytics, shipping zones, webhooks, store settings and subscriptions across 5 platforms (WooCommerce Subscriptions, Yith, Sumo, ASWC, WBTE).
* **SEO suite:** universal write across the same 8 plugins as Lite + a content auditor with scoring and bulk fixes.
* **Security and recovery:** 23 hardening measures with audit scoring, hacked-site cleanup (core integrity, malware scan, DB injection detection, rogue admin detection, salt regeneration), maintenance mode and Time Machine backups with one-click rollback.
* **Performance:** adapter for 7 cache plugins (WP Rocket, W3TC, WP Super Cache, LiteSpeed, SG Optimizer, Hummingbird, FlyingPress) and a profiler with 0–100 scoring and 20 built-in optimizations.
* **WP-CLI bridge** with per-command permissions, default 16-command blocklist and timeout/shell-metachar protection.
* **Plugin and theme installation** from WordPress.org or ZIP URL, with a 14-phase site-creation guide.
* **Users, roles and multisite:** users CRUD, roles and capabilities CRUD with escalation prevention, network-wide site CRUD and cross-site ability execution.
* **Full Site Editing write:** navigation, theme.json global styles, fonts, templates and widgets.
* **Custom fields write** across ACF, Meta Box, Pods and JetEngine (full repeater and flexible content support).
* **Multilingual write** with site-wide AI translation queueing across Polylang, WPML and TranslatePress.
* **AI image generation extended:** Google Gemini and Imagen with 10 aspect ratios, 1K/2K/4K, PNG/JPEG and image editing.
* **File manager, htaccess manager and wp-config** safe write.
* **Action logger with diff** and **HMAC-signed confirmations** on 30+ destructive abilities — nothing destructive runs without explicit cryptographic consent.
* **Advanced diagnostics:** hook inspector, REST route discovery, shortcode registry, Action Scheduler, SSL/DNS analysis and a coding-guidelines validator.

**[Get MCP Content Manager Premium](https://plugins.joseconti.com/en/product/sentinel-mcp/)**

== External Services ==

This plugin connects to the following external services:

= WordPress.org API =

The system diagnostics feature tests whether your server can make outgoing HTTP requests (both GET and POST) by connecting to the WordPress.org core version check API. This is used only when the user explicitly requests a system diagnostics report. The data sent is the WordPress version number. No personal data is transmitted.

* Service: [WordPress.org](https://wordpress.org/)
* [Terms of Use](https://wordpress.org/about/terms-of-service/)
* [Privacy Policy](https://wordpress.org/about/privacy/)

= OAuth 2.1 Authentication =

This plugin implements an OAuth 2.1 server so that MCP clients (Claude, ChatGPT, Copilot, Cursor, etc.) can authenticate with your site. The OAuth flow happens entirely between the MCP client application and your own WordPress site. No data is sent to any third-party service by the plugin during authentication. The MCP client connects directly to your site's REST API endpoints.

= Google Gemini (optional, only when image generation is configured) =

If — and only if — you configure a Google Gemini API key in **Settings > Sentinel-MCP > Settings**, the `generate-image` and `set-featured-from-prompt` abilities call Google's Gemini API at `generativelanguage.googleapis.com`. The data sent is: the prompt provided by the AI client, the model id you have configured (default `gemini-2.0-flash-exp`) and the API key. The response (a base64-encoded image) is saved to the Media Library and the prompt is stored as attachment meta. No data is sent to Google when the API key is empty or the abilities are not invoked.

* Service: [Google AI / Gemini](https://ai.google.dev/)
* [Terms of Service](https://ai.google.dev/gemini-api/terms)
* [Privacy Policy](https://policies.google.com/privacy)

== Installation ==

1. Upload the `mcp-sentinel` folder to `wp-content/plugins/` (or install via the WordPress admin).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Install and activate the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin if it is not already installed (the Lite plugin can also load a bundled copy automatically if nothing else provides one).
4. Go to **Settings > Sentinel-MCP** and run the **Get Started** wizard. It checks your environment, creates an OAuth client, lets you pick your AI client and runs a live connection test.
5. Open the **Connect** tab and copy the configuration block for your client (Claude Desktop, ChatGPT, Cursor, Windsurf, Continue, JetBrains AI or curl).
6. Start managing your content with natural language.

= Connecting Your MCP Client =

The plugin works with any MCP client that supports remote servers with OAuth 2.1.

**Your MCP Server URL is:**
`https://your-domain.com/wp-json/mcp/mcp-adapter-default-server`

You can find the exact URL in **Settings > Sentinel-MCP > Status**.

**Claude Desktop / Claude Code / Cowork**

1. Go to Settings > MCP Servers > Add server.
2. Paste your MCP Server URL as the endpoint.
3. When you connect for the first time, your browser opens an authorization page — click Authorize.
4. Done. Ask Claude to list your post types and start working.

**ChatGPT (OpenAI)**

1. Enable Developer Mode in ChatGPT settings.
2. Add a new remote MCP server and paste your MCP Server URL.
3. ChatGPT handles OAuth registration and authentication automatically (Dynamic Client Registration).

**VS Code / GitHub Copilot**

1. Open VS Code settings (1.101+) and add a new MCP server.
2. Paste your MCP Server URL.
3. Authenticate via the OAuth browser flow when prompted.

**Cursor / Windsurf / Continue / Augment Code / JetBrains AI**

1. Open the MCP server settings in your client.
2. Add a new server and paste your MCP Server URL.
3. Complete the OAuth 2.1 authentication when prompted.

The OAuth 2.1 handshake happens automatically — you only need to authorize once per device.

== Frequently Asked Questions ==

= How is this different from other MCP plugins on the WordPress repository? =

Three things, mostly. First, **auto-discovery**: the plugin reads your real schema (CPTs, taxonomies, registered meta, ACF, JetEngine) at runtime, so the AI client sees your specific install — no hand-maintained YAML or hardcoded tool list. Second, **breadth**: 60+ abilities in Lite versus the 10–20 typical of competing plugins, including multilingual read across Polylang/WPML/TranslatePress, SEO read across 8 plugins, WooCommerce read, FSE inventory and a full system diagnostics report. Third, **trust infrastructure**: OAuth 2.1 with PKCE, per-client allowlists, rate limiting, an activity log with 30-day retention, MCP annotations on every ability, a public health endpoint and a config-exporter for every major MCP client. Most other plugins charge for a subset of those.

= What is AI Mode and why does it say it requires WordPress 7.0? =

AI Mode is a built-in chat interface that lets you talk to AI assistants directly from the WordPress admin panel. It uses the Connectors API introduced in WordPress 7.0, so it becomes available when you update to that version.

In the meantime you can already use **all 60+ abilities** by connecting your AI assistant (Claude Desktop, ChatGPT, Copilot, Cursor, etc.) to your site via MCP. The MCP connection works on WordPress 6.9 and gives you the same access — through your preferred AI client.

= Does it work with any custom post type? =

Yes. The plugin auto-discovers all registered CPTs, including those from WooCommerce, ACF, Toolset, Pods, CPT UI, JetEngine, Jetpack and any custom implementation. If WordPress knows about it, your AI assistant does too.

= Do I need to write code or configure anything? =

No. Install, activate, run the Get Started wizard. The plugin handles discovery and content management automatically.

= Which MCP clients are supported? =

Any MCP-compatible client with remote server support and OAuth 2.1. Confirmed: Claude Desktop, Claude Code, Cowork, ChatGPT, GitHub Copilot in VS Code, Cursor AI, Windsurf, Continue, Augment Code and JetBrains AI Assistant. There are 500+ MCP clients today and the protocol is the same in all of them.

= Is it secure? =

Yes. The plugin uses OAuth 2.1 with PKCE for authentication. Every MCP connection requires an authorization. Each OAuth client has its own `allowed_abilities` allowlist so you can restrict what a given client can do. Hourly and daily rate limits prevent abuse. Every call is recorded in the Activity Log with client_id, ability, status and IP for 30 days. WordPress options access is restricted to a security whitelist. The Lite version provides read-only access to users, options and theme settings; all destructive operations on posts, comments and media require explicit caller authentication.

= Can I limit which abilities a specific AI client can use? =

Yes. Each OAuth client has its own `allowed_abilities` allowlist. Open **Settings > Sentinel-MCP > Authentication**, click the **Permissions** button next to the client and pick "All abilities" or "Restricted to selected abilities" with checkboxes grouped by area (Core, WooCommerce, Multilingual, SEO). You can let Claude Desktop run everything while a CI integration only sees `list-recent-posts` and `read-post`. The helper class `OAuth_Permissions` also exposes `set/get/is_allowed` APIs for programmatic management.

= How do I monitor MCP usage? =

Two ways. The **Activity Log** tab shows every call with date, client_id, ability, status, duration and IP — paginated and searchable. The **/wp-json/sentinel/v1/health** public endpoint returns plugin and adapter status for any uptime monitor (UptimeRobot, BetterStack, Pingdom, etc.).

= Can I use Lite and Premium at the same time? =

If both plugins are active, the Premium version takes over automatically and you will see a friendly notice suggesting you deactivate Lite. There will be no errors or conflicts.

= Does it work with WooCommerce? =

Lite auto-discovers WooCommerce post types and adds four read-only abilities when WooCommerce is active (`wc-get-store-info`, `wc-list-products`, `wc-list-recent-orders` with email/name redaction, `wc-list-coupons`). Full store management — variations, refunds, customers, analytics, shipping zones, webhooks, settings and subscriptions across 5 platforms — is in the **[Premium version](https://plugins.joseconti.com/en/product/sentinel-mcp/)**.

= Can I read SEO meta from my AI assistant? =

Yes, in Lite. The `seo-read-meta` ability has adapters for Yoast SEO, Rank Math, AIOSEO, The SEO Framework, SureRank, SEOPress, Slim SEO and Squirrly. Writing SEO meta and content audits are Premium features.

= Does it support multilingual sites? =

Lite ships read-only support for Polylang, WPML and TranslatePress: list languages, list translations, fetch a specific translation and list string translations. Creating translations and AI-powered translation queueing are Premium features.

= Does it include backups? =

The Time Machine backup system with automatic snapshots before updates and one-click rollback is a Premium feature. **[Get Premium](https://plugins.joseconti.com/en/product/sentinel-mcp/)** for peace of mind on every update.

= Can it detect and clean malware? =

Yes, with the Premium version. Your AI assistant can verify core file integrity against official checksums, scan wp-content for malware patterns, detect database injections (scripts, iframes, hidden spam, pharma hacks), identify rogue admin accounts, verify plugins against WordPress.org, analyze `.htaccess` for malicious rules, replace infected core files with clean versions and regenerate security salts. All diagnostic scans are read-only and all cleanup actions require explicit confirmation.

= Can I run WP-CLI commands? =

The WP-CLI bridge with per-command permissions and default blocklist is Premium-only.

= Can my AI assistant generate images? =

Yes, in Lite. Configure a Google Gemini API key in **Settings > Sentinel-MCP > Settings**, then call `generate-image` (1–3 images per prompt) or `set-featured-from-prompt` (one image generated and set as featured of a post). Each generated image is saved to the Media Library with the prompt stored as `_mcpcomal_gemini_prompt` meta and as alt text. Lite is limited to Google Gemini, the model's native PNG output and up to 3 images per call. The Imagen API, multiple aspect ratios, 2K/4K resolutions and image editing with prompts are Premium features.

= How do I get a Gemini API key? =

Go to [Google AI Studio](https://aistudio.google.com/app/apikey) (free tier available), generate an API key and paste it into **Settings > Sentinel-MCP > Settings**. The key is stored with autoload disabled and never leaves your site except in the outgoing call to `generativelanguage.googleapis.com`.

= How do I customize rate limits? =

Filter `mcpcomal_rate_limit_per_hour` (default 1000) and `mcpcomal_rate_limit_per_day` (default 10000). Both are per `client_id`. You can also override the defaults via the constants `SENTINEL_RATE_LIMIT_PER_HOUR` and `SENTINEL_RATE_LIMIT_PER_DAY` in wp-config.php.

= Where is data stored? =

In your WordPress database, in dedicated tables created on activation (OAuth, Chat AI, Activity Log) plus a backup directory in `wp-content/uploads/`. Nothing is sent to third parties; the OAuth flow runs entirely between the client app and your site.

== Screenshots ==

1. Sentinel-MCP settings page with the Get Started wizard.
2. Auto-discovered site schema in Claude.
3. Creating content with natural language.
4. Activity Log of every MCP call with client_id, status and duration.
5. Connect tab with config exporter for Claude Desktop, ChatGPT, Cursor, Windsurf, Continue and JetBrains AI.
6. Prompts gallery with curated prompts.
7. Status tab with "Detected on this site" badges.
8. System diagnostics report.

== Changelog ==

= 1.1.0 — 2026-04-29 =

This release closes the gap with competing MCP plugins on read coverage and adds onboarding, audit, granular permissions, a multilingual layer and AI image generation.

Quantitative: 31 → 62 abilities (56 base + 4 multilingual + 2 AI image).

* New: Discovery extended — list-post-types, list-taxonomies, list-post-statuses, list-shortcodes, get-permalink-structure
* New: Site stats — get-site-stats (post/comment/user counts), get-media-stats (mime breakdown + total size on disk)
* New: Content shortcuts — list-recent-posts, list-pending-comments, list-scheduled-posts, list-trashed-posts, list-post-revisions
* New: FSE read-only — list-blocks-registered, list-block-patterns, list-fse-templates (metadata only)
* New: Menus, widgets and sidebars (read-only) — list-nav-menus, list-widgets, list-sidebars
* New: WooCommerce read-only (conditional) — wc-get-store-info, wc-list-products, wc-list-recent-orders (with email/name redaction), wc-list-coupons
* New: SEO read universal — seo-read-meta with adapters for Yoast, Rank Math, AIOSEO, The SEO Framework, SureRank, SEOPress, Slim SEO and Squirrly
* New: System extended — list-cron-events, list-user-roles
* New: Multilingual read-only — i18n-list-languages, i18n-list-translations-for-post, i18n-get-post-in-language, i18n-list-string-translations (Polylang, WPML, TranslatePress)
* New: AI Image Generation (Gemini) — generate-image (1–3 images per call) and set-featured-from-prompt. Settings tab subsection for the API key and model. Imagen API, multiple aspect ratios, 2K/4K and image editing remain Premium-only.
* New: MCP annotations (readOnlyHint, destructiveHint, idempotentHint, openWorldHint) on every ability — existing and new
* New: Activity Log — every MCP tool call recorded with client_id, slug, status, duration, error code and IP. Retention 30 days, daily cron purge. Admin tab with paginated viewer
* New: Granular OAuth permissions — per-client allowed_abilities allowlist (DB column + helper class) plus the "Permissions" subview in the Authentication tab to edit it visually with checkboxes grouped by area
* New: Rate limiting — hourly (1000) and daily (10000) caps per client_id, configurable via filters
* New: Get Started wizard — environment check + OAuth client creation + AI client selection + connection test
* New: Connect tab — config exporter for Claude Desktop, ChatGPT, Cursor, Windsurf, Continue, JetBrains AI and a curl debug snippet
* New: Public /sentinel/v1/health endpoint (unauthenticated) for uptime monitoring
* New: Prompts gallery — 30+ curated prompts loaded from data/prompts.json
* New: Spanish translation (mcp-sentinel-es_ES.po/.mo) — admin tab labels and key UI strings
* New: Premium catalog navigable — searchable browser of the Premium feature catalog inside the Go Premium tab
* New: "Detected on this site" badges in Status — links to the relevant Premium category for WooCommerce, Yoast, Rank Math, multilingual plugins and ACF
* New: Premium upsell hints with per-OAuth-session throttle (one hint per category, not on every call)
* New: docs/manual-test-plan-v1.1.md — human QA checklist for every sprint area

= 1.0.2 — 2026-04-08 =
* Fixed content CRUD abilities (create, read, update, search, delete) not registering when the Premium version was installed but inactive
* Fixed Gutenberg block reference ability not registering under the same condition
* Added defensive category registration fallback for content management abilities

= 1.0.1 — 2026-04-08 =
* Improved AI Mode notice for WordPress < 7.0: explains that all abilities are already available via MCP connection and links to connection settings
* Fixed PHP compatibility issue with vendor dependencies (brick/math) for WordPress.org validation
* Added FAQ entry explaining AI Mode vs MCP connection
* Added OpenRouter logo for the WordPress 7.0 Connectors API registration
* Condensed Premium features section in description

= 1.0.0 =
* First public release
* Auto-discovers all CPTs, taxonomies and registered meta fields
* Universal CRUD for any content type (posts, pages, custom post types)
* Full taxonomy management (list, create, update, delete terms)
* Complete comment management with bulk moderation
* Media library management (list, upload, featured images, attachments)
* User directory with role-based listing (read-only)
* WordPress options reader with security whitelist
* Theme customizations reader
* Full system diagnostics and environment report
* Plugin management (list, activate, deactivate)
* Debug mode toggle (WP_DEBUG)
* Recovery mode clearing
* Gutenberg block reference for AI-assisted content creation
* OAuth 2.1 authentication with PKCE for secure MCP connections
* Compatible with Claude, ChatGPT, GitHub Copilot, Cursor, Windsurf, Continue and any MCP client
* Coexistence support with Premium version (no conflicts)

== Upgrade Notice ==

= 1.1.0 =
Major release: 60+ abilities, OAuth 2.1 allowlists with a visual editor, rate limiting, Activity Log with 30-day retention, Get Started wizard, Connect tab with config exporter for 6 AI clients, public /health endpoint, AI image generation with Google Gemini, multilingual read across Polylang/WPML/TranslatePress, SEO read across 8 plugins, Spanish translation. Auto-migrates the OAuth schema on upgrade — no manual steps.

= 1.0.2 =
Fixed content CRUD abilities not registering when Premium version was installed but inactive. All 30 abilities now register correctly.

= 1.0.1 =
Improved AI Mode notice for WordPress < 7.0 — now explains how to use MCP connection in the meantime. Fixed PHP compatibility issue. Added OpenRouter logo for Connectors API.

= 1.0.0 =
First public release. Manage your entire WordPress site from Claude, ChatGPT, Copilot or any MCP client.
