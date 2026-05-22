(function () {
	'use strict';

	var API_BASE = (window.aeSeoWriter && window.aeSeoWriter.apiBase) ? window.aeSeoWriter.apiBase : 'ae-seo-writer/v1';
	var DASHBOARD_URL = (window.aeSeoWriter && window.aeSeoWriter.dashboardUrl) || 'admin.php?page=ae-seo-writer';

	function restPath(p) {
		var s = (p.indexOf('/') === 0 ? p.slice(1) : p);
		return API_BASE + (s ? '/' + s : '');
	}

	function api(relativePath, opts) {
		opts = opts || {};
		opts.path = restPath(relativePath);
		if (opts.data !== undefined && opts.body === undefined) {
			opts.body = JSON.stringify(opts.data);
			opts.headers = opts.headers || {};
			opts.headers['Content-Type'] = 'application/json';
		}
		return wp.apiFetch(opts);
	}

	var listEl = document.getElementById('ae-seo-writer-queue-list');
	var statusEl = document.getElementById('ae-seo-writer-queue-status');
	var topicInput = document.getElementById('ae-seo-writer-queue-topic');
	var bulkInput = document.getElementById('ae-seo-writer-queue-bulk');

	function setStatus(msg, isError) {
		if (!statusEl) return;
		statusEl.textContent = msg;
		statusEl.className = isError ? 'ae-seo-writer-inline-error' : '';
	}

	function renderList(items) {
		if (!listEl) return;
		if (!items || !items.length) {
			listEl.innerHTML = '<p class="ae-seo-writer-muted">No topics in queue.</p>';
			return;
		}
		var html = '<table class="ae-seo-writer-table"><thead><tr><th>ID</th><th>Topic</th><th>Status</th><th>Run ID</th><th>Error</th><th>Added</th><th></th></tr></thead><tbody>';
		items.forEach(function (item) {
			var runLink = item.run_id
				? '<a href="' + DASHBOARD_URL + '&run_id=' + encodeURIComponent(item.run_id) + '">' + item.run_id + '</a>'
				 : '—';
			var errCell = (item.status === 'failed' && item.error_message)
				? '<span class="ae-seo-writer-error-inline" title="' + (item.error_message || '').replace(/"/g, '&quot;') + '">' + (item.error_message || '').replace(/</g, '&lt;').substring(0, 60) + (item.error_message && item.error_message.length > 60 ? '…' : '') + '</span>'
				: '—';
			var delBtn = (item.status === 'pending')
				? '<button type="button" class="button ae-seo-writer-btn-del" data-id="' + item.id + '">Remove</button>'
				: '';
			var rerunBtn = '<button type="button" class="button ae-seo-writer-btn-rerun" data-id="' + item.id + '">Re-run</button>';
			var actionsCell = (delBtn ? delBtn + ' ' : '') + rerunBtn;
			html += '<tr data-id="' + item.id + '">';
			html += '<td>' + item.id + '</td>';
			html += '<td>' + (item.topic || '').replace(/</g, '&lt;') + '</td>';
			html += '<td><span class="ae-seo-writer-status ae-seo-writer-status-' + (item.status || '') + '">' + (item.status || '') + '</span></td>';
			html += '<td>' + runLink + '</td>';
			html += '<td class="ae-seo-writer-cell-error">' + errCell + '</td>';
			html += '<td>' + (item.created_at || '') + '</td>';
			html += '<td>' + actionsCell + '</td></tr>';
		});
		html += '</tbody></table>';
		listEl.innerHTML = html;
		listEl.querySelectorAll('.ae-seo-writer-btn-del').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var id = parseInt(btn.dataset.id, 10);
				if (!confirm('Remove this topic from the queue?')) return;
				api('queue/' + id, { method: 'DELETE' })
					.then(function () { loadQueue(); })
					.catch(function (e) { setStatus(e.message || 'Error', true); });
			});
		});
		listEl.querySelectorAll('.ae-seo-writer-btn-rerun').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var id = parseInt(btn.dataset.id, 10);
				btn.disabled = true;
				setStatus('Starting re-run (new content)…');
				api('queue/' + id + '/rerun', { method: 'POST' })
					.then(function (r) {
						if (r.ok && r.run_id) {
							window.location.href = DASHBOARD_URL + '&run_id=' + encodeURIComponent(r.run_id);
							return;
						}
						setStatus(r.message || 'Re-run failed.', true);
						loadQueue();
					})
					.catch(function (e) {
						setStatus(e.message || 'Error', true);
						loadQueue();
					})
					.finally(function () { btn.disabled = false; });
			});
		});
	}

	function loadQueue() {
		setStatus('');
		api('queue')
			.then(function (items) { renderList(items); })
			.catch(function (e) {
				renderList([]);
				setStatus(e.message || 'Failed to load queue', true);
			});
	}

	document.getElementById('ae-seo-writer-queue-add')?.addEventListener('click', function () {
		var topic = topicInput && topicInput.value ? topicInput.value.trim() : '';
		if (!topic) return;
		setStatus('Adding…');
		api('queue', { method: 'POST', data: { topic: topic } })
			.then(function () {
				topicInput.value = '';
				loadQueue();
				setStatus('Added.');
			})
			.catch(function (e) { setStatus(e.message || 'Error', true); });
	});

	document.getElementById('ae-seo-writer-queue-bulk-add')?.addEventListener('click', function () {
		var raw = bulkInput && bulkInput.value ? bulkInput.value : '';
		if (!raw.trim()) return;
		setStatus('Adding…');
		api('queue/bulk', { method: 'POST', data: { topics: raw } })
			.then(function (r) {
				bulkInput.value = '';
				loadQueue();
				setStatus((r.added || 0) + ' topic(s) added.');
			})
			.catch(function (e) { setStatus(e.message || 'Error', true); });
	});

	document.getElementById('ae-seo-writer-queue-run-next')?.addEventListener('click', function () {
		setStatus('Starting run…');
		api('queue/run-next', { method: 'POST' })
			.then(function (r) {
				if (r.ok && r.run_id) {
					window.location.href = DASHBOARD_URL + '&run_id=' + encodeURIComponent(r.run_id);
					return;
				}
				setStatus(r.message || 'No pending topics.', true);
				loadQueue();
			})
			.catch(function (e) {
				setStatus(e.message || 'Error', true);
				loadQueue();
			});
	});

	loadQueue();
})();
