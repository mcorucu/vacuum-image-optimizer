# Launch Readiness Review — Vacuum Image Optimizer

**Phase:** 9 — Real-World QA & WordPress.org Launch Preparation
**Date:** 2026-06-19
**Plugin version:** 0.9.0
**Scope:** QA, UX review, asset-production planning, launch prep. **No code, architecture, database, queue, or engine changes** — recommendations only.

---

## Task 1 — Complete Admin UI Review (recommendations only)

Reviewed all nine admin screens (Dashboard, Bulk Optimize, WebP & AVIF, Compression, Lazy Load, Exclusions, Backup & Restore, Reports, System Status). The UI is consistent overall (shared `vio-card`, `vio-toggle`, `vio-form-table`, `form-table` patterns; tab labels fully translated). Findings below are **polish items**, not blockers.

### High-value findings

| # | Severity | Screen | Finding | Recommendation |
|---|----------|--------|---------|----------------|
| 1 | **High (UX)** | WebP & AVIF | The tab is a **static placeholder**: "Generate WebP" is `checked disabled` and "Generate AVIF" is `disabled` — neither control does anything. The **real** AVIF enable/quality settings live on the **Compression** tab. A user will toggle "Generate AVIF" here and nothing happens. | Before public launch, either (a) convert this tab to a **read-only status panel** ("WebP: always on · AVIF: enabled/disabled") with a link to Compression, or (b) wire the toggles to the actual settings. Do not ship inert toggles. |
| 2 | **Medium (IA)** | Compression | **Overloaded**: one form mixes six concerns — Compression Profile, Quality, Upload Automation, AVIF Generation, Frontend Delivery, Interface Language. Several duplicate the purpose of dedicated tabs. | Regroup post-launch: move **AVIF** to the WebP & AVIF tab, **Frontend Delivery** next to Lazy Load (both are "frontend"), and **Interface Language** to a general/Settings area. Keep Compression to profile + quality. |
| 3 | **Medium (wording)** | Global | Menu item reads **"Vacuum Optimizer"** while the page title and all docs say **"Vacuum Image Optimizer."** | Standardize on "Vacuum Image Optimizer" (or accept the short menu label intentionally and note it). Cosmetic; no functional impact. |
| 4 | **Low (IA)** | Compression / Lazy Load | Frontend Delivery (Compression) and Lazy Load (own tab) are both frontend-delivery concerns but split across tabs. | Consider a single "Frontend" tab post-launch. |
| 5 | **Low (consistency)** | Several views | Capability `wp_die` guard is present on Compression, LazyLoad, Exclusions, BackupRestore, Reports, but Dashboard/SystemStatus/BulkOptimize/Formats rely on the Router's `manage_options` gate only. | Harmless (Router already gates the page), but for consistency a guard could be added everywhere. No action needed for launch. |

### Spacing / alignment / visual
- Card, toggle, and form-table styling is centralized in `assets/admin/css/admin.css` (self-contained, single `:root` token set) — **no per-view inline styles**, so spacing/alignment are globally consistent. No spacing defects found in markup.
- Status lists (System Status) and stat cards (Dashboard/Reports) use shared classes — visually consistent.

### Duplicated information
- AVIF messaging appears on both **WebP & AVIF** (placeholder) and **Compression** (real). Resolving finding #1/#2 removes the duplication.
- Format fallback chain "AVIF → WebP → Original" is shown on the WebP & AVIF tab and described again under Frontend Delivery on Compression — acceptable, but consolidating helps.

**Conclusion:** No visual/spacing defects. The one launch-relevant item is the **placeholder WebP & AVIF tab (finding #1)** — recommend addressing before the public listing so screenshots and first-run UX don't show inert controls.

---

## Task 2 — First-Time User Experience Review (suggestions only)

Walking through a brand-new user's path:

| Stage | Experience | Friction | Suggestion |
|-------|-----------|----------|------------|
| Install | Standard upload/activate; no errors. | None. | — |
| Activation | Tables + defaults + cron created silently; lands on Plugins list. | **No "what next?" pointer.** | Add a one-time activation notice/redirect to the Dashboard (post-launch; not a blocker). |
| First visit | Dashboard shows zeros; tabs all reachable. | A new user may not know to start at **System Status** then **Bulk Optimize**. | A short "Getting started" 1-2-3 card on the Dashboard would orient users. |
| First optimization | Per-image **WebP** action in Media Library works immediately. | The **WebP & AVIF tab's disabled toggles** mislead (see Task 1 #1). | Fix the placeholder tab. |
| First queue run | Scan → Start → progress is clear; pause/resume work. | Queue runs only while the **Bulk Optimize tab stays open** (AJAX-driven); leaving the page pauses progress. This is documented but not surfaced in-UI. | Add an inline hint: "Keep this tab open while the queue runs." |
| First report export | One-click CSV; streamed, safe at scale. | Export button purpose is clear; no friction. | — |
| AVIF discovery | Must find it under **Compression**, not the "WebP & AVIF" tab. | Discoverability mismatch (Task 1 #1/#2). | Regroup settings post-launch. |

**Top friction points:** (1) placeholder WebP & AVIF tab, (2) no first-run orientation, (3) queue "keep tab open" not surfaced. All are **polish**, none block launch.

---

## Task 3 — WordPress.org Screenshot Content (photographer-style checklist)

Capture at **1280×800**, default "Fresh" admin color scheme, browser zoom 100%, on a site seeded with ~20–50 optimized images so numbers are realistic. Crop to the plugin content area.

### screenshot-1.png — Dashboard
- **Screen:** Media → Vacuum Image Optimizer → Dashboard.
- **Sample data:** ≥20 optimized images; non-zero savings (e.g., "42% saved", "180 MB → 104 MB"); a few recent-activity rows.
- **Ideal layout:** KPI cards in top row, savings highlighted, feature-status chips visible.
- **Visible:** total/optimized counts, space saved, WebP/AVIF generated, delivery + automation status.
- **Hidden:** other plugins' admin notices, unrelated WP admin bar items, personal site name (use a neutral demo title).

### screenshot-2.png — Media Library integration
- **Screen:** Media Library, **list view**.
- **Sample data:** mix of optimized + not-optimized JPEG/PNG; at least one row showing sizes + "Saved %".
- **Ideal layout:** the **Vacuum** column wide enough to show status + sizes; hover a row to reveal the WebP/AVIF/Restore actions.
- **Visible:** Vacuum status column, per-image actions.
- **Hidden:** bulk-action dropdown open state, unrelated columns if cramped.

### screenshot-3.png — Bulk Optimize
- **Screen:** Bulk Optimize tab, **mid-run**.
- **Sample data:** queue with some completed, a few pending, ideally 1 failed row to show Retry.
- **Ideal layout:** progress bar partially filled; Scan/Start/Pause/Resume row visible; stats counters populated.
- **Visible:** progress %, queue counts, controls, failed-jobs/retry area.
- **Hidden:** browser download bar, console.

### screenshot-4.png — Compression
- **Screen:** Compression tab.
- **Sample data:** Balanced profile selected, quality ~85, AVIF enabled.
- **Ideal layout:** profile selector + quality slider near top; AVIF + automation toggles visible.
- **Visible:** profiles, quality, AVIF, automation mode.
- **Hidden:** the long lower sections if they push the shot too tall — crop to the top settings block.

### screenshot-5.png — Reports
- **Screen:** Reports tab.
- **Sample data:** populated Storage Savings, Recent Activity, Top Savings tables.
- **Ideal layout:** summary figures on top, one data table visible, **Export CSV** button in frame.
- **Visible:** savings totals, activity table, export button.
- **Hidden:** empty states, truncated half-rows at the crop edge.

### screenshot-6.png — System Status
- **Screen:** System Status tab.
- **Sample data:** a healthy server (WebP available, uploads writable, queue table present).
- **Ideal layout:** Server Environment + Engine Support cards with green "Available" badges.
- **Visible:** PHP/WP versions, Imagick/GD, WebP/AVIF support, writable paths, queue table.
- **Hidden:** any real server hostnames/paths that leak environment details — use a demo server.

### screenshot-7.png — Localization
- **Screen:** Compression tab → Interface Language control (optionally show the UI rendered in a non-English locale).
- **Sample data:** language dropdown open or set to e.g. Deutsch/Türkçe.
- **Ideal layout:** the selector plus a glimpse of translated labels to prove localization.
- **Visible:** language selector + at least one translated section.
- **Hidden:** mixed half-translated states from a stale catalog (use a fully translated locale).

---

## Task 4 — WordPress.org Icon Prompts

Brand reference: rounded gradient tile (`#22C55E`→`#3B82F6`), white "sun over mountains" image glyph, purple `#A855F7` optimization spark, subtle white compression ring. Mark-only (no text).

**Prompt — `icon-256x256.png`:**
> A modern, flat WordPress plugin icon, 256×256px, perfectly square with transparent corners. A rounded-square tile (corner radius ~64px) filled with a smooth 45° linear gradient from emerald green `#22C55E` (top-left) to blue `#3B82F6` (bottom-right). Centered on the tile, a clean white minimalist "image" glyph: a small sun circle upper-left and a simple mountain-range silhouette across the lower third. A small purple `#A855F7` circular "spark" in the upper-right of the tile. A very thin white ring at ~35% opacity encircling the glyph. No text, no gradients on the glyph (pure white), no drop shadows, flat vector style, crisp edges, sRGB. Generous interior padding so the glyph sits within the central 70%.

**Prompt — `icon-128x128.png`:**
> Same artwork as the 256px icon, simplified for small size: rounded-square gradient tile (`#22C55E`→`#3B82F6`), white sun + mountain glyph, purple `#A855F7` spark upper-right. **Omit the thin compression ring** to keep it legible at 128px. No text, flat vector, transparent corners, sRGB, pixel-snapped. (Best produced by exporting the 256 vector at 128 rather than regenerating.)

---

## Task 5 — WordPress.org Banner Prompts

Brand reference: tri-color gradient `#22C55E`→`#3B82F6`→`#A855F7`, faint white grid, white logo tile left, white title + subtitle, decorative speed lines right.

**Prompt — `banner-1544x500.png` (produce first):**
> A wide WordPress.org plugin banner, 1544×500px. Full-bleed diagonal gradient background from emerald `#22C55E` (top-left) through blue `#3B82F6` (center) to purple `#A855F7` (bottom-right), overlaid with a faint 40px white grid pattern at ~10% opacity. In the left third, a white rounded-square logo tile containing a clean green `#22C55E` pixel/image mark, vertically centered. To its right, bold white title "Vacuum Image Optimizer" (~72px, weight 700) with a thin white subtitle below at ~90% opacity: "Lightning-fast image compression for WordPress" (~32px, weight 400). On the right side, a few diagonal white "speed line" strokes at ~30% opacity that do not overlap the text. Clean, modern, flat, high contrast. Keep all text/logo within a 7% inner safe margin; leave the lower-left strip free of essential elements. sRGB, no transparency.

**Prompt — `banner-772x250.png`:**
> The exact same composition as the 1544×500 banner, scaled to 772×250 (downscale the 1544 master, do not recompose). Title ~36px, subtitle ~16px, logo and speed lines proportionally smaller. Maintain the 7% inner safe margin and identical gradient, grid, and layout.

---

## Task 6 — Pre-Launch Test Plan (step-by-step)

> Run on a clean WordPress 6.2+ / PHP 8.1+ site with `WP_DEBUG` on. Also see `docs/PRE_RELEASE_CHECKLIST.md`.

### 6.1 Clean Install Test
1. Fresh WP site, no prior `vio_*` data.
2. Upload the candidate zip → **Activate**.
3. Confirm: no PHP notices; tables `wp_vio_queue/stats/backups` exist; options `vio_settings/vio_queue_state/vio_db_version` set; `vio_cleanup_backups` cron scheduled.
4. Open **Media → Vacuum Image Optimizer** → Dashboard renders with zeros.

### 6.2 Upgrade Test
1. Activate an older build (or set `vio_db_version` to a lower value).
2. Load any admin page → `Installer::maybe_upgrade()` runs.
3. Confirm: tables intact, `vio_settings` gains new keys (e.g. `backup_retention_days`) without losing existing values; `vio_db_version` updated; no data loss.

### 6.3 Bulk Queue Test
1. Upload 10–20 JPEG/PNG.
2. Bulk Optimize → **Scan Library** → confirm queued count.
3. **Start Queue** → progress advances; **Pause** then **Resume** work.
4. Force a failure (e.g. unreadable file) → appears in Failed Jobs with an error; **Retry** re-queues; after 3 attempts it stays failed with the limit reason shown.
5. Trigger the batch endpoint twice quickly → confirm no image processed twice (atomic claim).
6. Queue reaches **idle** when empty.

### 6.4 Upload Automation Test
1. **Disabled:** upload an image → no optimization, status untouched.
2. **Queue mode:** enable, upload → image added to queue, `_vio_auto_processed=queue`.
3. **Immediate mode:** switch, upload → WebP (+AVIF if on) generated synchronously, `_vio_auto_processed=immediate`.
4. Confirm an automation failure never breaks the upload.

### 6.5 Frontend Delivery Test
1. Optimize an image; enable **Frontend Delivery**, Preferred = Auto.
2. Visit a front-end page using that image.
3. AVIF-capable browser → receives `.avif`; WebP-only → `.webp`; legacy → original.
4. Confirm `srcset` candidates swap where derivatives exist; **no broken URLs**; DB URLs unchanged.
5. Disable delivery → originals served on next load.

### 6.6 Restore Test
1. With backups enabled, optimize an image (WebP + AVIF).
2. Media Library → **Restore**.
3. Confirm: original restored; `.webp`/`.avif` files deleted; derivative Media Library items removed; optimization meta cleared; status → "Not optimized"; front-end serves the original again.

### 6.7 Uninstall Test
1. **Deactivate** → `vio_cleanup_backups` cron unscheduled.
2. **Delete** → `uninstall.php` removes `vio_*` options (incl. `vio_last_backup_cleanup`), the report transient, custom tables, and `_vio_*` meta.
3. Confirm image files (originals, derivatives, backups) remain on disk by design; no orphaned options/cron.

---

## Task 7 — Final 1.0 Roadmap (realistic only)

### Version 1.0 — Public launch (polish, no new engineering)
- Produce WordPress.org listing assets (icon, banner, 7 screenshots).
- Resolve the **WebP & AVIF placeholder tab** (status panel or wire the toggles).
- Pre-1.0 i18n pass: regenerate POT, localize the two JS strings (`No failed jobs.`, `Retry`), refresh the 9 catalogs.
- Optional first-run orientation (activation notice / "getting started" card).
- Promote `0.9.0` → `1.0.0` once assets + checklist pass.

### Version 1.1 — Convenience & operability
- **WP-CLI** command for bulk optimize / status (`wp vio optimize`).
- **Background queue** via WP-Cron (process without keeping the Bulk Optimize tab open).
- **Bulk restore** and "**clear completed queue rows**" maintenance action.
- Settings IA cleanup (regroup AVIF/Delivery/Language per Task 1 #2).
- Per-row re-optimize/regenerate from the Reports table.

### Version 1.2 — Coverage & scale
- **Intermediate image sizes**: generate WebP/AVIF for thumbnail sizes (currently only the full-size derivative).
- Optional **`<picture>` element** delivery mode (in addition to URL swapping).
- **Multisite** support.
- **CDN / offloaded-media** awareness (skip/flag remote uploads cleanly).
- Scheduled/recurring bulk optimization.

*(Resize-on-upload / max-dimensions is intentionally deferred — it modifies originals and conflicts with the plugin's non-destructive guarantee; only as an explicit opt-in if ever added.)*

---

## Task 8 — GO / NO-GO Decision

**Decision: (B) Publish 0.9.0 after screenshots/assets.**

**Reasoning:**
- The plugin is **feature-complete and functionally validated** end-to-end (Phase 8.6): install, WebP, AVIF, queue, automation, delivery, reports, restore, backup cleanup, and uninstall all pass; `php -l` is clean across 34 files.
- **(A) Publish today is a NO** — a public `.org` listing requires the icon, banner, and 7 screenshots referenced by `readme.txt`; none exist yet, so the listing would be broken. The placeholder **WebP & AVIF tab** should also be tidied before first-run screenshots.
- **(C) Continue development is unwarranted** — no functional gaps remain; outstanding work is **packaging, branding, and polish**, not engineering.
- Therefore: finish the **launch assets** (specs/prompts in this doc), tidy the placeholder tab, run the pre-launch test plan, then publish **0.9.0** (promote to **1.0.0** when ready). Distribute **0.9.0-rc.1** privately in the meantime for real-world feedback.

---

## Validation
```
php -l vacuum-image-optimizer.php                       → No syntax errors
find src -maxdepth 5 -name "*.php" -exec php -l {} \;    → 34/34 clean
```

No code was modified in this phase.
