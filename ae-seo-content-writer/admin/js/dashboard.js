(function () {
	'use strict';

	var API_BASE = (window.aeSeoWriter && window.aeSeoWriter.apiBase) ? window.aeSeoWriter.apiBase : 'ae-seo-writer/v1';
	var root = document.getElementById('ae-seo-writer-root');
	if (!root) return;

	var runIdFromUrl = (function () {
		var m = /run_id=([a-zA-Z0-9\-_]+)/.exec(window.location.search);
		return m ? m[1] : null;
	})();

	function restPath(p) {
		var s = (p.indexOf('/') === 0 ? p.slice(1) : p);
		return API_BASE + (s ? '/' + s : '');
	}

	function api(path, opts) {
		opts = opts || {};
		opts.path = restPath(path);
		if (opts.data !== undefined && opts.body === undefined) {
			opts.body = JSON.stringify(opts.data);
			opts.headers = (opts.headers || {});
			opts.headers['Content-Type'] = 'application/json';
		}
		return wp.apiFetch(opts);
	}

	function refreshHealth() {
		var el = document.getElementById('ae-seo-writer-health');
		if (!el) return;
		api('health')
			.then(function (h) {
				var parts = [];
				parts.push('Runner: ' + (h.runner_ok ? 'OK' : 'Not reachable'));
				parts.push('Queue: ' + (h.queue_ok ? 'OK' : 'Error'));
				parts.push('Next run: ' + (h.next_run_formatted || (h.next_cron ? new Date(h.next_cron * 1000).toLocaleString() : '—')));
				el.innerHTML = '<p class="ae-seo-writer-health-line">' + parts.join(' · ') + '</p>';
				el.className = 'ae-seo-writer-health-bar' + (h.runner_ok && h.queue_ok ? ' ae-seo-writer-health-ok' : '');
			})
			.catch(function () {
				el.innerHTML = '<p class="ae-seo-writer-health-line ae-seo-writer-muted">Status unavailable.</p>';
			});
	}

	function renderLoading(msg) {
		root.innerHTML = '<p class="ae-seo-writer-loading">' + (msg || 'Loading…') + '</p>';
	}

	function renderError(msg) {
		root.innerHTML = '<p class="ae-seo-writer-error">' + (msg || 'Error loading data.') + '</p>';
	}

	function showConfirmDeleteModal(options) {
		var title = options.title || 'Delete run';
		var message = options.message || 'The run record and its generated image file will be removed. The WordPress post (if any) is not deleted.';
		var onConfirm = options.onConfirm;
		var onCancel = options.onCancel;
		var backdrop = document.createElement('div');
		backdrop.className = 'ae-seo-writer-modal-backdrop';
		backdrop.setAttribute('role', 'dialog');
		backdrop.setAttribute('aria-modal', 'true');
		backdrop.setAttribute('aria-labelledby', 'ae-seo-writer-modal-title');
		var dialog = document.createElement('div');
		dialog.className = 'ae-seo-writer-modal-dialog';
		dialog.innerHTML =
			'<h2 id="ae-seo-writer-modal-title" class="ae-seo-writer-modal-title">' + (title.replace(/</g, '&lt;')) + '</h2>' +
			'<p class="ae-seo-writer-modal-message">' + (message.replace(/</g, '&lt;')) + '</p>' +
			'<div class="ae-seo-writer-modal-actions">' +
			'<button type="button" class="button ae-seo-writer-modal-cancel">Cancel</button>' +
			'<button type="button" class="button ae-seo-writer-modal-confirm-delete">Delete</button>' +
			'</div>';
		backdrop.appendChild(dialog);
		function close() {
			document.body.removeChild(backdrop);
		}
		dialog.querySelector('.ae-seo-writer-modal-cancel').addEventListener('click', function () {
			close();
			if (typeof onCancel === 'function') onCancel();
		});
		dialog.querySelector('.ae-seo-writer-modal-confirm-delete').addEventListener('click', function () {
			var btn = this;
			btn.disabled = true;
			btn.textContent = 'Deleting…';
			if (typeof onConfirm === 'function') {
				onConfirm(function done() {
					close();
				}, function keepOpen(errMsg) {
					btn.disabled = false;
					btn.textContent = 'Delete';
					if (errMsg) alert(errMsg);
				});
			} else {
				close();
			}
		});
		backdrop.addEventListener('click', function (e) {
			if (e.target === backdrop) {
				close();
				if (typeof onCancel === 'function') onCancel();
			}
		});
		document.body.appendChild(backdrop);
	}

	function deleteRun(runId, afterDelete) {
		showConfirmDeleteModal({
			title: 'Delete this run?',
			message: 'The run record and its generated image file will be removed from the plugin and runner. The WordPress post (if any) is not deleted.',
			onConfirm: function (closeModal, keepOpen) {
				api('/runs/' + runId, { method: 'DELETE' })
					.then(function () {
						if (typeof afterDelete === 'function') afterDelete();
						else window.location.search = '?page=ae-seo-writer';
						closeModal();
					})
					.catch(function (err) {
						keepOpen(err.message || 'Could not delete run.');
					});
			}
		});
	}

	function renderRunsList(runs) {
		var html = '<p class="ae-seo-writer-root-intro">Pipeline runs (click to open full detail: progress, research, brief, image, draft editor). You can delete any run, including failed ones.</p>';
		html += '<div class="ae-seo-writer-runs-list">';
		if (!runs.length) {
			html += '<p class="ae-seo-writer-muted">No runs yet. <a href="' + (window.aeSeoWriter && window.aeSeoWriter.newRunUrl ? window.aeSeoWriter.newRunUrl : 'admin.php?page=ae-seo-writer-new') + '">Start a new run</a>.</p>';
		} else {
			runs.forEach(function (r) {
				html += '<div class="ae-seo-writer-run-card">';
				html += '<a class="ae-seo-writer-run-card-link" href="?page=ae-seo-writer&run_id=' + encodeURIComponent(r.run_id) + '" data-run-id="' + r.run_id + '">';
				html += '<strong>' + (r.topic || 'Untitled') + '</strong>';
				html += '<span class="stage">' + (r.status || '') + ' · ' + (r.current_stage || '') + ' · ' + (r.started_at || '') + '</span>';
				html += '</a>';
				html += '<button type="button" class="button ae-seo-writer-btn-delete-run" data-run-id="' + r.run_id + '" title="Delete run">Delete</button>';
				html += '</div>';
			});
		}
		html += '</div>';
		html += '<p class="ae-seo-writer-root-intro"><a href="admin.php?page=ae-seo-writer-new">Start new run</a></p>';
		root.innerHTML = html;
		root.querySelectorAll('.ae-seo-writer-run-card-link').forEach(function (el) {
			el.addEventListener('click', function (e) {
				e.preventDefault();
				var runId = el.dataset.runId;
				// Update URL so reload stays on run; Back button returns to list
				history.pushState(null, '', window.location.pathname + '?page=ae-seo-writer&run_id=' + encodeURIComponent(runId));
				loadRunDetail(runId);
			});
		});
		root.querySelectorAll('.ae-seo-writer-btn-delete-run').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				deleteRun(btn.dataset.runId, function () { renderRunsList(runs.filter(function (r) { return r.run_id !== btn.dataset.runId; })); });
			});
		});
	}

	var pollInterval = null;

	function stopPolling() {
		if (pollInterval) {
			clearInterval(pollInterval);
			pollInterval = null;
		}
	}

	function loadRunDetail(runId, cacheBust) {
		stopPolling();
		renderLoading('Loading run…');
		var path = '/runs/' + runId;
		if (cacheBust) path += '?_t=' + Date.now();
		api(path)
			.then(function (d) {
				// Keep URL in sync so reload stays on this run
				history.replaceState(null, '', window.location.pathname + '?page=ae-seo-writer&run_id=' + encodeURIComponent(runId));
				renderRunDetail(d, runId);
				if (d.status === 'running') {
					pollInterval = setInterval(function () {
						api('/runs/' + runId)
							.then(function (next) {
								updateProgressAndLog(next);
								if (next.status !== 'running') stopPolling();
							})
							.catch(function () {});
					}, 2500);
				}
			})
			.catch(function (err) {
				renderError(err.message || 'Run not found.');
			});
	}

	function progressPercent(d) {
		var order = ['started', 'research', 'brief', 'write', 'optimize', 'image', 'upload_media', 'wordpress_draft'];
		var stage = d.current_stage || 'started';
		var idx = order.indexOf(stage);
		if (idx === -1) idx = 0;
		// Only show 100% when run completed successfully; for error show how far it got
		if (d.status === 'success') return 100;
		if (d.status === 'error') return Math.min(99, Math.round(((idx + 1) / order.length) * 100));
		return Math.round(((idx + 1) / order.length) * 100);
	}

	function formatLogDetail(detail) {
		if (detail == null) return '';
		if (typeof detail === 'string') return detail.replace(/</g, '&lt;');
		if (Array.isArray(detail)) return '<ul><li>' + detail.map(function (x) { return (typeof x === 'string' ? x : JSON.stringify(x)).replace(/</g, '&lt;'); }).join('</li><li>') + '</li></ul>';
		var parts = [];
		Object.keys(detail).forEach(function (k) {
			var v = detail[k];
			if (Array.isArray(v) && v.length > 0 && typeof v[0] === 'string')
				parts.push(k + ': ' + v.slice(0, 10).join(', ') + (v.length > 10 ? '…' : ''));
			else if (typeof v === 'object' && v !== null)
				parts.push(k + ': ' + JSON.stringify(v));
			else
				parts.push(k + ': ' + String(v));
		});
		return parts.join('\n').replace(/</g, '&lt;');
	}

	function renderLogHtml(log) {
		if (!log || !log.length) return '<p class="ae-seo-writer-muted" style="padding: 1rem 1.25rem;">No log entries yet.</p>';
		return '<div class="ae-seo-writer-log-scroll">' + log.map(function (e) {
			var t = (e.at || '').split('T')[1] || '';
			if (t.length > 8) t = t.substring(0, 8);
			var cls = 'ae-seo-writer-log-entry ae-seo-writer-log-' + (e.level || 'info');
			var msg = (e.message || '').replace(/</g, '&lt;');
			var detailHtml = e.detail ? '<div class="ae-seo-writer-log-detail">' + formatLogDetail(e.detail) + '</div>' : '';
			return '<div class="' + cls + '"><span class="ae-seo-writer-log-time">' + t + '</span><div><span class="ae-seo-writer-log-msg">' + msg + '</span>' + detailHtml + '</div></div>';
		}).join('') + '</div>';
	}

	function updateProgressAndLog(d) {
		var pct = progressPercent(d);
		var barWrap = document.getElementById('ae-seo-writer-progress-wrap');
		var logInner = document.getElementById('ae-seo-writer-log-inner');
		if (barWrap) {
			var fill = barWrap.querySelector('.ae-seo-writer-progress-bar-fill');
			var text = barWrap.querySelector('.ae-seo-writer-progress-text');
			if (fill) fill.style.width = pct + '%';
			if (text) text.textContent = d.status === 'error' ? ('Failed at ' + (d.current_stage || 'started') + ' — ' + pct + '%') : ((d.current_stage || 'started') + ' — ' + pct + '% complete' + (d.status === 'running' ? ' (updating…)' : ''));
		}
		if (logInner) {
			var log = d.log || [];
			logInner.innerHTML = log.length ? renderLogHtml(log) : '<p class="ae-seo-writer-muted" style="padding: 1rem 1.25rem;">No log entries yet.</p>';
			var scrollEl = logInner.querySelector('.ae-seo-writer-log-scroll');
			if (scrollEl) scrollEl.scrollTop = scrollEl.scrollHeight;
		}
	}

	function renderRunDetail(d, runId) {
		var research = d.research || {};
		var brief = d.brief || {};
		var stagesList = d.stages || [];
		var stages = stagesList.length
			? '<div class="ae-seo-writer-stages-timeline">' + stagesList.map(function (s, i) {
				var done = d.status === 'success' || (i < stagesList.length - 1);
				var running = d.status === 'running' && i === stagesList.length - 1;
				var cl = 'stage-item' + (done ? ' stage-done' : '') + (running ? ' stage-running' : '');
				return '<div class="' + cl + '">' + s.name + ' <span class="ae-seo-writer-muted">' + (s.at || '') + '</span></div>';
			}).join('') + '</div>'
			: '—';
		var queriesHtml = (research.queries && research.queries.length) ? research.queries.join(', ') : '—';
		var linksHtml = '—';
		if (research.links_researched && research.links_researched.length) {
			linksHtml = '<ul class="research-links">' + research.links_researched.map(function (l) {
				return '<li><a href="' + l.url + '" target="_blank" rel="noopener">' + (l.title || l.url) + '</a></li>';
			}).join('') + '</ul>';
		}
		var outlineHtml = (brief.outline && brief.outline.length) ? brief.outline.map(function (o) {
			return o.text;
		}).join(' → ') : '—';
		var internalHtml = (brief.internal_links && brief.internal_links.length) ? brief.internal_links.map(function (l) {
			return l.anchor || l.url;
		}).join(', ') : '—';
		var imgHtml = '';
		// Prefer WordPress media URL (always works); fallback to runner proxy via fetch with auth
		var imgSrc = '';
		var restBase = (window.aeSeoWriter && window.aeSeoWriter.restUrl) ? String(window.aeSeoWriter.restUrl).replace(/\/$/, '') : '';
		if (restBase && restBase.indexOf('http') !== 0) {
			restBase = (window.aeSeoWriter && window.aeSeoWriter.homeUrl) ? window.aeSeoWriter.homeUrl.replace(/\/$/, '') + '/' + restBase : (window.location.origin + '/' + restBase);
		}
		if (d.wp_image_url) {
			imgSrc = d.wp_image_url + (d.wp_image_url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
		} else if (d.image_path && restBase) {
			imgSrc = restBase + '/runs/' + encodeURIComponent(runId) + '/image?t=' + Date.now();
		}
		if (imgSrc) {
			var useProxy = !d.wp_image_url && d.image_path;
			imgHtml = '<div class="ae-seo-writer-image-wrap" data-ae-seo-upload-pending="' + (useProxy ? '1' : '0') + '">';
			// Proxy image URL works without auth (plugin allows public read for run image). Use direct src so image loads reliably.
			imgHtml += '<img class="image-preview" src="' + imgSrc.replace(/"/g, '&quot;') + '" alt="Generated hero image" onerror="var w=this.closest(\'.ae-seo-writer-image-wrap\'); if(w){ var e=w.querySelector(\'.ae-seo-writer-image-fallback\'); if(e) e.style.display=\'block\'; this.style.display=\'none\'; }">';
			imgHtml += '<p class="ae-seo-writer-muted ae-seo-writer-image-fallback" style="display:none">Image could not be loaded. Ensure Runner is running (port 8765). Set WordPress Application Password in <strong>Settings → AE SEO Writer</strong> to add image to Media. Then click <strong>Regenerate image</strong> or reload.</p>';
			imgHtml += '<p class="ae-seo-writer-muted ae-seo-writer-image-uploading" style="display:none">Uploading to Media…</p>';
			imgHtml += '<p class="ae-seo-writer-muted ae-seo-writer-image-generated-label">Generated</p></div>';
		} else {
			imgHtml = '<p class="ae-seo-writer-muted">No image yet. Edit the prompt below and click Regenerate to create one.</p>';
		}
		var currentImagePrompt = (d.image_prompt || '').replace(/<\/textarea>/gi, '</' + 'textarea>');
		imgHtml += '<div class="ae-seo-writer-regenerate-image">';
		imgHtml += '<p class="ae-seo-writer-regenerate-label">Leave this blank to auto-generate a fresh prompt from the full article. Only type here if you want to force a custom prompt.</p>';
		if (currentImagePrompt) {
			imgHtml += '<details class="ae-seo-writer-current-image-prompt"><summary>Current generated prompt</summary><p>' + currentImagePrompt.replace(/</g, '&lt;') + '</p></details>';
		}
		imgHtml += '<label for="ae-seo-writer-image-prompt">Optional custom prompt</label>';
		imgHtml += '<textarea id="ae-seo-writer-image-prompt" class="ae-seo-writer-image-prompt-input" rows="4" placeholder="Leave blank to generate a new article-specific prompt. Example custom prompt: Close-up of a hand using a product interface in a real setting, natural daylight, 16:9, high resolution."></textarea>';
		imgHtml += '<button type="button" class="button button-primary" id="ae-seo-writer-btn-regenerate-image">Regenerate image</button>';
		imgHtml += '</div>';

		var wpLink = '';
		if (d.wp_post_id && window.aeSeoWriter && window.aeSeoWriter.wpEditUrl) {
			wpLink = '<p class="ae-seo-writer-wp-link">WordPress draft: <a href="' + window.aeSeoWriter.wpEditUrl.replace('%d', d.wp_post_id) + '" target="_blank">Edit in WP</a></p>';
		}

		var pct = progressPercent(d);
		var log = d.log || [];
		var html = '<div class="ae-seo-writer-detail">';
		html += '<h2>' + (d.topic || 'Untitled') + '</h2>';
		html += '<p><strong>Status:</strong> ' + (d.status || '') + ' · <strong>Stage:</strong> ' + (d.current_stage || '') + '</p>';
		if (d.status === 'error') {
			html += '<p class="ae-seo-writer-restart-row"><button type="button" class="button button-primary" id="ae-seo-writer-btn-restart-run">Restart run (same topic)</button> <span class="ae-seo-writer-muted">Starts a new pipeline run with this topic.</span></p>';
		}
		html += '<div id="ae-seo-writer-progress-wrap" class="ae-seo-writer-progress-wrap">';
		html += '<h3>Progress</h3>';
		html += '<div class="ae-seo-writer-progress-bar"><div class="ae-seo-writer-progress-bar-fill" style="width:' + pct + '%"></div></div>';
		html += '<p class="ae-seo-writer-progress-text">' + (d.status === 'error' ? 'Failed at ' + (d.current_stage || 'started') + ' — ' + pct + '%' : (d.current_stage || 'started') + ' — ' + pct + '% complete' + (d.status === 'running' ? ' (updating…)' : '')) + '</p>';
		html += '</div>';
		html += '<div id="ae-seo-writer-log-panel" class="ae-seo-writer-log-panel">';
		html += '<h3>Report log</h3>';
		html += '<div id="ae-seo-writer-log-inner">' + (log.length ? renderLogHtml(log) : '<p class="ae-seo-writer-muted" style="padding: 1rem 1.25rem;">No log entries yet.</p>') + '</div>';
		html += '</div>';
		html += '<h3>Progress (stages)</h3><div>' + (stages || '—') + '</div>';
		html += '<h3>Research (queries &amp; links)</h3><div class="research-queries">Queries: ' + queriesHtml + '</div><div>Links researched: ' + linksHtml + '</div>';
		html += '<h3>Brief (outline &amp; internal links)</h3><div class="brief-outline">Outline: ' + outlineHtml + '<br>Internal links: ' + internalHtml + '</div>';
		var notes = (d.notes && Array.isArray(d.notes) && d.notes.length) ? d.notes : [];
		var notesHtml = notes.length ? '<ul class="ae-seo-writer-optimizer-notes">' + notes.map(function (n) { return '<li>' + (n || '').replace(/</g, '&lt;') + '</li>'; }).join('') + '</ul>' : '<p class="ae-seo-writer-muted">None</p>';
		html += '<h3>Optimization notes</h3><div class="ae-seo-writer-notes">' + notesHtml + '</div>';
		var humanizerApplied = !!d.humanizer_applied;
		var humanizerNotes = d.humanizer_notes || {};
		var scrubStats = d.scrub_stats || {};
		var hasHumanizeStage = (d.stages || []).some(function (s) { return s.name === 'humanize'; });
		var humanizerSummary = (humanizerNotes.summary || '').replace(/</g, '&lt;');
		var reason = humanizerNotes.error ? (humanizerNotes.error || '').replace(/</g, '&lt;') : (!hasHumanizeStage ? 'This run used the pipeline before the humanizer was added. Restart the runner (uvicorn), then start a new run.' : '');
		var humanizerHtml = '<p class="ae-seo-writer-muted">Humanizer: ' + (humanizerApplied ? 'Applied' : 'Not applied') + (reason ? ' — ' + reason : '') + '</p>';
		if (humanizerSummary) { humanizerHtml += '<p>' + humanizerSummary + '</p>'; }
		if (Object.keys(scrubStats).length) {
			humanizerHtml += '<p class="ae-seo-writer-muted">Scrub: Unicode removed ' + (scrubStats.unicode_removed || 0) + ', format control ' + (scrubStats.format_control_removed || 0) + ', em-dashes replaced ' + (scrubStats.emdashes_replaced || 0) + '</p>';
		}
		html += '<h3>Humanizer</h3><div class="ae-seo-writer-humanizer">' + humanizerHtml + '</div>';
		var blogUrl = (d.blog_url || '').trim();
		var xPosts = (d.x_posts && Array.isArray(d.x_posts)) ? d.x_posts : [];
		if (xPosts.length === 0 && (d.twitter_post || '').trim()) {
			xPosts = [(d.twitter_post || '').trim()];
		}
		var twitterSection = '<h3>X (Twitter) — 4 posts for social</h3><div class="ae-seo-writer-twitter-section">';
		twitterSection += '<p class="ae-seo-writer-muted">Each post will include the blog link when you click "Post to X". Max 280 chars (link counts as 23).</p>';
		if (blogUrl) {
			twitterSection += '<p>Blog link: <a href="' + blogUrl.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener">' + blogUrl.replace(/</g, '&lt;') + '</a></p>';
		}
		twitterSection += '<div id="ae-seo-writer-x-posts-list" class="ae-seo-writer-x-posts-list">';
		for (var i = 0; i < 4; i++) {
			var postText = (xPosts[i] !== undefined ? xPosts[i] : '').replace(/<\/textarea>/gi, '</' + 'textarea>');
			twitterSection += '<div class="ae-seo-writer-x-post-item" data-index="' + i + '">';
			twitterSection += '<label>Post ' + (i + 1) + '</label>';
			twitterSection += '<p class="ae-seo-writer-x-post-text">' + (postText.replace(/</g, '&lt;').replace(/\n/g, '<br>') || '—') + '</p>';
			twitterSection += '<button type="button" class="button ae-seo-writer-btn-post-x" data-index="' + i + '">Post to X</button>';
			twitterSection += '<span class="ae-seo-writer-post-x-status" data-index="' + i + '"></span>';
			twitterSection += '</div>';
		}
		twitterSection += '</div>';
		twitterSection += '<p><button type="button" class="button button-primary" id="ae-seo-writer-btn-schedule-x">Schedule 4 daily posts (once per day for 4 days)</button>';
		twitterSection += ' <span id="ae-seo-writer-schedule-x-status"></span></p>';
		twitterSection += '<p id="ae-seo-writer-x-schedule-info" class="ae-seo-writer-muted"></p>';
		if (d.twitter_card_title || d.twitter_card_description) {
			twitterSection += '<p class="ae-seo-writer-muted">Twitter card (X media card):</p>';
			twitterSection += '<ul class="ae-seo-writer-card-meta"><li><strong>og:title / twitter:title:</strong> ' + (d.twitter_card_title || '—').replace(/</g, '&lt;') + '</li>';
			twitterSection += '<li><strong>og:description / twitter:description:</strong> ' + (d.twitter_card_description || '—').replace(/</g, '&lt;') + '</li></ul>';
		}
		twitterSection += '</div>';
		html += twitterSection;
		html += '<h3>Full blog post — edit, then Save and Send to WordPress</h3>';
		var draftText = (d.draft_md || '').replace(/<\/textarea>/gi, '</' + 'textarea>');
		html += '<textarea id="ae-seo-writer-draft-editor" class="ae-seo-writer-draft-editor">' + draftText + '</textarea>';
		html += '<h3>Featured image</h3>';
		if (d.image_model) {
			html += '<p class="ae-seo-writer-muted" style="margin-top:0">Model: ' + String(d.image_model).replace(/</g, '&lt;') + '</p>';
		}
		html += '<p class="ae-seo-writer-muted" style="margin-top:0">Images are automatically resized and compressed to under 200 KB when sent to WordPress.</p>';
		html += '<div class="ae-seo-writer-image-section">' + imgHtml + '</div>';
		html += '<div class="ae-seo-writer-actions">';
		html += '<button type="button" class="button" id="ae-seo-writer-btn-save">Save draft</button>';
		html += '<button type="button" class="button button-primary" id="ae-seo-writer-btn-send">Send to WordPress (draft)</button>';
		html += '<button type="button" class="button" id="ae-seo-writer-btn-publish">Publish to blog</button>';
		html += ' <button type="button" class="button ae-seo-writer-btn-delete" id="ae-seo-writer-btn-delete-run">Delete run</button>';
		html += '</div>';
		html += wpLink;
		html += '<p><a href="?page=ae-seo-writer">← Back to runs list</a></p>';
		html += '</div>';

		root.innerHTML = html;

		// If run has image but no wp_image_url, try to upload to Media in background (image already shows via proxy).
		var uploadPendingWrap = root.querySelector('.ae-seo-writer-image-wrap[data-ae-seo-upload-pending="1"]');
		if (uploadPendingWrap) {
			var uploadingEl = uploadPendingWrap.querySelector('.ae-seo-writer-image-uploading');
			if (uploadingEl) uploadingEl.style.display = 'block';
			api('/runs/' + runId + '/upload-image-to-media', { method: 'POST' })
				.then(function (res) {
					if (res && res.wp_image_url) {
						loadRunDetail(runId, true);
						return;
					}
					throw new Error('No URL returned');
				})
				.catch(function () {
					if (uploadingEl) uploadingEl.style.display = 'none';
				});
		}

		var editor = document.getElementById('ae-seo-writer-draft-editor');
		document.getElementById('ae-seo-writer-btn-save').addEventListener('click', function () {
			api('/runs/' + runId + '/draft', { method: 'PUT', data: { draft_md: editor.value } })
				.then(function () { alert('Draft saved.'); })
				.catch(function (err) { alert(err.message || 'Error'); });
		});
		document.getElementById('ae-seo-writer-btn-send').addEventListener('click', function () {
			api('/runs/' + runId + '/send-to-wordpress', { method: 'POST', data: { draft_md: editor.value, publish: false } })
				.then(function (res) {
					var postId = (res && res.wp_post_id) ? res.wp_post_id : null;
					if (postId && window.aeSeoWriter && window.aeSeoWriter.wpEditUrl) {
						window.location.href = window.aeSeoWriter.wpEditUrl.replace('%d', String(postId));
						return;
					}
					api('/runs/' + runId).then(function (d) {
						postId = d && d.wp_post_id ? d.wp_post_id : null;
						if (postId && window.aeSeoWriter && window.aeSeoWriter.wpEditUrl) {
							window.location.href = window.aeSeoWriter.wpEditUrl.replace('%d', String(postId));
						} else {
							alert('Sent to WordPress as draft.');
							loadRunDetail(runId);
						}
					}).catch(function () {
						alert('Sent to WordPress as draft.');
						loadRunDetail(runId);
					});
				})
				.catch(function (err) { alert(err.message || 'Error'); });
		});
		document.getElementById('ae-seo-writer-btn-publish').addEventListener('click', function () {
			if (!confirm('Publish this post to the live blog?')) return;
			api('/runs/' + runId + '/send-to-wordpress', { method: 'POST', data: { draft_md: editor.value, publish: true } })
				.then(function () {
					alert('Published.');
					loadRunDetail(runId);
				})
				.catch(function (err) { alert(err.message || 'Error'); });
		});
		var deleteRunBtn = document.getElementById('ae-seo-writer-btn-delete-run');
		if (deleteRunBtn) {
			deleteRunBtn.addEventListener('click', function () {
				deleteRun(runId, function () { window.location.search = '?page=ae-seo-writer'; });
			});
		}
		// Post to X buttons (one per of 4 posts)
		root.querySelectorAll('.ae-seo-writer-btn-post-x').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var idx = parseInt(btn.getAttribute('data-index'), 10);
				var statusEl = root.querySelector('.ae-seo-writer-post-x-status[data-index="' + idx + '"]');
				if (statusEl) statusEl.textContent = 'Posting…';
				btn.disabled = true;
				api('/runs/' + runId + '/post-to-x', { method: 'POST', data: { post_index: idx } })
					.then(function (res) {
						if (res.ok) {
							if (statusEl) statusEl.textContent = 'Posted ✓';
						} else {
							if (statusEl) statusEl.textContent = res.error || 'Failed';
							alert(res.error || 'Failed to post to X.');
						}
					})
					.catch(function (err) {
						if (statusEl) statusEl.textContent = 'Error';
						alert(err.message || 'Failed to post to X.');
					})
					.finally(function () {
						btn.disabled = false;
					});
			});
		});
		// Schedule 4 daily X posts
		var scheduleXBtn = document.getElementById('ae-seo-writer-btn-schedule-x');
		var scheduleXStatus = document.getElementById('ae-seo-writer-schedule-x-status');
		var scheduleXInfo = document.getElementById('ae-seo-writer-x-schedule-info');
		if (scheduleXBtn) {
			scheduleXBtn.addEventListener('click', function () {
				scheduleXBtn.disabled = true;
				if (scheduleXStatus) scheduleXStatus.textContent = 'Scheduling…';
				api('/runs/' + runId + '/schedule-x-posts', { method: 'POST' })
					.then(function (res) {
						if (res.ok && res.scheduled) {
							if (scheduleXStatus) scheduleXStatus.textContent = 'Scheduled ✓';
							renderXScheduleInfo(runId, res.schedule, scheduleXInfo);
						} else {
							if (scheduleXStatus) scheduleXStatus.textContent = 'Failed';
							alert(res.message || 'Could not schedule.');
						}
					})
					.catch(function (err) {
						if (scheduleXStatus) scheduleXStatus.textContent = 'Error';
						alert(err.message || 'Could not schedule.');
					})
					.finally(function () {
						scheduleXBtn.disabled = false;
					});
			});
		}
		function renderXScheduleInfo(runId, schedule, el) {
			if (!el || !schedule) return;
			var posted = (schedule.posted && Array.isArray(schedule.posted)) ? schedule.posted : [];
			var lines = [];
			for (var d = 0; d < 4; d++) {
				var at = schedule['day_' + d + '_at'];
				var dateStr = at ? new Date(at * 1000).toLocaleString() : '—';
				var done = posted.indexOf(d) !== -1;
				lines.push('Day ' + (d + 1) + ': ' + dateStr + (done ? ' (posted)' : ''));
			}
			el.innerHTML = 'Scheduled: ' + lines.join(' · ');
		}
		// Load existing X schedule for this run
		api('/runs/' + runId + '/x-schedule').then(function (r) {
			if (r.schedule && scheduleXInfo) {
				renderXScheduleInfo(runId, r.schedule, scheduleXInfo);
				if (scheduleXStatus) scheduleXStatus.textContent = 'Scheduled';
			}
		}).catch(function () {});
		var regenImgBtn = document.getElementById('ae-seo-writer-btn-regenerate-image');
		if (regenImgBtn) {
			regenImgBtn.addEventListener('click', function () {
				var promptEl = document.getElementById('ae-seo-writer-image-prompt');
				var promptText = promptEl ? promptEl.value.trim() : '';
				regenImgBtn.disabled = true;
				regenImgBtn.textContent = 'Generating…';
				var base = (window.aeSeoWriter && window.aeSeoWriter.restUrl) ? String(window.aeSeoWriter.restUrl).replace(/\/$/, '') : '';
				var url = base ? (base + '/regenerate-image') : '';
				var nonce = (window.aeSeoWriter && window.aeSeoWriter.nonce) ? window.aeSeoWriter.nonce : '';
				var body = JSON.stringify({ run_id: runId, prompt: promptText || null });
				if (!url) {
					alert('REST URL not configured. Reload the page.');
					regenImgBtn.disabled = false;
					regenImgBtn.textContent = 'Regenerate image';
					return;
				}
				fetch(url, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
					body: body,
					credentials: 'same-origin'
				})
					.then(function (res) {
						if (!res.ok) {
							// Read body once only (stream cannot be read twice).
							return res.text().then(function (text) {
								var msg = 'Request failed: ' + res.status;
								var code;
								try {
									var data = JSON.parse(text);
									code = data && data.code;
									msg = (data && data.message) ? data.message : (data && data.detail) ? data.detail : msg;
								} catch (e) {
									if (res.status === 503) msg = 'Image generation failed (503). Add OPENAI_API_KEY (recommended) or Gemini API key in Settings → AE SEO Writer.';
									if (text && text.length < 300) msg = msg + ' ' + text;
								}
								throw { status: res.status, message: msg, code: code };
							});
						}
						return res.json();
					})
					.then(function (res) {
						// Use wp_image_url from response so image shows immediately; then refetch full run
						if (res && res.wp_image_url) {
							var wrap = document.querySelector('.ae-seo-writer-image-wrap');
							if (wrap) {
								var img = wrap.querySelector('img.image-preview');
								if (img) {
									img.src = res.wp_image_url + (res.wp_image_url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
									img.style.display = '';
									var fallback = wrap.querySelector('.ae-seo-writer-image-fallback');
									if (fallback) fallback.style.display = 'none';
								}
							}
						}
						loadRunDetail(runId, true);
					})
					.catch(function (err) {
						var msg = (err && err.message) ? err.message : 'Could not regenerate image.';
						if (err && err.code === 'rest_no_route') msg = 'Regenerate endpoint not found. Reload and try again.';
						if (err && err.status === 502) msg = 'Runner unreachable. Check Runner URL and that the runner is running.';
						if (err && err.status === 503 && msg.indexOf('503') !== -1 && msg.indexOf('Image') === -1) msg = 'Image generation failed (503). Add OPENAI_API_KEY or Gemini API key in Settings → AE SEO Writer.';
						alert(msg);
						regenImgBtn.disabled = false;
						regenImgBtn.textContent = 'Regenerate image';
					});
			});
		}
		var restartBtn = document.getElementById('ae-seo-writer-btn-restart-run');
		if (restartBtn) {
			restartBtn.addEventListener('click', function () {
				var topic = (d && d.topic) ? d.topic : '';
				if (!topic) { alert('No topic to restart.'); return; }
				restartBtn.disabled = true;
				restartBtn.textContent = 'Starting…';
				api('run', { method: 'POST', data: { topic: topic } })
					.then(function (r) {
						var newId = r.run_id;
						if (newId) {
							window.location.search = '?page=ae-seo-writer&run_id=' + encodeURIComponent(newId);
						} else {
							restartBtn.disabled = false;
							restartBtn.textContent = 'Restart run (same topic)';
							loadRunDetail(runId);
						}
					})
					.catch(function (err) {
						alert(err.message || 'Could not start run.');
						restartBtn.disabled = false;
						restartBtn.textContent = 'Restart run (same topic)';
					});
			});
		}
	}

	function init() {
		refreshHealth();
		window.addEventListener('popstate', function () {
			var m = /run_id=([a-zA-Z0-9\-_]+)/.exec(window.location.search);
			if (m) {
				loadRunDetail(m[1]);
			} else {
				stopPolling();
				renderLoading('Loading runs…');
				api('runs')
					.then(function (runs) { renderRunsList(runs); })
					.catch(function (err) { renderError(err.message || 'Could not load runs.'); });
			}
		});
		if (runIdFromUrl) {
			loadRunDetail(runIdFromUrl);
			return;
		}
		renderLoading('Loading runs…');
		api('runs')
			.then(function (runs) {
				renderRunsList(runs);
			})
			.catch(function (err) {
				renderError(err.message || 'Could not load runs. Is the Runner URL set and the runner running?');
			});
	}

	init();
})();
