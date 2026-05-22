# alexevans.io - Complete Backup (2026-05-22)

Complete backup of Alex Evans' personal website at alexevans.io

## Contents
- **alexevans-blog-files-20260522.tar.gz** - Full WordPress installation (36.4 MB)
  - WordPress core, themes, plugins, uploads
  - wp-config.php with database credentials
  - All blog posts and pages

- **ae-seo-content-writer/** - Custom SEO blog writer plugin
  - AI-powered content pipeline (research → write → optimize → image → publish)
  - Topic queue with WP-Cron scheduling
  - Image generation via Gemini/DALL-E
  - X/Twitter auto-posting
  - DataForSEO integration

- **README.md** - This file

## Source
- VPS: 187.124.232.90
- WordPress path: /var/www/alexevans/blog/
- Database: alexevans_blog (MariaDB in Docker container)

## Restoration
1. Extract files to /var/www/alexevans/blog/ on the VPS
2. Restore database backup via MySQL/MariaDB
3. Ensure nginx serves the WordPress directory
4. Verify wp-config.php database credentials

## GitHub
Full repo: https://github.com/giantsdigitaldev/Alex-Evans-Io
