<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Prompts admin tab.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

	/**
	 * Renders the Prompts gallery tab.
	 */
	class Admin_Tab_Prompts extends Admin_Tab
	{

		/**
		 * Constructor.
		 */
		public function __construct()
		{
			parent::__construct('prompts', __('Prompts', 'mcp-sentinel'));
		}

		/**
		 * Render the tab content.
		 *
		 * @return void
		 */
		public function render(): void
		{
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters from URL.
			$category = isset($_GET['cat']) ? sanitize_key((string) $_GET['cat']) : '';
			$keyword  = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$catalog    = Prompt_Gallery::load();
			$total      = Prompt_Gallery::total_count();
			$categories = Prompt_Gallery::filter(
				'' === $category ? null : $category,
				'' === $keyword ? null : $keyword
			);
			?>
			<div class="card" style="max-width:900px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Prompts gallery (<?php echo esc_html((string) $total); ?>)</h2>
				<p>Copy any of these prompts and paste them into your AI client (Claude Desktop, Cursor, ChatGPT&hellip;) connected to this site.</p>

				<form method="get" style="margin-bottom:15px;">
					<input type="hidden" name="page" value="sentinel-settings">
					<input type="hidden" name="tab" value="prompts">
					<select name="cat">
						<option value="">All categories</option>
						<?php foreach ((array) $catalog['categories'] as $cat) : ?>
							<option value="<?php echo esc_attr((string) ($cat['slug'] ?? '')); ?>" <?php selected($category, (string) ($cat['slug'] ?? '')); ?>>
								<?php echo esc_html((string) ($cat['label'] ?? $cat['slug'] ?? '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<input type="text" name="q" value="<?php echo esc_attr($keyword); ?>" placeholder="Search&hellip;" style="width:240px;">
					<button type="submit" class="button">Filter</button>
				</form>

				<?php if (empty($categories)) : ?>
					<p>No prompts match the current filters.</p>
				<?php else : ?>
					<?php foreach ($categories as $cat) : ?>
						<h3 style="margin-top:20px;"><?php echo esc_html((string) ($cat['label'] ?? '')); ?></h3>
						<?php foreach ((array) ($cat['prompts'] ?? []) as $prompt) : ?>
							<div style="border:1px solid #dcdcde;border-radius:4px;padding:10px;margin-bottom:8px;">
								<strong><?php echo esc_html((string) ($prompt['title'] ?? '')); ?></strong>
								<?php if (! empty($prompt['description'])) : ?>
									<p style="margin:4px 0;color:#666;font-size:13px;"><?php echo esc_html((string) $prompt['description']); ?></p>
								<?php endif; ?>
								<textarea readonly rows="2" style="width:100%;font-family:monospace;font-size:12px;" onclick="this.select()"><?php echo esc_textarea((string) ($prompt['prompt'] ?? '')); ?></textarea>
							</div>
						<?php endforeach; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<?php
		}
	}

