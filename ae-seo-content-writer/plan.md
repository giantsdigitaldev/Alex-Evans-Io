# AE SEO Content Writer — Upgrade Plan

This plugin is being upgraded to a **fully functional, fully featured** SEO blog writer with automation and a polished UI.

## Full plan (step-by-step)

See **`/var/www/alexevans/docs/PLAN-AE-SEO-PLUGIN-FULL-UPGRADE.md`** (or `docs/PLAN-AE-SEO-PLUGIN-FULL-UPGRADE.md` from repo root) for the complete implementation plan.

## Summary of phases

| Phase | Focus |
|-------|--------|
| **1** | Pipeline completeness: topic queue in plugin, real research (DataForSEO), optional humanizer, optional notify |
| **2** | Automation: WordPress Cron to run one topic from queue on a schedule; “Run now” and Schedule UI |
| **3** | UI/UX: Design system (alexevans.io/blog tokens), timeline, Topic queue page, Schedule page, polished run detail |
| **4** | Optional: Context file editing (brand_voice, internal links, etc.) inside plugin |
| **5** | Reliability: retries, logs, error states, Cron cleanup |

## References

- Pipeline stages and design: `docs/automation-pipeline-design.md`
- Next steps and dashboard spec: `docs/SEO-BLOG-AUTOMATION-PLAN-AND-NEXT-STEPS.md`
- Blog design tokens: `docs/blog-theme-design-system.md`
- Launch checklist: `docs/launch-metrics-and-sprint.md`

## Implementation order (from full plan)

1. Topic queue (DB + UI + “Run next”)
2. WP-Cron + Schedule UI
3. UI/UX (design system, timeline, pages)
4. Real research (DataForSEO)
5. Humanizer + optimizer notes
6. Notify (optional)
7. Context editing (optional)
8. Polish (retries, logs, errors)
