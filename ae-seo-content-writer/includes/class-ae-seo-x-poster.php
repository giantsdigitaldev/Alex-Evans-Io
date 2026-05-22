<?php
/**
 * AE SEO Content Writer — Post to X (Twitter) via API v2 with OAuth 1.0a.
 * Used for manual "Post to X" and for scheduled daily X posts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AE_SEO_Content_Writer_X_Poster {

	const API_URL = 'https://api.twitter.com/2/tweets';
	const OPTION_SCHEDULE = 'ae_seo_writer_x_schedule';
	const CRON_HOOK = 'ae_seo_writer_x_daily_post';

	/**
	 * Post one tweet to X (Twitter) using OAuth 1.0a.
	 * Tweet text: max 280 chars (X counts a URL as 23 chars, so message + " " + url ≤ 280).
	 *
	 * @param string $text Tweet text (do not include URL; caller appends blog URL).
	 * @param string $blog_url Optional. If provided, appended to text (space + url). Must be ≤280 total.
	 * @return array{ok: bool, tweet_id?: string, error?: string}
	 */
	public static function post_tweet( $text, $blog_url = '' ) {
		$opts = AE_SEO_Content_Writer_Settings::get_options();
		$api_key    = isset( $opts['x_api_key'] ) ? trim( (string) $opts['x_api_key'] ) : '';
		$api_secret = isset( $opts['x_api_secret'] ) ? trim( (string) $opts['x_api_secret'] ) : '';
		$token      = isset( $opts['x_access_token'] ) ? trim( (string) $opts['x_access_token'] ) : '';
		$token_sec  = isset( $opts['x_access_token_secret'] ) ? trim( (string) $opts['x_access_token_secret'] ) : '';

		if ( $api_key === '' || $api_secret === '' || $token === '' || $token_sec === '' ) {
			return [ 'ok' => false, 'error' => __( 'X API credentials not configured. Add them in Settings → AE SEO Writer.', 'ae-seo-content-writer' ) ];
		}

		$message = $text;
		if ( $blog_url !== '' ) {
			$url = preg_replace( '/^https?:\/\//', '', $blog_url );
			$url = 'https://' . $url;
			$message = trim( $text ) . ' ' . $url;
		}
		$message = mb_substr( $message, 0, 280 );

		$body = wp_json_encode( [ 'text' => $message ] );
		$auth = self::oauth1_authorization_header( 'POST', self::API_URL, $body, $api_key, $api_secret, $token, $token_sec );
		if ( is_wp_error( $auth ) ) {
			return [ 'ok' => false, 'error' => $auth->get_error_message() ];
		}

		$res = wp_remote_post(
			self::API_URL,
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => $auth,
				],
				'body' => $body,
			]
		);

		if ( is_wp_error( $res ) ) {
			return [ 'ok' => false, 'error' => $res->get_error_message() ];
		}
		$code = wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );

		if ( $code >= 200 && $code < 300 && ! empty( $data['data']['id'] ) ) {
			return [ 'ok' => true, 'tweet_id' => $data['data']['id'] ];
		}
		$err = isset( $data['errors'][0]['message'] ) ? $data['errors'][0]['message'] : ( isset( $data['detail'] ) ? $data['detail'] : $raw );
		return [ 'ok' => false, 'error' => is_string( $err ) ? $err : __( 'X API error.', 'ae-seo-content-writer' ) ];
	}

	/**
	 * Build OAuth 1.0a Authorization header for Twitter API v2.
	 *
	 * @param string $method GET or POST
	 * @param string $url Full request URL
	 * @param string $body Request body (for POST JSON)
	 * @param string $consumer_key API Key
	 * @param string $consumer_secret API Secret
	 * @param string $token Access Token
	 * @param string $token_secret Access Token Secret
	 * @return string|WP_Error Authorization header value or error
	 */
	private static function oauth1_authorization_header( $method, $url, $body, $consumer_key, $consumer_secret, $token, $token_secret ) {
		$params = [
			'oauth_consumer_key'     => $consumer_key,
			'oauth_nonce'            => wp_generate_password( 32, false ),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => (string) time(),
			'oauth_token'            => $token,
			'oauth_version'          => '1.0',
		];
		$base = $method . '&' . rawurlencode( $url ) . '&' . rawurlencode( self::oauth1_parameter_string( $params ) );
		$key  = rawurlencode( $consumer_secret ) . '&' . rawurlencode( $token_secret );
		$sig  = base64_encode( hash_hmac( 'sha1', $base, $key, true ) );
		$params['oauth_signature'] = $sig;
		$parts = [];
		foreach ( $params as $k => $v ) {
			$parts[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
		}
		return 'OAuth ' . implode( ', ', $parts );
	}

	private static function oauth1_parameter_string( $params ) {
		ksort( $params );
		$p = [];
		foreach ( $params as $k => $v ) {
			$p[] = rawurlencode( $k ) . '=' . rawurlencode( $v );
		}
		return implode( '&', $p );
	}

	/**
	 * Get scheduled X posts (run_id => [ day_index => scheduled_timestamp, ... ], next_run_id, next_day ).
	 *
	 * @return array
	 */
	public static function get_schedule() {
		$raw = get_option( self::OPTION_SCHEDULE, [] );
		return is_array( $raw ) ? $raw : [];
	}

	/**
	 * Schedule 4 daily X posts for a run. Overwrites any existing schedule for this run_id.
	 *
	 * @param string $run_id Run ID
	 * @param array  $x_posts Array of 4 tweet texts
	 * @param string $blog_url Blog URL to append to each tweet
	 * @return bool
	 */
	public static function schedule_four_daily_posts( $run_id, $x_posts, $blog_url ) {
		if ( ! is_array( $x_posts ) || count( $x_posts ) < 4 ) {
			return false;
		}
		$schedule = self::get_schedule();
		$tz = wp_timezone();
		$start = new DateTime( 'today', $tz );
		$start->setTime( 8, 30, 0 );
		if ( $start->getTimestamp() <= time() ) {
			$start->modify( '+1 day' );
		}
		$schedule[ $run_id ] = [
			'blog_url' => $blog_url,
			'posts'    => array_slice( array_map( 'strval', $x_posts ), 0, 4 ),
			'day_0_at' => $start->getTimestamp(),
			'day_1_at' => ( clone $start )->modify( '+1 day' )->getTimestamp(),
			'day_2_at' => ( clone $start )->modify( '+2 days' )->getTimestamp(),
			'day_3_at' => ( clone $start )->modify( '+3 days' )->getTimestamp(),
			'posted'   => [], // 0,1,2,3 when posted
		];
		update_option( self::OPTION_SCHEDULE, $schedule );
		return true;
	}

	/**
	 * Mark a day as posted for a run.
	 *
	 * @param string $run_id Run ID
	 * @param int    $day_index 0–3
	 */
	public static function mark_posted( $run_id, $day_index ) {
		$schedule = self::get_schedule();
		if ( ! isset( $schedule[ $run_id ] ) || ! is_array( $schedule[ $run_id ]['posted'] ) ) {
			return;
		}
		$schedule[ $run_id ]['posted'] = array_unique( array_merge( $schedule[ $run_id ]['posted'], [ $day_index ] ) );
		sort( $schedule[ $run_id ]['posted'] );
		update_option( self::OPTION_SCHEDULE, $schedule );
	}

	/**
	 * Cron: post one scheduled X post per run that is due today.
	 */
	public static function cron_post_due() {
		$schedule = self::get_schedule();
		$now = time();
		$tz = wp_timezone();
		$today_start = ( new DateTime( 'today', $tz ) )->getTimestamp();
		$today_end   = $today_start + DAY_IN_SECONDS;

		foreach ( $schedule as $run_id => $item ) {
			if ( ! is_array( $item ) || empty( $item['posts'] ) || empty( $item['blog_url'] ) ) {
				continue;
			}
			$posted = isset( $item['posted'] ) && is_array( $item['posted'] ) ? $item['posted'] : [];
			for ( $d = 0; $d < 4; $d++ ) {
				if ( in_array( $d, $posted, true ) ) {
					continue;
				}
				$key = 'day_' . $d . '_at';
				if ( ! isset( $item[ $key ] ) ) {
					continue;
				}
				$at = (int) $item[ $key ];
				if ( $at >= $today_start && $at < $today_end && $at <= $now + 300 ) {
					$text = isset( $item['posts'][ $d ] ) ? $item['posts'][ $d ] : $item['posts'][0];
					$result = self::post_tweet( $text, $item['blog_url'] );
					if ( ! empty( $result['ok'] ) ) {
						self::mark_posted( $run_id, $d );
					}
					break; // one tweet per run per day
				}
			}
		}
	}

	/**
	 * Unschedule a run's X posts.
	 *
	 * @param string $run_id Run ID
	 */
	public static function unschedule_run( $run_id ) {
		$schedule = self::get_schedule();
		unset( $schedule[ $run_id ] );
		update_option( self::OPTION_SCHEDULE, $schedule );
	}
}
