<?php

declare(strict_types=1);

namespace SentinelMCP;

/**
 * REST API controller for Chat AI.
 *
 * Exposes endpoints for sending messages, managing conversations,
 * and querying available AI providers.
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder <kcrowdergoog@gmail.com>
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @since      1.1.0
 */

defined('ABSPATH') || exit;

/**
 * REST Chat controller.
 */
class REST_Chat
{

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'sentinel/v1';

	/**
	 * Register REST routes.
	 */
	public static function init(): void
	{
		add_action('rest_api_init', array(__CLASS__, 'register_routes'));
	}

	/**
	 * Register all chat endpoints.
	 */
	public static function register_routes(): void
	{
		$ns = self::NAMESPACE;

		// Send message.
		register_rest_route(
			$ns,
			'/chat/send',
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'handle_send'),
				'permission_callback' => array(__CLASS__, 'check_permissions'),
				'args'                => array(
					'conversation_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'message'         => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// List conversations.
		register_rest_route(
			$ns,
			'/chat/conversations',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array(__CLASS__, 'handle_list_conversations'),
					'permission_callback' => array(__CLASS__, 'check_permissions'),
					'args'                => array(
						'limit'  => array(
							'type'              => 'integer',
							'default'           => 50,
							'sanitize_callback' => 'absint',
						),
						'offset' => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array(__CLASS__, 'handle_create_conversation'),
					'permission_callback' => array(__CLASS__, 'check_permissions'),
					'args'                => array(
						'provider' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model'    => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Single conversation (get, delete, rename).
		register_rest_route(
			$ns,
			'/chat/conversations/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array(__CLASS__, 'handle_get_conversation'),
					'permission_callback' => array(__CLASS__, 'check_permissions'),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array(__CLASS__, 'handle_delete_conversation'),
					'permission_callback' => array(__CLASS__, 'check_permissions'),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array(__CLASS__, 'handle_rename_conversation'),
					'permission_callback' => array(__CLASS__, 'check_permissions'),
					'args'                => array(
						'title' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Search conversations.
		register_rest_route(
			$ns,
			'/chat/search',
			array(
				'methods'             => 'GET',
				'callback'            => array(__CLASS__, 'handle_search'),
				'permission_callback' => array(__CLASS__, 'check_permissions'),
				'args'                => array(
					'q'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit' => array(
						'type'              => 'integer',
						'default'           => 30,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Providers list.
		register_rest_route(
			$ns,
			'/chat/providers',
			array(
				'methods'             => 'GET',
				'callback'            => array(__CLASS__, 'handle_providers'),
				'permission_callback' => array(__CLASS__, 'check_permissions'),
			)
		);

		// Switch provider/model on a conversation.
		register_rest_route(
			$ns,
			'/chat/switch-provider',
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'handle_switch_provider'),
				'permission_callback' => array(__CLASS__, 'check_permissions'),
				'args'                => array(
					'conversation_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'provider'        => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'model'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Permission check: require manage_options.
	 *
	 * @return bool
	 */
	public static function check_permissions(): bool
	{
		return current_user_can('manage_options');
	}

	// ─── Handlers ────────────────────────────────────────────────────

	/**
	 * Send a message to the AI and return the response.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_send(\WP_REST_Request $request): \WP_REST_Response
	{
		$conversation_id = $request->get_param('conversation_id');
		$message         = $request->get_param('message');
		$user_id         = get_current_user_id();

		if (empty(trim($message))) {
			return new \WP_REST_Response(
				array('success' => false, 'error' => 'Message cannot be empty.'),
				400
			);
		}

		$result = Chat_Engine::process_message($conversation_id, $message, $user_id);

		if (! $result['success']) {
			return new \WP_REST_Response($result, 400);
		}

		// Include updated conversation data.
		$conversation = Chat_DB::get_conversation($conversation_id, $user_id);

		return new \WP_REST_Response(
			array(
				'success'      => true,
				'message'      => $result['message'],
				'conversation' => $conversation,
			)
		);
	}

	/**
	 * List conversations for the current user.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_list_conversations(\WP_REST_Request $request): \WP_REST_Response
	{
		$user_id = get_current_user_id();
		$limit   = $request->get_param('limit');
		$offset  = $request->get_param('offset');

		$conversations = Chat_DB::list_conversations($user_id, $limit, $offset);

		// Attach last message preview to each conversation.
		foreach ($conversations as &$conv) {
			$last = Chat_DB::get_last_message((int) $conv['id']);
			$conv['last_message'] = $last ? mb_substr($last['content'], 0, 100) : '';
			$conv['last_role']    = $last ? $last['role'] : '';
		}

		return new \WP_REST_Response(
			array(
				'success'       => true,
				'conversations' => $conversations,
			)
		);
	}

	/**
	 * Create a new conversation.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_create_conversation(\WP_REST_Request $request): \WP_REST_Response
	{
		$user_id  = get_current_user_id();
		$provider = $request->get_param('provider') ?: Chat_Engine::get_default_provider();
		$model    = $request->get_param('model') ?: Chat_Engine::get_default_model($provider);

		$conv_id = Chat_DB::create_conversation($user_id, $provider, $model);

		if (! $conv_id) {
			return new \WP_REST_Response(
				array('success' => false, 'error' => 'Failed to create conversation.'),
				500
			);
		}

		$conversation = Chat_DB::get_conversation($conv_id, $user_id);

		return new \WP_REST_Response(
			array(
				'success'      => true,
				'conversation' => $conversation,
			),
			201
		);
	}

	/**
	 * Get a conversation with all its messages.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_get_conversation(\WP_REST_Request $request): \WP_REST_Response
	{
		$id      = (int) $request->get_param('id');
		$user_id = get_current_user_id();

		$conversation = Chat_DB::get_conversation($id, $user_id);
		if (! $conversation) {
			return new \WP_REST_Response(
				array('success' => false, 'error' => 'Conversation not found.'),
				404
			);
		}

		$messages = Chat_DB::get_messages($id, $user_id);

		return new \WP_REST_Response(
			array(
				'success'      => true,
				'conversation' => $conversation,
				'messages'     => $messages,
			)
		);
	}

	/**
	 * Delete a conversation.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_delete_conversation(\WP_REST_Request $request): \WP_REST_Response
	{
		$id      = (int) $request->get_param('id');
		$user_id = get_current_user_id();

		$deleted = Chat_DB::delete_conversation($id, $user_id);

		if (! $deleted) {
			return new \WP_REST_Response(
				array('success' => false, 'error' => 'Conversation not found or already deleted.'),
				404
			);
		}

		return new \WP_REST_Response(array('success' => true));
	}

	/**
	 * Rename a conversation.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_rename_conversation(\WP_REST_Request $request): \WP_REST_Response
	{
		$id      = (int) $request->get_param('id');
		$user_id = get_current_user_id();
		$title   = $request->get_param('title');

		$updated = Chat_DB::update_title($id, $user_id, $title);

		if (! $updated) {
			return new \WP_REST_Response(
				array('success' => false, 'error' => 'Conversation not found.'),
				404
			);
		}

		return new \WP_REST_Response(array('success' => true));
	}

	/**
	 * Search conversations.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_search(\WP_REST_Request $request): \WP_REST_Response
	{
		$user_id = get_current_user_id();
		$query   = $request->get_param('q');
		$limit   = $request->get_param('limit');

		$results = Chat_DB::search_conversations($user_id, $query, $limit);

		return new \WP_REST_Response(
			array(
				'success'       => true,
				'conversations' => $results,
			)
		);
	}

	/**
	 * List available AI providers and models.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_providers(): \WP_REST_Response
	{
		return new \WP_REST_Response(
			array(
				'success'   => true,
				'providers' => Chat_Engine::get_available_providers(),
				'default'   => Chat_Engine::get_default_provider(),
			)
		);
	}

	/**
	 * Switch provider/model for a conversation.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_switch_provider(\WP_REST_Request $request): \WP_REST_Response
	{
		$conv_id  = $request->get_param('conversation_id');
		$provider = $request->get_param('provider');
		$model    = $request->get_param('model');
		$user_id  = get_current_user_id();

		// Validate provider exists.
		$providers = Chat_Provider_Registry::get_providers();
		if (! isset($providers[$provider])) {
			return new \WP_REST_Response(
				array('success' => false, 'error' => 'Unknown provider.'),
				400
			);
		}

		$updated = Chat_DB::update_provider($conv_id, $user_id, $provider, $model);

		if (! $updated) {
			return new \WP_REST_Response(
				array('success' => false, 'error' => 'Conversation not found.'),
				404
			);
		}

		return new \WP_REST_Response(array('success' => true));
	}
}
