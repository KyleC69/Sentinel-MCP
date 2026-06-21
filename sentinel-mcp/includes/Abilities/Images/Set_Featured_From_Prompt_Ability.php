<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Images;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\Image_Generator;

defined('ABSPATH') || exit;

/**
 * Generate image and set as featured ability.
 *
 * Generates one image with Gemini, saves it to the Media Library, and assigns it as the featured image of a target post.
 */
class Set_Featured_From_Prompt_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/set-featured-from-prompt';
    }

    public static function label(): string
    {
        return 'Generate image and set as featured';
    }

    public static function category(): string
    {
        return 'sentinel-images';
    }

    public static function description(): string
    {
        return 'Shortcut: generate one image with Gemini for the given prompt, save to Media Library and assign it as the featured image of a target post. Returns the attachment_id, url and post_id.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'required'             => array('prompt', 'post_id'),
            'properties'           => array(
                'prompt'  => array(
                    'type'        => 'string',
                    'minLength'   => 3,
                    'maxLength'   => 2000,
                    'description' => 'Description of the image to generate.',
                ),
                'post_id' => array(
                    'type'    => 'integer',
                    'minimum' => 1,
                ),
            ),
            'additionalProperties' => false,
        );
    }

    public static function output_schema(): array
    {
        return array(
            'type'                 => 'object',
            'additionalProperties' => true,
        );
    }

    public static function permission_callback(): callable
    {
        return static function ($input) {
            $post_id = isset($input['post_id']) ? absint($input['post_id']) : 0;
            if (! $post_id) {
                return false;
            }
            return current_user_can('edit_post', $post_id) && current_user_can('upload_files');
        };
    }

    public static function execute(array $input = array()): array
    {
        $prompt  = isset($input['prompt']) ? sanitize_textarea_field((string) $input['prompt']) : '';
        $post_id = isset($input['post_id']) ? absint($input['post_id']) : 0;

        if (! $post_id || ! get_post($post_id)) {
            return array(
                'success' => false,
                'message' => 'Post not found.',
            );
        }

        $result = Image_Generator::generate($prompt, 1, $post_id);
        if (! $result['ok'] || empty($result['items'][0]['attachment_id'])) {
            return array(
                'success' => false,
                'message' => $result['message'] ?? 'Image generation failed.',
            );
        }

        $attachment_id = (int) $result['items'][0]['attachment_id'];
        $ok            = set_post_thumbnail($post_id, $attachment_id);
        if (! $ok) {
            return array(
                'success'       => false,
                'message'       => 'Image generated but failed to set as featured.',
                'attachment_id' => $attachment_id,
                'post_id'       => $post_id,
            );
        }

        return array(
            'success'       => true,
            'post_id'       => $post_id,
            'attachment_id' => $attachment_id,
            'url'           => (string) ($result['items'][0]['url'] ?? ''),
        );
    }

    public static function meta(): array
    {
        return array(
            'mcp' => array(
                'public'      => true,
                'annotations' => array(
                    'readOnlyHint'    => false,
                    'destructiveHint' => false,
                    'idempotentHint'  => false,
                    'openWorldHint'   => true,
                ),
            ),
        );
    }
}
