<?php

/**
 * Chat AI Admin page — fullscreen chat interface + Admin Bar button.
 *
 * Renders a fullscreen chat UI that hides WordPress admin chrome,
 * uses the user's selected WP admin color scheme, and provides
 * an "AI Mode" button in the admin bar.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @since      1.1.0
 */

defined('ABSPATH') || exit;

/**
 * Chat AI admin page and admin bar button.
 */
class SENTINEL_Admin_Chat
{

	/**
	 * Initialize hooks.
	 */
	public static function init(): void
	{
		add_action('admin_bar_menu', array(__CLASS__, 'add_admin_bar_button'), 999);
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_bar_css'));
	}

	/**
	 * Add "AI Mode" button to the WordPress admin bar (right side).
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 */
	public static function add_admin_bar_button(\WP_Admin_Bar $wp_admin_bar): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}
		if (! is_admin()) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'sentinel-ai-mode',
				'title' => '<span class="ab-icon dashicons dashicons-format-chat"></span><span class="ab-label">AI Mode</span>',
				'href'  => admin_url('admin.php?page=sentinel-chat'),
				'meta'  => array(
					'class' => 'sentinel-ai-mode-button',
					'title' => __('Open AI Chat (fullscreen)', 'mcp-sentinel'),
				),
			)
		);
	}

	/**
	 * Enqueue minimal CSS for the admin bar button on all admin pages.
	 */
	public static function enqueue_admin_bar_css(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		wp_add_inline_style(
			'admin-bar',
			'#wp-admin-bar-sentinel-ai-mode .ab-icon.dashicons { font-size: 20px; margin-right: 4px !important; }
			#wp-admin-bar-sentinel-ai-mode .ab-icon:before { top: 2px; }'
		);
	}

	/**
	 * Render the fullscreen chat page.
	 *
	 * Called as the callback for the sentinel-chat submenu page.
	 */
	public static function render_page(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'mcp-sentinel'));
		}

		// Require WordPress 7.0+ (accept beta/RC/alpha builds).
		$wp_ver = get_bloginfo('version');
		if (version_compare(preg_replace('/-(alpha|beta|RC)\d*$/i', '', $wp_ver), '7.0', '<')) {
			echo '<div class="wrap">';
			echo '<div style="max-width:600px;margin:80px auto;text-align:center;">';
			echo '<span class="dashicons dashicons-warning" style="font-size:48px;width:48px;height:48px;color:#dba617;"></span>';
			echo '<h1 style="margin-top:16px;">' . esc_html__('AI Mode requires WordPress 7.0', 'mcp-sentinel') . '</h1>';
			echo '<p style="font-size:15px;color:#50575e;">';
			echo esc_html__('The built-in AI chat uses the Connectors API introduced in WordPress 7.0. Update WordPress when it becomes available to unlock AI Mode.', 'mcp-sentinel');
			echo '</p>';
			echo '<div style="margin-top:24px;padding:16px 20px;background:#f0f6fc;border-left:4px solid #2271b1;border-radius:4px;text-align:left;">';
			echo '<p style="margin:0 0 8px;font-size:14px;font-weight:600;color:#1d2327;">';
			echo esc_html__('You can already use all abilities via MCP', 'mcp-sentinel');
			echo '</p>';
			echo '<p style="margin:0;font-size:13px;color:#50575e;">';
			echo esc_html__('Connect your AI assistant (Claude, ChatGPT, Copilot, Cursor, etc.) to your site as an MCP remote server. All content management features work right now with WordPress 6.9.', 'mcp-sentinel');
			echo '</p>';
			echo '</div>';
			printf(
				'<p style="margin-top:20px;"><strong>%s:</strong> %s</p>',
				esc_html__('Current version', 'mcp-sentinel'),
				esc_html(get_bloginfo('version'))
			);
			$settings_url = admin_url('options-general.php?page=sentinel-settings');
			echo '<p style="margin-top:20px;">';
			echo '<a href="' . esc_url($settings_url) . '" class="button button-primary">';
			echo esc_html__('View MCP Connection Details', 'mcp-sentinel');
			echo '</a> ';
			echo '<a href="' . esc_url(admin_url('update-core.php')) . '" class="button">';
			echo esc_html__('Update WordPress', 'mcp-sentinel');
			echo '</a></p>';
			echo '</div></div>';
			return;
		}

		// Enqueue vendor libraries.
		wp_enqueue_script(
			'sentinel-marked',
			SENTINEL_URL . 'assets/js/vendor/marked.min.js',
			array(),
			'15.0.4',
			true
		);
		wp_enqueue_script(
			'sentinel-dompurify',
			SENTINEL_URL . 'assets/js/vendor/purify.min.js',
			array(),
			'3.2.4',
			true
		);
		wp_enqueue_script(
			'sentinel-highlight',
			SENTINEL_URL . 'assets/js/vendor/highlight.min.js',
			array(),
			'11.11.1',
			true
		);

		// Enqueue chat app.
		wp_enqueue_style(
			'sentinel-chat',
			SENTINEL_URL . 'assets/css/mcpcomal-chat.css',
			array(),
			SENTINEL_VERSION
		);
		wp_enqueue_script(
			'sentinel-chat',
			SENTINEL_URL . 'assets/js/mcpcomal-chat.js',
			array('sentinel-marked', 'sentinel-dompurify', 'sentinel-highlight'),
			SENTINEL_VERSION,
			true
		);

		// Build provider data for JS.
		$providers        = SENTINEL_Chat_Engine::get_available_providers();
		$default_provider = SENTINEL_Chat_Engine::get_default_provider();
		$has_any_key      = false;
		foreach ($providers as $p) {
			if ($p['has_key']) {
				$has_any_key = true;
				break;
			}
		}

		wp_localize_script(
			'sentinel-chat',
			'mcpcomalChat',
			array(
				'restUrl'         => esc_url_raw(rest_url('sentinel/v1/chat/')),
				'nonce'           => wp_create_nonce('wp_rest'),
				'userId'          => get_current_user_id(),
				'userName'        => wp_get_current_user()->display_name,
				'userAvatar'      => get_avatar_url(get_current_user_id(), array('size' => 64)),
				'hasApiKey'       => $has_any_key,
				'providers'       => $providers,
				'defaultProvider' => $default_provider,
				'defaultModel'    => SENTINEL_Chat_Engine::get_default_model($default_provider),
				'settingsUrl'     => admin_url('options-connectors.php'),
				'adminUrl'        => admin_url(),
				'siteInfo'        => array(
					'name'       => get_bloginfo('name'),
					'url'        => home_url(),
					'wp_version' => get_bloginfo('version'),
				),
				'i18n'            => self::get_i18n_strings(),
			)
		);

		// Output fullscreen CSS: hide admin chrome.
		echo '<style>
			#wpadminbar, #adminmenumain, #adminmenuback, #adminmenuwrap, #wpfooter { display: none !important; }
			#wpcontent, #wpbody-content { margin-left: 0 !important; padding: 0 !important; }
			html.wp-toolbar { padding-top: 0 !important; }
			#wpbody { padding-top: 0 !important; }
			.wrap { margin: 0 !important; padding: 0 !important; max-width: none !important; }
		</style>';

		// Inject WP admin color scheme as CSS custom properties.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS custom properties from controlled data.
		echo '<style>' . self::get_color_scheme_css() . '</style>';

		// The app container — JS takes over from here.
		echo '<div id="sentinel-chat-app" data-loading="true"></div>';
	}

	/**
	 * Get CSS custom properties based on the user's WordPress admin color scheme.
	 *
	 * @return string CSS rules.
	 */
	private static function get_color_scheme_css(): string
	{
		global $_wp_admin_css_colors;

		$admin_color = get_user_option('admin_color');
		$scheme_slug = $admin_color ? $admin_color : 'fresh';

		if (! isset($_wp_admin_css_colors[$scheme_slug])) {
			$scheme_slug = 'fresh';
		}

		$scheme = $_wp_admin_css_colors[$scheme_slug];

		$colors       = $scheme->colors;
		$base         = $colors[0] ?? '#1d2327';
		$highlight    = $colors[1] ?? '#2c3338';
		$notification = $colors[2] ?? '#d63638';
		$action       = $colors[3] ?? $colors[2] ?? '#2271b1';

		$icon_colors = isset($scheme->icon_colors) ? (array) $scheme->icon_colors : array();
		$icon_base   = $icon_colors['base'] ?? '#a7aaad';
		$icon_focus  = $icon_colors['focus'] ?? '#72aee6';

		$sidebar_is_dark = self::is_dark_color($base);

		if ($sidebar_is_dark) {
			$sidebar_text           = '#f0f0f1';
			$sidebar_text_secondary = $icon_base;
			$sidebar_border         = 'rgba(255, 255, 255, 0.1)';
			$sidebar_hover          = 'rgba(255, 255, 255, 0.08)';
			$sidebar_input_bg       = 'rgba(255, 255, 255, 0.1)';
			$sidebar_input_border   = 'rgba(255, 255, 255, 0.15)';
		} else {
			$sidebar_text           = '#1d2327';
			$sidebar_text_secondary = '#50575e';
			$sidebar_border         = 'rgba(0, 0, 0, 0.1)';
			$sidebar_hover          = 'rgba(0, 0, 0, 0.06)';
			$sidebar_input_bg       = 'rgba(0, 0, 0, 0.06)';
			$sidebar_input_border   = 'rgba(0, 0, 0, 0.12)';
		}

		$bg_main        = '#f0f0f1';
		$text_primary   = '#1d2327';
		$text_secondary = '#50575e';
		$border         = '#c3c4c7';
		$msg_ai_bg      = '#ffffff';
		$msg_ai_text    = '#1d2327';
		$input_bg       = '#ffffff';
		$input_text     = '#1d2327';
		$hover_bg       = 'rgba(0, 0, 0, 0.04)';

		return ":root {
			--sentinel-chat-bg-sidebar: {$base};
			--sentinel-chat-bg-main: {$bg_main};
			--sentinel-chat-accent: var(--wp-admin-theme-color, {$action});
			--sentinel-chat-accent-hover: {$highlight};
			--sentinel-chat-notification: {$notification};
			--sentinel-chat-text: {$text_primary};
			--sentinel-chat-text-secondary: {$text_secondary};
			--sentinel-chat-border: {$border};
			--sentinel-chat-msg-user-bg: var(--wp-admin-theme-color, {$action});
			--sentinel-chat-msg-user-text: #ffffff;
			--sentinel-chat-msg-ai-bg: {$msg_ai_bg};
			--sentinel-chat-msg-ai-text: {$msg_ai_text};
			--sentinel-chat-input-bg: {$input_bg};
			--sentinel-chat-input-text: {$input_text};
			--sentinel-chat-hover: {$hover_bg};
			--sentinel-chat-sidebar-text: {$sidebar_text};
			--sentinel-chat-sidebar-text-secondary: {$sidebar_text_secondary};
			--sentinel-chat-sidebar-border: {$sidebar_border};
			--sentinel-chat-sidebar-hover: {$sidebar_hover};
			--sentinel-chat-sidebar-input-bg: {$sidebar_input_bg};
			--sentinel-chat-sidebar-input-border: {$sidebar_input_border};
			--sentinel-chat-icon-focus: {$icon_focus};
		}";
	}

	/**
	 * Check if a hex color is dark (luminance < 0.5).
	 *
	 * @param string $hex Hex color string (e.g. #1d2327).
	 * @return bool
	 */
	private static function is_dark_color(string $hex): bool
	{
		$hex = ltrim($hex, '#');

		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = hexdec(substr($hex, 0, 2)) / 255;
		$g = hexdec(substr($hex, 2, 2)) / 255;
		$b = hexdec(substr($hex, 4, 2)) / 255;

		$luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

		return $luminance < 0.5;
	}

	/**
	 * Get translatable strings for the JS frontend.
	 *
	 * @return array
	 */
	private static function get_i18n_strings(): array
	{
		return array(
			'newChat'            => __('New Chat', 'mcp-sentinel'),
			'searchPlaceholder'  => __('Search conversations...', 'mcp-sentinel'),
			'inputPlaceholder'   => __('Type a message...', 'mcp-sentinel'),
			'send'               => __('Send', 'mcp-sentinel'),
			'backToAdmin'        => __('Back to Admin', 'mcp-sentinel'),
			'noConversations'    => __('No conversations yet. Start a new chat!', 'mcp-sentinel'),
			'noApiKey'           => __('No API key configured. Please add one in Settings.', 'mcp-sentinel'),
			'goToSettings'       => __('Go to Settings', 'mcp-sentinel'),
			'welcomeTitle'       => __('Welcome to AI Chat', 'mcp-sentinel'),
			'welcomeSubtitle'    => __('Manage your WordPress site through natural language.', 'mcp-sentinel'),
			'welcomeSuggestion1' => __('What plugins are installed?', 'mcp-sentinel'),
			'welcomeSuggestion2' => __('Show me site info', 'mcp-sentinel'),
			'welcomeSuggestion3' => __('List all pages', 'mcp-sentinel'),
			'welcomeSuggestion4' => __('Create a new page', 'mcp-sentinel'),
			'thinking'           => __('Thinking...', 'mcp-sentinel'),
			'toolUsed'           => __('Used', 'mcp-sentinel'),
			'toolInput'          => __('Input', 'mcp-sentinel'),
			'toolOutput'         => __('Result', 'mcp-sentinel'),
			'deleteChat'         => __('Delete', 'mcp-sentinel'),
			'renameChat'         => __('Rename', 'mcp-sentinel'),
			'deleteConfirm'      => __('Delete this conversation?', 'mcp-sentinel'),
			'errorGeneric'       => __('An error occurred. Please try again.', 'mcp-sentinel'),
			'provider'           => __('Provider', 'mcp-sentinel'),
			'model'              => __('Model', 'mcp-sentinel'),
			'tokens'             => __('tokens', 'mcp-sentinel'),
		);
	}
}
