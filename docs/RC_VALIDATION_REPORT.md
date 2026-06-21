# RC Validation Report — Vacuum Image Optimizer 0.9.0-rc.1

**Phase:** 8.6 — Clean Install QA & RC Validation
**Date:** 2026-06-19
**Method:** End-to-end code trace of every user workflow from a fresh-install
perspective. Read-only review except for confirmed bug fixes.

---

## Task 1 — Clean Install Review

| Check | Result |
|-------|--------|
| Activation hook → `Installer::activate()` | ✅ runs requirements check, table creation, defaults, cron |
| PHP requirement gate | ✅ `wp_die` on PHP < 8.1 with a clear message |
| Tables created (`vio_queue`, `vio_stats`, `vio_backups`) via `dbDelta` | ✅ correct schema + indexes |
| Default options (`vio_settings`, `vio_queue_state`, `vio_db_version`, `vio_phase5_queue_ready`) | ✅ set; `backup_retention_days` default `0` included |
| Cron registration (`vio_cleanup_backups`, daily) | ✅ scheduled on activate, handled by `BackupCleanup` |
| `maybe_upgrade()` on admin_init | ✅ re-runs table/defaults when version changes; merges new keys non-destructively |

**No activation issues found.**

## Task 2 — First User Experience

| Check | Result |
|-------|--------|
| Menu under **Media → Vacuum Optimizer** | ✅ `add_submenu_page`, cap `manage_options` |
| Router dispatch → 9 tabs | ✅ all map to existing view classes (Dashboard, BulkOptimize, Formats, Compression, LazyLoad, BackupRestore, Exclusions, Reports, SystemStatus) |
| Dashboard / Reports / System Status / Localization | ✅ all reachable; assets enqueued on `media_page_vacuum-image-optimizer` |
| Media Library column + row actions | ✅ enqueued on `upload.php`; translatable labels |
| Asset references | ✅ `admin-icon.svg` + `icon.svg` exist; no broken refs after 8.5 cleanup |

**No confusing/blocking UX found.** (Minor: 5 row actions show on every image regardless of state — cosmetic, documented in 8.3.)

## Task 3 — WebP Flow Validation (Upload → Generate → Delivery → Reports → Restore)

- **Generate:** `WebPGenerator` writes `_vio_webp_path/url/size`, `_vio_source_size`, `_vio_savings_*`, `_vio_engine_used`, `_vio_optimized_at`, `_vio_status=optimized`; registers a derivative Library item (`_vio_webp_attachment_id`). ✅
- **Delivery:** `DeliveryEngine::swap_url()` serves `.webp` only when the file exists; never rewrites DB URLs. ✅
- **Reports:** `StatsService` reads the same meta keys the engine writes; `get_report_summary` cached 5 min. ✅
- **Restore:** `RestoreEngine` deletes the WebP file + derivative attachment, clears link/optimization meta, status → pending. ✅
- **Metadata lifecycle:** writer keys ↔ reader keys ↔ cleanup keys all match. ✅

## Task 4 — AVIF Flow Validation

- **Generation:** `AVIFGenerator` writes `_vio_avif_path/url/size`, `_vio_avif_savings_*`, `_vio_avif_engine_used`, `_vio_avif_generated_at`; registers derivative (`_vio_avif_attachment_id`). Parallel format — AVIF failure never fails WebP. ✅
- **Reporting:** `get_avif_generated_count` / `get_total_avif_size` / `get_avif_total_savings` read the keys the engine writes. ✅
- **Cleanup + restore:** `RestoreEngine::remove_derivatives()` reads `_vio_avif_attachment_id` and `_vio_avif_path` (both set by the engine), deletes file + attachment; `reset_optimization_metadata()` clears all `_vio_avif_*`. ✅

## Task 5 — Bulk Queue Validation (Scan → Queue → Process → Retry → Complete)

- **Scan:** `scan_library()` queues eligible JPEG/PNG not already optimized; `has_queue_entry` dedup. ✅
- **Process:** `QueueProcessor` uses atomic `claim_job()` (8.4) — no double-processing. ✅
- **Retry:** manual only; `retry_job()` enforces `VIO_MAX_RETRIES` (3); exhausted jobs stay failed; reason surfaced via AJAX error message → `showError()` in JS. ✅
- **Complete:** state flips to `idle` when no pending/processing remain. ✅
- **JS ↔ controller:** all 7 AJAX actions match exactly. ✅

## Task 6 — Upload Automation Validation

| State | Behavior | Result |
|-------|----------|--------|
| Disabled | `handle_new_attachment` returns early | ✅ |
| Queue | `add_attachment` + `_vio_auto_processed=queue` | ✅ |
| Immediate | synchronous WebP (+AVIF if enabled) + `_vio_auto_processed=immediate` | ✅ |
| Failure | caught, `_vio_status=error` stored, upload never interrupted | ✅ |

## Task 7 — Localization Validation

- **Switching:** `filter_plugin_locale` overrides only this text domain when a language is selected. ✅
- **Fallback:** invalid/unknown locale → WordPress default; untranslated strings → English (by design). ✅
- **Coverage:** 9 catalogs each had 258/258 translated as of 8.3.

**Remaining untranslated text (English fallback — cosmetic, not functional):**
1. **POT is stale** — strings added in 8.3–8.4 are not yet extracted, so they show English in all 9 locales:
   - `Regenerate` (Media row action)
   - `Backup Retention`, `days`, *"Automatically delete original backups older than this many days during the daily cleanup…"*
   - *"This job reached the maximum of %d attempts and will stay failed."*
2. **Hardcoded JS strings** in `assets/admin/js/admin.js` not passed through `wp_localize_script`:
   - `No failed jobs.`
   - `Retry`

> Recommendation (pre-1.0 i18n pass, not an RC blocker): regenerate the POT with
> `wp i18n make-pot`, move the two JS strings into the `vioQueue.i18n` bundle, and
> retranslate the delta across the 9 locales.

## Task 8 — WordPress.org Submission Review

| Requirement | Status |
|-------------|--------|
| `readme.txt` header (Stable tag 0.9.0, Tested up to 6.8, Requires PHP 8.1) | ✅ complete |
| Description / FAQ / Changelog / Upgrade Notice | ✅ present |
| Plugin header + text domain matches slug | ✅ |
| `uninstall.php` cleanup | ✅ (fixed this phase) |
| License references (GPLv2-or-later) consistent | ✅ |
| **Screenshots** `screenshot-1..7.png` | ❌ referenced in readme but not produced (SVN `/assets/`) |
| **Icon** `icon-256x256.png` / `icon-128x128.png` | ❌ only SVG concept exists |
| **Banner** `banner-772x250.png` / `banner-1544x500.png` | ❌ only SVG concept exists |

## Task 9 — Final RC Verdict

**NEARLY READY** for `0.9.0-rc.1`.

**Reasoning:** Every functional workflow — install, activation, WebP, AVIF, queue,
upload automation, delivery, reports, restore, backup cleanup, uninstall — traces
end-to-end with consistent metadata lifecycles and no functional defects. The one
code bug found (uninstall left an orphaned option + transient) is **fixed**.
What remains are **non-code, non-functional** gaps: the WordPress.org listing media
(screenshots/icon/banner) must be produced, and a pre-1.0 i18n pass should refresh
the POT and localize two JS strings. None of these block RC packaging or testing;
they block the *public .org submission*. The RC zip itself is ready to build and test.

---

## Bugs Found / Fixed This Phase

| Bug | Severity | Status |
|-----|----------|--------|
| `uninstall.php` did not remove `vio_last_backup_cleanup` option (added in 8.4) | Low | **Fixed** |
| `uninstall.php` did not clear the `vio_report_summary` transient (orphaned `_transient_*` rows) | Low | **Fixed** |

## Remaining (non-blocking) Issues
- Stale POT + untranslated 8.3–8.4 strings (English fallback works).
- Two hardcoded English JS strings (`No failed jobs.`, `Retry`).
- WordPress.org listing media not yet produced.

## Validation
```
php -l vacuum-image-optimizer.php                       → No syntax errors
find src -maxdepth 5 -name "*.php" -exec php -l {} \;    → 34/34 clean
php -l uninstall.php                                     → No syntax errors
```
