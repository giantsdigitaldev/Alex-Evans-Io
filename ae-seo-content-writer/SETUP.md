# AE SEO Content Writer — Setup & next steps

## 1. Install the runner (Python app)

The plugin talks to a **runner** — a small Python API that runs the SEO pipeline (research, brief, write, image, send to WordPress). The runner lives in this repo under `automation/`.

- **From the repo**: If you cloned or downloaded the full project, the runner is at `automation/` (sibling to `blog/`). No separate GitHub “SEO blog writer” app is required; it’s part of this codebase.
- **Paths**:
  - Plugin: `blog/wp-content/plugins/ae-seo-content-writer/`
  - Runner: `automation/` (contains `dashboard_app.py`, `run_pipeline.py`, etc.)

## 2. Run the runner

From the **project root** (so `automation/` is available):

```bash
cd /var/www/alexevans   # or your project path
python3 -m venv .venv   # optional
source .venv/bin/activate
pip install fastapi uvicorn ...   # install deps from automation/requirements.txt if present
cd automation
uvicorn dashboard_app:app --host 0.0.0.0 --port 8765
```

Leave this terminal open. The runner will listen on `http://127.0.0.1:8765`.

- **Optional**: Use a process manager (systemd, supervisord) or `screen`/`tmux` so it keeps running after you log out.

## 3. Activate the plugin

1. In WordPress admin go to **Plugins**.
2. Find **AE SEO Content Writer** and click **Activate**.

On activation the plugin will:

- Set the default Runner URL to `http://127.0.0.1:8765` (if not already set).
- Create the topic queue table.
- Register the schedule (Cron) hook (schedule is off until you enable it).

## 4. Configure

1. **Settings → AE SEO Writer**  
   - Runner API URL should show `http://127.0.0.1:8765` (or your runner URL).  
   - Add **Anthropic** and **Gemini** API keys (and optional DataForSEO, WordPress username / Application Password).  
   - Save.

2. **AE SEO Writer → Dashboard**  
   - Click **Test connection**. You should see “Connection OK” when the runner is running.

## 5. Give it a topic and watch it work

**Option A — Single run**

1. **AE SEO Writer → New run**  
2. Enter a topic (e.g. “AI web design trends 2026”).  
3. Click **Start pipeline run**.  
4. You’ll be redirected to the Dashboard with that run open; refresh to see stages (research → brief → write → image → draft).

**Option B — Topic queue**

1. **AE SEO Writer → Topic queue**  
2. Add one or more topics (single add or bulk paste).  
3. Click **Run next** to send the first pending topic to the runner.  
4. Open **Dashboard** to see the run and its stages.

**Option C — Schedule**

1. **AE SEO Writer → Schedule**  
2. Enable “Automated runs” and choose **Daily at 8:30 am** (or another frequency).  
3. Add topics in **Topic queue**; the schedule runs **one topic from the queue** at each cron time (e.g. one post per day at 8:30 am).  
4. Use **Run now** to trigger one run immediately without waiting.

If your site has **DISABLE_WP_CRON** set in `wp-config.php`, add a system crontab so WordPress cron runs at 8:30 (or at least daily), e.g. from the project root:

```bash
30 8 * * * cd /var/www/alexevans/blog && wp cron event run --due-now
```

(Adjust path and timezone; the plugin’s “Daily at 8:30 am” uses your WordPress site timezone.)

## 6. Next steps & high-quality SEO (SEO Machine–style)

- **Done**: Topic queue, WP-Cron, Schedule UI, UI/UX (paper style), polish (retries, error messages, health), **real research** (DataForSEO in runner when credentials are set), **optimizer notes** in run detail.
- **Optional next**: Humanizer pass in optimizer; Slack/email when draft is ready; context editing from plugin.
- For full comparison to [SEO Machine](https://github.com/TheCraigHewitt/seomachine) and remaining steps for high-quality SEO articles, see **`docs/REMAINING-STEPS-AE-SEO-PLUGIN.md`** (in the project root docs folder).

See **Topic queue**, **Schedule**, and **Dashboard** for full control; use **TESTING.md** in this folder for a manual test checklist.

## 7. X (Twitter) — auto-post 4 tweets per article

Each pipeline run generates **4 X (Twitter) post** variants (engaging, curiosity-invoking, with the blog link). You can:

- **Post to X now**: On the run detail page, use **Post to X** next to each of the 4 posts to publish that tweet (with the article link) to [@alexevans_io](https://x.com/alexevans_io).
- **Schedule 4 daily posts**: Click **Schedule 4 daily posts** to auto-post one tweet per day for 4 days (8:30 am each day, in your site timezone). WP-Cron must run daily (see section 5).

**Required**: Add X API credentials in **Settings → AE SEO Writer** so the plugin can post on your behalf. See **X-API-CREDENTIALS.md** in this folder for exactly which keys and tokens to create in the [X Developer Portal](https://developer.x.com/).
