<?php
/**
 * AE SEO Content Writer — Dashboard UI: runs list, run detail (stages, research, brief, image, draft editor), New Run.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AE_SEO_Content_Writer_Dashboard {

	public static function register() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ], 10, 1 );
		AE_SEO_Content_Writer_REST::register();
	}

	public static function add_menu() {
		$cap = 'edit_posts';
		add_menu_page(
			__( 'AE SEO Content Writer', 'ae-seo-content-writer' ),
			__( 'AE SEO Writer', 'ae-seo-content-writer' ),
			$cap,
			'ae-seo-writer',
			[ __CLASS__, 'render_dashboard' ],
			'dashicons-edit-large',
			30
		);
		add_submenu_page(
			'ae-seo-writer',
			__( 'Dashboard', 'ae-seo-content-writer' ),
			__( 'Dashboard', 'ae-seo-content-writer' ),
			$cap,
			'ae-seo-writer',
			[ __CLASS__, 'render_dashboard' ]
		);
		add_submenu_page(
			'ae-seo-writer',
			__( 'Topic queue', 'ae-seo-content-writer' ),
			__( 'Topic queue', 'ae-seo-content-writer' ),
			$cap,
			'ae-seo-writer-queue',
			[ __CLASS__, 'render_queue' ]
		);
		add_submenu_page(
			'ae-seo-writer',
			__( 'New Run', 'ae-seo-content-writer' ),
			__( 'New Run', 'ae-seo-content-writer' ),
			$cap,
			'ae-seo-writer-new',
			[ __CLASS__, 'render_new_run' ]
		);
		add_submenu_page(
			'ae-seo-writer',
			__( 'Schedule', 'ae-seo-content-writer' ),
			__( 'Schedule', 'ae-seo-content-writer' ),
			$cap,
			'ae-seo-writer-schedule',
			[ __CLASS__, 'render_schedule' ]
		);
		add_submenu_page(
			'ae-seo-writer',
			__( 'Settings', 'ae-seo-content-writer' ),
			__( 'Settings', 'ae-seo-content-writer' ),
			'manage_options',
			'ae-seo-writer-settings',
			[ 'AE_SEO_Content_Writer_Settings', 'render_page' ]
		);
	}

	public static function enqueue( $hook ) {
		$pages = [ 'toplevel_page_ae-seo-writer', 'ae-seo-writer_page_ae-seo-writer-new', 'ae-seo-writer_page_ae-seo-writer-queue', 'ae-seo-writer_page_ae-seo-writer-schedule' ];
		if ( ! in_array( $hook, $pages, true ) ) {
			return;
		}
		$rest_url = rest_url( AE_SEO_Content_Writer_REST::NAMESPACE );
		$nonce    = wp_create_nonce( 'wp_rest' );
		wp_enqueue_style(
			'ae-seo-writer-admin',
			AE_SEO_WRITER_PLUGIN_URL . 'admin/css/admin.css',
			[],
			AE_SEO_WRITER_VERSION
		);
		wp_enqueue_script(
			'ae-seo-writer-dashboard',
			AE_SEO_WRITER_PLUGIN_URL . 'admin/js/dashboard.js',
			[ 'wp-api-fetch' ],
			AE_SEO_WRITER_VERSION,
			true
		);
		$api_base = AE_SEO_Content_Writer_REST::NAMESPACE;
		wp_localize_script( 'ae-seo-writer-dashboard', 'aeSeoWriter', [
			'restUrl'     => $rest_url,
			'apiBase'     => $api_base,
			'nonce'       => $nonce,
			'wpEditUrl'   => admin_url( 'post.php?post=%d&action=edit' ),
			'homeUrl'     => home_url( '/' ),
			'newRunUrl'   => admin_url( 'admin.php?page=ae-seo-writer-new' ),
			'dashboardUrl'=> admin_url( 'admin.php?page=ae-seo-writer' ),
		] );
		if ( $hook === 'ae-seo-writer_page_ae-seo-writer-queue' ) {
			wp_enqueue_script(
				'ae-seo-writer-queue',
				AE_SEO_WRITER_PLUGIN_URL . 'admin/js/queue.js',
				[ 'wp-api-fetch' ],
				AE_SEO_WRITER_VERSION,
				true
			);
			wp_localize_script( 'ae-seo-writer-queue', 'aeSeoWriter', [
				'restUrl' => $rest_url,
				'apiBase' => $api_base,
				'dashboardUrl' => admin_url( 'admin.php?page=ae-seo-writer' ),
			] );
		}
		if ( $hook === 'ae-seo-writer_page_ae-seo-writer-schedule' ) {
			wp_enqueue_script(
				'ae-seo-writer-schedule',
				AE_SEO_WRITER_PLUGIN_URL . 'admin/js/schedule.js',
				[ 'wp-api-fetch' ],
				AE_SEO_WRITER_VERSION,
				true
			);
			wp_localize_script( 'ae-seo-writer-schedule', 'aeSeoWriter', [
				'restUrl' => $rest_url,
				'apiBase' => $api_base,
			] );
		}
	}

	public static function render_dashboard() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		// Auto-set default runner URL once if never set (e.g. plugin was installed before activation hook existed).
		$runner_url = AE_SEO_Content_Writer_Settings::get_runner_url();
		if ( ! $runner_url && current_user_can( 'manage_options' ) ) {
			AE_SEO_Content_Writer_Settings::set_default_runner_url();
			$runner_url = AE_SEO_Content_Writer_Settings::get_runner_url();
		}
		$default_url  = AE_SEO_Content_Writer_Settings::get_default_runner_url();
		?>
		<div class="wrap ae-seo-writer-wrap">
			<h1><?php esc_html_e( 'AE SEO Content Writer — Dashboard', 'ae-seo-content-writer' ); ?></h1>
			<div class="ae-seo-writer-runner-bar">
				<p style="margin-bottom:0.5em;">
					<strong><?php esc_html_e( 'Runner', 'ae-seo-content-writer' ); ?></strong>
					<?php if ( $runner_url ) : ?>
						<code><?php echo esc_html( $runner_url ); ?></code>
					<?php else : ?>
						<span class="ae-seo-writer-muted"><?php esc_html_e( 'Not set', 'ae-seo-content-writer' ); ?></span>
					<?php endif; ?>
				</p>
				<p>
					<button type="button" class="button" id="ae-seo-writer-btn-set-default-runner"><?php echo esc_html( sprintf( __( 'Set default Runner URL (%s)', 'ae-seo-content-writer' ), $default_url ) ); ?></button>
					<button type="button" class="button" id="ae-seo-writer-btn-set-default-start-cmd"><?php esc_html_e( 'Set default runner start command', 'ae-seo-content-writer' ); ?></button>
					<button type="button" class="button" id="ae-seo-writer-btn-start-runner"><?php esc_html_e( 'Start runner', 'ae-seo-content-writer' ); ?></button>
					<button type="button" class="button" id="ae-seo-writer-btn-restart-runner"><?php esc_html_e( 'Restart runner', 'ae-seo-content-writer' ); ?></button>
					<button type="button" class="button" id="ae-seo-writer-btn-test-runner"><?php esc_html_e( 'Test connection', 'ae-seo-content-writer' ); ?></button>
					<span id="ae-seo-writer-runner-test-result" class="ae-seo-writer-runner-test-result"></span>
					<span id="ae-seo-writer-start-runner-result" class="ae-seo-writer-runner-test-result"></span>
					<span id="ae-seo-writer-restart-runner-result" class="ae-seo-writer-runner-test-result"></span>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ae-seo-writer-settings' ) ); ?>" class="button"><?php esc_html_e( 'Settings (API keys)', 'ae-seo-content-writer' ); ?></a>
				</p>
			</div>
			<?php if ( ! $runner_url ) : ?>
				<div class="ae-seo-writer-notice notice notice-warning">
					<p><?php esc_html_e( 'Runner URL is not set. Click "Set default Runner URL" above to use localhost:8765, or open Settings to enter a custom URL. Then start the runner (e.g. uvicorn dashboard_app:app --host 0.0.0.0 --port 8765).', 'ae-seo-content-writer' ); ?></p>
				</div>
			<?php endif; ?>
			<div id="ae-seo-writer-health" class="ae-seo-writer-health-bar" aria-live="polite"></div>
			<div id="ae-seo-writer-root"></div>
		</div>
		<script>
		(function() {
			var restNamespace = '<?php echo esc_js( AE_SEO_Content_Writer_REST::NAMESPACE ); ?>';
			function restPath(p) { var s = (p.indexOf('/') === 0 ? p.slice(1) : p); return restNamespace + (s ? '/' + s : ''); }
			document.getElementById('ae-seo-writer-btn-set-default-runner')?.addEventListener('click', function() {
				wp.apiFetch({ path: restPath('set-default-runner'), method: 'POST' }).then(function() {
					location.reload();
				}).catch(function(e) { alert(e.message || 'Error'); });
			});
			document.getElementById('ae-seo-writer-btn-set-default-start-cmd')?.addEventListener('click', function() {
				wp.apiFetch({ path: restPath('set-default-runner-start-command'), method: 'POST' }).then(function() {
					location.reload();
				}).catch(function(e) { alert(e.message || 'Error'); });
			});
			document.getElementById('ae-seo-writer-btn-start-runner')?.addEventListener('click', function() {
				var el = document.getElementById('ae-seo-writer-start-runner-result');
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
			document.getElementById('ae-seo-writer-btn-test-runner')?.addEventListener('click', function() {
				var el = document.getElementById('ae-seo-writer-runner-test-result');
				el.textContent = '<?php echo esc_js( __( 'Testing…', 'ae-seo-content-writer' ) ); ?>';
				el.className = 'ae-seo-writer-runner-test-result';
				wp.apiFetch({ path: restPath('test-runner') }).then(function() {
					el.textContent = '<?php echo esc_js( __( 'Connection OK', 'ae-seo-content-writer' ) ); ?>';
					el.className = 'ae-seo-writer-runner-test-result ae-seo-writer-test-ok';
				}).catch(function(e) {
					el.textContent = (e.message || '<?php echo esc_js( __( 'Failed', 'ae-seo-content-writer' ) ); ?>');
					el.className = 'ae-seo-writer-runner-test-result ae-seo-writer-test-fail';
				});
			});
			document.getElementById('ae-seo-writer-btn-restart-runner')?.addEventListener('click', function() {
				var btn = document.getElementById('ae-seo-writer-btn-restart-runner');
				var el = document.getElementById('ae-seo-writer-restart-runner-result');
				if (!confirm('<?php echo esc_js( __( 'Restart the runner? It will exit and (if managed by systemd/supervisor) start again. Any run in progress will stop.', 'ae-seo-content-writer' ) ); ?>')) {
					return;
				}
				btn.disabled = true;
				el.textContent = '<?php echo esc_js( __( 'Restarting…', 'ae-seo-content-writer' ) ); ?>';
				el.className = 'ae-seo-writer-runner-test-result';
				wp.apiFetch({ path: restPath('restart-runner'), method: 'POST' }).then(function(r) {
					el.textContent = (r.message || '<?php echo esc_js( __( 'Restart requested. Wait a few seconds, then Test connection.', 'ae-seo-content-writer' ) ); ?>');
					el.className = 'ae-seo-writer-runner-test-result ae-seo-writer-test-ok';
				}).catch(function(e) {
					el.textContent = (e.message || '<?php echo esc_js( __( 'Failed', 'ae-seo-content-writer' ) ); ?>');
					el.className = 'ae-seo-writer-runner-test-result ae-seo-writer-test-fail';
				}).finally(function() {
					btn.disabled = false;
				});
			});
		})();
		</script>
		<?php
	}

	public static function render_new_run() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$runner_url = AE_SEO_Content_Writer_Settings::get_runner_url();
		?>
		<div class="wrap ae-seo-writer-wrap">
			<h1><?php esc_html_e( 'New SEO Content Run', 'ae-seo-content-writer' ); ?></h1>
			<?php if ( ! $runner_url ) : ?>
				<div class="ae-seo-writer-notice notice notice-warning">
					<p><?php esc_html_e( 'Runner URL is not set.', 'ae-seo-content-writer' ); ?>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ae-seo-writer-settings' ) ); ?>"><?php esc_html_e( 'Open Settings', 'ae-seo-content-writer' ); ?></a></p>
				</div>
			<?php else : ?>
				<form id="ae-seo-writer-new-run-form" class="ae-seo-writer-form">
					<p>
						<label for="ae-seo-writer-topic"><?php esc_html_e( 'Topic / title for the article', 'ae-seo-content-writer' ); ?></label><br>
						<input type="text" id="ae-seo-writer-topic" name="topic" class="large-text" placeholder="<?php esc_attr_e( 'e.g. AI web design trends 2026', 'ae-seo-content-writer' ); ?>" required>
					</p>
					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Start pipeline run', 'ae-seo-content-writer' ); ?></button>
						<span id="ae-seo-writer-new-run-status"></span>
					</p>
				</form>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ae-seo-writer' ) ); ?>"><?php esc_html_e( 'Back to Dashboard', 'ae-seo-content-writer' ); ?></a></p>
			<?php endif; ?>
		</div>
		<script>
		document.getElementById('ae-seo-writer-new-run-form')?.addEventListener('submit', function(e) {
			e.preventDefault();
			var topic = document.getElementById('ae-seo-writer-topic').value.trim();
			var statusEl = document.getElementById('ae-seo-writer-new-run-status');
			if (!topic) return;
			statusEl.textContent = '<?php echo esc_js( __( 'Starting…', 'ae-seo-content-writer' ) ); ?>';
			wp.apiFetch({
				path: '/<?php echo esc_js( AE_SEO_Content_Writer_REST::NAMESPACE ); ?>/run',
				method: 'POST',
				body: JSON.stringify({ topic: topic }),
				headers: { 'Content-Type': 'application/json' }
			}).then(function(r) {
				statusEl.textContent = '<?php echo esc_js( __( 'Run started.', 'ae-seo-content-writer' ) ); ?>';
				window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=ae-seo-writer&run_id=' ) ); ?>' + r.run_id;
			}).catch(function(err) {
				statusEl.textContent = (err.message || '<?php echo esc_js( __( 'Error', 'ae-seo-content-writer' ) ); ?>');
			});
		});
		</script>
		<?php
	}

	public static function render_queue() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$dashboard_url = admin_url( 'admin.php?page=ae-seo-writer' );
		?>
		<div class="wrap ae-seo-writer-wrap ae-seo-writer-page-queue">
			<h1 class="ae-seo-writer-page-title"><?php esc_html_e( 'Topic queue', 'ae-seo-content-writer' ); ?></h1>
			<p class="ae-seo-writer-description"><?php esc_html_e( 'Add topics for the pipeline. Run the next pending topic manually or let Schedule run it automatically.', 'ae-seo-content-writer' ); ?></p>
			<div class="ae-seo-writer-queue-actions">
				<div class="ae-seo-writer-form-row">
					<input type="text" id="ae-seo-writer-queue-topic" class="ae-seo-writer-input" placeholder="<?php esc_attr_e( 'Enter a topic…', 'ae-seo-content-writer' ); ?>">
					<button type="button" class="button button-primary" id="ae-seo-writer-queue-add"><?php esc_html_e( 'Add topic', 'ae-seo-content-writer' ); ?></button>
				</div>
				<div class="ae-seo-writer-form-row">
					<textarea id="ae-seo-writer-queue-bulk" class="ae-seo-writer-textarea" rows="4" placeholder="<?php esc_attr_e( 'Paste one topic per line for bulk add…', 'ae-seo-content-writer' ); ?>"></textarea>
					<button type="button" class="button" id="ae-seo-writer-queue-bulk-add"><?php esc_html_e( 'Bulk add', 'ae-seo-content-writer' ); ?></button>
				</div>
				<button type="button" class="button button-primary" id="ae-seo-writer-queue-run-next"><?php esc_html_e( 'Run next', 'ae-seo-content-writer' ); ?></button>
				<span id="ae-seo-writer-queue-status"></span>
			</div>
			<div class="ae-seo-writer-queue-list-wrap">
				<h2><?php esc_html_e( 'Queue', 'ae-seo-content-writer' ); ?></h2>
				<div id="ae-seo-writer-queue-list"></div>
			</div>
			<p><a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( '← Dashboard', 'ae-seo-content-writer' ); ?></a></p>
		</div>
		<div id="ae-seo-writer-queue-root"></div>
		<?php
	}

	public static function render_schedule() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$next = AE_SEO_Content_Writer_Cron::get_next_run();
		$next_formatted = $next ? wp_date( 'Y-m-d H:i:s', $next ) : '—';
		$enabled = AE_SEO_Content_Writer_Cron::is_enabled();
		$frequency = AE_SEO_Content_Writer_Cron::get_frequency();
		?>
		<div class="wrap ae-seo-writer-wrap ae-seo-writer-page-schedule">
			<h1 class="ae-seo-writer-page-title"><?php esc_html_e( 'Schedule', 'ae-seo-content-writer' ); ?></h1>
			<p class="ae-seo-writer-description"><?php esc_html_e( 'Automatically run the next topic from the queue on a schedule. Ensure the runner is running and the Topic queue has pending topics.', 'ae-seo-content-writer' ); ?></p>
			<div class="ae-seo-writer-schedule-card">
				<p>
					<label class="ae-seo-writer-toggle">
						<input type="checkbox" id="ae-seo-writer-schedule-enabled" <?php checked( $enabled ); ?>>
						<?php esc_html_e( 'Enable automated runs', 'ae-seo-content-writer' ); ?>
					</label>
				</p>
				<p>
					<label for="ae-seo-writer-schedule-frequency"><?php esc_html_e( 'Frequency', 'ae-seo-content-writer' ); ?></label>
					<select id="ae-seo-writer-schedule-frequency">
						<option value="ae_seo_writer_daily_830" <?php selected( $frequency, 'ae_seo_writer_daily_830' ); ?>><?php esc_html_e( 'Daily at 8:30 am', 'ae-seo-content-writer' ); ?></option>
						<option value="daily" <?php selected( $frequency, 'daily' ); ?>><?php esc_html_e( 'Daily', 'ae-seo-content-writer' ); ?></option>
						<option value="twicedaily" <?php selected( $frequency, 'twicedaily' ); ?>><?php esc_html_e( 'Twice daily', 'ae-seo-content-writer' ); ?></option>
						<option value="ae_seo_writer_twicedaily" <?php selected( $frequency, 'ae_seo_writer_twicedaily' ); ?>><?php esc_html_e( 'Every 12 hours', 'ae-seo-content-writer' ); ?></option>
						<option value="weekly" <?php selected( $frequency, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'ae-seo-content-writer' ); ?></option>
						<option value="ae_seo_writer_weekly" <?php selected( $frequency, 'ae_seo_writer_weekly' ); ?>><?php esc_html_e( 'Once weekly', 'ae-seo-content-writer' ); ?></option>
					</select>
				</p>
				<p>
					<strong><?php esc_html_e( 'Next run', 'ae-seo-content-writer' ); ?>:</strong>
					<span id="ae-seo-writer-schedule-next-run"><?php echo esc_html( $next_formatted ); ?></span>
				</p>
				<p>
					<button type="button" class="button button-primary" id="ae-seo-writer-schedule-run-now"><?php esc_html_e( 'Run now', 'ae-seo-content-writer' ); ?></button>
					<span id="ae-seo-writer-schedule-run-now-status"></span>
				</p>
			</div>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ae-seo-writer' ) ); ?>"><?php esc_html_e( '← Dashboard', 'ae-seo-content-writer' ); ?></a></p>
		</div>
		<?php
	}
}
