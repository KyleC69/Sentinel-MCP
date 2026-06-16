<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * Activity Log admin tab.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined('ABSPATH') || exit;

	/**
	 * Renders the Activity Log tab.
	 */
	class Admin_Tab_Activity extends Admin_Tab
	{

		/**
		 * Constructor.
		 */
		public function __construct()
		{
			parent::__construct('activity', __('Activity Log', 'mcp-sentinel'));
		}

		/**
		 * Render the tab content.
		 *
		 * @return void
		 */
		public function render(): void
		{
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters from URL.
			$page      = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
			$client_id = isset($_GET['filter_client']) ? sanitize_text_field(wp_unslash((string) $_GET['filter_client'])) : '';
			$status    = isset($_GET['filter_status']) ? sanitize_key((string) $_GET['filter_status']) : '';
			$ability   = isset($_GET['filter_ability']) ? sanitize_text_field(wp_unslash((string) $_GET['filter_ability'])) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$result = Activity_Log::query(
				[
					'page'      => $page,
					'per_page'  => 50,
					'client_id' => $client_id,
					'status'    => $status,
					'ability'   => $ability,
				]
			);

			$total       = (int) $result['total'];
			$total_pages = max(1, (int) ceil($total / 50));
			$base_url    = $this->tab_url('activity');
			?>
			<div class="card" style="max-width:900px;margin-bottom:20px;padding:15px;">
				<h2 style="margin-top:0;">Activity Log</h2>
				<p>Last 30 days of MCP tool calls. Retention is fixed at 30 days; older entries are purged daily.</p>

				<form method="get" style="margin:10px 0;">
					<input type="hidden" name="page" value="sentinel-settings">
					<input type="hidden" name="tab" value="activity">
					<input type="text" name="filter_client" value="<?php echo esc_attr($client_id); ?>" placeholder="Client ID" style="width:240px;">
					<input type="text" name="filter_ability" value="<?php echo esc_attr($ability); ?>" placeholder="Ability slug" style="width:240px;">
					<select name="filter_status">
						<option value="">All statuses</option>
						<?php foreach (['ok', 'error', 'denied', 'rate_limited'] as $st) : ?>
							<option value="<?php echo esc_attr($st); ?>" <?php selected($status, $st); ?>><?php echo esc_html($st); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button">Filter</button>
					<?php if ($client_id || $status || $ability) : ?>
						<a class="button" href="<?php echo esc_url($base_url); ?>">Clear</a>
					<?php endif; ?>
				</form>

				<table class="widefat striped">
					<thead>
						<tr>
							<th>Time (UTC)</th>
							<th>Client</th>
							<th>User</th>
							<th>Ability</th>
							<th>Status</th>
							<th>Duration</th>
							<th>Error</th>
							<th>IP</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($result['items'])) : ?>
							<tr>
								<td colspan="8" style="text-align:center;padding:20px;">No entries.</td>
							</tr>
						<?php else : ?>
							<?php foreach ($result['items'] as $row) : ?>
								<tr>
									<td><?php echo esc_html((string) $row['ts']); ?></td>
									<td><code><?php echo esc_html((string) ($row['oauth_client_id'] ?? '')); ?></code></td>
									<td><?php echo $row['user_id'] ? esc_html('#' . (int) $row['user_id']) : '&mdash;'; ?></td>
									<td><code><?php echo esc_html((string) $row['ability_slug']); ?></code></td>
									<td><?php echo esc_html((string) $row['status']); ?></td>
									<td><?php echo null !== $row['duration_ms'] ? esc_html((int) $row['duration_ms'] . ' ms') : '&mdash;'; ?></td>
									<td><?php echo $row['error_code'] ? esc_html((string) $row['error_code']) : '&mdash;'; ?></td>
									<td><?php echo $row['ip'] ? esc_html((string) $row['ip']) : '&mdash;'; ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<?php if ($total_pages > 1) : ?>
					<div style="margin-top:10px;">
						<?php
						$paginate = paginate_links(
							[
								'base'      => add_query_arg('paged', '%#%', $base_url),
								'format'    => '',
								'current'   => $page,
								'total'     => $total_pages,
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							]
						);
						echo wp_kses_post((string) $paginate);
						?>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}
	}

