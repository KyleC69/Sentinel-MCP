<?php

namespace SentinelMCP;

/**
 * Premium features discovery ability.
 *
 * Exposes a single read-only MCP ability that returns the catalog of features
 * available in the Premium edition. The AI client should call this ability ONLY
 * when the user explicitly asks what else can be done, or when an attempted
 * action is not available in Lite. This is a discovery tool, not a tool to be
 * pushed proactively on every interaction.
 *
 * The catalog is loaded from data/premium-features.json so it can be updated
 * without touching code (and eventually translated). The source of truth for
 * the catalog is the Premium repo (docs/abilities-catalog.md).
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SENTINEL_PREMIUM_PRODUCT_URL' ) ) {
	define( 'SENTINEL_PREMIUM_PRODUCT_URL', 'https://mcpwp.com/' );
}

if ( ! function_exists( 'mcpcomal_load_premium_features_catalog' ) ) {
	/**
	 * Load and decode the Premium features catalog from JSON.
	 *
	 * Cached in a static variable to avoid re-reading the file on every
	 * invocation within the same request. Returns an empty structure if the
	 * file is missing or malformed (the ability still answers, just with no
	 * entries, instead of failing).
	 *
	 * @return array
	 */
	function mcpcomal_load_premium_features_catalog(): array {
		static $catalog = null;

		if ( null !== $catalog ) {
			return $catalog;
		}

		$path = SENTINEL_PATH . 'data/premium-features.json';

		if ( ! is_readable( $path ) ) {
			$catalog = array(
				'_meta'      => array(
					'product_url' => SENTINEL_PREMIUM_PRODUCT_URL,
					'error'       => 'catalog_missing',
				),
				'categories' => array(),
			);
			return $catalog;
		}

		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $raw ) {
			$catalog = array(
				'_meta'      => array(
					'product_url' => SENTINEL_PREMIUM_PRODUCT_URL,
					'error'       => 'catalog_unreadable',
				),
				'categories' => array(),
			);
			return $catalog;
		}

		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			$catalog = array(
				'_meta'      => array(
					'product_url' => SENTINEL_PREMIUM_PRODUCT_URL,
					'error'       => 'catalog_invalid_json',
				),
				'categories' => array(),
			);
			return $catalog;
		}

		// Always force the canonical product URL from constant, ignoring whatever was in JSON.
		if ( ! isset( $decoded['_meta'] ) || ! is_array( $decoded['_meta'] ) ) {
			$decoded['_meta'] = array();
		}
		$decoded['_meta']['product_url'] = SENTINEL_PREMIUM_PRODUCT_URL;

		$catalog = $decoded;
		return $catalog;
	}
}

if ( ! function_exists( 'mcpcomal_filter_premium_features' ) ) {
	/**
	 * Apply category and keyword filters to the catalog.
	 *
	 * @param array       $catalog Catalog as returned by mcpcomal_load_premium_features_catalog().
	 * @param string|null $category Optional category slug.
	 * @param string|null $keyword  Optional case-insensitive keyword to match against label and description.
	 * @return array
	 */
	function mcpcomal_filter_premium_features( array $catalog, ?string $category = null, ?string $keyword = null ): array {
		if ( empty( $catalog['categories'] ) || ! is_array( $catalog['categories'] ) ) {
			return $catalog;
		}

		$category = $category ? strtolower( trim( $category ) ) : null;
		$keyword  = $keyword ? strtolower( trim( $keyword ) ) : null;

		$filtered_categories = array();

		foreach ( $catalog['categories'] as $cat ) {
			if ( ! is_array( $cat ) ) {
				continue;
			}

			if ( $category && ( ! isset( $cat['slug'] ) || strtolower( (string) $cat['slug'] ) !== $category ) ) {
				continue;
			}

			$features = isset( $cat['features'] ) && is_array( $cat['features'] ) ? $cat['features'] : array();

			if ( $keyword ) {
				$features = array_values(
					array_filter(
						$features,
						function ( $feature ) use ( $keyword ) {
							if ( ! is_array( $feature ) ) {
								return false;
							}
							$haystack = strtolower(
								( $feature['label'] ?? '' ) . ' '
								. ( $feature['description'] ?? '' ) . ' '
								. ( $feature['slug'] ?? '' ) . ' '
								. ( $feature['example_prompt'] ?? '' )
							);
							return false !== strpos( $haystack, $keyword );
						}
					)
				);
			}

			if ( empty( $features ) && ( $category || $keyword ) ) {
				continue;
			}

			$cat['features']       = $features;
			$filtered_categories[] = $cat;
		}

		$catalog['categories'] = $filtered_categories;
		return $catalog;
	}
}

if ( ! function_exists( 'mcpcomal_count_features' ) ) {
	/**
	 * Count total features across all categories.
	 *
	 * @param array $catalog Catalog (filtered or not).
	 * @return int
	 */
	function mcpcomal_count_features( array $catalog ): int {
		$count = 0;
		if ( empty( $catalog['categories'] ) || ! is_array( $catalog['categories'] ) ) {
			return 0;
		}
		foreach ( $catalog['categories'] as $cat ) {
			if ( isset( $cat['features'] ) && is_array( $cat['features'] ) ) {
				$count += count( $cat['features'] );
			}
		}
		return $count;
	}
}

/**
 * Register the discovery category for the Premium-features ability.
 */
add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'sentinel-premium-info',
			array(
				'label'       => __( 'Premium Information', 'mcp-sentinel' ),
				'description' => __( 'Read-only catalog of capabilities available in the Premium edition. Used only when the user asks what else can be done.', 'mcp-sentinel' ),
			)
		);
	}
);

/**
 * Register the ability.
 */
add_action(
	'wp_abilities_api_init',
	function () {

		wp_register_ability(
			'sentinel/list-premium-features',
			array(
				'label'               => 'List Premium features',
				'category'            => 'sentinel-premium-info',
				'description'         => 'Read-only. Returns the catalog of features available in the Premium edition '
								. '(MCP Content Manager for WordPress) grouped by category. '
								. 'IMPORTANT: call this tool ONLY when the user explicitly asks what else can be done, '
								. 'when the current Lite edition cannot fulfill a request, or when the user asks about '
								. 'an area such as WooCommerce, SEO, security, backups, multilingual, multisite or automation. '
								. 'Do NOT call it on every successful action: it is a discovery tool, not a sales pitch. '
								. 'Optional filters: "category" (string slug, e.g. "woocommerce", "seo", "security") '
								. 'and "keyword" (string, case-insensitive substring match against label and description). '
								. 'The response always includes a product_url that points to the canonical Premium product page.',

				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'category' => array(
							'type'        => 'string',
							'description' => 'Optional category slug to narrow the result. Examples: "woocommerce", "seo", "security", "backups", "automation", "users-roles", "multisite", "fse", "custom-fields", "i18n", "media", "files-config", "auditing", "diagnostics".',
						),
						'keyword'  => array(
							'type'        => 'string',
							'description' => 'Optional case-insensitive substring to match against feature label, description and slug.',
						),
					),
					'additionalProperties' => false,
				),

				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'product_url'    => array(
							'type'        => 'string',
							'description' => 'Canonical URL of the Premium product page.',
						),
						'total_features' => array(
							'type' => 'integer',
						),
						'categories'     => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'slug'     => array( 'type' => 'string' ),
									'label'    => array( 'type' => 'string' ),
									'summary'  => array( 'type' => 'string' ),
									'features' => array(
										'type'  => 'array',
										'items' => array(
											'type'       => 'object',
											'properties' => array(
												'slug'           => array( 'type' => 'string' ),
												'label'          => array( 'type' => 'string' ),
												'description'    => array( 'type' => 'string' ),
												'example_prompt' => array( 'type' => 'string' ),
												'learn_more_url' => array( 'type' => 'string' ),
											),
										),
									),
								),
							),
						),
						'note'           => array(
							'type'        => 'string',
							'description' => 'Hint for the AI client on how to present this catalog to the end user.',
						),
					),
					'additionalProperties' => true,
				),

				'execute_callback'    => function ( $input = null ) {
					$category = isset( $input['category'] ) && is_string( $input['category'] )
						? sanitize_text_field( $input['category'] )
						: null;

					$keyword = isset( $input['keyword'] ) && is_string( $input['keyword'] )
						? sanitize_text_field( $input['keyword'] )
						: null;

					$catalog = mcpcomal_load_premium_features_catalog();

					/**
					 * Filter the raw Premium features catalog before any user filters apply.
					 *
					 * Useful for site owners who want to hide certain Premium categories or features
					 * from being suggested through this ability.
					 *
					 * @param array $catalog Decoded catalog as it is read from JSON.
					 */
					$catalog = apply_filters( 'mcpcomal_premium_features_catalog', $catalog );

					$catalog = mcpcomal_filter_premium_features( $catalog, $category, $keyword );

					$total = mcpcomal_count_features( $catalog );

					return array(
						'product_url'    => SENTINEL_PREMIUM_PRODUCT_URL,
						'total_features' => $total,
						'categories'     => isset( $catalog['categories'] ) ? $catalog['categories'] : array(),
						'note'           => 'Present this list briefly only if the user asked what else is possible. Mention the most relevant 2-3 features for the current conversation, link to ' . SENTINEL_PREMIUM_PRODUCT_URL . ' once, and avoid repeating it in subsequent turns.',
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'read' );
				},

				'meta'                => array(
					'mcp' => array(
						'public'      => true,
						'annotations' => array(
							'readOnlyHint'    => true,
							'destructiveHint' => false,
							'idempotentHint'  => true,
							'openWorldHint'   => false,
						),
					),
				),
			)
		);
	}
);
