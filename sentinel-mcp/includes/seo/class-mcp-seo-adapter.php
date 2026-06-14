<?php

namespace SentinelMCP;

/**
 * SEO read adapter (Sprint 1.7).
 *
 * Detects which SEO plugin is active and returns a unified read-only object
 * for a given post. Designed to be defensive: every external call is guarded
 * by class_exists / function_exists / table existence. Editing SEO meta is
 * reserved for the Premium edition.
 *
 * Supported plugins (in detection order):
 *   1. Yoast SEO
 *   2. Rank Math
 *   3. All in One SEO (AIOSEO)
 *   4. The SEO Framework
 *   5. SureRank
 *   6. SEOPress
 *   7. Slim SEO
 *   8. Squirrly SEO
 *
 * @package    SENTINEL
 * @author     Kyle L Crowder
 * @copyright  2026 Kyle L Crowder
 * @license    GPL-2.0-or-later
 * @link       https://github.com/KyleC69/Sentinel-MCP
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'SentinelMCP\SENTINEL_SEO_Adapter' ) ) {

	/**
	 * Lightweight SEO meta reader across multiple plugins.
	 */
	class SENTINEL_SEO_Adapter {

		/**
		 * Detect which SEO plugins are active on the site.
		 *
		 * @return array Array of plugin slugs in order of detection.
		 */
		public static function detect_active_plugins(): array {
			$active = array();

			// 1. Yoast SEO.
			if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
				$active[] = 'yoast';
			}

			// 2. Rank Math.
			if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
				$active[] = 'rank_math';
			}

			// 3. AIOSEO.
			if ( defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\\Plugin\\AIOSEO' ) ) {
				$active[] = 'aioseo';
			}

			// 4. The SEO Framework.
			if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'tsf' ) ) {
				$active[] = 'the_seo_framework';
			}

			// 5. SureRank.
			if ( defined( 'SURERANK_VERSION' ) || class_exists( 'SureRank' ) ) {
				$active[] = 'surerank';
			}

			// 6. SEOPress.
			if ( defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_init' ) ) {
				$active[] = 'seopress';
			}

			// 7. Slim SEO.
			if ( defined( 'SLIM_SEO_VER' ) || class_exists( 'SlimSEO\\Plugin' ) ) {
				$active[] = 'slim_seo';
			}

			// 8. Squirrly SEO.
			if ( defined( 'SQ_VERSION' ) || class_exists( 'SQ_Classes_ObjController' ) ) {
				$active[] = 'squirrly';
			}

			return $active;
		}

		/**
		 * Read unified SEO meta for a post.
		 *
		 * @param int $post_id Post ID.
		 * @return array {
		 *     Returns one entry per detected SEO plugin. Empty array when no SEO plugin is active.
		 *     @type string seo_plugin       Slug of the SEO plugin.
		 *     @type string title            SEO title (may be a template, not the rendered value).
		 *     @type string description      Meta description.
		 *     @type string focus_keyword    Focus keyword (when supported).
		 *     @type string canonical        Canonical URL.
		 *     @type bool   robots_noindex   Whether the post is marked noindex.
		 *     @type array  raw_meta         Raw meta values inspected for this plugin.
		 * }
		 */
		public static function read_for_post( int $post_id ): array {
			$post_id = absint( $post_id );
			if ( ! $post_id || ! get_post( $post_id ) ) {
				return array();
			}

			$active  = self::detect_active_plugins();
			$results = array();

			foreach ( $active as $slug ) {
				$method = 'read_' . $slug;
				if ( method_exists( __CLASS__, $method ) ) {
					$entry = call_user_func( array( __CLASS__, $method ), $post_id );
					if ( ! empty( $entry ) ) {
						$results[] = $entry;
					}
				}
			}

			return $results;
		}

		/**
		 * Yoast SEO reader.
		 *
		 * @param int $post_id Post ID.
		 * @return array
		 */
		protected static function read_yoast( int $post_id ): array {
			$raw = array(
				'_yoast_wpseo_title'              => get_post_meta( $post_id, '_yoast_wpseo_title', true ),
				'_yoast_wpseo_metadesc'           => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
				'_yoast_wpseo_focuskw'            => get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
				'_yoast_wpseo_canonical'          => get_post_meta( $post_id, '_yoast_wpseo_canonical', true ),
				'_yoast_wpseo_meta-robots-noindex' => get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ),
			);

			return array(
				'seo_plugin'     => 'yoast',
				'title'          => (string) $raw['_yoast_wpseo_title'],
				'description'    => (string) $raw['_yoast_wpseo_metadesc'],
				'focus_keyword'  => (string) $raw['_yoast_wpseo_focuskw'],
				'canonical'      => (string) $raw['_yoast_wpseo_canonical'],
				'robots_noindex' => '1' === (string) $raw['_yoast_wpseo_meta-robots-noindex'] || '2' === (string) $raw['_yoast_wpseo_meta-robots-noindex'],
				'raw_meta'       => $raw,
			);
		}

		/**
		 * Rank Math reader.
		 *
		 * @param int $post_id Post ID.
		 * @return array
		 */
		protected static function read_rank_math( int $post_id ): array {
			$robots = get_post_meta( $post_id, 'rank_math_robots', true );
			$is_noindex = false;
			if ( is_array( $robots ) ) {
				$is_noindex = in_array( 'noindex', $robots, true );
			} elseif ( is_string( $robots ) && '' !== $robots ) {
				$is_noindex = false !== strpos( $robots, 'noindex' );
			}

			$raw = array(
				'rank_math_title'         => get_post_meta( $post_id, 'rank_math_title', true ),
				'rank_math_description'   => get_post_meta( $post_id, 'rank_math_description', true ),
				'rank_math_focus_keyword' => get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
				'rank_math_canonical_url' => get_post_meta( $post_id, 'rank_math_canonical_url', true ),
				'rank_math_robots'        => $robots,
			);

			return array(
				'seo_plugin'     => 'rank_math',
				'title'          => (string) $raw['rank_math_title'],
				'description'    => (string) $raw['rank_math_description'],
				'focus_keyword'  => (string) $raw['rank_math_focus_keyword'],
				'canonical'      => (string) $raw['rank_math_canonical_url'],
				'robots_noindex' => $is_noindex,
				'raw_meta'       => $raw,
			);
		}

		/**
		 * AIOSEO reader. Queries the aioseo_posts table when available, falls back to postmeta.
		 *
		 * @param int $post_id Post ID.
		 * @return array
		 */
		protected static function read_aioseo( int $post_id ): array {
			global $wpdb;

			$table = $wpdb->prefix . 'aioseo_posts';
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( $found === $table ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT title, description, keywords, canonical_url, robots_noindex FROM {$table} WHERE post_id = %d", $post_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				if ( is_array( $row ) ) {
					return array(
						'seo_plugin'     => 'aioseo',
						'title'          => isset( $row['title'] ) ? (string) $row['title'] : '',
						'description'    => isset( $row['description'] ) ? (string) $row['description'] : '',
						'focus_keyword'  => isset( $row['keywords'] ) ? (string) $row['keywords'] : '',
						'canonical'      => isset( $row['canonical_url'] ) ? (string) $row['canonical_url'] : '',
						'robots_noindex' => ! empty( $row['robots_noindex'] ),
						'raw_meta'       => $row,
					);
				}
			}

			$raw = array(
				'_aioseo_title'       => get_post_meta( $post_id, '_aioseo_title', true ),
				'_aioseo_description' => get_post_meta( $post_id, '_aioseo_description', true ),
				'_aioseo_keywords'    => get_post_meta( $post_id, '_aioseo_keywords', true ),
			);

			return array(
				'seo_plugin'     => 'aioseo',
				'title'          => (string) $raw['_aioseo_title'],
				'description'    => (string) $raw['_aioseo_description'],
				'focus_keyword'  => (string) $raw['_aioseo_keywords'],
				'canonical'      => '',
				'robots_noindex' => false,
				'raw_meta'       => $raw,
			);
		}

		/**
		 * The SEO Framework reader.
		 *
		 * @param int $post_id Post ID.
		 * @return array
		 */
		protected static function read_the_seo_framework( int $post_id ): array {
			$raw = array(
				'_genesis_title'         => get_post_meta( $post_id, '_genesis_title', true ),
				'_genesis_description'   => get_post_meta( $post_id, '_genesis_description', true ),
				'_genesis_canonical_uri' => get_post_meta( $post_id, '_genesis_canonical_uri', true ),
				'_genesis_noindex'       => get_post_meta( $post_id, '_genesis_noindex', true ),
			);

			return array(
				'seo_plugin'     => 'the_seo_framework',
				'title'          => (string) $raw['_genesis_title'],
				'description'    => (string) $raw['_genesis_description'],
				'focus_keyword'  => '',
				'canonical'      => (string) $raw['_genesis_canonical_uri'],
				'robots_noindex' => '1' === (string) $raw['_genesis_noindex'],
				'raw_meta'       => $raw,
			);
		}

		/**
		 * SureRank reader. Best-effort generic postmeta scan.
		 *
		 * @param int $post_id Post ID.
		 * @return array
		 */
		protected static function read_surerank( int $post_id ): array {
			$raw = array(
				'_surerank_title'       => get_post_meta( $post_id, '_surerank_title', true ),
				'_surerank_description' => get_post_meta( $post_id, '_surerank_description', true ),
				'_surerank_canonical'   => get_post_meta( $post_id, '_surerank_canonical', true ),
				'_surerank_robots'      => get_post_meta( $post_id, '_surerank_robots', true ),
			);

			$robots         = (string) $raw['_surerank_robots'];
			$is_noindex     = false !== strpos( $robots, 'noindex' );

			return array(
				'seo_plugin'     => 'surerank',
				'title'          => (string) $raw['_surerank_title'],
				'description'    => (string) $raw['_surerank_description'],
				'focus_keyword'  => '',
				'canonical'      => (string) $raw['_surerank_canonical'],
				'robots_noindex' => $is_noindex,
				'raw_meta'       => $raw,
			);
		}

		/**
		 * SEOPress reader.
		 *
		 * @param int $post_id Post ID.
		 * @return array
		 */
		protected static function read_seopress( int $post_id ): array {
			$raw = array(
				'_seopress_titles_title'    => get_post_meta( $post_id, '_seopress_titles_title', true ),
				'_seopress_titles_desc'     => get_post_meta( $post_id, '_seopress_titles_desc', true ),
				'_seopress_robots_canonical' => get_post_meta( $post_id, '_seopress_robots_canonical', true ),
				'_seopress_robots_index'    => get_post_meta( $post_id, '_seopress_robots_index', true ),
				'_seopress_analysis_target_kw' => get_post_meta( $post_id, '_seopress_analysis_target_kw', true ),
			);

			return array(
				'seo_plugin'     => 'seopress',
				'title'          => (string) $raw['_seopress_titles_title'],
				'description'    => (string) $raw['_seopress_titles_desc'],
				'focus_keyword'  => (string) $raw['_seopress_analysis_target_kw'],
				'canonical'      => (string) $raw['_seopress_robots_canonical'],
				'robots_noindex' => 'yes' === (string) $raw['_seopress_robots_index'],
				'raw_meta'       => $raw,
			);
		}

		/**
		 * Slim SEO reader. Stored as a single serialized 'slim_seo' meta.
		 *
		 * @param int $post_id Post ID.
		 * @return array
		 */
		protected static function read_slim_seo( int $post_id ): array {
			$slim = get_post_meta( $post_id, 'slim_seo', true );
			if ( ! is_array( $slim ) ) {
				$slim = array();
			}

			return array(
				'seo_plugin'     => 'slim_seo',
				'title'          => isset( $slim['title'] ) ? (string) $slim['title'] : '',
				'description'    => isset( $slim['description'] ) ? (string) $slim['description'] : '',
				'focus_keyword'  => '',
				'canonical'      => isset( $slim['canonical'] ) ? (string) $slim['canonical'] : '',
				'robots_noindex' => isset( $slim['noindex'] ) ? (bool) $slim['noindex'] : false,
				'raw_meta'       => array( 'slim_seo' => $slim ),
			);
		}

		/**
		 * Squirrly SEO reader. Stub — Squirrly stores most data in its own tables; this returns
		 * what is available in postmeta and signals the limitation.
		 *
		 * @param int $post_id Post ID.
		 * @return array
		 */
		protected static function read_squirrly( int $post_id ): array {
			$raw = array(
				'_sq_post_keyword'  => get_post_meta( $post_id, '_sq_post_keyword', true ),
				'_sq_meta_title'    => get_post_meta( $post_id, '_sq_meta_title', true ),
				'_sq_meta_desc'     => get_post_meta( $post_id, '_sq_meta_desc', true ),
			);

			return array(
				'seo_plugin'     => 'squirrly',
				'title'          => (string) $raw['_sq_meta_title'],
				'description'    => (string) $raw['_sq_meta_desc'],
				'focus_keyword'  => (string) $raw['_sq_post_keyword'],
				'canonical'      => '',
				'robots_noindex' => false,
				'raw_meta'       => $raw,
				'_partial'       => 'Squirrly stores most SEO data in its own DB tables; only postmeta is reflected here.',
			);
		}
	}
}
