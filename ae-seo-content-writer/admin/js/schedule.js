(function () {
	'use strict';

	var API_BASE = (window.aeSeoWriter && window.aeSeoWriter.apiBase) ? window.aeSeoWriter.apiBase : 'ae-seo-writer/v1';

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

	var nextRunEl = document.getElementById('ae-seo-writer-schedule-next-run');
	var runNowStatusEl = document.getElementById('ae-seo-writer-schedule-run-now-status');

	function refreshSchedule() {
		api('schedule')
			.then(function (r) {
				if (nextRunEl) nextRunEl.textContent = r.next_run_formatted || '—';
			})
			.catch(function () {
				if (nextRunEl) nextRunEl.textContent = '—';
			});
	}

	document.getElementById('ae-seo-writer-schedule-enabled')?.addEventListener('change', function () {
		var enabled = this.checked;
		api('schedule', { method: 'POST', data: { enabled: enabled } })
			.then(function (r) {
				if (nextRunEl) nextRunEl.textContent = r.next_run_formatted || '—';
			})
			.catch(function (e) { alert(e.message || 'Error'); });
	});

	document.getElementById('ae-seo-writer-schedule-frequency')?.addEventListener('change', function () {
		var frequency = this.value;
		api('schedule', { method: 'POST', data: { frequency: frequency } })
			.then(function (r) {
				if (nextRunEl) nextRunEl.textContent = r.next_run_formatted || '—';
			})
			.catch(function (e) { alert(e.message || 'Error'); });
	});

	document.getElementById('ae-seo-writer-schedule-run-now')?.addEventListener('click', function () {
		if (!runNowStatusEl) return;
		runNowStatusEl.textContent = 'Running…';
		api('queue/run-next', { method: 'POST' })
			.then(function (r) {
				if (r.ok) {
					runNowStatusEl.textContent = r.run_id ? 'Run started.' : 'No pending topics.';
					refreshSchedule();
				} else {
					runNowStatusEl.textContent = r.message || 'Done.';
				}
			})
			.catch(function (e) {
				runNowStatusEl.textContent = e.message || 'Error';
			});
	});

	refreshSchedule();
})();
