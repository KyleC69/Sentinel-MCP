<?php

/**
 * File Manager for MCP Content Manager.
 *
 * Provides secure file read/write/backup operations restricted
 * to the wp-content/ directory. Used by recovery abilities and
 * shared with the sentinel mu-plugin (which embeds its own copy).
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://plugins.joseconti.com/product/sentinel-mcp/
 */

defined('ABSPATH') || exit;

if (! class_exists('SENTINEL_File_Manager')) {

	/**
	 * Secure file operations manager for wp-content directory.
	 */
	class SENTINEL_File_Manager
	{

		const MAX_BACKUPS_PER_FILE = 10;
		const MAX_FILE_SIZE        = 5 * 1024 * 1024; // 5 MB.
		const BACKUP_DIR_NAME      = 'sentinel-backups';

		/**
		 * Whitelisted files in the WordPress root (outside wp-content/) that can be read/written.
		 */
		const ALLOWED_ABSPATH_FILES = array(
			'.htaccess',
			'.maintenance',
		);

		/**
		 * Allowed file extensions for read/write operations.
		 */
		const ALLOWED_EXTENSIONS = array(
			'php',
			'css',
			'js',
			'html',
			'htm',
			'txt',
			'json',
			'xml',
			'ini',
			'htaccess',
			'yaml',
			'yml',
			'md',
			'log',
			'pot',
			'po',
			'mo',
		);

		/**
		 * Get the backup directory path inside the uploads directory.
		 *
		 * Uses wp_upload_dir() to determine the correct uploads basedir,
		 * which works correctly regardless of custom WP_CONTENT_DIR or
		 * non-standard WordPress directory structures.
		 *
		 * @return string Absolute path to the backup directory.
		 */
		public static function get_backup_dir(): string
		{
			$upload_dir = wp_upload_dir();
			return $upload_dir['basedir'] . '/' . self::BACKUP_DIR_NAME;
		}

		/**
		 * Initialise and return the WP_Filesystem instance.
		 *
		 * @return WP_Filesystem_Base|false The filesystem object or false on failure.
		 */
		private static function get_wp_filesystem()
		{
			global $wp_filesystem;

			if ($wp_filesystem instanceof WP_Filesystem_Base) {
				return $wp_filesystem;
			}

			if (! function_exists('WP_Filesystem')) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			WP_Filesystem();

			return ($wp_filesystem instanceof WP_Filesystem_Base) ? $wp_filesystem : false;
		}

		/**
		 * Get a human-readable display path.
		 *
		 * Returns a relative path for wp-content files, or the basename
		 * for ABSPATH files (.htaccess, .maintenance).
		 *
		 * @param string $absolute_path Absolute file path.
		 * @return string Display-friendly path.
		 */
		public static function get_display_path(string $absolute_path): string
		{
			$norm_path   = wp_normalize_path($absolute_path);
			$content_dir = wp_normalize_path(realpath(WP_CONTENT_DIR));

			if (str_starts_with($norm_path, $content_dir . '/')) {
				return str_replace($content_dir . '/', '', $norm_path);
			}

			return basename($norm_path);
		}

		/**
		 * Ensure the backup directory exists with proper protection.
		 */
		public static function ensure_backup_dir(): void
		{
			$dir = self::get_backup_dir();

			if (! is_dir($dir)) {
				wp_mkdir_p($dir);
			}

			$filesystem = self::get_wp_filesystem();
			if (! $filesystem) {
				return;
			}

			// Protect with .htaccess.
			$htaccess = $dir . '/.htaccess';
			if (! file_exists($htaccess)) {
				$filesystem->put_contents($htaccess, "Order deny,allow\nDeny from all\n", FS_CHMOD_FILE);
			}

			// Add empty index.php.
			$index = $dir . '/index.php';
			if (! file_exists($index)) {
				$filesystem->put_contents($index, "<?php\n// Silence is golden.\n", FS_CHMOD_FILE);
			}
		}

		/**
		 * Validate and normalize a file path.
		 *
		 * Ensures the path is within WP_CONTENT_DIR (or is a whitelisted ABSPATH
		 * file like .htaccess or .maintenance), has an allowed
		 * extension, and does not contain directory traversal attempts.
		 *
		 * @param string $path      The path to validate (relative to wp-content/, or bare filename for ABSPATH files).
		 * @param bool   $must_exist Whether the file must already exist.
		 * @return string|WP_Error Normalized absolute path on success, WP_Error on failure.
		 */
		public static function validate_path(string $path, bool $must_exist = true)
		{
			// Block obvious traversal attempts before any processing.
			if (str_contains($path, '..')) {
				return new WP_Error('path_traversal', 'Directory traversal is not allowed.');
			}

			// Block null bytes.
			if (str_contains($path, "\0")) {
				return new WP_Error('invalid_path', 'Null bytes are not allowed in paths.');
			}

			// Block stream wrappers (phar://, php://, etc.).
			if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $path)) {
				return new WP_Error('invalid_path', 'Stream wrappers are not allowed.');
			}

			// Normalize the path.
			$path = wp_normalize_path($path);

			// Check if this is a whitelisted ABSPATH file (bare filename, no directory).
			$is_abspath_file = false;
			$clean_name      = basename($path);

			if (
				in_array($clean_name, self::ALLOWED_ABSPATH_FILES, true)
				&& false === strpos(ltrim($path, '/'), '/')
			) {

				$is_abspath_file = true;
				$abspath_norm    = wp_normalize_path(ABSPATH);
				$path            = $abspath_norm . $clean_name;
			}

			// If it's a relative path (not ABSPATH file), make it absolute relative to WP_CONTENT_DIR.
			$content_dir = wp_normalize_path(WP_CONTENT_DIR);
			if (! $is_abspath_file && ! str_starts_with($path, '/')) {
				$path = $content_dir . '/' . $path;
			}

			// Use WordPress validate_file() for extra safety (skip for ABSPATH files — already whitelisted).
			if (! $is_abspath_file) {
				$relative = str_replace($content_dir . '/', '', $path);
				if (0 !== validate_file($relative)) {
					return new WP_Error('invalid_path', 'Path validation failed.');
				}
			}

			if ($must_exist) {
				// Resolve to real path to prevent symlink attacks.
				$real_path = realpath($path);
				if (false === $real_path) {
					$display = $is_abspath_file ? $clean_name : str_replace($content_dir . '/', '', $path);
					return new WP_Error('file_not_found', 'File does not exist: ' . $display);
				}

				if ($is_abspath_file) {
					// Verify resolved path is within the WordPress root directory.
					$real_abspath = wp_normalize_path(realpath(ABSPATH));
					$norm_real    = wp_normalize_path($real_path);

					if (! str_starts_with($norm_real, $real_abspath)) {
						return new WP_Error('path_outside_allowed', 'Path resolves outside allowed directories.');
					}
				} else {
					$real_content_dir = realpath(WP_CONTENT_DIR);

					// Verify the resolved path is within wp-content.
					if (! str_starts_with(wp_normalize_path($real_path), wp_normalize_path($real_content_dir))) {
						return new WP_Error('path_outside_content', 'Path resolves outside of wp-content/.');
					}
				}

				$path = wp_normalize_path($real_path);
			} else {
				// For new files, verify the parent directory exists and is in the allowed area.
				$parent      = dirname($path);
				$real_parent = realpath($parent);

				if (false === $real_parent) {
					return new WP_Error('parent_not_found', 'Parent directory does not exist.');
				}

				if ($is_abspath_file) {
					$real_abspath = wp_normalize_path(realpath(ABSPATH));
					$norm_parent  = wp_normalize_path($real_parent);

					if ($real_abspath !== $norm_parent && ! str_starts_with($norm_parent, $real_abspath)) {
						return new WP_Error('path_outside_allowed', 'Path resolves outside allowed directories.');
					}
				} else {
					$real_content_dir = realpath(WP_CONTENT_DIR);

					if (! str_starts_with(wp_normalize_path($real_parent), wp_normalize_path($real_content_dir))) {
						return new WP_Error('path_outside_content', 'Path resolves outside of wp-content/.');
					}
				}

				$path = wp_normalize_path($real_parent) . '/' . basename($path);
			}

			// Verify the path is not in the backups directory (skip for ABSPATH files).
			if (! $is_abspath_file) {
				$backup_dir = wp_normalize_path(self::get_backup_dir());
				if (str_starts_with($path, $backup_dir)) {
					return new WP_Error('backup_dir_protected', 'Cannot operate on files inside the backups directory.');
				}
			}

			// Check file extension (skip for whitelisted ABSPATH files).
			if (! $is_abspath_file) {
				$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
				if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
					return new WP_Error(
						'extension_not_allowed',
						sprintf(
							'File extension "%s" is not allowed. Allowed: %s',
							$extension,
							implode(', ', self::ALLOWED_EXTENSIONS)
						)
					);
				}
			}

			return $path;
		}

		/**
		 * Read a file.
		 *
		 * @param string $path Path relative to wp-content/ or absolute.
		 * @return array Result with success, content, size, modified, permissions.
		 */
		public static function read_file(string $path): array
		{
			$validated = self::validate_path($path, true);

			if (is_wp_error($validated)) {
				return array(
					'success' => false,
					'message' => $validated->get_error_message(),
				);
			}

			$size = filesize($validated);
			if ($size > self::MAX_FILE_SIZE) {
				return array(
					'success' => false,
					'message' => sprintf(
						'File too large (%s). Maximum allowed: %s.',
						size_format($size),
						size_format(self::MAX_FILE_SIZE)
					),
				);
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content = file_get_contents($validated);

			if (false === $content) {
				return array(
					'success' => false,
					'message' => 'Could not read file.',
				);
			}

			return array(
				'success'     => true,
				'path'        => self::get_display_path($validated),
				'content'     => $content,
				'size'        => $size,
				'modified'    => gmdate('Y-m-d H:i:s', filemtime($validated)),
				'permissions' => substr(sprintf('%o', fileperms($validated)), -4),
			);
		}

		/**
		 * Write a file with automatic backup.
		 *
		 * @param string $path    Path relative to wp-content/ or absolute.
		 * @param string $content The content to write.
		 * @return array Result with success, backup_id, message.
		 */
		public static function write_file(string $path, string $content): array
		{
			$file_exists = true;
			$validated   = self::validate_path($path, true);

			if (is_wp_error($validated)) {
				// If file doesn't exist, try creating it.
				if ('file_not_found' === $validated->get_error_code()) {
					$validated   = self::validate_path($path, false);
					$file_exists = false;

					if (is_wp_error($validated)) {
						return array(
							'success' => false,
							'message' => $validated->get_error_message(),
						);
					}
				} else {
					return array(
						'success' => false,
						'message' => $validated->get_error_message(),
					);
				}
			}

			// Create backup of existing file before writing.
			$backup_id = null;
			if ($file_exists) {
				$backup_id = self::create_backup($validated);
				if (false === $backup_id) {
					return array(
						'success' => false,
						'message' => 'Could not create backup before writing.',
					);
				}
			}

			// Write the file using WP_Filesystem.
			$filesystem = self::get_wp_filesystem();
			if (! $filesystem) {
				return array(
					'success' => false,
					'message' => 'Could not initialise WordPress filesystem.',
				);
			}

			$written = $filesystem->put_contents($validated, $content, FS_CHMOD_FILE);

			if (false === $written) {
				return array(
					'success' => false,
					'message' => 'Could not write file. Check permissions.',
				);
			}

			$display    = self::get_display_path($validated);
			$final_size = filesize($validated);

			return array(
				'success'   => true,
				'path'      => $display,
				'backup_id' => $backup_id,
				'size'      => $final_size,
				'message'   => sprintf(
					'File "%s" written successfully (%s).%s',
					$display,
					size_format($final_size),
					$backup_id ? ' Backup created: ' . $backup_id : ' (new file, no backup needed)'
				),
			);
		}

		/**
		 * Create a backup of a file.
		 *
		 * @param string $path Absolute path to the file to backup.
		 * @return string|false Backup ID on success, false on failure.
		 */
		public static function create_backup(string $path)
		{
			self::ensure_backup_dir();

			if (! is_file($path) || ! is_readable($path)) {
				return false;
			}

			$backup_dir  = self::get_backup_dir();
			$path_hash   = hash('sha256', wp_normalize_path($path));
			$timestamp   = gmdate('Ymd_His');
			$backup_id   = $path_hash . '_' . $timestamp;
			$backup_file = $backup_dir . '/' . $backup_id . '.bak';

			// Store metadata alongside the backup.
			$meta = array(
				'original_path' => wp_normalize_path($path),
				'relative_path' => self::get_display_path($path),
				'created_at'    => gmdate('Y-m-d H:i:s'),
				'original_size' => filesize($path),
				'backup_id'     => $backup_id,
			);

			$filesystem = self::get_wp_filesystem();
			if (! $filesystem) {
				return false;
			}

			if (! $filesystem->copy($path, $backup_file)) {
				return false;
			}

			$meta_file = $backup_dir . '/' . $backup_id . '.json';
			$filesystem->put_contents($meta_file, wp_json_encode($meta, JSON_PRETTY_PRINT), FS_CHMOD_FILE);

			// Enforce max backups per file.
			self::cleanup_old_backups($path_hash);

			return $backup_id;
		}

		/**
		 * Clean up old backups for a file, keeping only MAX_BACKUPS_PER_FILE.
		 *
		 * @param string $path_hash SHA256 hash of the file path.
		 */
		private static function cleanup_old_backups(string $path_hash): void
		{
			$backup_dir = self::get_backup_dir();
			$pattern    = $backup_dir . '/' . $path_hash . '_*.bak';
			$backups    = glob($pattern);

			if (! $backups || count($backups) <= self::MAX_BACKUPS_PER_FILE) {
				return;
			}

			// Sort by modification time (oldest first).
			usort(
				$backups,
				function ($a, $b) {
					return filemtime($a) - filemtime($b);
				}
			);

			// Remove oldest backups.
			$to_remove = count($backups) - self::MAX_BACKUPS_PER_FILE;
			for ($i = 0; $i < $to_remove; $i++) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink($backups[$i]);

				// Remove associated meta file.
				$meta_file = str_replace('.bak', '.json', $backups[$i]);
				if (file_exists($meta_file)) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					unlink($meta_file);
				}
			}
		}

		/**
		 * List available backups.
		 *
		 * @param string $path Optional. Filter by original file path (relative to wp-content/).
		 * @return array List of backup entries.
		 */
		public static function list_backups(string $path = ''): array
		{
			$backup_dir = self::get_backup_dir();

			if (! is_dir($backup_dir)) {
				return array();
			}

			$meta_files = glob($backup_dir . '/*.json');
			if (! $meta_files) {
				return array();
			}

			$backups = array();
			foreach ($meta_files as $meta_file) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$meta = json_decode(file_get_contents($meta_file), true);
				if (! $meta) {
					continue;
				}

				// Filter by path if provided.
				if ($path) {
					$normalized_filter = wp_normalize_path($path);
					$relative          = $meta['relative_path'] ?? '';
					if (false === strpos($relative, $normalized_filter)) {
						continue;
					}
				}

				$backup_file = str_replace('.json', '.bak', $meta_file);
				if (! file_exists($backup_file)) {
					continue;
				}

				$backups[] = array(
					'backup_id'     => $meta['backup_id'],
					'original_path' => $meta['relative_path'] ?? $meta['original_path'],
					'date'          => $meta['created_at'],
					'size'          => filesize($backup_file),
				);
			}

			// Sort by date, newest first.
			usort(
				$backups,
				function ($a, $b) {
					return strcmp($b['date'], $a['date']);
				}
			);

			return $backups;
		}

		/**
		 * Restore a file from a backup.
		 *
		 * @param string $backup_id The backup ID to restore.
		 * @return array Result with success, restored_path, message.
		 */
		public static function restore_backup(string $backup_id): array
		{
			// Sanitize backup ID to prevent path traversal.
			$backup_id = preg_replace('/[^a-f0-9_]/', '', $backup_id);

			$backup_dir  = self::get_backup_dir();
			$backup_file = $backup_dir . '/' . $backup_id . '.bak';
			$meta_file   = $backup_dir . '/' . $backup_id . '.json';

			if (! file_exists($backup_file) || ! file_exists($meta_file)) {
				return array(
					'success' => false,
					'message' => 'Backup not found: ' . $backup_id,
				);
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$meta = json_decode(file_get_contents($meta_file), true);
			if (! $meta || empty($meta['original_path'])) {
				return array(
					'success' => false,
					'message' => 'Backup metadata is corrupted.',
				);
			}

			$target_path = $meta['original_path'];

			// Validate the target path is still within wp-content or is an allowed root file.
			$real_content_dir  = wp_normalize_path(realpath(WP_CONTENT_DIR));
			$real_abspath      = wp_normalize_path(realpath(ABSPATH));
			$normalized_target = wp_normalize_path($target_path);

			$is_in_content = str_starts_with($normalized_target, $real_content_dir);
			$is_abspath_ok = in_array(basename($normalized_target), self::ALLOWED_ABSPATH_FILES, true)
				&& str_starts_with($normalized_target, $real_abspath);

			if (! $is_in_content && ! $is_abspath_ok) {
				return array(
					'success' => false,
					'message' => 'Backup target path is outside allowed directories.',
				);
			}

			// Create a backup of the current file before restoring (safety net).
			if (file_exists($target_path)) {
				self::create_backup($target_path);
			}

			$filesystem = self::get_wp_filesystem();
			if (! $filesystem) {
				return array(
					'success' => false,
					'message' => 'Could not initialise WordPress filesystem.',
				);
			}

			if (! $filesystem->copy($backup_file, $target_path)) {
				return array(
					'success' => false,
					'message' => 'Could not restore file. Check permissions.',
				);
			}

			$relative = $meta['relative_path'] ?? str_replace($real_content_dir . '/', '', $normalized_target);

			return array(
				'success'       => true,
				'restored_path' => $relative,
				'message'       => sprintf('File "%s" restored from backup %s.', $relative, $backup_id),
			);
		}

		/**
		 * Get site health information.
		 *
		 * @return array Health data.
		 */
		public static function get_site_health(): array
		{
			global $wp_version;

			$health = array(
				'wp_version'           => $wp_version,
				'php_version'          => PHP_VERSION,
				'memory_limit'         => ini_get('memory_limit'),
				'memory_usage'         => size_format(memory_get_usage(true)),
				'max_execution'        => ini_get('max_execution_time'),
				'active_plugins_count' => count((array) get_option('active_plugins', array())),
				'is_recovery_mode'     => function_exists('wp_is_recovery_mode') && wp_is_recovery_mode(),
			);

			// Paused plugins (if in recovery mode).
			if (function_exists('wp_paused_plugins')) {
				$paused = wp_paused_plugins()->get_all();
				if (! empty($paused)) {
					$health['paused_plugins'] = array_keys($paused);
				}
			}

			// Disk space (measured from the uploads directory).
			$uploads_dir = wp_upload_dir();
			$disk_free   = disk_free_space($uploads_dir['basedir']);
			if (false !== $disk_free) {
				$health['disk_free'] = size_format($disk_free);
			}

			return $health;
		}

		/**
		 * Read the PHP error log.
		 *
		 * @param int $lines Number of lines to read from the end.
		 * @return array Result with log entries.
		 */
		public static function get_error_log(int $lines = 50): array
		{
			$log_path = ini_get('error_log');

			// Also check common WP locations.
			$content_dir    = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
			$possible_paths = array_filter(
				array(
					$log_path,
					$content_dir . '/debug.log',
				)
			);

			$found_path = '';
			foreach ($possible_paths as $p) {
				if ($p && is_file($p) && is_readable($p)) {
					$found_path = $p;
					break;
				}
			}

			if (! $found_path) {
				return array(
					'success'  => false,
					'log_path' => $log_path ? $log_path : '(not configured)',
					'message'  => 'Error log file not found or not readable.',
					'entries'  => array(),
				);
			}

			// Read last N lines efficiently.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$handle = fopen($found_path, 'r');
			if (! $handle) {
				return array(
					'success'  => false,
					'log_path' => $found_path,
					'message'  => 'Could not open error log.',
					'entries'  => array(),
				);
			}

			// Seek from end to read last lines.
			$result    = array();
			$chunk     = 8192;
			$file_size = filesize($found_path);

			if (0 === $file_size) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				fclose($handle);
				return array(
					'success'  => true,
					'log_path' => $found_path,
					'entries'  => array(),
					'message'  => 'Error log is empty.',
				);
			}

			// Read from end of file.
			$pos        = $file_size;
			$buffer     = '';
			$line_count = 0;

			while ($pos > 0 && $line_count < $lines) {
				$read_size = min($chunk, $pos);
				$pos      -= $read_size;
				fseek($handle, $pos);
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				$buffer     = fread($handle, $read_size) . $buffer;
				$line_count = substr_count($buffer, "\n");
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose($handle);

			$all_lines  = explode("\n", trim($buffer));
			$last_lines = array_slice($all_lines, -$lines);

			// Parse log entries.
			$entries = array();
			foreach ($last_lines as $line) {
				$line = trim($line);
				if (empty($line)) {
					continue;
				}

				$entry = array('raw' => $line);

				// Try to parse standard PHP error log format: [date] type: message in file on line N.
				if (preg_match('/^\[([^\]]+)\]\s+(PHP\s+\w+[^:]*?):\s+(.+?)(?:\s+in\s+(.+?)\s+on\s+line\s+(\d+))?$/', $line, $matches)) {
					$entry['date']    = $matches[1];
					$entry['type']    = trim($matches[2]);
					$entry['message'] = $matches[3];
					if (! empty($matches[4])) {
						$entry['file'] = $matches[4];
						$entry['line'] = (int) $matches[5];
					}
				}

				$entries[] = $entry;
			}

			return array(
				'success'  => true,
				'log_path' => $found_path,
				'entries'  => $entries,
			);
		}

		/**
		 * List all plugins with their status.
		 *
		 * @return array List of plugins.
		 */
		public static function list_plugins(): array
		{
			if (! function_exists('get_plugins')) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$all_plugins    = get_plugins();
			$active_plugins = (array) get_option('active_plugins', array());

			$paused_plugins = array();
			if (function_exists('wp_paused_plugins')) {
				$paused_plugins = wp_paused_plugins()->get_all();
			}

			$result = array();
			foreach ($all_plugins as $plugin_file => $plugin_data) {
				$slug = dirname($plugin_file);
				if ('.' === $slug) {
					$slug = basename($plugin_file, '.php');
				}

				$result[] = array(
					'name'    => $plugin_data['Name'],
					'slug'    => $slug,
					'version' => $plugin_data['Version'],
					'active'  => in_array($plugin_file, $active_plugins, true),
					'paused'  => array_key_exists($slug, $paused_plugins),
					'file'    => $plugin_file,
				);
			}

			return $result;
		}

		/**
		 * Toggle a plugin's active state.
		 *
		 * @param string $plugin Plugin file path (relative to plugins directory).
		 * @param string $action 'activate' or 'deactivate'.
		 * @return array Result.
		 */
		public static function toggle_plugin(string $plugin, string $action): array
		{
			if (! function_exists('get_plugins')) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugin = sanitize_text_field($plugin);

			// Verify plugin exists.
			$all_plugins = get_plugins();
			if (! isset($all_plugins[$plugin])) {
				return array(
					'success' => false,
					'message' => sprintf('Plugin not found: %s', $plugin),
				);
			}

			$plugin_name = $all_plugins[$plugin]['Name'];

			if ('activate' === $action) {
				$result = activate_plugin($plugin);
				if (is_wp_error($result)) {
					return array(
						'success' => false,
						'plugin'  => $plugin,
						'message' => $result->get_error_message(),
					);
				}
				return array(
					'success'    => true,
					'plugin'     => $plugin,
					'new_status' => 'active',
					'message'    => sprintf('Plugin "%s" activated.', $plugin_name),
				);
			}

			if ('deactivate' === $action) {
				deactivate_plugins($plugin);
				return array(
					'success'    => true,
					'plugin'     => $plugin,
					'new_status' => 'inactive',
					'message'    => sprintf('Plugin "%s" deactivated.', $plugin_name),
				);
			}

			return array(
				'success' => false,
				'message' => 'Invalid action. Use "activate" or "deactivate".',
			);
		}

		/**
		 * Delete the .maintenance file to exit WordPress maintenance mode.
		 *
		 * WordPress creates ABSPATH/.maintenance during core/plugin/theme updates.
		 * Sometimes this file is not removed after the update, leaving the site
		 * showing "Briefly unavailable for scheduled maintenance."
		 *
		 * @return array Result with success and message.
		 */
		public static function delete_maintenance(): array
		{
			$file = ABSPATH . '.maintenance';

			if (! file_exists($file)) {
				return array(
					'success' => true,
					'message' => 'No .maintenance file found. The site is not in maintenance mode.',
				);
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			if (! unlink($file)) {
				return array(
					'success' => false,
					'message' => 'Could not delete .maintenance file. Check file permissions.',
				);
			}

			return array(
				'success' => true,
				'message' => 'Maintenance mode cleared. The .maintenance file has been deleted.',
			);
		}
	}
}
