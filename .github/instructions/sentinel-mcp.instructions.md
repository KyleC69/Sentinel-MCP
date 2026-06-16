---
description: "Use when working with the Sentinel-MCP WordPress plugin codebase. Covers project structure, coding standards, class naming, hook patterns, and quality expectations for PHP 8.3 WordPress plugins."
name: "Sentinel-MCP Plugin Standards"
applyTo: "sentinel-mcp/**/*.php"
---

# Sentinel-MCP Plugin — Agent Guidelines

## Project Overview

Sentinel-MCP is a WordPress plugin (v2.0.2) that exposes a WordPress site as an MCP (Model Context Protocol) server. It provides AI assistants with structured access to WordPress content, users, media, WooCommerce, SEO, and multilingual data.

**Key directories:**
- `sentinel-mcp.php` — Main plugin entry point (defines constants, loads autoloader, bootstraps classes)
- `includes/` — Core classes and ability definitions (~30 files)
- `includes/chat/` — Chat AI engine using `soukicz/llm` SDK
- `includes/oauth/` — OAuth 2.1 server implementation
- `includes/i18n/` — Adapters for Polylang, WPML, TranslatePress
- `includes/seo/` — Universal SEO read adapter
- `vendor/` — Bundled Composer dependencies
- `tests/` — PHPUnit tests (minimal stub-based setup)

## Versions and Compatibilty

This plugin uses cutting-edge technology with a minimum Wordpress version 7.0 so backward compatibility should not be considered in any decision making.

## Guideline Precedence & Fallbacks

When these instructions are ambiguous, silent on a specific situation, or conflict with each other, resolve them in this order:

1. **User's explicit request** — If the user explicitly asks for something that contradicts these guidelines, follow the user and briefly note which guideline was bypassed.
2. **Current published best practices** — For any topic not covered here (or where the instruction is unclear), follow modern PHP 8.3 and current WordPress core best practices as of the latest stable release.
3. **Existing codebase pattern** — When adding to an existing file, match the style already present in that file unless it violates a higher-priority rule above.
4. **These guidelines** — Use the rules below as the final authority when the above do not apply.

### Fallback examples
- **Callback syntax**: If the hook-style rule and the `[]` modernization rule conflict, prefer `[$this, 'method']` / `[__CLASS__, 'method']` (modern syntax) for new code; leave existing `array()` callbacks untouched unless you are already editing that line.
- **Existing files without `declare(strict_types=1)`**: Add it when editing the file unless doing so would break known untyped integrations; if you do not add it, tell the user why.
- **Verification tools unavailable**: If you cannot run `php -l` or PHPUnit, state that explicitly, identify the exact files/tests that should be run, and do not claim verification was completed.

## Architecture Patterns

### Class Naming & Structure
- **Namespace**: `SentinelMCP` — all classes live in the `SentinelMCP` namespace
- **Prefix**: No `SENTINEL_` prefix; the namespace provides uniqueness (e.g., `Admin`, `Chat_Engine`)
- **Function prefix**: `mcpcomal_*` for standalone helper functions (global namespace)
- **File guards**: Every file must start with `defined('ABSPATH') || exit;`
- **Class guards**: Wrap class definitions with `if (! class_exists('SentinelMCP\ClassName'))`
- **Hook style**: Use `[$this, 'method']` or `[__CLASS__, 'method']` for callbacks
- **Cross-namespace references**: Use `use SentinelMCP\ClassName;` or fully-qualified `\SentinelMCP\ClassName`

### Loading Pattern
The plugin does **not** use PSR-4 autoloading. `sentinel-mcp.php` manually `require_once`s every class file. When adding a new class:
1. Create the file in the appropriate `includes/` subdirectory
2. Add the `require_once` entry in `sentinel-mcp.php` in the correct load order
3. Ensure class guards are present to prevent redeclaration

## Quality Standards

### Modern PHP (PHP 8.3+)
- **Use `declare(strict_types=1);`** at the top of every new PHP file
- **Prefer `[]` over `array()`** — the codebase currently mixes both; normalize to `[]` when editing
- **Use typed properties and return types** where possible
- **Use `match` expressions** instead of long `switch` blocks when appropriate
- **Use nullsafe operator (`?->`)** and named arguments where they improve readability

### WordPress Best Practices
- **Sanitize all inputs**: Use `sanitize_text_field()`, `sanitize_key()`, `absint()` as appropriate
- **Nonce verification**: Use `check_admin_referer()` or `wp_nonce_field()` for all state-changing actions
- **Capability checks**: Always verify `current_user_can()` before privileged operations
- **Database queries**: Use `$wpdb->prepare()` for all dynamic queries; add `// phpcs:ignore` annotations for direct DB access when justified
- **Transients/options**: Use `update_option()` with `$autoload = false` for large or infrequently accessed settings
- **REST API**: Validate permissions via `permission_callback`; use `rest_ensure_response()`

### Code Hygiene
- **Remove debug code before committing**: No `var_dump()`, `print_r()`, or `error_log()` debug dumps in production code
- **No dead code**: Remove impossible conditions (e.g., `class_exists` checks that can never match)
- **Consistent PHPDoc**: Use `@package SENTINEL`, `@since` version tags, and `@link https://github.com/KyleC69/Sentinel-MCP`
- **No hardcoded secrets**: API keys must always be retrieved via `get_option()`, environment variables, or constants — never literals

## Feedback Rules — Alert the User

When you notice any of the following in existing code, **explicitly flag it to the user** with a brief explanation:

| Pattern | Why it's a problem | Suggested fix |
|---------|-------------------|---------------|
| `array()` instead of `[]` | Inconsistent with modern PHP | Normalize to `[]` |
| Missing `declare(strict_types=1)` | Loses type safety benefits | Add at file top |
| `var_dump` / `print_r` in production | Leaks data, breaks output | Remove or replace with structured logging |
| Impossible `class_exists` guards | Dead code, confusion | Remove or fix the logic |
| Hardcoded API keys or URLs | Security risk, inflexible | Move to options/constants |
| `global $wpdb` without abstraction | Tight coupling, hard to test | Inject or wrap in a repository method |
| Missing nonce/capability checks | Security vulnerability | Add `check_admin_referer()` and `current_user_can()` |
| Mixed PHPDoc `@link` domains | Inconsistent attribution | Standardize to `https://github.com/KyleC69/Sentinel-MCP` |
| Spanish or non-English comments | Inconsistent codebase language | Translate to English |
| Direct `$_POST` / `$_GET` access without `wp_unslash()` | Potential security issue | Use `sanitize_*` + `wp_unslash()` |

## Testing Expectations

- **New abilities** must include a PHPUnit test in `tests/` mirroring the existing stub-based approach
- **New REST endpoints** must include a test for the `permission_callback` and happy path
- **Run `php -l`** on modified files before considering work complete
- **Prefer testable code**: Avoid global state; use dependency injection or static factory methods that can be mocked

## Experimental API Awareness

This plugin uses the **WordPress 7.0 Connectors API** (`wp_connectors_init`, `wp_get_connectors()`, etc.) and the bundled **wordpress/mcp-adapter** package. These are cutting-edge APIs:

- Do **not** assume public documentation matches the bundled version exactly
- Do **not** change pinned vendor packages (especially `wordpress/mcp-adapter` and `soukicz/llm`) unless explicitly requested
- When in doubt, reference the actual source in `vendor/` rather than online docs

## Summary Checklist

Before finishing any task in this codebase:

- [ ] `defined('ABSPATH') || exit;` present at file top
- [ ] `declare(strict_types=1);` present for new files
- [ ] `namespace SentinelMCP;` present for all class files
- [ ] Class/function naming follows `SENTINEL_*` / `mcpcomal_*` conventions
- [ ] No debug dumps or hardcoded secrets
- [ ] All tests pass including new ones
- [ ] Security checks (nonce + capabilities) in place for state-changing code
- [ ] `php -l` passes with no errors - **Mandatory**
- [ ] Any potentially problematic or poor quality code patterns found nearby are flagged to the user

## Quality Priority Statement

**Code quality and design principles are of the highest priority in this codebase.** When working with Sentinel-MCP:

- Always prefer clean, maintainable, and testable code over quick fixes
- Follow SOLID principles where applicable — single responsibility, open/closed, dependency inversion
- Flag any quality issues you identify to the user, even if they are outside the scope of the current task
- Do not leave "TODO" comments in production code without explicit user approval
- Prefer composition over inheritance, dependency injection over global state, and explicit contracts over implicit behavior
- When you see technical debt, anti-patterns, or design flaws, alert the user with a clear explanation and suggested remediation
