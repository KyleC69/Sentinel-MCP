<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Settings admin tab.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

if (! class_exists('SentinelMCP\SENTINEL_Admin_Tab_Settings')) {

	/**
	 * Renders the Settings tab: debug logging + Gemini config.
	 *
	 * Also handles its own POST action (mcpcomal_save_settings).
	 */
	class SENTINEL_Admin_Tab_Settings extends SENTINEL_Admin_Tab
	{

		/**
		 * Constructor.
		 */
		public function __construct()
		{
			parent::__construct('settings', __('Settings', 'mcp-sentinel'));
		}

		/**
		 * Render the tab content.
		 *
		 * @return void
		 */
		public function render(): void
		{
			$debug_enabled = (bool) get_option('mcpcomal_debug_logging', false);
			$gemini_key    = (string) get_option('mcpcomal_gemini_api_key', '');
			$gemini_model  = (string) get_option('mcpcomal_gemini_model', SENTINEL_Image_Generator::DEFAULT_MODEL);
			$key_masked    = '' === $gemini_key ? '' : str_repeat('•', max(0, strlen($gemini_key) - 4)) . substr($gemini_key, -4);
			?>
			<form method="post">
				<?php wp_nonce_field('mcpcomal_save_settings'); ?>

				<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
					<h2 style="margin-top:0;"><?php esc_html_e('Debug Logging', 'mcp-sentinel'); ?></h2>
					<p><?php esc_html_e('When enabled, MCP Content Manager writes debug messages to the PHP error log. Useful for troubleshooting, but should be disabled in production to avoid excessive log growth.', 'mcp-sentinel'); ?></p>
					<table class="form-table" style="margin:0;">
						<tr>
							<th scope="row" style="padding:8px 10px 8px 0;"><?php esc_html_e('Enable debug logging', 'mcp-sentinel'); ?></th>
							<td style="padding:8px 10px;">
								<label>
									<input type="checkbox" name="mcpcomal_debug_logging" value="1" <?php checked($debug_enabled); ?> />
									<?php esc_html_e('Write [SENTINEL-DEBUG] messages to the PHP error log.', 'mcp-sentinel'); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
					<h2 style="margin-top:0;"><?php esc_html_e('AI Image Generation (Gemini)', 'mcp-sentinel'); ?></h2>
					<p>
						<?php esc_html_e('Configure a Google Gemini API key to enable the generate-image and set-featured-from-prompt abilities. Lite uses the Gemini generateContent endpoint with image output. Imagen API, multiple aspect ratios, 2K/4K, image editing and safety controls are reserved for Premium.', 'mcp-sentinel'); ?>
					</p>
					<p>
						<?php
						printf(
							/* translators: %s: link to Google AI Studio */
							esc_html__('Get a free API key at %s.', 'mcp-sentinel'),
							'<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">aistudio.google.com</a>'
						);
						?>
					</p>
					<table class="form-table" style="margin:0;">
						<tr>
							<th scope="row" style="padding:8px 10px 8px 0;"><?php esc_html_e('Gemini API key', 'mcp-sentinel'); ?></th>
							<td style="padding:8px 10px;">
								<?php if ('' !== $gemini_key) : ?>
									<p style="margin:0 0 6px;color:#50575e;">
										<?php esc_html_e('Currently configured:', 'mcp-sentinel'); ?>
										<code><?php echo esc_html($key_masked); ?></code>
									</p>
								<?php endif; ?>
								<input type="password"
									name="mcpcomal_gemini_api_key"
									value=""
									placeholder="<?php echo '' === $gemini_key ? esc_attr__('Paste your API key', 'mcp-sentinel') : esc_attr__('Leave blank to keep current key', 'mcp-sentinel'); ?>"
									autocomplete="off"
									style="width:380px;font-family:monospace;" />
								<?php if ('' !== $gemini_key) : ?>
									<button type="submit" name="mcpcomal_clear_gemini_api_key" value="1" class="button button-link-delete" formnovalidate>
										<?php esc_html_e('Remove key', 'mcp-sentinel'); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row" style="padding:8px 10px 8px 0;"><?php esc_html_e('Model', 'mcp-sentinel'); ?></th>
							<td style="padding:8px 10px;">
								<input type="text"
									name="mcpcomal_gemini_model"
									value="<?php echo esc_attr($gemini_model); ?>"
									style="width:380px;font-family:monospace;" />
								<p class="description">
									<?php
									printf(
										/* translators: %s: default model id */
										esc_html__('Default: %s. Must be a Gemini model that supports image output.', 'mcp-sentinel'),
										'<code>' . esc_html(SENTINEL_Image_Generator::DEFAULT_MODEL) . '</code>'
									);
									?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<p><button type="submit" name="mcpcomal_save_settings" value="1" class="button button-primary"><?php esc_html_e('Save Settings', 'mcp-sentinel'); ?></button></p>
			</form>
			<?php
		}

		/**
		 * Handle POST actions for the Settings tab.
		 *
		 * @return bool True if a redirect was issued.
		 */
		public function handle_post(): bool
		{
			if (! isset($_POST['mcpcomal_save_settings'])) {
				return false;
			}

			check_admin_referer('mcpcomal_save_settings');

			// Explicit "Remove key" button takes precedence over the input field.
			if (! empty($_POST['mcpcomal_clear_gemini_api_key'])) {
				update_option('mcpcomal_gemini_api_key', '', false);
				$this->redirect_with_notice('settings', 'success', 'Gemini API key removed.');
			}

			update_option('mcpcomal_debug_logging', ! empty($_POST['mcpcomal_debug_logging']));

			if (isset($_POST['mcpcomal_gemini_api_key'])) {
				$key = trim(sanitize_text_field(wp_unslash((string) $_POST['mcpcomal_gemini_api_key'])));
				// Empty input = keep current key (so re-saving the form does not wipe it).
				if ('' !== $key) {
					update_option('mcpcomal_gemini_api_key', $key, false);
				}
			}
			if (isset($_POST['mcpcomal_gemini_model'])) {
				$model = sanitize_text_field(wp_unslash((string) $_POST['mcpcomal_gemini_model']));
				update_option('mcpcomal_gemini_model', '' === $model ? SENTINEL_Image_Generator::DEFAULT_MODEL : $model, false);
			}

			$this->redirect_with_notice('settings', 'success', 'Settings saved.');
		}
	}
}
