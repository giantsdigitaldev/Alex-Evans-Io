<?php
/**
 * AE SEO Content Writer — Settings (API keys, Runner URL).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AE_SEO_Content_Writer_Settings {

	const OPTION_GROUP = 'ae_seo_writer_settings';
	const OPTION_NAME  = 'ae_seo_writer_options';

	public static function register() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ], 20 );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_settings_script' ], 10, 1 );
	}

	public static function enqueue_settings_script( $hook ) {
		if ( $hook !== 'settings_page_ae-seo-writer-settings' ) {
			return;
		}
		wp_enqueue_style(
			'ae-seo-writer-admin',
			AE_SEO_WRITER_PLUGIN_URL . 'admin/css/admin.css',
			[],
			AE_SEO_WRITER_VERSION
		);
		wp_enqueue_script( 'wp-api-fetch' );
	}

	public static function add_menu() {
		add_options_page(
			__( 'AE SEO Content Writer', 'ae-seo-content-writer' ),
			__( 'AE SEO Writer', 'ae-seo-content-writer' ),
			'manage_options',
			'ae-seo-writer-settings',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function register_settings() {
		register_setting( self::OPTION_GROUP, self::OPTION_NAME, [
			'type'              => 'array',
			'sanitize_callback' => [ __CLASS__, 'sanitize' ],
		] );

		$section = 'ae_seo_writer_main';
		add_settings_section(
			$section,
			__( 'API Keys & Runner', 'ae-seo-content-writer' ),
			function () {
				echo '<p>' . esc_html__( 'Configure the content pipeline runner and API keys. The runner is a small HTTP service (Python) that performs research, writing, image generation, and draft creation. Store keys here to trigger runs from the plugin dashboard.', 'ae-seo-content-writer' ) . '</p>';
			},
			'ae-seo-writer-settings'
		);

		$fields = [
			'runner_url' => [
				'title' => __( 'Runner API URL', 'ae-seo-content-writer' ),
				'type'  => 'url',
				'desc'  => __( 'e.g. http://127.0.0.1:8765 (must be reachable from this server)', 'ae-seo-content-writer' ),
			],
			'anthropic_api_key' => [
				'title' => __( 'Anthropic API Key (Claude)', 'ae-seo-content-writer' ),
				'type'  => 'password',
				'desc'  => __( 'For article generation', 'ae-seo-content-writer' ),
			],
			'openai_api_key' => [
				'title' => __( 'OpenAI API Key (Categories + Images)', 'ae-seo-content-writer' ),
				'type'  => 'password',
				'desc'  => __( 'Category selection (GPT-4o mini) and hero images (DALL-E 3, 16:9 photorealistic). Set this for cheaper, natural-looking featured images. Leave blank to use Gemini for images only.', 'ae-seo-content-writer' ),
			],
			'gemini_api_key' => [
				'title' => __( 'Gemini API Key (Images fallback)', 'ae-seo-content-writer' ),
				'type'  => 'password',
				'desc'  => __( 'Hero image generation when OpenAI key is not set. Generated images are resized to under 200 KB for featured images.', 'ae-seo-content-writer' ),
			],
			'gemini_image_model' => [
				'title' => __( 'Gemini image model (optional)', 'ae-seo-content-writer' ),
				'type'  => 'text',
				'desc'  => __( 'Only used when Gemini is used for images (no OpenAI key). Leave blank for default. Images are auto-compressed before upload.', 'ae-seo-content-writer' ),
			],
			'dataforseo_login' => [
				'title' => __( 'DataForSEO Login', 'ae-seo-content-writer' ),
				'type'  => 'text',
				'desc'  => __( 'Optional: keyword/SERP research', 'ae-seo-content-writer' ),
			],
			'dataforseo_password' => [
				'title' => __( 'DataForSEO Password', 'ae-seo-content-writer' ),
				'type'  => 'password',
				'desc'  => '',
			],
			'wordpress_username' => [
				'title' => __( 'WordPress Username (for runner)', 'ae-seo-content-writer' ),
				'type'  => 'text',
				'desc'  => __( 'Login of the user whose Application Password is used. Required for Cron runs.', 'ae-seo-content-writer' ),
			],
			'wordpress_app_password' => [
				'title' => __( 'WordPress Application Password', 'ae-seo-content-writer' ),
				'type'  => 'password',
				'desc'  => __( 'Create under Users → Profile → Application Passwords. Used by the runner to create drafts.', 'ae-seo-content-writer' ),
			],
			'runner_start_command' => [
				'title' => __( 'Runner start command (optional)', 'ae-seo-content-writer' ),
				'type'  => 'text',
				'desc'  => __( 'Script or command to start the runner from the app (e.g. /path/to/automation/start_runner.sh). Leave empty to start the runner manually or via systemd. Requires PHP exec() allowed on the server.', 'ae-seo-content-writer' ),
			],
			'x_api_key' => [
				'title' => __( 'X (Twitter) API Key', 'ae-seo-content-writer' ),
				'type'  => 'password',
				'desc'  => __( 'Consumer Key from X Developer Portal (Keys and tokens). Required for "Post to X" and auto-posting 4 daily tweets per article.', 'ae-seo-content-writer' ),
			],
			'x_api_secret' => [
				'title' => __( 'X (Twitter) API Secret', 'ae-seo-content-writer' ),
				'type'  => 'password',
				'desc'  => __( 'Consumer Secret from X Developer Portal.', 'ae-seo-content-writer' ),
			],
			'x_access_token' => [
				'title' => __( 'X (Twitter) Access Token', 'ae-seo-content-writer' ),
				'type'  => 'password',
				'desc'  => __( 'OAuth 1.0a Access Token for @alexevans_io (from Keys and tokens → Access Token and Secret).', 'ae-seo-content-writer' ),
			],
			'x_access_token_secret' => [
				'title' => __( 'X (Twitter) Access Token Secret', 'ae-seo-content-writer' ),
				'type'  => 'password',
				'desc'  => __( 'Access Token Secret from X Developer Portal.', 'ae-seo-content-writer' ),
			],
		];

		foreach ( $fields as $key => $config ) {
			add_settings_field(
				'ae_seo_writer_' . $key,
				$config['title'],
				function () use ( $key, $config ) {
					$opts = self::get_options();
					$val  = isset( $opts[ $key ] ) ? $opts[ $key ] : '';
					$type = $config['type'];
					$id   = 'ae_seo_writer_' . $key;
					if ( $type === 'password' || $type === 'url' ) {
						echo '<input type="' . ( $type === 'url' ? 'url' : 'password' ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::OPTION_NAME . '[' . $key . ']' ) . '" value="' . esc_attr( $val ) . '" class="regular-text" autocomplete="off" />';
					} else {
						echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::OPTION_NAME . '[' . $key . ']' ) . '" value="' . esc_attr( $val ) . '" class="regular-text" />';
					}
					if ( ! empty( $config['desc'] ) ) {
						echo '<p class="description">' . esc_html( $config['desc'] ) . '</p>';
					}
				},
				'ae-seo-writer-settings',
				$section
			);
		}
	}

	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return get_option( self::OPTION_NAME, [] );
		}
		$allowed = [ 'runner_url', 'anthropic_api_key', 'openai_api_key', 'gemini_api_key', 'gemini_image_model', 'dataforseo_login', 'dataforseo_password', 'wordpress_username', 'wordpress_app_password', 'runner_start_command', 'x_api_key', 'x_api_secret', 'x_access_token', 'x_access_token_secret' ];
		$current = self::get_options();
		foreach ( $allowed as $k ) {
			if ( ! isset( $input[ $k ] ) || ! is_string( $input[ $k ] ) ) {
				continue;
			}
			// Don't overwrite password fields with empty (user may not re-enter when editing other settings).
			if ( in_array( $k, [ 'wordpress_app_password', 'dataforseo_password', 'x_api_key', 'x_api_secret', 'x_access_token', 'x_access_token_secret' ], true ) && trim( $input[ $k ] ) === '' ) {
				continue;
			}
			if ( $k === 'runner_start_command' ) {
				$current[ $k ] = substr( preg_replace( '/[\r\n]/', '', trim( $input[ $k ] ) ), 0, 512 );
			} else {
				$current[ $k ] = sanitize_text_field( $input[ $k ] );
			}
		}
		return $current;
	}

	public static function get_options() {
		$saved = get_option( self::OPTION_NAME, [] );
		return is_array( $saved ) ? $saved : [];
	}

	public static function get_runner_url() {
		$opts = self::get_options();
		$url  = isset( $opts['runner_url'] ) ? trim( $opts['runner_url'] ) : '';
		return rtrim( $url, '/' );
	}

	public static function get_default_runner_url() {
		return defined( 'AE_SEO_WRITER_DEFAULT_RUNNER_URL' ) ? AE_SEO_WRITER_DEFAULT_RUNNER_URL : 'http://127.0.0.1:8765';
	}

	/** Optional command to start the runner from the app (script path or shell command). Empty if not set. */
	public static function get_runner_start_command() {
		$opts = self::get_options();
		return isset( $opts['runner_start_command'] ) ? trim( (string) $opts['runner_start_command'] ) : '';
	}

	/** Set runner URL to default and save. Returns true. */
	public static function set_default_runner_url() {
		$opts = self::get_options();
		$opts['runner_url'] = self::get_default_runner_url();
		update_option( self::OPTION_NAME, $opts );
		return true;
	}

	/** Default runner start command when automation/ is sibling to WordPress root (e.g. /var/www/alexevans/automation/start_runner.sh). */
	public static function get_default_runner_start_command() {
		if ( defined( 'AE_SEO_WRITER_RUNNER_START_CMD' ) && is_string( AE_SEO_WRITER_RUNNER_START_CMD ) ) {
			return trim( AE_SEO_WRITER_RUNNER_START_CMD );
		}
		return rtrim( dirname( ABSPATH ), '/\\' ) . '/automation/start_runner.sh';
	}

	/** Set runner start command to default path and save. Returns true. */
	public static function set_default_runner_start_command() {
		$opts = self::get_options();
		$opts['runner_start_command'] = self::get_default_runner_start_command();
		update_option( self::OPTION_NAME, $opts );
		return true;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$runner_url = self::get_runner_url();
		$default_url = self::get_default_runner_url();
		?>
		<div class="wrap ae-seo-writer-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div class="ae-seo-writer-settings-actions ae-seo-writer-settings-card">
				<p><strong><?php esc_html_e( 'Quick actions', 'ae-seo-content-writer' ); ?></strong></p>
				<p>
					<button type="button" class="button" id="ae-seo-writer-set-default-runner"><?php echo esc_html( sprintf( __( 'Set default Runner URL (%s)', 'ae-seo-content-writer' ), $default_url ) ); ?></button>
					<button type="button" class="button" id="ae-seo-writer-set-default-start-cmd"><?php echo esc_html( sprintf( __( 'Set default runner start command (%s)', 'ae-seo-content-writer' ), esc_html( AE_SEO_Content_Writer_Settings::get_default_runner_start_command() ) ) ); ?></button>
					<button type="button" class="button" id="ae-seo-writer-start-runner"><?php esc_html_e( 'Start runner', 'ae-seo-content-writer' ); ?></button>
					<button type="button" class="button" id="ae-seo-writer-test-runner"><?php esc_html_e( 'Test connection to runner', 'ae-seo-content-writer' ); ?></button>
					<span id="ae-seo-writer-test-result" style="margin-left:8px;"></span>
					<span id="ae-seo-writer-start-result" class="ae-seo-writer-runner-test-result" style="margin-left:8px;"></span>
				</p>
			</div>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( 'ae-seo-writer-settings' );
				submit_button( __( 'Save Settings', 'ae-seo-content-writer' ) );
				?>
			</form>
			<p><?php esc_html_e( 'API keys are stored securely in the WordPress database (wp_options) and are only sent to the runner when you start a new pipeline run. You do not need to put them in a .env file.', 'ae-seo-content-writer' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ae-seo-writer' ) ); ?>"><?php esc_html_e( 'Go to Dashboard', 'ae-seo-content-writer' ); ?></a></p>
		</div>
		<script>
		(function() {
			var restNamespace = '<?php echo esc_js( AE_SEO_Content_Writer_REST::NAMESPACE ); ?>';
			function restPath(p) { return restNamespace + (p ? '/' + (p.indexOf('/') === 0 ? p.slice(1) : p) : ''); }
			document.getElementById('ae-seo-writer-set-default-runner')?.addEventListener('click', function() {
				wp.apiFetch({ path: restPath('set-default-runner'), method: 'POST' }).then(function() {
					location.reload();
				}).catch(function(e) { alert(e.message || 'Error'); });
			});
			document.getElementById('ae-seo-writer-set-default-start-cmd')?.addEventListener('click', function() {
				wp.apiFetch({ path: restPath('set-default-runner-start-command'), method: 'POST' }).then(function(r) {
					location.reload();
				}).catch(function(e) { alert(e.message || 'Error'); });
			});
			document.getElementById('ae-seo-writer-start-runner')?.addEventListener('click', function() {
				var el = document.getElementById('ae-seo-writer-start-result');
				el.textContent = '<?php echo esc_js( __( 'Starting…', 'ae-seo-content-writer' ) ); ?>';
				el.className = 'ae-seo-writer-runner-test-result';
				wp.apiFetch({ path: restPath('start-runner'), method: 'POST' }).then(function(r) {
					el.textContent = (r.message || '<?php echo esc_js( __( 'Command sent.', 'ae-seo-content-writer' ) ); ?>');
					el.className = 'ae-seo-writer-runner-test-result ae-seo-writer-test-ok';
				}).catch(function(e) {
					el.textContent = (e.message || '<?php echo esc_js( __( 'Failed', 'ae-seo-content-writer' ) ); ?>');
					el.className = 'ae-seo-writer-runner-test-result ae-seo-writer-test-fail';
				});
			});
			document.getElementById('ae-seo-writer-test-runner')?.addEventListener('click', function() {
				var el = document.getElementById('ae-seo-writer-test-result');
				el.textContent = '<?php echo esc_js( __( 'Testing…', 'ae-seo-content-writer' ) ); ?>';
				el.className = '';
				wp.apiFetch({ path: restPath('test-runner') }).then(function() {
					el.textContent = '<?php echo esc_js( __( 'Connection OK', 'ae-seo-content-writer' ) ); ?>';
					el.className = 'ae-seo-writer-test-ok';
				}).catch(function(e) {
					el.textContent = (e.message || '<?php echo esc_js( __( 'Failed', 'ae-seo-content-writer' ) ); ?>');
					el.className = 'ae-seo-writer-test-fail';
				});
			});
		})();
		</script>
		<?php
	}
}
