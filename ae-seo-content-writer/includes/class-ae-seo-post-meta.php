<?php
/**
 * AE SEO Content Writer — Store and expose SEO meta per post (meta title, meta description, keywords).
 * Persisted in wp_postmeta; exposed via REST API so the automation runner can set them when creating/updating posts.
 * Theme uses these values in <head> for search engines and social (og/twitter).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AE_SEO_Content_Writer_Post_Meta {

	const META_KEY_TITLE       = 'ae_seo_meta_title';
	const META_KEY_DESCRIPTION = 'ae_seo_meta_description';
	const META_KEY_KEYWORDS    = 'ae_seo_keywords';
	const META_KEY_PRIMARY_KW  = 'ae_seo_primary_keyword';
	const META_KEY_TWITTER_CARD_TITLE       = 'ae_seo_twitter_card_title';
	const META_KEY_TWITTER_CARD_DESCRIPTION = 'ae_seo_twitter_card_description';

	/** Meta keys that are exposed in REST and used in front-end head. */
	public static function get_meta_keys() {
		return [
			self::META_KEY_TITLE,
			self::META_KEY_DESCRIPTION,
			self::META_KEY_KEYWORDS,
			self::META_KEY_PRIMARY_KW,
			self::META_KEY_TWITTER_CARD_TITLE,
			self::META_KEY_TWITTER_CARD_DESCRIPTION,
		];
	}

	public static function register() {
		foreach ( self::get_meta_keys() as $key ) {
			register_post_meta(
				'post',
				$key,
				[
					'type'              => 'string',
					'description'       => __( 'SEO meta for this post.', 'ae-seo-content-writer' ),
					'single'            => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
					'show_in_rest'      => true,
					'schema'            => [ 'type' => 'string' ],
				]
			);
		}
	}

	/**
	 * Get all SEO meta for a post (for use in theme head).
	 *
	 * @param int $post_id Post ID.
	 * @return array{meta_title: string, meta_description: string, keywords: string, primary_keyword: string}
	 */
	public static function get_seo_meta( $post_id ) {
		return [
			'meta_title'               => (string) get_post_meta( $post_id, self::META_KEY_TITLE, true ),
			'meta_description'         => (string) get_post_meta( $post_id, self::META_KEY_DESCRIPTION, true ),
			'keywords'                 => (string) get_post_meta( $post_id, self::META_KEY_KEYWORDS, true ),
			'primary_keyword'          => (string) get_post_meta( $post_id, self::META_KEY_PRIMARY_KW, true ),
			'twitter_card_title'       => (string) get_post_meta( $post_id, self::META_KEY_TWITTER_CARD_TITLE, true ),
			'twitter_card_description' => (string) get_post_meta( $post_id, self::META_KEY_TWITTER_CARD_DESCRIPTION, true ),
		];
	}

	/**
	 * Output Open Graph and Twitter Card meta in wp_head for single posts.
	 * Ensures X shows a proper media card when the blog URL is shared.
	 */
	public static function wp_head_twitter_card() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}
		$seo = self::get_seo_meta( $post_id );
		$title = $seo['twitter_card_title'] ?: $seo['meta_title'] ?: wp_get_document_title();
		$desc  = $seo['twitter_card_description'] ?: $seo['meta_description'] ?: wp_trim_words( get_the_excerpt(), 25 );
		$url   = get_permalink();
		$image = get_the_post_thumbnail_url( $post_id, 'large' );
		if ( ! $title || ! $url ) {
			return;
		}
		echo '<meta property="og:type" content="article">' . "\n";
		echo '<meta property="og:url" content="' . esc_attr( $url ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_attr( $image ) . '">' . "\n";
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		echo '<meta name="twitter:site" content="@alexevans_io">' . "\n";
		echo '<meta name="twitter:url" content="' . esc_attr( $url ) . '">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
		if ( $image ) {
			echo '<meta name="twitter:image" content="' . esc_attr( $image ) . '">' . "\n";
		}
	}
}
