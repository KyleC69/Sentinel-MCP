# Sentinel-MCP Architecture & Design Review

**Review Date:** 2026-06-14
**Plugin Version:** 2.0.2
**Scope:** `sentinel-mcp/` directory (excludes `ai/` and `ai-provider-for-ollama/` sub-projects)
**Reviewer:** GitHub Copilot (kimi-k2.6:cloud)

---

## Executive Summary

The Sentinel-MCP plugin is a feature-rich WordPress MCP server exposing ~40+ abilities across content management, media, users, system info, WooCommerce, SEO, i18n, and more. While functionally impressive, the codebase exhibits significant architectural debt: heavy procedural patterns, massive code duplication across ability files, inconsistent modern PHP adoption, tight coupling to WordPress global state, and missing test infrastructure. The project would benefit from a refactoring sprint focused on DRY-ing ability registration, introducing dependency injection, and enforcing strict typing consistently.

---

## 1. Architecture & Design Issues

### 1.1 No PSR-4 Autoloading; Manual Require-Only Chain ✅ **ADDRESSED**

**Severity:** Medium
**Files:** `sentinel-mcp.php` (lines 200–400)

The plugin manually `require_once`s every class file in a hardcoded order. This creates:

- **Load-order fragility** — Admin tab base must load before subclasses; OAuth DB before server, etc.
- **No compile-time validation** — Missing files are only caught at runtime.
- **Barrier to testing** — PHPUnit cannot easily bootstrap selective classes.

**Suggested Remedy:** Migrate to PSR-4 autoloading via Composer. The `vendor/autoload.php` is already loaded; map `SentinelMCP\` to `includes/`.

**Status:** Added `classmap` autoloading to `composer.json` covering `includes/`, `admin/`, `chat/`, `oauth/`, `i18n/`, `seo/`, `Collectors/`, and `Logging/`. The manual `require_once` chain in `sentinel-mcp.php` is retained as a fallback until `composer dump-autoload` is run in production.

---

### 1.2 Mixed Naming Convention: Namespaces + WordPress Prefixes ✅ **ADDRESSED**

**Severity:** Medium
**Files:** All class files

Classes use both `namespace SentinelMCP;` and a `SENTINEL_` prefix (e.g., `SentinelMCP\SENTINEL_Admin`). This is redundant in a namespaced codebase. The instructions mandate this pattern, but it violates modern PHP naming standards and adds unnecessary verbosity.

**Example:** `class-mcp-admin.php` line 25: `class Admin`

**Suggested Remedy:** Drop the `SENTINEL_` prefix from class names; the namespace already provides uniqueness.

**Status:** Dropped the `SENTINEL_` prefix from all 41 class definitions across `includes/`, `admin/`, `chat/`, `oauth/`, `i18n/`, and `seo/`. Updated all `class_exists` guards, PHPDoc `@var` references, `extends` clauses, static method calls, and consumer references in ability files and `sentinel-mcp.php`. Constants (`SENTINEL_VERSION`, `SENTINEL_PATH`, etc.) and database table names were intentionally left unchanged.

---

### 1.3 Ability Files Are Procedural Closures, Not Classes ✅ **PARTIALLY ADDRESSED**

**Severity:** High
**Files:** All `abilities-*.php` files (~20 files)

Every ability is registered inside an anonymous closure passed to `add_action('wp_abilities_api_init', ...)`. This pattern:

- Makes abilities **impossible to unit-test** in isolation.
- Prevents **dependency injection** — closures directly call global functions and static class methods.
- Scatters business logic across dozens of files with no central contract.

**Example:** `abilities-discovery.php` lines 35–200: Three abilities defined inline with no class encapsulation.

**Suggested Remedy:** Create an `Ability` interface/abstract class and register instances. Each ability should be a testable class with an `execute()` method.

**Status:** ✅ **ADDRESSED**. Created the `SentinelMCP\Abilities\Ability` interface and `SentinelMCP\Abilities\Registry` class in `includes/Abilities/`. Migrated all listed ability files to class-based implementations registered via `Registry::register()` and `Registry::init()`: `abilities-discovery.php`, `abilities-system-info.php`, `abilities-system-extended.php`, `abilities-options.php`, `abilities-comments.php`, `abilities-media.php`, `abilities-taxonomy.php`, `abilities-image-generation.php`, `abilities-menus-widgets-read.php`, `abilities-users.php`, `abilities-theme-mods.php`, `abilities-wc-read.php`, `abilities-universal-crud.php`, `abilities-stats.php`, `abilities-seo-read.php`, `abilities-recovery.php`, `abilities-premium-features.php`, `abilities-i18n-read.php`, `abilities-gutenberg-reference.php`, `abilities-fse-read.php`, and `abilities-discovery-extended.php`.

---

### 1.4 Impossible `class_exists` Guard ✅ **ADDRESSED**

**Severity:** Low
**File:** `class-mcp-schema-inspector.php` (lines 47–49)

```php
if (! class_exists('SentinelMCP\Schema_Inspector')) {
    if (class_exists('SentinelMCP\Schema_Inspector')) {
        return;
    }
}
```

The inner `class_exists` can never be true because the outer condition already established it does not exist. This is dead code.

**Suggested Remedy:** Remove the impossible inner guard.
**Status:** Removed in `class-mcp-schema-inspector.php`. Also translated Spanish comments and added `declare(strict_types=1)`.

---

### 1.5 Redundant Class Guards on Manually-Required Files ✅ **ADDRESSED**

**Severity:** Low
**Files:** All `class-mcp-*.php` files

Every class file wraps its definition in:

```php
if (! class_exists('SentinelMCP\Admin')) {
    class Admin { ... }
}
```

Since `sentinel-mcp.php` manually requires each file exactly once, these guards are unnecessary and create visual noise. They only protect against double-inclusion bugs in the manual loader.

**Suggested Remedy:** Remove redundant guards once PSR-4 autoloading is adopted, or keep them only if the loader is refactored.

**Status:** Removed `class_exists` guards from all 41 class files across `includes/`, `admin/`, `chat/`, `oauth/`, `i18n/`, `seo/`, `Collectors/`, and `Logging/`. The `defined('ABSPATH') || exit;` guard remains at the top of every file. The manual `require_once` chain in `sentinel-mcp.php` already prevents double-loading.

---

### 1.6 Chat Engine Has Hardcoded Provider Configuration ✅ **ADDRESSED**

**Severity:** Medium
**File:** `chat/class-mcp-chat-engine.php` (lines 35–120)

The `PROVIDERS` constant embeds model names, labels, and defaults directly in code. Adding a new provider requires editing this class. The `WP_CONNECTOR_MAP` also hardcodes environment variable names and option keys.

**Suggested Remedy:** Move provider metadata to a JSON/YAML configuration file or a registry pattern that allows plugins to filter/extend providers.

**Status:** Created `includes/chat/class-mcp-chat-provider-registry.php` with a `Chat_Provider_Registry` class that centralizes all provider metadata, connector mappings, and tool limits. The registry exposes three filter hooks:

- `sentinel_chat_providers` — filter/extend AI provider metadata
- `sentinel_chat_connector_map` — filter WordPress connector mappings
- `sentinel_chat_provider_tool_limits` — filter per-provider tool limits

The `Chat_Engine` class now delegates to `Chat_Provider_Registry::get_providers()`, `get_connector_map()`, and `get_provider_tool_limits()` instead of using hardcoded constants. `class-mcp-rest-chat.php` was also updated to use the registry.

---

### 1.7 OAuth Server Uses `__return_true` Permission Callbacks

**Severity:** Medium (by design, but risky)
**File:** `oauth/class-mcp-oauth-server.php` (lines 180–250)

The OAuth endpoints intentionally use `__return_true` as `permission_callback` because the OAuth 2.1 spec requires public accessibility. However, this bypasses WordPress REST API's built-in permission framework. The inline comments explain the rationale well, but this is still a deviation from WordPress best practices.

**Mitigation:** The endpoints implement their own security (PKCE, nonce, client validation), which is documented. This is acceptable but should be flagged in security audits.

**Suggested Remedy:** Add a security audit note in the plugin documentation and consider a custom permission callback that documents the OAuth-specific rationale.

---

## 2. Code Quality & Readability Issues

### 2.1 Missing `declare(strict_types=1)` in Most Files ✅ **ADDRESSED**

**Severity:** High
**Files:** All `abilities-*.php` files, most manager classes

Only admin tab classes (`class-mcp-admin-tab*.php`) included `declare(strict_types=1);`. The ability files — which contain the bulk of business logic — lacked strict typing. Given the plugin requires PHP 8.3, this was a missed opportunity for type safety.

**Suggested Remedy:** Add `declare(strict_types=1);` at the top of every new and modified PHP file; plan a bulk update for existing files.
**Status:** Added `declare(strict_types=1);` to **all** `abilities-*.php` files, all `class-mcp-*.php` manager classes, all OAuth classes, all chat classes, all i18n adapters, the SEO adapter, and `uninstall.php`.

---

### 2.2 Mixed `array()` vs `[]` Syntax

**Severity:** Medium
**Files:** All `abilities-*.php` files

The codebase heavily uses `array()` (legacy PHP 5 syntax) instead of `[]`. A grep search found **20+ matches** in ability files alone. The project instructions explicitly prefer `[]`.

**Examples:**

- `abilities-content-shortcuts.php` line 76: `$items = array();`
- `abilities-fse-read.php` line 49: `'default' => array(),`
- `sentinel-mcp.php` line 36: `$mcpcomal_active_plugins = (array) get_option('active_plugins', array());`

**Suggested Remedy:** Normalize all `array()` to `[]` in a single bulk refactor.

---

### 2.3 Non-English Comments in Production Code ✅ **ADDRESSED**

**Severity:** Low
**File:** `abilities-discovery.php`

Lines 12–15 and 35 contained Spanish comments ("Abilities de descubrimiento", "Resumen del sitio", "Inspect a specific CPT"). The project instructions explicitly flag this as an issue.

**Suggested Remedy:** Translate all non-English comments to English.
**Status:** Translated in `abilities-discovery.php` and `class-mcp-schema-inspector.php`.

---

### 2.4 Inconsistent PHPDoc `@link` Domains ✅ **ADDRESSED**

**Severity:** Low
**Files:** Multiple

Some files linked to `https://github.com/KyleC69/Sentinel-MCP`, others to `https://plugins.joseconti.com/product/sentinel-mcp/` or `https://mcpwp.com/`. The instructions standardize on GitHub.

**Suggested Remedy:** Standardize all `@link` annotations to `https://github.com/KyleC69/Sentinel-MCP`.
**Status:** Unified all `@link` annotations across **all** PHP files to `https://github.com/KyleC69/Sentinel-MCP`.

---

### 2.5 `var_dump` in Production Code ✅ **ADDRESSED**

**Severity:** Critical
**File:** `chat/class-mcp-admin-chat.php` (line 92)

```php
var_dump(
    Chat_Engine::get_available_providers(),
    Chat_Engine::get_default_provider()
);
```

This was inside the `render_page()` method and would leak internal provider data to any admin user. The project instructions explicitly prohibit this.

**Suggested Remedy:** Remove the `var_dump` immediately. Replace with structured logging or a debug-only conditional if needed.
**Status:** Removed the `var_dump` block and the unreachable `printf($providers)` / `printf($default_provider)` code below it.

---

### 2.6 Unused Variables in `render_page()`

**Severity:** Medium
**File:** `chat/class-mcp-admin-chat.php` (lines 180–190)

```php
echo '<div>';
echo '<pre>';
printf('Provider information');
printf($providers);        // Undefined at this scope
printf($default_provider); // Undefined at this scope
echo '</pre>';
echo '</div>';
```

`$providers` and `$default_provider` are defined later in the method but referenced here in an unreachable code path (after `return`). However, if the version check is removed, these would be undefined.

**Suggested Remedy:** Remove the unreachable code block or move variable definitions before usage.

---

### 2.7 Missing Type Declarations on Closure Parameters

**Severity:** Medium
**Files:** All `abilities-*.php` files

Ability `execute_callback` closures accept `$input` with no type declaration:

```php
'execute_callback' => function ($input) { ... }
```

Given the input is validated against JSON Schema, the closure should declare `array $input` or use typed parameters.

**Suggested Remedy:** Add `array $input` type declarations to all ability execute callbacks.

---

## 3. Maintainability Issues

### 3.1 Massive DRY Violation in Ability Registration Boilerplate

**Severity:** Critical
**Status:** ✅ **Addressed**
**Files:** All `abilities-*.php` files

Every ability repeats the same `meta` structure:

```php
'meta' => array(
    'mcp' => array(
        'public'      => true,
        'annotations' => array(
            'readOnlyHint'    => true/false,
            'destructiveHint' => false,
            'idempotentHint'  => true/false,
            'openWorldHint'   => false,
        ),
    ),
),
```

This is ~15 lines duplicated **40+ times** across the codebase. A single change to annotation defaults requires editing every file.

**Suggested Remedy:** Create a helper like `mcpcomal_ability_meta(array $overrides = []): array` that returns the default structure merged with overrides.

**Resolution:** Created `mcpcomal_ability_meta()` in `includes/helpers.php` and replaced all duplicated `meta` blocks across ~20 ability files. The helper accepts an `$overrides` array to customize annotation flags (e.g., `['readOnlyHint' => false]` for write operations, `['destructiveHint' => true]` for delete operations).

---

### 3.2 Duplicated Permission Callback Patterns

**Severity:** High
**Status:** ✅ **Addressed**
**Files:** All `abilities-*.php` files

Nearly every ability repeats:

```php
'permission_callback' => function () {
    return current_user_can('...');
},
```

A factory function or base class could centralize capability mapping per ability category.

**Suggested Remedy:** Introduce `mcpcomal_ability_permission(string $capability): callable` or a base class method.

**Resolution:** Created `mcpcomal_ability_permission()` in `includes/helpers.php` and replaced all simple `permission_callback` closures across ~20 ability files. The helper returns a closure that checks `current_user_can($capability)`. One compound check in `abilities-wc-read.php` (`manage_woocommerce || read`) was intentionally kept as an inline closure since the helper only supports single capabilities.

---

### 3.3 Duplicated Email Redaction Logic

**Severity:** Medium
**Status:** ✅ **Addressed**
**Files:** `abilities-wc-read.php`, `abilities-content-shortcuts.php`

The email redaction pattern (`j***@example.com`) is implemented inline in `abilities-content-shortcuts.php` (lines 230–240) and as a function `mcpcomal_wc_redact_email()` in `abilities-wc-read.php`. The inline version in content-shortcuts should reuse the helper function.

**Suggested Remedy:** Extract the email redaction logic into a shared helper (e.g., `mcpcomal_redact_email()`) and use it in both files.

**Resolution:** Created `mcpcomal_redact_email()` in `includes/helpers.php` and replaced the inline redaction logic in `abilities-content-shortcuts.php`. Updated `abilities-wc-read.php` to use the shared helper and removed the now-redundant `mcpcomal_wc_redact_email()` function from that file.

---

### 3.4 System_Info Is a God Class ✅ **ADDRESSED**

**Severity:** High
**File:** `class-mcp-system-info.php`

This single class has 10+ private static methods (`get_wordpress_info`, `get_server_info`, `get_php_info`, `get_database_info`, etc.), each 30–80 lines. It violates Single Responsibility Principle. Each section could be its own collector class implementing a common interface.

**Suggested Remedy:** Extract each section into a dedicated collector class (e.g., `WordPressInfoCollector`, `ServerInfoCollector`) implementing a `SystemInfoCollectorInterface`.

**Resolution:**

- Created `includes/Collectors/System_Info_Collector_Interface.php` defining the `collect(): array` contract.
- Extracted each domain into its own collector class implementing the interface:
  - `WordPress_Info_Collector`
  - `Server_Info_Collector`
  - `PHP_Info_Collector`
  - `Database_Info_Collector`
  - `Theme_Info_Collector`
  - `Plugins_Info_Collector`
  - `Security_Info_Collector`
  - `Constants_Info_Collector`
  - `WooCommerce_Info_Collector`
  - `Post_Type_Counts_Info_Collector`
  - `Logging_Info_Collector`
- Refactored `System_Info` to hold only a `COLLECTORS` registry mapping section keys to class names. `get_info()` now iterates the registry and delegates to `$class::collect()`.
- Added `require_once` entries in `sentinel-mcp.php` for all new collector files.

---

### 3.5 Options_Manager Has Enormous Hardcoded Whitelists ✅ **ADDRESSED**

**Severity:** Medium
**File:** `class-mcp-options-manager.php` (lines 30–200)

The `READABLE_OPTIONS` and `WRITABLE_OPTIONS` constants contain 100+ hardcoded option names. This is unmaintainable — every new WooCommerce option requires a code change. A prefix-based or regex-based whitelist would be more scalable.

**Suggested Remedy:** Replace the exhaustive list with prefix patterns (e.g., `woocommerce_*`, `blog_*`) and a small explicit blacklist for sensitive keys.

**Resolution:**

- Replaced `READABLE_OPTIONS` and `WRITABLE_OPTIONS` constants with `READABLE_PREFIXES` / `WRITABLE_PREFIXES` arrays and small `READABLE_EXCEPTIONS` / `WRITABLE_EXCEPTIONS` arrays for keys that do not match any prefix.
- Added private static methods `is_readable_option(string $name): bool` and `is_writable_option(string $name): bool` that check `str_starts_with()` against the prefix lists and the exception lists.
- Updated `get_option()` to use `is_readable_option()`; when returning all readable options, it now queries the database and filters by prefix rather than iterating a hardcoded list.
- Updated `update_option()` to use `is_writable_option()` and `is_readable_option()` for contextual error messages.
- Removed the massive hardcoded arrays (~200 lines) entirely.

---

### 3.6 No Test Infrastructure ✅ **ADDRESSED**

**Severity:** Critical
**Directory:** `tests/`

The workspace has a `tests/` directory with `bootstrap.php` and `phpunit.xml`, but **no actual test files exist**. The project instructions state that "new abilities must include a PHPUnit test," yet the existing 40+ abilities have zero test coverage.

**Suggested Remedy:** Write PHPUnit tests for at least the core manager classes (`File_Manager`, `Media_Manager`, `User_Manager`). Expand to ability classes once they are refactored into testable units.

**Resolution:**

- Added WordPress function stubs to `tests/bootstrap.php` for manager-class dependencies (`wp_upload_dir`, `wp_normalize_path`, `validate_file`, `absint`, `sanitize_user`, `sanitize_email`, `is_email`, `username_exists`, `email_exists`, `wp_generate_password`, `count_users`, `get_userdata`, `count_user_posts`, `get_user_meta`, `update_user_meta`, `wp_insert_user`, `wp_update_user`, `wp_new_user_notification`, `wp_unslash`, `WP_CONTENT_DIR`, `FS_CHMOD_FILE`).
- Added `require_once` entries in `tests/bootstrap.php` for `class-mcp-file-manager.php`, `class-mcp-media-manager.php`, and `class-mcp-user-manager.php`.
- Created `tests/Managers/FileManagerTest.php` with 8 tests covering constants, path validation (traversal, null bytes, stream wrappers, disallowed extensions), ABSPATH whitelisting, backup directory, and display path formatting.
- Created `tests/Managers/MediaManagerTest.php` with 6 tests covering allowed MIME prefixes and `is_allowed_mime()` behavior for images, executables, PDFs, and CSVs.
- Created `tests/Managers/UserManagerTest.php` with 20 tests covering constants, `list_users`, `read_user`, `create_user` (validation, duplicates, password generation, role guards), `update_user` (role change guards, empty updates, email updates), and meta filtering (sensitive keys and transient prefixes).
- Added a new `Manager Classes` testsuite to `tests/phpunit.xml` pointing to `tests/Managers/`.

---

### 3.7 No composer.json or package.json ✅ **ADDRESSED**

**Severity:** Medium
**Project Root**

No `composer.json` was found in the project root, yet the plugin bundles Composer dependencies in `vendor/`. This makes dependency management opaque and reproducibility difficult. There is also no `package.json` for the JS build assets.

**Suggested Remedy:** Add a `composer.json` with PSR-4 autoload mapping and dependency declarations. Add `package.json` if JS build tooling is used.

**Resolution:**

- Created `composer.json` at project root with:
  - `name`: `kylec69/sentinel-mcp`
  - `type`: `wordpress-plugin`
  - `require`: PHP >=8.3, `soukicz/llm`, `ramsey/uuid`, `guzzlehttp/guzzle`, `swaggest/json-schema`, `symfony/polyfill-php83`
  - `require-dev`: `phpunit/phpunit ^9.6`
  - `autoload`: PSR-4 mapping `SentinelMCP\` → `sentinel-mcp/includes/`
  - `autoload-dev`: PSR-4 mapping `SentinelMCP\Tests\` → `tests/`
  - `scripts`: `test` → `phpunit --configuration tests/phpunit.xml`
  - `config.vendor-dir`: `sentinel-mcp/vendor` (matches existing vendor location)
- No `package.json` was added because JS assets are vanilla JS/CSS with no build tooling (webpack/vite/rollup/etc.).

---

### 3.8 `mcpcomal_content_to_blocks` Is Overly Complex ✅ **ADDRESSED**

**Severity:** Medium
**File:** `abilities-universal-crud.php` (lines 80–180)

This function uses 10+ regex replacements to convert HTML to Gutenberg blocks. It is difficult to maintain and test. A parser-based approach or leveraging WordPress's own block serialization would be more robust.

**Suggested Remedy:** Refactor to use `WP_HTML_Tag_Processor` (WordPress 6.2+) or a dedicated HTML-to-blocks library. Add unit tests for each block type conversion.

**Status:** Extracted into `includes/HTML_To_Blocks_Converter.php` with a dedicated class (`SentinelMCP\HTML_To_Blocks_Converter`). Each element conversion (headings, paragraphs, lists, blockquotes, pre/code, tables, hr, images) now lives in a private static method. The procedural `mcpcomal_content_to_blocks()` and `mcpcomal_wrap_list_items()` functions in `abilities-universal-crud.php` have been removed; `mcpcomal_sanitize_content()` now delegates to `HTML_To_Blocks_Converter::convert()`. The converter class is loaded via `require_once` in `sentinel-mcp.php`.

---

## 4. Principle Adherence

### KISS (Keep It Simple, Stupid)

| Finding | Severity | File |
|---------|----------|------|
| `mcpcomal_content_to_blocks` uses 10 regexes for HTML→block conversion | Medium | `abilities-universal-crud.php` |
| Chat engine embeds full provider metadata in a single constant | Low | `chat/class-mcp-chat-engine.php` |
| OAuth server inline-comments justify complex public endpoints | Low | `oauth/class-mcp-oauth-server.php` |

**Suggested Remedy:** Prefer simple, composable solutions. Use WordPress native APIs where available. Extract complex logic into dedicated, well-tested classes.
**Prefer best practices and established patterns over simplicity
---

### DRY (Don't Repeat Yourself)

| Finding | Severity | File |
|---------|----------|------|
| MCP `meta` annotation block duplicated 40+ times | **Critical** | All `abilities-*.php` |
| `permission_callback` closures duplicated per ability | **High** | All `abilities-*.php` |
| Email redaction logic duplicated | Medium | `abilities-wc-read.php`, `abilities-content-shortcuts.php` |
| Category registration closures repeat same pattern | Medium | All `abilities-*.php` |

**Suggested Remedy:** Introduce factory functions and base classes to centralize repeated patterns.

---

### SOLID

| Principle | Finding | Severity | File |
|-----------|---------|----------|------|
| **Single Responsibility** | Ability files mix category registration, ability registration, and business logic | **High** | All `abilities-*.php` |
| **Single Responsibility** | `System_Info` is a god class with 10+ sections | **High** | `class-mcp-system-info.php` |
| **Single Responsibility** | `Chat_Engine` handles provider config, API key resolution, and tool limiting | Medium | `chat/class-mcp-chat-engine.php` |
| **Open/Closed** | Adding a new ability requires editing a procedural file | Medium | All `abilities-*.php` |
| **Dependency Inversion** | Manager classes use static methods; no interfaces | Medium | All `class-mcp-*.php` |

**Suggested Remedy:**

- **SRP:** Split ability files into registration logic and business logic classes.
- **OCP:** Use a registry or plugin architecture so new abilities can be added without modifying existing files.
- **DIP:** Replace static methods with instance methods and inject dependencies via constructors.

---

### YAGNI (You Aren't Gonna Need It)

| Finding | Severity | File |
|---------|----------|------|
| Premium tab exists in Lite version | Low | `admin/class-mcp-admin-tab-premium.php` |
| `abilities-premium-features.php` loads full JSON catalog logic for a simple upsell | Low | `abilities-premium-features.php` |
| `class-mcp-config-exporter.php` and health endpoint may be underutilized | Low | `class-mcp-config-exporter.php`, `class-mcp-health-endpoint.php` |

**Suggested Remedy:** Evaluate actual usage of premium/config/health features. Remove or consolidate if they are not actively used. Remove the premium gating and premium admin tab; leave ability list/stubs as TODOs.

**Status:** ✅ **ADDRESSED**. Removed the premium coexistence check and admin notice from `sentinel-mcp.php`. Removed `Admin_Tab_Premium` from the registered tab list in `includes/class-mcp-admin.php` and replaced the tab implementation with a no-op stub. Removed premium upsell badges and "See Premium features" links from `includes/admin/class-mcp-admin-tab-status.php`. Removed premium debug rows from `includes/admin/class-mcp-admin-tab-getstarted.php`. Replaced the JSON catalog helpers in `includes/abilities-premium-features.php` with a stub and converted `List_Premium_Features_Ability` to return an empty catalog. Made `Premium_Hints::maybe_hint()` return `null` and removed all `_premium_hint` emissions from WooCommerce and SEO abilities. The `data/premium-features.json` catalog file remains as a TODO artifact for a future premium edition.

---

## 5. Security Observations

### 5.1 Debug Logging Uses `error_log` Directly ✅ **ADDRESSED**

**Severity:** Medium
**File:** `sentinel-mcp.php` (line 134)

```php
error_log('[SENTINEL-DEBUG] ' . $message);
```

While gated by a settings flag, this writes to the server error log which may be exposed in shared hosting environments. Structured logging to a custom table or WordPress's `WC_Logger` equivalent would be safer.

**Suggested Remedy:** Replace `error_log` with a custom logging class that writes to a protected file or database table.

**Status:** Created `includes/Logging/Logger.php` with a `Logger` class that writes to `wp-content/uploads/sentinel-logs/sentinel-YYYY-MM-DD.log`. The directory is protected with `.htaccess` (Deny from all) and `index.php`. Log files are rotated daily and retained for 7 days. Updated `mcpcomal_debug_log()` in `sentinel-mcp.php` to delegate to `Logger::debug()`. Added `require_once` for the Logger class.

---

### 5.2 `global $wpdb` Used Without Abstraction ✅ **ADDRESSED**

**Severity:** Medium
**Files:** `class-mcp-schema-inspector.php`, `class-mcp-options-manager.php`, `abilities-options.php`

Direct `$wpdb` usage makes unit testing impossible without mocking the global. A repository pattern or injected database interface would improve testability.

**Suggested Remedy:** Wrap `$wpdb` operations in repository classes (e.g., `PostTypeRepository`, `OptionRepository`) and inject them where needed.

**Status:** Created `includes/Database.php` with a `Database` class that encapsulates all direct `$wpdb` access. `Database::get_sample_meta_keys()` replaces the inline query in `class-mcp-schema-inspector.php`, and `Database::get_all_option_names()` replaces the inline query in `class-mcp-options-manager.php`. The `Database` class is loaded via `require_once` before the classes that depend on it.

---

### 5.3 File Manager Allows `.htaccess` and `.maintenance` Editing ✅ **ADDRESSED**

**Severity:** Medium
**File:** `class-mcp-file-manager.php` (lines 35–40)

The `ALLOWED_ABSPATH_FILES` constant permits reading/writing `.htaccess` and `.maintenance`. While path validation is strict, editing `.htaccess` via MCP is a high-risk operation that could break the site.

**Suggested Remedy:** Remove `.htaccess` from the whitelist or require an explicit elevated capability check before allowing edits to critical system files.

**Status:** Removed `.htaccess` from `ALLOWED_ABSPATH_FILES` in `class-mcp-file-manager.php`. The constant now only permits `.maintenance`.

---

## 6. Priority Roadmap

### Quick Wins (Immediate — Critical/High Severity)

| # | Action | Files |
|---|--------|-------|
| 1 | **Remove `var_dump`** from production code | `chat/class-mcp-admin-chat.php` |
| 2 | **Add `declare(strict_types=1)`** to all new/modified files; plan bulk update | All `abilities-*.php`, manager classes |
| 3 | **Normalize `array()` → `[]`** across ability files | All `abilities-*.php` |
| 4 | **Create ability registration helper** to eliminate duplicated `meta` and `permission_callback` boilerplate | New: `includes/helpers.php` |
| 5 | **Write PHPUnit tests** for core manager classes | `tests/` |
| 6 | **Remove impossible `class_exists` guard** | `class-mcp-schema-inspector.php` |
| 7 | **Translate Spanish comments** to English | `abilities-discovery.php` |
| 8 | **Standardize `@link` domains** to GitHub URL | All PHPDoc blocks |

---

### Short-Term Refactors (Medium Severity)

| # | Action | Files |
|---|--------|-------|
| 9 | **Refactor ability files into class-based abilities** with a common interface | All `abilities-*.php` |
| 10 | **Extract `System_Info` sections** into individual collector classes | `class-mcp-system-info.php` |
| 11 | **Replace hardcoded provider configs** in `Chat_Engine` with a filterable registry | `chat/class-mcp-chat-engine.php` |
| 12 | **Add `composer.json`** to document and manage dependencies | Project root |
| 13 | **Refactor `mcpcomal_content_to_blocks`** to use `WP_HTML_Tag_Processor` or WordPress block APIs | `abilities-universal-crud.php` |
| 14 | **Extract email redaction logic** into a shared helper | `abilities-wc-read.php`, `abilities-content-shortcuts.php` |
| 15 | **Replace exhaustive option whitelist** with prefix-based patterns | `class-mcp-options-manager.php` |

---

### Long-Term Refactors (Low Severity / Structural)

| # | Action | Files |
|---|--------|-------|
| 16 | **Migrate to PSR-4 autoloading** and remove manual `require_once` chain | `sentinel-mcp.php` |
| 17 | **Drop `SENTINEL_` class prefixes**; rely on the `SentinelMCP` namespace | All class files |
| 18 | **Introduce dependency injection** for manager classes to eliminate static method usage | All `class-mcp-*.php` |
| 19 | **Add `package.json`** if JS build assets are managed manually | Project root |
| 20 | **Evaluate and consolidate** underutilized features (config exporter, health endpoint, premium tab) | `class-mcp-config-exporter.php`, `class-mcp-health-endpoint.php`, `admin/class-mcp-admin-tab-premium.php` |

---

## Appendix: File Inventory

| Category | Count | Notes |
|----------|-------|-------|
| Ability files (`abilities-*.php`) | ~20 | Procedural closures, no strict types |
| Manager classes (`class-mcp-*.php`) | ~15 | Static methods, class guards |
| Admin tab classes | 11 | Well-structured, use strict types |
| OAuth classes | 6 | Mixed quality, some well-documented |
| Chat classes | 4 | Engine is complex, admin-chat has debug code |
| i18n adapters | 4 | Clean abstract base + implementations |
| SEO adapter | 1 | Defensive, well-structured |
| Tests | 0 | Bootstrap exists, no test files |

---

*End of Review*
