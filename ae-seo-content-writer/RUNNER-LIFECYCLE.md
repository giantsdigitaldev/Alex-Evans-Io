# Runner lifecycle: starting, staying up, restarts

## Start the runner from the app

You can start the runner by pressing **Start runner** on the Dashboard or in Settings **if**:

1. **Runner start command is set**  
   In **Settings → AE SEO Writer**, under "Runner start command (optional)", enter either:
   - The path to the start script, e.g. `/var/www/alexevans/automation/start_runner.sh`, or  
   - A full command, e.g. `cd /var/www/alexevans/automation && nohup uvicorn dashboard_app:app --host 0.0.0.0 --port 8765 &`  
   Save settings.

2. **Your server allows PHP to run shell commands**  
   The plugin uses PHP `exec()` to run that command in the background. Many hosts disable `exec()` in `disable_functions` for security. If you see an error like "This server does not allow starting the runner from PHP," the button will not work on that host. Use one of the options below instead.

**If the button doesn’t work:** Start the runner manually in a terminal (see SETUP.md) or run it as a systemd service (recommended for production) so it starts on boot and you don’t need the button.

---

## How often do I have to start the runner?

Only when it isn’t running:

- After a server reboot (unless you use systemd).
- After you’ve stopped it (e.g. closed the terminal or killed the process).
- After a crash (manual or systemd restart).

You do **not** need to start it again after each pipeline run. One runner process handles many runs.

---

## Will it run indefinitely?

Yes. Once started, the runner keeps running until:

- You stop it (Ctrl+C in the terminal, or `kill <pid>`).
- The server reboots (unless you use a service manager).
- The process crashes or is killed (e.g. out of memory).

It does not exit after a single request or after a run finishes.

---

## Will it freeze or stall? Do I have to restart it occasionally?

- **Normal use:** The API stays responsive while pipeline runs execute in background threads. You shouldn’t need to restart for each run.
- **Possible issues:** A bad request (e.g. an API hang to Anthropic or DataForSEO), a bug, or memory growth over time could cause a stall or crash.
- **Practical approach:** Restart the runner occasionally (e.g. weekly) if you run it manually. If you use systemd, you can set `Restart=on-failure` (or `always`) so it comes back after a crash.

---

## Recommended: run as a systemd service

So the runner starts on boot and restarts on failure, run it as a service instead of only from the app or terminal.

1. Copy the unit file from the repo and install it:

```bash
sudo cp /var/www/alexevans/automation/ae-seo-runner.service /etc/systemd/system/
```

Or create `/etc/systemd/system/ae-seo-runner.service` manually. The repo file looks like:

```ini
[Unit]
Description=AE SEO Content Writer runner
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/alexevans/automation
Environment=PATH=/usr/bin:/usr/local/bin
ExecStart=/var/www/alexevans/automation/venv/bin/python -m uvicorn dashboard_app:app --host 0.0.0.0 --port 8765
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Adjust `User`, `WorkingDirectory`, and `ExecStart` (e.g. `/usr/bin/python3` if you don’t use the venv) if needed.

2. Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable ae-seo-runner
sudo systemctl start ae-seo-runner
sudo systemctl status ae-seo-runner
```

3. After that, you usually don’t need to press **Start runner** in the app; the service keeps the runner up across reboots and restarts it if it crashes.
