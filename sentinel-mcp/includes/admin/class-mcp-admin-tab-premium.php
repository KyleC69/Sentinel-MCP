<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Premium admin tab.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

if (! class_exists('SentinelMCP\SENTINEL_Admin_Tab_Premium')) {

	/**
	 * Renders the Go Premium tab: feature list and CTA.
	 */
	class SENTINEL_Admin_Tab_Premium extends SENTINEL_Admin_Tab
	{

		/**
		 * Constructor.
		 */
		public function __construct()
		{
			parent::__construct('premium', __('Go Premium', 'mcp-sentinel'));
		}

		/**
		 * Render the tab content.
		 *
		 * @return void
		 */
		public function render(): void
		{
			$premium_url = SENTINEL_PREMIUM_PRODUCT_URL;
			$catalog     = mcpcomal_load_premium_features_catalog();

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
			$keyword = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
			$cat_filter = isset($_GET['cat']) ? sanitize_key((string) $_GET['cat']) : '';

			$filtered     = mcpcomal_filter_premium_features(
				$catalog,
				'' === $cat_filter ? null : $cat_filter,
				'' === $keyword ? null : $keyword
			);
			$total_filter = mcpcomal_count_features($filtered);
			$total_all    = mcpcomal_count_features($catalog);
			$cat_count    = isset($catalog['categories']) && is_array($catalog['categories']) ? count($catalog['categories']) : 0;
			?>
			<div class="card" style="max-width:900px;margin-bottom:20px;padding:20px 24px;">
				<h2 style="margin-top:0;font-size:1.4em;">
					<?php esc_html_e('Unlock the Full Power of MCP Content Manager', 'mcp-sentinel'); ?>
				</h2>
				<p style="font-size:14px;color:#50575e;">
					<?php
					printf(
						/* translators: 1: total feature count, 2: category count */
						esc_html__('Premium ships %1$s+ abilities across %2$s categories. Manage your entire WordPress site — content, store, security and more — from Claude, ChatGPT, Copilot or any MCP client.', 'mcp-sentinel'),
						esc_html((string) $total_all),
						esc_html((string) $cat_count)
					);
					?>
				</p>

				<p style="text-align:center;margin:16px 0 8px;">
					<a href="<?php echo esc_url($premium_url); ?>" target="_blank" rel="noopener noreferrer"
						class="button button-primary button-hero" style="font-size:16px;padding:8px 32px;">
						<?php esc_html_e('Get Sentinel-MCP Pro', 'mcp-sentinel'); ?> &rarr;
					</a>
				</p>

				<form method="get" style="margin:10px 0 20px;">
					<input type="hidden" name="page" value="sentinel-settings">
					<input type="hidden" name="tab" value="premium">
					<select name="cat">
						<option value=""><?php esc_html_e('All categories', 'mcp-sentinel'); ?></option>
						<?php foreach ((array) ($catalog['categories'] ?? []) as $cat) : ?>
							<option value="<?php echo esc_attr((string) ($cat['slug'] ?? '')); ?>" <?php selected($cat_filter, (string) ($cat['slug'] ?? '')); ?>>
								<?php echo esc_html((string) ($cat['label'] ?? $cat['slug'] ?? '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<input type="text" name="q" value="<?php echo esc_attr($keyword); ?>" placeholder="<?php esc_attr_e('Search features…', 'mcp-sentinel'); ?>" style="width:240px;">
					<button type="submit" class="button"><?php esc_html_e('Filter', 'mcp-sentinel'); ?></button>
					<?php if ($cat_filter || $keyword) : ?>
						<a class="button" href="<?php echo esc_url(admin_url('options-general.php?page=sentinel-settings&tab=premium')); ?>">
							<?php esc_html_e('Clear', 'mcp-sentinel'); ?>
						</a>
					<?php endif; ?>
				</form>

				<?php if (($cat_filter || $keyword) && 0 === $total_filter) : ?>
					<p><?php esc_html_e('No features match the current filters.', 'mcp-sentinel'); ?></p>
				<?php endif; ?>

				<?php foreach ((array) ($filtered['categories'] ?? []) as $cat) : ?>
					<details open style="border:1px solid #dcdcde;border-radius:4px;margin-bottom:10px;padding:10px;">
						<summary style="font-weight:bold;font-size:14px;cursor:pointer;">
							<?php echo esc_html((string) ($cat['label'] ?? '')); ?>
							<span style="color:#50575e;font-weight:normal;">
								(<?php echo esc_html((string) count((array) ($cat['features'] ?? []))); ?>)
							</span>
						</summary>
						<?php if (! empty($cat['summary'])) : ?>
							<p style="color:#50575e;font-size:13px;margin:6px 0;"><?php echo esc_html((string) $cat['summary']); ?></p>
						<?php endif; ?>
						<table class="widefat" style="margin-top:10px;">
							<tbody>
								<?php foreach ((array) ($cat['features'] ?? []) as $feat) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html((string) ($feat['label'] ?? '')); ?></strong>
											<?php if (! empty($feat['description'])) : ?>
												<br><span style="color:#50575e;font-size:13px;"><?php echo esc_html((string) $feat['description']); ?></span>
											<?php endif; ?>
											<?php if (! empty($feat['example_prompt'])) : ?>
												<details style="margin-top:6px;">
													<summary style="cursor:pointer;color:#2271b1;"><?php esc_html_e('Example prompt', 'mcp-sentinel'); ?></summary>
													<code style="display:block;background:#f6f7f7;padding:6px;margin-top:4px;font-size:12px;"><?php echo esc_html((string) $feat['example_prompt']); ?></code>
												</details>
											<?php endif; ?>
										</td>
										<td style="width:120px;text-align:right;">
											<?php
											$learn_more = ! empty($feat['learn_more_url']) ? (string) $feat['learn_more_url'] : $premium_url;
											?>
											<a class="button" href="<?php echo esc_url($learn_more); ?>" target="_blank" rel="noopener noreferrer">
												<?php esc_html_e('Learn more', 'mcp-sentinel'); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</details>
				<?php endforeach; ?>

				<p style="text-align:center;margin:20px 0 8px;">
					<a href="<?php echo esc_url($premium_url); ?>" target="_blank" rel="noopener noreferrer"
						class="button button-primary button-hero" style="font-size:16px;padding:8px 32px;">
						<?php esc_html_e('Get Sentinel-MCP Pro', 'mcp-sentinel'); ?> &rarr;
					</a>
				</p>
				<p style="text-align:center;color:#50575e;font-size:13px;">
					<?php esc_html_e('Your Lite settings and data will be preserved automatically when you upgrade.', 'mcp-sentinel'); ?>
				</p>
			</div>
			<?php
		}
	}
}
