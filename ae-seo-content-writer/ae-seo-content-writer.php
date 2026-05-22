<?php
/**
 * Plugin Name: AE SEO Content Writer
 * Plugin URI: https://alexevans.io/blog
 * Description: Full-featured SEO blog pipeline: content research, creation, image generation (Gemini), refinements, and human review. Dashboard and settings for API keys; full visibility into every stage before publishing.
 * Version: 1.0.0
 * Author: Alex Evans
 * Author URI: https://alexevans.io
 * Text Domain: ae-seo-content-writer
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AE_SEO_WRITER_VERSION', '1.0.0' );
define( 'AE_SEO_WRITER_DB_VERSION', 2 );
define( 'AE_SEO_WRITER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AE_SEO_WRITER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AE_SEO_WRITER_DEFAULT_RUNNER_URL', 'http://127.0.0.1:8765' );

require_once AE_SEO_WRITER_PLUGIN_DIR . 'includes/class-ae-seo-settings.php';
require_once AE_SEO_WRITER_PLUGIN_DIR . 'includes/class-ae-seo-queue.php';
require_once AE_SEO_WRITER_PLUGIN_DIR . 'includes/class-ae-seo-cron.php';
require_once AE_SEO_WRITER_PLUGIN_DIR . 'includes/class-ae-seo-post-meta.php';
require_once AE_SEO_WRITER_PLUGIN_DIR . 'includes/class-ae-seo-x-poster.php';
require_once AE_SEO_WRITER_PLUGIN_DIR . 'includes/class-ae-seo-rest.php';
require_once AE_SEO_WRITER_PLUGIN_DIR . 'includes/class-ae-seo-dashboard.php';

/**
 * Register admin menu and assets.
 */
function ae_seo_writer_init() {
	AE_SEO_Content_Writer_Post_Meta::register();
	AE_SEO_Content_Writer_REST::register();
	AE_SEO_Content_Writer_Cron::register();
	ae_seo_writer_register_x_cron();
	// Ensure queue table exists on every request (REST requests are not is_admin(), so table may never have been created).
	AE_SEO_Content_Writer_Queue::create_table();
	if ( is_admin() && (int) get_option( 'ae_seo_writer_db_version', 0 ) < AE_SEO_WRITER_DB_VERSION ) {
		update_option( 'ae_seo_writer_db_version', AE_SEO_WRITER_DB_VERSION );
	}
	// Front-end: output Twitter Card / Open Graph meta for single posts so X shows correct media card.
	if ( ! is_admin() ) {
		add_action( 'wp_head', [ 'AE_SEO_Content_Writer_Post_Meta', 'wp_head_twitter_card' ], 5 );
		return;
	}
	AE_SEO_Content_Writer_Settings::register();
	AE_SEO_Content_Writer_Dashboard::register();
}
add_action( 'init', 'ae_seo_writer_init' );

/**
 * On activation: set default Runner URL, create queue table, register cron.
 */
function ae_seo_writer_activate() {
	$opts = get_option( 'ae_seo_writer_options', [] );
	if ( ! is_array( $opts ) ) {
		$opts = [];
	}
	if ( empty( $opts['runner_url'] ) ) {
		$opts['runner_url'] = AE_SEO_WRITER_DEFAULT_RUNNER_URL;
		update_option( 'ae_seo_writer_options', $opts );
	}
	AE_SEO_Content_Writer_Queue::create_table();
	AE_SEO_Content_Writer_Cron::register();
	AE_SEO_Content_Writer_Cron::reschedule();
}
register_activation_hook( __FILE__, 'ae_seo_writer_activate' );

/**
 * On deactivation: clear scheduled cron.
 */
function ae_seo_writer_deactivate() {
	AE_SEO_Content_Writer_Cron::unschedule();
}
register_deactivation_hook( __FILE__, 'ae_seo_writer_deactivate' );

/**
 * Add settings link on plugins list.
 */
function ae_seo_writer_plugin_links( $links, $file ) {
	if ( plugin_basename( __FILE__ ) !== $file ) {
		return $links;
	}
	$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=ae-seo-writer-settings' ) ) . '">' . __( 'Settings', 'ae-seo-content-writer' ) . '</a>';
	$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=ae-seo-writer' ) ) . '">' . __( 'Dashboard', 'ae-seo-content-writer' ) . '</a>';
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ae_seo_writer_plugin_links', 10, 2 );

/**
 * When a post is published, ping search engines so they recrawl the sitemap and discover the new content sooner.
 */
function ae_seo_writer_ping_search_engines_on_publish( $new_status, $old_status, $post ) {
	if ( $new_status !== 'publish' || $old_status === 'publish' || $post->post_type !== 'post' ) {
		return;
	}
	$sitemap_url = home_url( '/wp-sitemap.xml' );
	$sitemap_encoded = rawurlencode( $sitemap_url );
	$ping_urls = [
		'https://www.google.com/ping?sitemap=' . $sitemap_encoded,
		'https://www.bing.com/ping?sitemap=' . $sitemap_encoded,
	];
	foreach ( $ping_urls as $url ) {
		wp_remote_get( $url, [ 'blocking' => false, 'timeout' => 5 ] );
	}
}
add_action( 'transition_post_status', 'ae_seo_writer_ping_search_engines_on_publish', 10, 3 );

/**
 * Register cron for daily X (Twitter) posts. Runs once per day to post scheduled tweets.
 */
function ae_seo_writer_register_x_cron() {
	add_action( AE_SEO_Content_Writer_X_Poster::CRON_HOOK, [ 'AE_SEO_Content_Writer_X_Poster', 'cron_post_due' ] );
	if ( ! wp_next_scheduled( AE_SEO_Content_Writer_X_Poster::CRON_HOOK ) ) {
		$tz = wp_timezone();
		$next = new DateTime( 'today 08:30', $tz );
		if ( $next->getTimestamp() <= time() ) {
			$next->modify( '+1 day' );
		}
		wp_schedule_event( $next->getTimestamp(), 'daily', AE_SEO_Content_Writer_X_Poster::CRON_HOOK );
	}
}
