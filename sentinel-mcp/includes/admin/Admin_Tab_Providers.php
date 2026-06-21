<?php

declare(strict_types=1);

namespace SentinelMCP;

use WordPress\AiClient\AiClient;

/**
 * Providers admin tab.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

/**
 * Renders the Providers tab: lists available AI connectors and their status.
 *
 * @since 2.0
 */
class Admin_Tab_Providers extends Admin_Tab
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		parent::__construct('providers', __('Providers', 'mcp-sentinel'));
	}

	/**
	 * Render the tab content.
	 *
	 * @return void
	 */
	public function render(): void
	{
		$has_ai_client = class_exists('\WordPress\AiClient\AiClient');

	add_action('admin_notices', function () use ($has_ai_client) {
		if (! empty($has_ai_client)) {
			return;
		}

		echo '<div class="notice notice-error"><p>Required component AIClient is missing.</p></div>';
	});

	$connectors            = $this->get_ai_connectors();

?>
		<div class="card" style="max-width:900px;margin-bottom:20px;padding:15px;">
			<h2 style="margin-top:0;"><?php esc_html_e('AI Providers', 'mcp-sentinel'); ?></h2>
			<p><?php esc_html_e('These are the AI provider connectors registered in your WordPress site. Sentinel-MCP uses them to discover API keys and provider metadata.', 'mcp-sentinel'); ?></p>

			<div class="ai-dashboard-status__column">
				<h4 class="ai-dashboard-status__section-title"><?php esc_html_e('Connectors', 'ai'); ?></h4>
				<ul class="ai-dashboard-status__list">
					<?php foreach ($connectors as $connector) : ?>
						<li class="ai-dashboard-status__list-item">
							<?php if ($connector['configured']) : ?>
								<span class="dashicons dashicons-yes-alt ai-dashboard-status__icon--success"></span>
							<?php else : ?>
								<span class="dashicons dashicons-no ai-dashboard-status__icon--error"></span>
							<?php endif; ?>
							<?php echo esc_html($connector['name']); ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<a class="ai-dashboard-status__column-link" href="<?php echo esc_url(admin_url('options-connectors.php')); ?>">
					<?php esc_html_e('Manage Connectors', 'ai'); ?>
				</a>
			</div>
		</div>

		<div class="card" style="max-width:900px;margin-bottom:20px;padding:15px;">
			<h3 style="margin-top:0;"><?php esc_html_e('How connectors work', 'mcp-sentinel'); ?></h3>
			<p><?php esc_html_e('AI providers are registered by the WordPress AI Client or by AI provider plugins. Each provider describes how to authenticate. Sentinel-MCP reads the AiClient registry to discover providers and API keys automatically.', 'mcp-sentinel'); ?></p>
			<p style="margin-bottom:5px;"><strong><?php esc_html_e('API key source priority:', 'mcp-sentinel'); ?></strong></p>
			<ol style="margin-top:5px;">
				<li><?php esc_html_e('Environment variable (e.g., ANTHROPIC_API_KEY)', 'mcp-sentinel'); ?></li>
				<li><?php esc_html_e('PHP constant (e.g., define("ANTHROPIC_API_KEY", "sk-..."))', 'mcp-sentinel'); ?></li>
				<li><?php esc_html_e('WordPress option (stored in the database)', 'mcp-sentinel'); ?></li>
			</ol>
		</div>
<?php
	}


	/**
	 * Returns AI provider connectors with their configuration status.
	 *
	 * @since 0.8.0
	 *
	 * @return list<array{name: string, configured: bool}> Connector info.
	 */
	private function get_ai_connectors(): array
	{
		$connectors = array();

		foreach (get_ai_connectors() as $slug => $connector_data) {
			$auth       = $connector_data['authentication'];
			$configured = 'api_key' === $auth['method']
				&& is_connector_configured($slug);

			/**
			 * Filters whether an AI connector is configured.
			 *
			 * Allows third-party plugins to declare credential availability for
			 * connectors that do not rely on API key settings.
			 *
			 * The dynamic portion of the hook name, `$slug`, refers to the connector slug.
			 * For example, if the connector slug is 'openai', the hook name
			 * will be 'wpai_is_openai_connector_configured'.
			 *
			 * @since 0.9.0
			 *
			 * @param bool $configured Whether the connector is configured.
			 * @param array<string, mixed> $connector_data The connector data.
			 */
			$configured = (bool) apply_filters("wpai_is_{$slug}_connector_configured", $configured, $connector_data);

			$connectors[] = array(
				'name'       => $connector_data['name'] ?? $slug,
				'configured' => $configured,
			);
		}

		return $connectors;
	}



	/**
	 * Determine the configuration status of a connector.
	 *
	 * Queries the AiClient registry for the provider instance and its
	 * authentication configuration, then delegates to the local helper
	 * get_connector_api_key_source() for source detection.
	 *
	 * @param string $id Connector ID (e.g. 'anthropic').
	 * @return array{
	 *     configured: bool,
	 *     source: string,
	 *     method: string,
	 *     source_detail: string
	 * }
	 */
	private function get_connector_status(string $id): array
	{
		// Prefer AiClient metadata when available.
		if (class_exists('\WordPress\AiClient\AiClient')) {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			if ($registry->hasProvider($id)) {
				return $this->get_ai_client_status($id, $registry);
			}
		}

		// Fall back to the WordPress Connectors API.
		if (function_exists('wp_is_connector_registered') && wp_is_connector_registered($id)) {
			return $this->get_wp_connector_status($id);
		}

		return [
			'configured'    => false,
			'source'        => __('Not registered', 'mcp-sentinel'),
			'method'        => 'none',
			'source_detail' => '',
		];
	}

	/**
	 * Get status from AiClient registry.
	 *
	 * @param string   $id       Connector ID.
	 * @param mixed    $registry AiClient registry.
	 * @return array
	 */
	private function get_ai_client_status(string $id, $registry): array
	{
		$class_name = $registry->getProviderClassName($id);
		if (! $class_name || ! class_exists($class_name)) {
			return [
				'configured'    => false,
				'source'        => __('Provider class missing', 'mcp-sentinel'),
				'method'        => 'none',
				'source_detail' => '',
			];
		}

		$provider = new $class_name();
		if (! method_exists($provider, 'getAuthentication')) {
			return [
				'configured'    => false,
				'source'        => __('No auth metadata', 'mcp-sentinel'),
				'method'        => 'none',
				'source_detail' => '',
			];
		}

		$auth   = $provider->getAuthentication();
		$method = $auth['method'] ?? 'none';

		if ('none' === $method) {
			return [
				'configured'    => true,
				'source'        => __('No auth required', 'mcp-sentinel'),
				'method'        => $method,
				'source_detail' => '',
			];
		}

		if ('api_key' !== $method) {
			return [
				'configured'    => false,
				'source'        => __('Unsupported method', 'mcp-sentinel'),
				'method'        => $method,
				'source_detail' => '',
			];
		}

		$source = get_connector_api_key_source(
			$auth['setting_name']  ?? '',
			$auth['env_var_name']  ?? '',
			$auth['constant_name'] ?? ''
		);

		if ('none' !== $source) {
			return [
				'configured'    => true,
				'source'        => $this->map_source_label($source),
				'method'        => $method,
				'source_detail' => $this->resolve_source_detail($source, $id, $auth),
			];
		}

		return [
			'configured'    => false,
			'source'        => __('Not configured', 'mcp-sentinel'),
			'method'        => $method,
			'source_detail' => $this->build_missing_hint(
				$id,
				$auth['env_var_name']  ?? strtoupper($id) . '_API_KEY',
				$auth['constant_name'] ?? strtoupper($id) . '_API_KEY',
				$auth['setting_name']  ?? '',
				$auth['credentials_url'] ?? ''
			),
		];
	}

	/**
	 * Get status from WordPress Connectors API.
	 *
	 * @param string $id Connector ID.
	 * @return array
	 */
	private function get_wp_connector_status(string $id): array
	{
		$connector = wp_get_connector($id);
		if (! is_array($connector)) {
			return [
				'configured'    => false,
				'source'        => __('Connector data unavailable', 'mcp-sentinel'),
				'method'        => 'none',
				'source_detail' => '',
			];
		}

		$auth   = $connector['authentication'] ?? [];
		$method = $auth['method'] ?? 'none';

		if ('none' === $method) {
			return [
				'configured'    => true,
				'source'        => __('No auth required', 'mcp-sentinel'),
				'method'        => $method,
				'source_detail' => '',
			];
		}

		if ('api_key' !== $method) {
			return [
				'configured'    => false,
				'source'        => __('Unsupported method', 'mcp-sentinel'),
				'method'        => $method,
				'source_detail' => '',
			];
		}

		$source = get_connector_api_key_source(
			$auth['setting_name']  ?? '',
			$auth['env_var_name']  ?? '',
			$auth['constant_name'] ?? ''
		);

		if ('none' !== $source) {
			return [
				'configured'    => true,
				'source'        => $this->map_source_label($source),
				'method'        => $method,
				'source_detail' => $this->resolve_source_detail($source, $id, $auth),
			];
		}

		return [
			'configured'    => false,
			'source'        => __('Not configured', 'mcp-sentinel'),
			'method'        => $method,
			'source_detail' => $this->build_missing_hint(
				$id,
				$auth['env_var_name']  ?? strtoupper($id) . '_API_KEY',
				$auth['constant_name'] ?? strtoupper($id) . '_API_KEY',
				$auth['setting_name']  ?? '',
				$auth['credentials_url'] ?? ''
			),
		];
	}

	/**
	 * Map a raw source slug to a human-readable label.
	 *
	 * @param string $source One of 'env_var', 'constant', 'setting'.
	 * @return string
	 */
	private function map_source_label(string $source): string
	{
		$labels = [
			'env_var'   => __('Environment variable', 'mcp-sentinel'),
			'constant'  => __('PHP constant', 'mcp-sentinel'),
			'setting'   => __('WordPress option', 'mcp-sentinel'),
		];
		return $labels[$source] ?? $source;
	}

	/**
	 * Derive the environment-variable name for a connector.
	 *
	 * Uses the explicit env_var_name when set, otherwise falls back to the
	 * AI-provider naming convention: {PROVIDER_ID}_API_KEY (uppercase).
	 *
	 * @param string $id   Connector ID.
	 * @param array  $auth Authentication config.
	 * @param string $type Connector type (e.g. 'ai_provider').
	 * @return string
	 */
	private function derive_env_var_name(string $id, array $auth, string $type): string
	{
		if (! empty($auth['env_var_name'])) {
			return $auth['env_var_name'];
		}
		if ('ai_provider' !== $type) {
			return '';
		}
		return strtoupper($id) . '_API_KEY';
	}

	/**
	 * Derive the PHP constant name for a connector.
	 *
	 * Uses the explicit constant_name when set, otherwise falls back to the
	 * AI-provider naming convention: {PROVIDER_ID}_API_KEY (uppercase).
	 *
	 * @param string $id   Connector ID.
	 * @param array  $auth Authentication config.
	 * @param string $type Connector type (e.g. 'ai_provider').
	 * @return string
	 */
	private function derive_constant_name(string $id, array $auth, string $type): string
	{
		if (! empty($auth['constant_name'])) {
			return $auth['constant_name'];
		}
		if ('ai_provider' !== $type) {
			return '';
		}
		return strtoupper($id) . '_API_KEY';
	}

	/**
	 * Build a hint showing what the user can set to configure this connector.
	 *
	 * @param string $id             Connector ID.
	 * @param string $env_var_name   Derived or explicit env var name.
	 * @param string $constant_name  Derived or explicit constant name.
	 * @param string $setting_name    Database option name.
	 * @param string $credentials_url URL to provider's key management page.
	 * @return string
	 */
	private function build_missing_hint(string $id, string $env_var_name, string $constant_name, string $setting_name, string $credentials_url): string
	{
		$hints = [];

		if ($env_var_name) {
			$hints[] = sprintf(
				/* translators: %s: environment variable name */
				__('Set env var %s', 'mcp-sentinel'),
				'<code>' . esc_html($env_var_name) . '</code>'
			);
		}
		if ($constant_name) {
			$hints[] = sprintf(
				/* translators: %s: PHP constant name */
				__('Define constant %s', 'mcp-sentinel'),
				'<code>' . esc_html($constant_name) . '</code>'
			);
		}
		if ($setting_name) {
			$hints[] = sprintf(
				/* translators: %s: option name */
				__('Save option %s', 'mcp-sentinel'),
				'<code>' . esc_html($setting_name) . '</code>'
			);
		}

		if (empty($hints)) {
			return '';
		}

		$hint_text = implode(' ' . __('or', 'mcp-sentinel') . ' ', $hints);

		if ($credentials_url) {
			$hint_text .= ' — ' . sprintf(
				/* translators: %s: link to credentials page */
				__('<a href="%s" target="_blank" rel="noopener noreferrer">Get API key →</a>', 'mcp-sentinel'),
				esc_url($credentials_url)
			);
		}

		return $hint_text;
	}

	/**
	 * Resolve the source detail (e.g. env var name) from get_connector_api_key_source() result.
	 *
	 * @param string $source One of 'env_var', 'constant', 'setting'.
	 * @param string $id     Connector ID.
	 * @param array  $auth   Authentication config.
	 * @return string
	 */
	private function resolve_source_detail(string $source, string $id, array $auth): string
	{
		$is_ai_provider = empty($auth['env_var_name']) && empty($auth['constant_name']);
		$derived          = $is_ai_provider ? strtoupper($id) . '_API_KEY' : '';

		switch ($source) {
			case 'env_var':
				return ! empty($auth['env_var_name']) ? $auth['env_var_name'] : $derived;
			case 'constant':
				return ! empty($auth['constant_name']) ? $auth['constant_name'] : $derived;
			case 'setting':
				return $auth['setting_name'] ?? '';
			default:
				return '';
		}
	}
}
