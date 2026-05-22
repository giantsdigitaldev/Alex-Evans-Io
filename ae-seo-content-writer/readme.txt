=== AE SEO Content Writer ===
Contributors: alexevans
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later

Full-featured SEO blog pipeline inside WordPress: content research, creation, image generation (Gemini), refinements, and human review. Dashboard and settings for API keys; full visibility into every stage before publishing.

== Description ==

* **Settings** — Store API keys (Anthropic, Gemini, DataForSEO, WordPress Application Password) and the Runner API URL.
* **Dashboard** — List all pipeline runs; open any run to see full progress, research (queries and links), brief (outline, internal links), generated image (Gemini / Nano Banana style), and the full blog post in an editor. Save draft, send to WordPress as draft, or publish.
* **New Run** — Enter a topic and start a pipeline run (research → brief → write → optimize → image → upload → draft).
* **Image compression** — Generated hero images are automatically resized and compressed to under 200 KB before upload to WordPress (featured image ready).

Requires the Python runner to be running (see docs/automation-pipeline-design.md and automation/README.md). The plugin proxies all requests to the runner so you manage everything from WordPress.

== Installation ==

1. Upload the plugin folder to wp-content/plugins/ae-seo-content-writer.
2. Activate the plugin.
3. Go to Settings → AE SEO Writer and set Runner API URL (e.g. http://127.0.0.1:8765) and API keys.
4. Start the runner: from the automation directory, run `uvicorn dashboard_app:app --host 0.0.0.0 --port 8765`.
5. Use AE SEO Writer in the admin menu to open the dashboard and start runs.

== Frequently Asked Questions ==

= Where does the runner run? =

On the same server as WordPress (or another host reachable from the server). The plugin calls the runner via server-side HTTP (no CORS). Configure the Runner API URL in Settings.

= Do I need to create a WordPress Application Password? =

Yes, if the runner creates drafts on this site. Create one under Users → Your Profile → Application Passwords and store it in the plugin Settings. The runner uses it to POST drafts via the WordPress REST API.
