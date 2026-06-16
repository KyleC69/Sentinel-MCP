<?php

declare(strict_types=1);

namespace SentinelMCP\Abilities\Images;

use SentinelMCP\Abilities\Ability;
use SentinelMCP\Image_Generator;

defined('ABSPATH') || exit;

/**
 * Generate AI image(s) ability.
 *
 * Generates 1 to 3 images for a prompt using Google Gemini and saves each to the Media Library.
 */
class Generate_Image_Ability implements Ability
{
    public static function slug(): string
    {
        return 'sentinel/generate-image';
    }

    public static function label(): string
    {
        return 'Generate AI image(s)';
    }

    public static function category(): string
    {
        return 'sentinel-images';
    }

    public static function description(): string
    {
        return 'Generate 1 to 3 images for a prompt using Google Gemini and save each one to the Media Library. Returns attachment IDs and URLs. The output is square-ish PNG at the model native size; aspect ratio selection, 2K/4K, Imagen API and image editing are Premium. Optional: attach the generated image(s) to a specific post via attach_to_post.';
    }

    public static function input_schema(): array
    {
        return array(
            'type'                 => 'object',
            'default'              => array(),
            'required'             => array('prompt'),
            'properties'           => array(
                'prompt'         => array(
                    'type'        => 'string',
                    'minLength'   => 3,
                    'maxLength'   => 2000,
                    'description' => 'Description of the image to generate.',
                ),
                'count'          => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 3,
                    'default'     => 1,
                    'description' => 'How many images to generate (1-3).',
                ),
                'attach_to_post' => array(
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'default'     => 0,
                    'description' => 'Optional post ID to attach the new attachments to.',
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
        return \SentinelMCP\mcpcomal_ability_permission('upload_files');
    }

    public static function execute(array $input = array()): array
    {
        $prompt         = isset($input['prompt']) ? sanitize_textarea_field((string) $input['prompt']) : '';
        $count          = isset($input['count']) ? (int) $input['count'] : 1;
        $attach_to_post = isset($input['attach_to_post']) ? absint($input['attach_to_post']) : 0;

        $result = Image_Generator::generate($prompt, $count, $attach_to_post);
        if (! $result['ok']) {
            return array(
                'success'       => false,
                'message'       => $result['message'] ?? 'Image generation failed.',
                'partial_items' => $result['items'] ?? array(),
            );
        }

        return array(
            'success' => true,
            'count'   => count($result['items']),
            'items'   => $result['items'],
        );
    }

    public static function meta(): array
    {
        return \SentinelMCP\mcpcomal_ability_meta(array('readOnlyHint' => false, 'idempotentHint' => false, 'openWorldHint' => true));
    }
}
