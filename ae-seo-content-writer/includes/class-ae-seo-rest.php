<?php
/**
 * AE SEO Content Writer — REST proxy to runner API (avoids CORS, sends API keys server-side).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AE_SEO_Content_Writer_REST {

	const NAMESPACE = 'ae-seo-writer/v1';

	public static function register() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	private static function runner_url( $path = '' ) {
		$base = AE_SEO_Content_Writer_Settings::get_runner_url();
		if ( ! $base ) {
			return '';
		}
		$base = rtrim( $base, '/' );
		// Use 127.0.0.1 instead of localhost so PHP/cURL connects via IPv4 and avoids "Failed to connect" when the runner listens on 0.0.0.0.
		if ( preg_match( '#^https?://localhost(:\d+)?(/?.*)$#i', $base, $m ) ) {
			$base = 'http://127.0.0.1' . ( isset( $m[1] ) ? $m[1] : ':8765' ) . ( isset( $m[2] ) ? $m[2] : '' );
		}
		$path = ltrim( $path, '/' );
		return $path !== '' ? ( $base . '/' . $path ) : $base;
	}

	/** Build payload for runner POST /api/run (topic + API keys from settings). */
	private static function run_payload( $topic ) {
		$opts = AE_SEO_Content_Writer_Settings::get_options();
		$payload = [
			'topic'                   => $topic,
			'anthropic_api_key'       => $opts['anthropic_api_key'] ?? '',
			'openai_api_key'          => $opts['openai_api_key'] ?? '',
			'gemini_api_key'          => $opts['gemini_api_key'] ?? '',
			'dataforseo_login'        => $opts['dataforseo_login'] ?? '',
			'dataforseo_password'     => $opts['dataforseo_password'] ?? '',
			'wordpress_url'            => home_url( '/' ),
			'wordpress_username'      => ( $opts['wordpress_username'] ?? '' ) ?: ( wp_get_current_user()->user_login ?? '' ),
			'wordpress_app_password'  => $opts['wordpress_app_password'] ?? '',
		];
		if ( ! empty( trim( (string) ( $opts['gemini_image_model'] ?? '' ) ) ) ) {
			$payload['gemini_image_model'] = sanitize_text_field( $opts['gemini_image_model'] );
		}
		return $payload;
	}

	/** Build payload for runner POST regenerate-image (prompt + API keys + WordPress creds for upload). */
	private static function regenerate_image_payload( $body, $opts ) {
		$payload = [
			'prompt'                => isset( $body['prompt'] ) ? sanitize_textarea_field( $body['prompt'] ) : null,
			'wordpress_url'         => home_url( '/' ),
			'wordpress_username'    => ( ! empty( $opts['wordpress_username'] ) ) ? $opts['wordpress_username'] : ( wp_get_current_user()->user_login ?? '' ),
			'wordpress_app_password' => $opts['wordpress_app_password'] ?? '',
		];
		if ( ! empty( $opts['openai_api_key'] ) ) {
			$payload['openai_api_key'] = $opts['openai_api_key'];
		}
		if ( ! empty( $opts['gemini_api_key'] ) ) {
			$payload['gemini_api_key'] = $opts['gemini_api_key'];
		}
		if ( ! empty( trim( (string) ( $opts['gemini_image_model'] ?? '' ) ) ) ) {
			$payload['gemini_image_model'] = sanitize_text_field( $opts['gemini_image_model'] );
		}
		return $payload;
	}

	/** Build payload for runner POST upload-image-to-media (WordPress creds only). */
	private static function upload_image_to_media_payload( $opts ) {
		return [
			'wordpress_url'          => home_url( '/' ),
			'wordpress_username'     => ( ! empty( $opts['wordpress_username'] ) ) ? $opts['wordpress_username'] : ( wp_get_current_user()->user_login ?? '' ),
			'wordpress_app_password' => $opts['wordpress_app_password'] ?? '',
		];
	}

	private static function request( $method, $path, $body = null ) {
		$url = self::runner_url( $path );
		if ( ! $url ) {
			return new WP_Error( 'no_runner', __( 'Runner URL not configured.', 'ae-seo-content-writer' ), [ 'status' => 502 ] );
		}
		$args = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => [ 'Content-Type' => 'application/json' ],
		];
		if ( $body !== null ) {
			$args['body'] = wp_json_encode( $body );
		}
		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );
		if ( $code >= 400 ) {
			return new WP_Error( 'runner_error', $data['detail'] ?? $raw, [ 'status' => $code ] );
		}
		return $data;
	}

	public static function register_routes() {
		$permission_edit = function () {
			return current_user_can( 'edit_posts' );
		};
		$permission_manage = function () {
			return current_user_can( 'manage_options' );
		};

		register_rest_route( self::NAMESPACE, '/health', [
			'methods'             => 'GET',
			'callback'            => function () {
				$runner_ok = false;
				if ( self::runner_url() ) {
					$r = self::request( 'GET', '/api/runs' );
					$runner_ok = ! is_wp_error( $r );
				}
				$queue_ok = false;
				global $wpdb;
				$wpdb->last_error = '';
				$table = AE_SEO_Content_Writer_Queue::table_name();
				$wpdb->get_var( "SELECT 1 FROM $table LIMIT 1" );
				$queue_ok = ( $wpdb->last_error === '' );
				$next = AE_SEO_Content_Writer_Cron::get_next_run();
				return rest_ensure_response( [
					'runner_ok'  => $runner_ok,
					'queue_ok'   => $queue_ok,
					'next_cron'  => $next,
					'schedule_enabled' => AE_SEO_Content_Writer_Cron::is_enabled(),
				] );
			},
			'permission_callback' => $permission_edit,
		] );

		register_rest_route( self::NAMESPACE, '/set-default-runner', [
			'methods'             => 'POST',
			'callback'            => function () {
				AE_SEO_Content_Writer_Settings::set_default_runner_url();
				return rest_ensure_response( [ 'ok' => true ] );
			},
			'permission_callback' => $permission_manage,
		] );

		register_rest_route( self::NAMESPACE, '/set-default-runner-start-command', [
			'methods'             => 'POST',
			'callback'            => function () {
				AE_SEO_Content_Writer_Settings::set_default_runner_start_command();
				return rest_ensure_response( [
					'ok'      => true,
					'message' => __( 'Runner start command set. You can now use Start runner.', 'ae-seo-content-writer' ),
				] );
			},
			'permission_callback' => $permission_manage,
		] );

		register_rest_route( self::NAMESPACE, '/test-runner', [
			'methods'             => 'GET',
			'callback'            => function () {
				$r = self::request( 'GET', '/api/runs' );
				return is_wp_error( $r ) ? $r : rest_ensure_response( [ 'ok' => true ] );
			},
			'permission_callback' => $permission_edit,
		] );

		register_rest_route( self::NAMESPACE, '/restart-runner', [
			'methods'             => 'POST',
			'callback'            => function () {
				$r = self::request( 'POST', '/api/restart' );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				return rest_ensure_response( [ 'ok' => true, 'message' => __( 'Restart requested. Runner will exit and, if managed by systemd/supervisor, start again in a few seconds.', 'ae-seo-content-writer' ) ] );
			},
			'permission_callback' => $permission_manage,
		] );

		register_rest_route( self::NAMESPACE, '/start-runner', [
			'methods'             => 'POST',
			'callback'            => function () {
				$cmd = AE_SEO_Content_Writer_Settings::get_runner_start_command();
				if ( $cmd === '' ) {
					return new WP_Error( 'no_command', __( 'Runner start command is not configured. Add a script path (e.g. /path/to/automation/start_runner.sh) in Settings → AE SEO Writer, or start the runner manually.', 'ae-seo-content-writer' ), [ 'status' => 400 ] );
				}
				if ( ! function_exists( 'exec' ) || in_array( 'exec', array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ), true ) ) {
					return new WP_Error( 'exec_disabled', __( 'This server does not allow starting the runner from PHP. Start the runner manually (e.g. in a terminal) or run it as a systemd service. See RUNNER-LIFECYCLE.md in the plugin docs.', 'ae-seo-content-writer' ), [ 'status' => 503 ] );
				}
				$cmd = $cmd . ' > /dev/null 2>&1 &';
				@exec( $cmd );
				return rest_ensure_response( [
					'started' => true,
					'message' => __( 'Runner start command sent. Wait a few seconds, then use Test connection.', 'ae-seo-content-writer' ),
				] );
			},
			'permission_callback' => $permission_manage,
		] );

		register_rest_route( self::NAMESPACE, '/runs', [
			'methods'             => 'GET',
			'callback'            => function () {
				$r = self::request( 'GET', '/api/runs' );
				return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
			},
			'permission_callback' => $permission_edit,
		] );

		// Sub-routes under /runs/{id}/ must be registered before the generic /runs/{id} so they match first.
		$run_id_regex = '(?P<run_id>[a-zA-Z0-9\-_.]+)';

		register_rest_route( self::NAMESPACE, '/runs/' . $run_id_regex . '/draft', [
			'methods'             => 'PUT',
			'callback'            => function ( $req ) {
				$body = $req->get_json_params();
				$r    = self::request( 'PUT', '/api/runs/' . $req['run_id'] . '/draft', [ 'draft_md' => $body['draft_md'] ?? '' ] );
				return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
			},
			'permission_callback' => $permission_edit,
			'args'                => [ 'run_id' => [ 'required' => true ] ],
		] );

		register_rest_route( self::NAMESPACE, '/runs/' . $run_id_regex . '/send-to-wordpress', [
			'methods'             => 'POST',
			'callback'            => function ( $req ) {
				$body = $req->get_json_params() ?: [];
				$opts = AE_SEO_Content_Writer_Settings::get_options();
				if ( ! empty( $opts['openai_api_key'] ) ) {
					$body['openai_api_key'] = $opts['openai_api_key'];
				}
				$r    = self::request( 'POST', '/api/runs/' . $req['run_id'] . '/send-to-wordpress', $body );
				return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
			},
			'permission_callback' => $permission_edit,
			'args'                => [ 'run_id' => [ 'required' => true ] ],
		] );

		register_rest_route( self::NAMESPACE, '/runs/' . $run_id_regex . '/regenerate-image', [
			'methods'             => 'POST',
			'callback'            => function ( $req ) {
				$body = $req->get_json_params() ?: [];
				$opts = AE_SEO_Content_Writer_Settings::get_options();
				$payload = self::regenerate_image_payload( $body, $opts );
				$r = self::request( 'POST', '/api/runs/' . $req['run_id'] . '/regenerate-image', $payload );
				return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
			},
			'permission_callback' => $permission_edit,
			'args'                => [ 'run_id' => [ 'required' => true ] ],
		] );

		register_rest_route( self::NAMESPACE, '/runs/' . $run_id_regex . '/upload-image-to-media', [
			'methods'             => 'POST',
			'callback'            => function ( $req ) {
				$opts = AE_SEO_Content_Writer_Settings::get_options();
				$payload = self::upload_image_to_media_payload( $opts );
				$r = self::request( 'POST', '/api/runs/' . $req['run_id'] . '/upload-image-to-media', $payload );
				return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
			},
			'permission_callback' => $permission_edit,
			'args'                => [ 'run_id' => [ 'required' => true ] ],
		] );

		// Post one of the 4 X posts to Twitter/X now.
		register_rest_route( self::NAMESPACE, '/runs/' . $run_id_regex . '/post-to-x', [
			'methods'             => 'POST',
			'callback'            => function ( $req ) {
				$body = $req->get_json_params() ?: [];
				$post_index = isset( $body['post_index'] ) ? (int) $body['post_index'] : 0;
				$run = self::request( 'GET', '/api/runs/' . $req['run_id'] );
				if ( is_wp_error( $run ) ) {
					return $run;
				}
				$x_posts = isset( $run['x_posts'] ) && is_array( $run['x_posts'] ) ? $run['x_posts'] : [];
				$blog_url = isset( $run['blog_url'] ) ? trim( (string) $run['blog_url'] ) : '';
				if ( empty( $x_posts ) ) {
					$fallback = isset( $run['twitter_post'] ) ? trim( (string) $run['twitter_post'] ) : '';
					if ( $fallback !== '' ) {
						$x_posts = [ $fallback ];
					}
				}
				if ( $post_index < 0 || $post_index >= count( $x_posts ) ) {
					$post_index = 0;
				}
				$text = $x_posts[ $post_index ];
				$result = AE_SEO_Content_Writer_X_Poster::post_tweet( $text, $blog_url );
				return rest_ensure_response( $result );
			},
			'permission_callback' => $permission_edit,
			'args'                => [ 'run_id' => [ 'required' => true ] ],
		] );

		// Schedule 4 daily X posts for this run (one per day for 4 days).
		register_rest_route( self::NAMESPACE, '/runs/' . $run_id_regex . '/schedule-x-posts', [
			'methods'             => 'POST',
			'callback'            => function ( $req ) {
				$run = self::request( 'GET', '/api/runs/' . $req['run_id'] );
				if ( is_wp_error( $run ) ) {
					return $run;
				}
				$x_posts = isset( $run['x_posts'] ) && is_array( $run['x_posts'] ) ? $run['x_posts'] : [];
				$blog_url = isset( $run['blog_url'] ) ? trim( (string) $run['blog_url'] ) : '';
				if ( count( $x_posts ) < 4 ) {
					$fallback = isset( $run['twitter_post'] ) ? trim( (string) $run['twitter_post'] ) : '';
					while ( count( $x_posts ) < 4 && $fallback !== '' ) {
						$x_posts[] = $fallback;
					}
				}
				if ( count( $x_posts ) < 4 || $blog_url === '' ) {
					return new WP_Error( 'invalid', __( 'Run must have 4 X posts and a blog URL.', 'ae-seo-content-writer' ), [ 'status' => 400 ] );
				}
				AE_SEO_Content_Writer_X_Poster::schedule_four_daily_posts( $req['run_id'], $x_posts, $blog_url );
				$schedule = AE_SEO_Content_Writer_X_Poster::get_schedule();
				$item = isset( $schedule[ $req['run_id'] ] ) ? $schedule[ $req['run_id'] ] : null;
				return rest_ensure_response( [ 'ok' => true, 'scheduled' => true, 'schedule' => $item ] );
			},
			'permission_callback' => $permission_edit,
			'args'                => [ 'run_id' => [ 'required' => true ] ],
		] );

		// Get X post schedule for a run.
		register_rest_route( self::NAMESPACE, '/runs/' . $run_id_regex . '/x-schedule', [
			'methods'             => 'GET',
			'callback'            => function ( $req ) {
				$schedule = AE_SEO_Content_Writer_X_Poster::get_schedule();
				$item = isset( $schedule[ $req['run_id'] ] ) ? $schedule[ $req['run_id'] ] : null;
				return rest_ensure_response( [ 'schedule' => $item ] );
			},
			'permission_callback' => $permission_edit,
			'args'                => [ 'run_id' => [ 'required' => true ] ],
		] );

		register_rest_route( self::NAMESPACE, '/runs/' . $run_id_regex, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( $req ) {
					$r = self::request( 'GET', '/api/runs/' . $req['run_id'] );
					return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
				},
				'permission_callback' => $permission_edit,
				'args'                => [ 'run_id' => [ 'required' => true ] ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => function ( $req ) {
					$r = self::request( 'DELETE', '/api/runs/' . $req['run_id'] );
					if ( is_wp_error( $r ) ) {
						return $r;
					}
					return rest_ensure_response( $r );
				},
				'permission_callback' => $permission_edit,
				'args'                => [ 'run_id' => [ 'required' => true ] ],
			],
		] );

		// Regenerate image: run_id in body (avoids URL path matching issues that cause 404).
		register_rest_route( self::NAMESPACE, '/regenerate-image', [
			'methods'             => 'POST',
			'callback'            => function ( $req ) {
				$body = $req->get_json_params() ?: [];
				$run_id = isset( $body['run_id'] ) ? sanitize_text_field( $body['run_id'] ) : '';
				if ( $run_id === '' ) {
					return new WP_Error( 'invalid', __( 'run_id is required.', 'ae-seo-content-writer' ), [ 'status' => 400 ] );
				}
				$opts = AE_SEO_Content_Writer_Settings::get_options();
				$payload = self::regenerate_image_payload( $body, $opts );
				$r = self::request( 'POST', '/api/runs/' . $run_id . '/regenerate-image', $payload );
				if ( is_wp_error( $r ) ) {
					$code = $r->get_error_data();
					$status = is_array( $code ) && isset( $code['status'] ) ? $code['status'] : 502;
					return new WP_Error( $r->get_error_code(), $r->get_error_message(), [ 'status' => $status ] );
				}
				return rest_ensure_response( $r );
			},
			'permission_callback' => $permission_edit,
			'args'                => [],
		] );

		register_rest_route( self::NAMESPACE, '/run', [
			'methods'             => 'POST',
			'callback'            => function ( $req ) {
				$body = $req->get_json_params() ?: [];
				$topic = isset( $body['topic'] ) ? sanitize_text_field( $body['topic'] ) : '';
				if ( $topic === '' ) {
					return new WP_Error( 'invalid', __( 'Topic is required.', 'ae-seo-content-writer' ), [ 'status' => 400 ] );
				}
				$payload = self::run_payload( $topic );
				$r = self::request( 'POST', '/api/run', $payload );
				return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
			},
			'permission_callback' => $permission_edit,
		] );

		// Topic queue
		register_rest_route( self::NAMESPACE, '/queue', [
			'methods'             => 'GET',
			'callback'            => function () {
				$items = AE_SEO_Content_Writer_Queue::get_all( 'ASC' );
				return rest_ensure_response( $items );
			},
			'permission_callback' => $permission_edit,
		] );

		register_rest_route( self::NAMESPACE, '/queue', [
			'methods'             => 'POST',
			'callback'            => function ( $req ) {
				$body = $req->get_json_params() ?: [];
				$topic = isset( $body['topic'] ) ? sanitize_text_field( $body['topic'] ) : '';
				if ( $topic === '' ) {
					return new WP_Error( 'invalid', __( 'Topic is required.', 'ae-seo-content-writer' ), [ 'status' => 400 ] );
				}
				$id = AE_SEO_Content_Writer_Queue::add( $topic );
				if ( ! $id ) {
					return new WP_Error( 'db_error', __( 'Could not add topic.', 'ae-seo-content-writer' ), [ 'status' => 500 ] );
				}
				$row = AE_SEO_Content_Writer_Queue::get_by_id( $id );
				return rest_ensure_response( [ 'id' => $id, 'item' => $row ] );
			},
			'permission_callback' => $permission_edit,
		] );

		register_rest_route( self::NAMESPACE, '/queue/bulk', [
			'methods'             => 'POST',
			'callback'            => function ( $req ) {
				$body = $req->get_json_params() ?: [];
				$topics = isset( $body['topics'] ) ? $body['topics'] : '';
				if ( is_string( $topics ) ) {
					$topics = preg_split( '/\r\n|\r|\n/', $topics, -1, PREG_SPLIT_NO_EMPTY );
				}
				if ( ! is_array( $topics ) ) {
					return new WP_Error( 'invalid', __( 'Topics must be string or array.', 'ae-seo-content-writer' ), [ 'status' => 400 ] );
				}
				$n = AE_SEO_Content_Writer_Queue::add_bulk( $topics );
				return rest_ensure_response( [ 'added' => $n ] );
			},
			'permission_callback' => $permission_edit,
		] );

		register_rest_route( self::NAMESPACE, '/queue/run-next', [
			'methods'             => 'POST',
			'callback'            => function () {
				$row = AE_SEO_Content_Writer_Queue::get_pending();
				if ( ! $row ) {
					return rest_ensure_response( [ 'ok' => false, 'message' => __( 'No pending topics in queue.', 'ae-seo-content-writer' ) ] );
				}
				AE_SEO_Content_Writer_Queue::set_status( $row['id'], AE_SEO_Content_Writer_Queue::STATUS_RUNNING );
				$payload = self::run_payload( $row['topic'] );
				$r = self::request( 'POST', '/api/run', $payload );
				if ( is_wp_error( $r ) ) {
					$r = self::request( 'POST', '/api/run', $payload );
					if ( is_wp_error( $r ) ) {
						AE_SEO_Content_Writer_Queue::set_status( $row['id'], AE_SEO_Content_Writer_Queue::STATUS_FAILED, null, $r->get_error_message() );
						return $r;
					}
				}
				$run_id = isset( $r['run_id'] ) ? $r['run_id'] : null;
				AE_SEO_Content_Writer_Queue::set_status( $row['id'], AE_SEO_Content_Writer_Queue::STATUS_DONE, $run_id );
				return rest_ensure_response( [
					'ok'      => true,
					'run_id'  => $run_id,
					'topic'   => $row['topic'],
					'item_id' => (int) $row['id'],
				] );
			},
			'permission_callback' => $permission_edit,
		] );

		register_rest_route( self::NAMESPACE, '/queue/(?P<id>\d+)', [
			'methods'             => 'DELETE',
			'callback'            => function ( $req ) {
				$id = (int) $req['id'];
				$row = AE_SEO_Content_Writer_Queue::get_by_id( $id );
				if ( ! $row ) {
					return new WP_Error( 'not_found', __( 'Queue item not found.', 'ae-seo-content-writer' ), [ 'status' => 404 ] );
				}
				AE_SEO_Content_Writer_Queue::delete_by_id( $id );
				return rest_ensure_response( [ 'ok' => true ] );
			},
			'permission_callback' => $permission_edit,
			'args'                => [ 'id' => [ 'required' => true, 'type' => 'integer' ] ],
		] );

		// Re-run a queue item: same topic, new run (unique content).
		register_rest_route( self::NAMESPACE, '/queue/(?P<id>\d+)/rerun', [
			'methods'             => 'POST',
			'callback'            => function ( $req ) {
				$id  = (int) $req['id'];
				$row = AE_SEO_Content_Writer_Queue::get_by_id( $id );
				if ( ! $row ) {
					return new WP_Error( 'not_found', __( 'Queue item not found.', 'ae-seo-content-writer' ), [ 'status' => 404 ] );
				}
				$topic = isset( $row['topic'] ) ? trim( (string) $row['topic'] ) : '';
				if ( $topic === '' ) {
					return new WP_Error( 'invalid', __( 'Queue item has no topic.', 'ae-seo-content-writer' ), [ 'status' => 400 ] );
				}
				AE_SEO_Content_Writer_Queue::set_status( $id, AE_SEO_Content_Writer_Queue::STATUS_RUNNING );
				$payload = self::run_payload( $topic );
				$r       = self::request( 'POST', '/api/run', $payload );
				if ( is_wp_error( $r ) ) {
					$r = self::request( 'POST', '/api/run', $payload );
					if ( is_wp_error( $r ) ) {
						AE_SEO_Content_Writer_Queue::set_status( $id, AE_SEO_Content_Writer_Queue::STATUS_FAILED, null, $r->get_error_message() );
						return $r;
					}
				}
				$run_id = isset( $r['run_id'] ) ? $r['run_id'] : null;
				AE_SEO_Content_Writer_Queue::set_status( $id, AE_SEO_Content_Writer_Queue::STATUS_DONE, $run_id );
				return rest_ensure_response( [
					'ok'      => true,
					'run_id'  => $run_id,
					'topic'   => $topic,
					'item_id' => (int) $id,
				] );
			},
			'permission_callback' => $permission_edit,
			'args'                => [ 'id' => [ 'required' => true, 'type' => 'integer' ] ],
		] );

		// Schedule (Cron)
		register_rest_route( self::NAMESPACE, '/schedule', [
			'methods'             => 'GET',
			'callback'            => function () {
				$next = AE_SEO_Content_Writer_Cron::get_next_run();
				return rest_ensure_response( [
					'enabled'   => AE_SEO_Content_Writer_Cron::is_enabled(),
					'frequency' => AE_SEO_Content_Writer_Cron::get_frequency(),
					'next_run'  => $next,
					'next_run_formatted' => $next ? wp_date( 'Y-m-d H:i:s', $next ) : null,
				] );
			},
			'permission_callback' => $permission_edit,
		] );

		register_rest_route( self::NAMESPACE, '/schedule', [
			'methods'             => 'POST',
			'callback'            => function ( $req ) {
				$body = $req->get_json_params() ?: [];
				$enabled = isset( $body['enabled'] ) ? (bool) $body['enabled'] : null;
				$frequency = isset( $body['frequency'] ) ? sanitize_text_field( $body['frequency'] ) : null;
				if ( $enabled !== null ) {
					AE_SEO_Content_Writer_Cron::set_enabled( $enabled, $frequency );
				} elseif ( $frequency !== null ) {
					AE_SEO_Content_Writer_Cron::set_frequency( $frequency );
					AE_SEO_Content_Writer_Cron::reschedule();
				}
				$next = AE_SEO_Content_Writer_Cron::get_next_run();
				return rest_ensure_response( [
					'ok'        => true,
					'enabled'   => AE_SEO_Content_Writer_Cron::is_enabled(),
					'frequency' => AE_SEO_Content_Writer_Cron::get_frequency(),
					'next_run'  => $next,
					'next_run_formatted' => $next ? wp_date( 'Y-m-d H:i:s', $next ) : null,
				] );
			},
			'permission_callback' => $permission_manage,
		] );

		// Proxy image from runner so browser can load it (runner may be localhost).
		// No auth required: run_id is unguessable (timestamp + random), so exposure risk is minimal.
		register_rest_route( self::NAMESPACE, '/runs/' . $run_id_regex . '/image', [
			'methods'             => 'GET',
			'callback'            => function ( $req ) {
				$url = self::runner_url( '/api/runs/' . $req['run_id'] . '/image' );
				if ( ! $url ) {
					return new WP_Error( 'no_runner', '', [ 'status' => 502 ] );
				}
				$res = wp_remote_get( $url, [ 'timeout' => 15 ] );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$code = wp_remote_retrieve_response_code( $res );
				if ( $code >= 400 ) {
					return new WP_Error( 'runner_error', '', [ 'status' => $code ] );
				}
				$body = wp_remote_retrieve_body( $res );
				$type = wp_remote_retrieve_header( $res, 'content-type' ) ?: 'image/png';
				return new WP_REST_Response( $body, 200, [ 'Content-Type' => $type ] );
			},
			'permission_callback' => '__return_true',
			'args'                => [ 'run_id' => [ 'required' => true ] ],
		] );
	}
}
