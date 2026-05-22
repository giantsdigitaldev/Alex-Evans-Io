<?php
/**
 * AE SEO Content Writer — WP-Cron: run next topic from queue on schedule.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AE_SEO_Content_Writer_Cron {

	const HOOK     = 'ae_seo_writer_cron_run';
	const OPTION   = 'ae_seo_writer_cron';
	const INTERVAL = 'ae_seo_writer_twicedaily';

	public static function register() {
		add_filter( 'cron_schedules', [ __CLASS__, 'add_schedules' ] );
		add_action( self::HOOK, [ __CLASS__, 'run_next' ] );
	}

	public const FREQ_DAILY_830 = 'ae_seo_writer_daily_830';

	public static function add_schedules( $schedules ) {
		if ( ! isset( $schedules['ae_seo_writer_daily_830'] ) ) {
			$schedules['ae_seo_writer_daily_830'] = [
				'interval' => DAY_IN_SECONDS,
				'display'  => __( 'Daily at 8:30 am', 'ae-seo-content-writer' ),
			];
		}
		if ( ! isset( $schedules['ae_seo_writer_twicedaily'] ) ) {
			$schedules['ae_seo_writer_twicedaily'] = [
				'interval' => 12 * HOUR_IN_SECONDS,
				'display'  => __( 'Twice daily', 'ae-seo-content-writer' ),
			];
		}
		if ( ! isset( $schedules['ae_seo_writer_weekly'] ) ) {
			$schedules['ae_seo_writer_weekly'] = [
				'interval' => 7 * DAY_IN_SECONDS,
				'display'  => __( 'Once weekly', 'ae-seo-content-writer' ),
			];
		}
		return $schedules;
	}

	public static function get_options() {
		$opts = get_option( self::OPTION, [] );
		return is_array( $opts ) ? $opts : [];
	}

	public static function is_enabled() {
		$opts = self::get_options();
		return ! empty( $opts['enabled'] );
	}

	public static function get_frequency() {
		$opts = self::get_options();
		$f = isset( $opts['frequency'] ) ? $opts['frequency'] : self::FREQ_DAILY_830;
		$allowed = [ 'daily', 'twicedaily', 'ae_seo_writer_twicedaily', 'ae_seo_writer_daily_830', 'weekly', 'ae_seo_writer_weekly' ];
		if ( ! in_array( $f, $allowed, true ) ) {
			return self::FREQ_DAILY_830;
		}
		return $f;
	}

	public static function set_enabled( $enabled, $frequency = null ) {
		$opts = self::get_options();
		$opts['enabled'] = (bool) $enabled;
		if ( $frequency !== null ) {
			$opts['frequency'] = $frequency;
		}
		update_option( self::OPTION, $opts );
		self::reschedule();
	}

	public static function set_frequency( $frequency ) {
		$opts = self::get_options();
		$opts['frequency'] = $frequency;
		update_option( self::OPTION, $opts );
		self::reschedule();
	}

	/** Next 8:30 am in site timezone (Unix timestamp). */
	public static function get_next_830_timestamp() {
		$tz = wp_timezone();
		$now = new DateTime( 'now', $tz );
		$today_830 = new DateTime( $now->format( 'Y-m-d' ) . ' 08:30:00', $tz );
		if ( $now < $today_830 ) {
			return $today_830->getTimestamp();
		}
		$tomorrow = (clone $today_830 )->modify( '+1 day' );
		return $tomorrow->getTimestamp();
	}

	/** Unschedule all, then schedule next if enabled. Runs one topic from queue per occurrence. */
	public static function reschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		if ( ! self::is_enabled() ) {
			return;
		}
		$f = self::get_frequency();
		$first_run = time();
		if ( $f === self::FREQ_DAILY_830 ) {
			$first_run = self::get_next_830_timestamp();
		}
		wp_schedule_event( $first_run, $f, self::HOOK );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/** Next scheduled time (Unix timestamp or null). */
	public static function get_next_run() {
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			return null;
		}
		foreach ( $crons as $ts => $hooks ) {
			if ( $ts < time() ) {
				continue;
			}
			if ( isset( $hooks[ self::HOOK ] ) ) {
				return $ts;
			}
		}
		return null;
	}

	/** Cron callback: run one topic from queue via runner. */
	public static function run_next() {
		$row = AE_SEO_Content_Writer_Queue::get_pending();
		if ( ! $row ) {
			return;
		}
		AE_SEO_Content_Writer_Queue::set_status( $row['id'], AE_SEO_Content_Writer_Queue::STATUS_RUNNING );
		$opts = AE_SEO_Content_Writer_Settings::get_options();
		$payload = [
			'topic'                   => $row['topic'],
			'anthropic_api_key'       => $opts['anthropic_api_key'] ?? '',
			'gemini_api_key'          => $opts['gemini_api_key'] ?? '',
			'dataforseo_login'        => $opts['dataforseo_login'] ?? '',
			'dataforseo_password'     => $opts['dataforseo_password'] ?? '',
			'wordpress_url'           => home_url( '/' ),
			'wordpress_username'      => $opts['wordpress_username'] ?? '',
			'wordpress_app_password'  => $opts['wordpress_app_password'] ?? '',
		];
		$runner_url = AE_SEO_Content_Writer_Settings::get_runner_url();
		if ( ! $runner_url ) {
			AE_SEO_Content_Writer_Queue::set_status( $row['id'], AE_SEO_Content_Writer_Queue::STATUS_FAILED, null, __( 'Runner URL not set.', 'ae-seo-content-writer' ) );
			return;
		}
		$res = wp_remote_post(
			$runner_url . '/api/run',
			[
				'timeout' => 45,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( $payload ),
			]
		);
		if ( is_wp_error( $res ) ) {
			$res = wp_remote_post(
				$runner_url . '/api/run',
				[
					'timeout' => 45,
					'headers' => [ 'Content-Type' => 'application/json' ],
					'body'    => wp_json_encode( $payload ),
				]
			);
		}
		if ( is_wp_error( $res ) ) {
			AE_SEO_Content_Writer_Queue::set_status( $row['id'], AE_SEO_Content_Writer_Queue::STATUS_FAILED, null, $res->get_error_message() );
			return;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 || ! is_array( $body ) || empty( $body['run_id'] ) ) {
			$err = is_array( $body ) && isset( $body['detail'] ) ? $body['detail'] : wp_remote_retrieve_body( $res );
			AE_SEO_Content_Writer_Queue::set_status( $row['id'], AE_SEO_Content_Writer_Queue::STATUS_FAILED, null, is_string( $err ) ? $err : __( 'Runner returned an error.', 'ae-seo-content-writer' ) );
			return;
		}
		AE_SEO_Content_Writer_Queue::set_status( $row['id'], AE_SEO_Content_Writer_Queue::STATUS_DONE, $body['run_id'] );
	}
}
