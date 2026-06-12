<?php

/**
 * Chat AI Database layer.
 *
 * Creates tables and provides CRUD operations for
 * conversations and messages in the Chat AI feature.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @since      1.1.0
 */

defined('ABSPATH') || exit;

/**
 * Database operations for the Chat AI subsystem.
 */
class SENTINEL_Chat_DB
{

	const DB_VERSION     = '1.0.0';
	const OPT_DB_VERSION = 'mcpcomal_chat_db_version';

	/**
	 * Whether the tables existence has been verified in this request.
	 *
	 * @var bool
	 */
	private static bool $table_verified = false;

	/**
	 * Get the conversations table name.
	 *
	 * @return string
	 */
	public static function conversations_table(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'mcpcomal_chat_conversations';
	}

	/**
	 * Get the messages table name.
	 *
	 * @return string
	 */
	public static function messages_table(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'mcpcomal_chat_messages';
	}

	/**
	 * Create the chat database tables.
	 *
	 * @return void
	 */
	public static function create_tables(): void
	{
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$conversations = self::conversations_table();
		$messages      = self::messages_table();

		$sql_conversations = "CREATE TABLE {$conversations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL DEFAULT 'New conversation',
			provider varchar(32) NOT NULL DEFAULT 'anthropic',
			model varchar(64) NOT NULL DEFAULT 'claude-sonnet-4-6',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$sql_messages = "CREATE TABLE {$messages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL,
			content longtext NOT NULL,
			tool_calls longtext DEFAULT NULL,
			tool_results longtext DEFAULT NULL,
			tokens_in int unsigned NOT NULL DEFAULT 0,
			tokens_out int unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id)
		) {$charset_collate};";

		dbDelta($sql_conversations);
		dbDelta($sql_messages);

		update_option(self::OPT_DB_VERSION, self::DB_VERSION);

		self::$table_verified = true;
	}

	/**
	 * Upgrade tables if the DB version has changed.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void
	{
		if (get_option(self::OPT_DB_VERSION) !== self::DB_VERSION) {
			self::create_tables();
		}
	}

	/**
	 * Lazy check: verify the tables exist, create if missing.
	 *
	 * @return void
	 */
	public static function ensure_tables(): void
	{
		if (self::$table_verified) {
			return;
		}

		global $wpdb;

		$table  = self::conversations_table();
		$exists = $wpdb->get_var(
			$wpdb->prepare('SHOW TABLES LIKE %s', $table)
		);

		if ($exists === $table) {
			self::$table_verified = true;
			return;
		}

		self::create_tables();
	}

	/**
	 * Drop the chat tables.
	 *
	 * @return void
	 */
	public static function drop_tables(): void
	{
		global $wpdb;

		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mcpcomal_chat_messages");
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mcpcomal_chat_conversations");

		delete_option(self::OPT_DB_VERSION);
	}

	// ─── Conversations CRUD ──────────────────────────────────────────

	/**
	 * Create a new conversation.
	 *
	 * @param int    $user_id  WordPress user ID.
	 * @param string $provider AI provider slug.
	 * @param string $model    AI model ID.
	 * @return int|false Conversation ID on success, false on failure.
	 */
	public static function create_conversation(int $user_id, string $provider = 'anthropic', string $model = 'claude-sonnet-4-6'): int|false
	{
		global $wpdb;

		self::ensure_tables();

		$now = gmdate('Y-m-d H:i:s');

		$inserted = $wpdb->insert(
			self::conversations_table(),
			array(
				'user_id'    => $user_id,
				'provider'   => sanitize_text_field($provider),
				'model'      => sanitize_text_field($model),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array('%d', '%s', '%s', '%s', '%s')
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get a single conversation by ID (enforces user ownership).
	 *
	 * @param int $id      Conversation ID.
	 * @param int $user_id WordPress user ID.
	 * @return array|null Conversation row or null.
	 */
	public static function get_conversation(int $id, int $user_id): array|null
	{
		global $wpdb;

		self::ensure_tables();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d AND user_id = %d',
				self::conversations_table(),
				$id,
				$user_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * List conversations for a user, most recent first.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $limit   Maximum number of results.
	 * @param int $offset  Offset for pagination.
	 * @return array List of conversation rows.
	 */
	public static function list_conversations(int $user_id, int $limit = 50, int $offset = 0): array
	{
		global $wpdb;

		self::ensure_tables();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d ORDER BY updated_at DESC LIMIT %d OFFSET %d',
				self::conversations_table(),
				$user_id,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Update conversation title.
	 *
	 * @param int    $id      Conversation ID.
	 * @param int    $user_id WordPress user ID (ownership check).
	 * @param string $title   New title.
	 * @return bool True on success.
	 */
	public static function update_title(int $id, int $user_id, string $title): bool
	{
		global $wpdb;

		$updated = $wpdb->update(
			self::conversations_table(),
			array('title' => sanitize_text_field($title)),
			array('id' => $id, 'user_id' => $user_id),
			array('%s'),
			array('%d', '%d')
		);

		return false !== $updated && $updated > 0;
	}

	/**
	 * Update conversation provider and model.
	 *
	 * @param int    $id       Conversation ID.
	 * @param int    $user_id  WordPress user ID (ownership check).
	 * @param string $provider AI provider slug.
	 * @param string $model    AI model ID.
	 * @return bool True on success.
	 */
	public static function update_provider(int $id, int $user_id, string $provider, string $model): bool
	{
		global $wpdb;

		$updated = $wpdb->update(
			self::conversations_table(),
			array(
				'provider' => sanitize_text_field($provider),
				'model'    => sanitize_text_field($model),
			),
			array('id' => $id, 'user_id' => $user_id),
			array('%s', '%s'),
			array('%d', '%d')
		);

		return false !== $updated && $updated > 0;
	}

	/**
	 * Touch the updated_at timestamp of a conversation.
	 *
	 * @param int $id Conversation ID.
	 * @return void
	 */
	public static function touch(int $id): void
	{
		global $wpdb;

		$wpdb->update(
			self::conversations_table(),
			array('updated_at' => gmdate('Y-m-d H:i:s')),
			array('id' => $id),
			array('%s'),
			array('%d')
		);
	}

	/**
	 * Delete a conversation and all its messages.
	 *
	 * @param int $id      Conversation ID.
	 * @param int $user_id WordPress user ID (ownership check).
	 * @return bool True on success.
	 */
	public static function delete_conversation(int $id, int $user_id): bool
	{
		global $wpdb;

		// Verify ownership.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE id = %d AND user_id = %d',
				self::conversations_table(),
				$id,
				$user_id
			)
		);

		if (! $exists) {
			return false;
		}

		// Delete messages first.
		$wpdb->delete(
			self::messages_table(),
			array('conversation_id' => $id),
			array('%d')
		);

		// Delete conversation.
		$deleted = $wpdb->delete(
			self::conversations_table(),
			array('id' => $id),
			array('%d')
		);

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Search conversations by title and message content.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $query   Search query.
	 * @param int    $limit   Maximum number of results.
	 * @return array List of matching conversation rows.
	 */
	public static function search_conversations(int $user_id, string $query, int $limit = 30): array
	{
		global $wpdb;

		self::ensure_tables();

		$like = '%' . $wpdb->esc_like($query) . '%';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DISTINCT c.* FROM %i c
				LEFT JOIN %i m ON c.id = m.conversation_id
				WHERE c.user_id = %d AND (c.title LIKE %s OR m.content LIKE %s)
				ORDER BY c.updated_at DESC
				LIMIT %d',
				self::conversations_table(),
				self::messages_table(),
				$user_id,
				$like,
				$like,
				$limit
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	// ─── Messages CRUD ───────────────────────────────────────────────

	/**
	 * Add a message to a conversation.
	 *
	 * @param int         $conversation_id Conversation ID.
	 * @param string      $role            Message role (user, assistant, system).
	 * @param string      $content         Message content.
	 * @param array|null  $tool_calls      Tool calls made by assistant.
	 * @param array|null  $tool_results    Tool execution results.
	 * @param int         $tokens_in       Input tokens used.
	 * @param int         $tokens_out      Output tokens used.
	 * @return int|false Message ID on success, false on failure.
	 */
	public static function add_message(
		int $conversation_id,
		string $role,
		string $content,
		?array $tool_calls = null,
		?array $tool_results = null,
		int $tokens_in = 0,
		int $tokens_out = 0
	): int|false {
		global $wpdb;

		self::ensure_tables();

		$inserted = $wpdb->insert(
			self::messages_table(),
			array(
				'conversation_id' => $conversation_id,
				'role'            => sanitize_text_field($role),
				'content'         => $content,
				'tool_calls'      => null !== $tool_calls ? wp_json_encode($tool_calls) : null,
				'tool_results'    => null !== $tool_results ? wp_json_encode($tool_results) : null,
				'tokens_in'       => $tokens_in,
				'tokens_out'      => $tokens_out,
			),
			array('%d', '%s', '%s', '%s', '%s', '%d', '%d')
		);

		if ($inserted) {
			self::touch($conversation_id);
		}

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get all messages for a conversation (verifies user ownership).
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id         WordPress user ID.
	 * @return array List of message rows.
	 */
	public static function get_messages(int $conversation_id, int $user_id): array
	{
		global $wpdb;

		self::ensure_tables();

		// Verify ownership via join.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT m.* FROM %i m
				INNER JOIN %i c ON m.conversation_id = c.id
				WHERE m.conversation_id = %d AND c.user_id = %d
				ORDER BY m.created_at ASC',
				self::messages_table(),
				self::conversations_table(),
				$conversation_id,
				$user_id
			),
			ARRAY_A
		);

		if (! $rows) {
			return array();
		}

		foreach ($rows as &$row) {
			if (null !== $row['tool_calls']) {
				$row['tool_calls'] = json_decode($row['tool_calls'], true) ?? array();
			}
			if (null !== $row['tool_results']) {
				$row['tool_results'] = json_decode($row['tool_results'], true) ?? array();
			}
		}

		return $rows;
	}

	/**
	 * Get the last message from a conversation.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return array|null Message row or null.
	 */
	public static function get_last_message(int $conversation_id): array|null
	{
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE conversation_id = %d ORDER BY created_at DESC LIMIT 1',
				self::messages_table(),
				$conversation_id
			),
			ARRAY_A
		);

		if ($row) {
			if (null !== $row['tool_calls']) {
				$row['tool_calls'] = json_decode($row['tool_calls'], true) ?? array();
			}
			if (null !== $row['tool_results']) {
				$row['tool_results'] = json_decode($row['tool_results'], true) ?? array();
			}
		}

		return $row ?: null;
	}

	/**
	 * Delete old conversations and their messages.
	 *
	 * Removes conversations not updated in the last N days.
	 *
	 * @param int $days Number of days to keep.
	 * @return int Number of deleted conversations.
	 */
	public static function cleanup_old(int $days = 90): int
	{
		global $wpdb;

		self::ensure_tables();

		$threshold = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

		// Get IDs of old conversations.
		$old_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE updated_at < %s',
				self::conversations_table(),
				$threshold
			)
		);

		if (empty($old_ids)) {
			return 0;
		}

		$placeholders = implode(',', array_fill(0, count($old_ids), '%d'));

		// Delete messages.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}mcpcomal_chat_messages WHERE conversation_id IN ({$placeholders})",
				...$old_ids
			)
		);

		// Delete conversations.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}mcpcomal_chat_conversations WHERE id IN ({$placeholders})",
				...$old_ids
			)
		);

		return (int) $deleted;
	}
}
