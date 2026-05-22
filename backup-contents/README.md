# alexevans.io - Complete Backup (2026-05-22)

Complete backup of Alex Evans' personal website at alexevans.io

## Contents
- **alexevans-blog-files-20260522.tar.gz** (36.4 MB) - Full WordPress installation
  - WordPress core, themes, plugins, uploads
  - wp-config.php (PostgreSQL via PG4WP)
  - All blog posts and pages
  
- **alexevans-blog-pg-20260522.sql** (0.4 MB) - PostgreSQL database dump
  - alexevans_blog database (PostgreSQL 17)
  - All WordPress tables, posts, settings
  
- **ae-seo-content-writer/** - Custom SEO blog writer plugin
  - AI-powered content pipeline (research → write → optimize → image → publish)
  - Topic queue with WP-Cron scheduling
  - Image generation via Gemini/DALL-E
  - X/Twitter auto-posting
  - DataForSEO integration

## Source
- VPS: 187.124.232.90
- WordPress path: /var/www/alexevans/blog/
- Database: alexevans_blog (PostgreSQL 17 on host, port 5432)
- pg4wp plugin: /var/www/alexevans/blog/wp-content/pg4wp/

## GitHub
https://github.com/giantsdigitaldev/Alex-Evans-Io
