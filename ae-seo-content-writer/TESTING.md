# AE SEO Content Writer — Testing & reliability checklist

Use this to verify functionality and reliability after changes.

**Health check (programmatic):** `GET /wp-json/ae-seo-writer/v1/health` (when logged in with `edit_posts`) returns `runner_ok`, `queue_ok`, `next_cron`, `schedule_enabled`. Use for smoke tests.

## 1. Settings & runner

- [ ] **Runner URL** — Settings → AE SEO Writer: Set default runner URL (or enter custom). Save. Dashboard shows the URL.
- [ ] **Test connection** — Dashboard or Settings: Click "Test connection". Expect "Connection OK" when runner is running at that URL; "Failed" or error when runner is down.
- [ ] **API keys** — Store Anthropic, Gemini, WordPress username, Application Password. Save. Keys are not printed in HTML (password fields).

## 2. Topic queue

- [ ] **Add topic** — Topic queue → Enter a topic → Add topic. Row appears with status "pending".
- [ ] **Bulk add** — Paste several lines (one topic per line) → Bulk add. All appear as pending.
- [ ] **List** — Queue table shows id, topic, status, run_id, created_at. Remove button only for pending.
- [ ] **Remove** — Click Remove on a pending topic. Row disappears.
- [ ] **Run next** — With runner running and API keys set: Click "Run next". One pending topic is sent to runner; redirect to Dashboard with that run_id, or message "No pending topics" if queue empty. Queue row for that topic shows status "done" and run_id link.

## 3. Schedule (Cron)

- [ ] **View Schedule** — Schedule page shows Enable toggle, Frequency, Next run, Run now.
- [ ] **Enable** (admin only) — Check "Enable automated runs". Next run shows a future time. Uncheck: Next run shows "—".
- [ ] **Frequency** (admin only) — Change frequency; Next run updates.
- [ ] **Run now** — Click "Run now". Same as "Run next" on Topic queue: one topic from queue is run. Status text updates; Next run may update.
- [ ] **Cron callback** — With automation enabled and pending topic in queue, wait for next scheduled time (or trigger cron manually: `wp cron event run ae_seo_writer_cron_run`). One topic should run and queue row marked done.

## 4. Dashboard & run detail

- [ ] **Runs list** — Dashboard loads; shows runs (or "No runs yet"). Click a run → run detail.
- [ ] **Run detail** — Progress (timeline with stages), Research (queries, links), Brief (outline, internal links), Image (if any), Draft editor. Save draft, Send to WordPress (draft), Publish.
- [ ] **Image** — If run has image_path, image loads (via plugin proxy). No broken image.

## 5. New run

- [ ] **Single run** — New Run → Enter topic → Start pipeline run. Redirects to Dashboard with that run_id. Run appears in list.

## 6. Reliability

- [ ] **Runner down** — With runner URL set but runner not running: Test connection fails. Run next / Run now show error. No PHP errors.
- [ ] **No runner URL** — Dashboard shows "Set default runner URL"; list may show error. Set default → reload → Test connection.
- [ ] **REST 404** — Ensure permalinks are not "Plain". Use "Post name" or another structure so `/wp-json/ae-seo-writer/v1/...` works.
- [ ] **Deactivate plugin** — Deactivate: Cron is cleared (no orphan events). Activate again: Table exists, Cron can be re-enabled.

## 7. Variables to test (summary)

| Variable / flow        | How to test |
|------------------------|-------------|
| Runner URL             | Set, save, Test connection |
| API keys               | Save; run a pipeline (needs keys for Claude/Gemini/WP) |
| Queue add / bulk / list| Add topic, bulk add, refresh list |
| Queue run next         | Run next with runner up + pending topic |
| Schedule enabled       | Toggle (admin); Next run appears |
| Schedule frequency     | Change; Next run updates |
| Run now                | Click; one topic runs |
| Dashboard runs list    | Open dashboard; runs load |
| Run detail             | Click run; stages, research, draft editor |
| Send to WordPress      | Edit draft → Send to WordPress (draft); post in WP |
| Publish                | Publish → confirm; post goes live |
