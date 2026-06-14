<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Info admin tab.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

if (! class_exists('SentinelMCP\SENTINEL_Admin_Tab_Info')) {

	/**
	 * Renders the Info tab: site structure and registered abilities.
	 */
	class SENTINEL_Admin_Tab_Info extends SENTINEL_Admin_Tab
	{

		/**
		 * Constructor.
		 */
		public function __construct()
		{
			parent::__construct('info', __('Info', 'mcp-sentinel'));
		}

		/**
		 * Render the tab content.
		 *
		 * @return void
		 */
		public function render(): void
		{
			$post_types = SENTINEL_Schema_Inspector::get_site_schema_summary();
			?>
			<!-- Site structure -->
			<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Site structure (<?php echo esc_html($post_types['total_cpts']); ?> content types)</h2>
				<p>This is what your AI assistant (Claude, ChatGPT, Copilot, etc.) will be able to view and manage automatically:</p>
				<table class="widefat striped" style="max-width:100%;">
					<thead>
						<tr>
							<th>Type</th>
							<th>Slug</th>
							<th>Taxonomies</th>
							<th>Meta fields</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($post_types['post_types'] as $pt) : ?>
							<tr>
								<td><strong><?php echo esc_html($pt['label']); ?></strong></td>
								<td><code><?php echo esc_html($pt['name']); ?></code></td>
								<td>
									<?php
									$tax_list = implode(', ', $pt['taxonomies']);
									echo esc_html($tax_list ? $tax_list : '—');
									?>
								</td>
								<td><?php echo (int) $pt['meta_field_count']; ?> fields</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Registered abilities (dynamic) -->
			<?php if (function_exists('wp_get_abilities') && function_exists('wp_get_ability_categories')) : ?>
				<?php
				$all_abilities = array_filter(
					wp_get_abilities(),
					function ($ability) {
						return 0 === strpos($ability->get_name(), 'sentinel/');
					}
				);

				$all_categories = wp_get_ability_categories();

				$grouped    = [];
				$no_cat     = [];
				$cat_labels = [];

				foreach ($all_categories as $cat) {
					$cat_labels[$cat->get_slug()] = $cat->get_label();
					$grouped[$cat->get_slug()]    = [];
				}

				foreach ($all_abilities as $ability) {
					$cat_slug = $ability->get_category();
					if ($cat_slug && isset($grouped[$cat_slug])) {
						$grouped[$cat_slug][] = $ability;
					} else {
						$no_cat[] = $ability;
					}
				}

				$grouped = array_filter($grouped);
				$total_count = count($all_abilities);
				?>
				<div class="card" style="max-width:700px;padding:15px;">
					<h2 style="margin-top:0;">Registered MCP abilities (<?php echo (int) $total_count; ?>)</h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th>Ability</th>
								<th>Description</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($grouped as $cat_slug => $abilities) : ?>
								<tr>
									<td colspan="2" style="background:#f0f0f1;font-weight:bold;">
										<?php echo esc_html($cat_labels[$cat_slug] ?? $cat_slug); ?>
									</td>
								</tr>
								<?php foreach ($abilities as $ability) : ?>
									<tr>
										<td><code><?php echo esc_html($ability->get_name()); ?></code></td>
										<td><?php echo esc_html($ability->get_description()); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endforeach; ?>
							<?php if (! empty($no_cat)) : ?>
								<tr>
									<td colspan="2" style="background:#f0f0f1;font-weight:bold;">Other</td>
								</tr>
								<?php foreach ($no_cat as $ability) : ?>
									<tr>
										<td><code><?php echo esc_html($ability->get_name()); ?></code></td>
										<td><?php echo esc_html($ability->get_description()); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<div class="card" style="max-width:700px;padding:15px;">
					<h2 style="margin-top:0;">Registered MCP abilities</h2>
					<p>The WordPress Abilities API is not available. Install the MCP Adapter to see registered abilities.</p>
				</div>
			<?php endif; ?>
			<?php
		}
	}
}
