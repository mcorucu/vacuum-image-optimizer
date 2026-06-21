# Vacuum Image Optimizer — Production Readiness Report

**Phase:** 8.3 — Real-World QA & Stress-Test Audit
**Audit date:** 2026-06-19
**Plugin version audited:** 0.9.0
**Audit type:** Production-oriented QA & scalability audit (audit-only; no architecture changes)

---

## 0. Scope & Method

This was a read-only scalability and QA audit of the full plugin surface. No
architecture was redesigned and no major features were added. One low-risk
defect was fixed (untranslated Media Library row-action labels). All findings
below are derived from static inspection of the source, the database schema,
the cron wiring, and the translation catalogs.

**Validation run (required):**

```
php -l vacuum-image-optimizer.php            → No syntax errors
find src -maxdepth 5 -name "*.php" -exec php -l → 33/33 files, No syntax errors
```

---

## 1. Files Inspected

| Area | Files |
|------|-------|
| Bootstrap | `vacuum-image-optimizer.php`, `src/Plugin.php` |
| Install / lifecycle | `src/Core/Installer.php`, `src/Core/Uninstaller.php`, `uninstall.php` |
| Queue | `src/Queue/QueueManager.php`, `src/Queue/QueueProcessor.php`, `src/Queue/AjaxController.php` |
| Engine | `src/Engine/WebPGenerator.php`, `src/Engine/AVIFGenerator.php`, `src/Engine/RestoreEngine.php` |
| Stats / Reports | `src/Stats/StatsService.php`, `src/Admin/ReportExporter.php` |
| Media Library | `src/Media/LibraryIntegration.php`, `src/Media/AttachmentActions.php`, `src/Media/DerivativeLibrary.php` |
| Frontend delivery | `src/Frontend/DeliveryEngine.php`, `src/Frontend/LazyLoad.php` |
| Backup | `src/Backup/BackupManager.php`, `src/Backup/BackupPathHelper.php` |
| Upload automation | `src/Upload/UploadAutomation.php` |
| Settings / i18n | `src/Settings/CompressionSettings.php`, `src/Admin/Router.php` |
| Translations | `languages/*.pot`, 9 × `.po` / `.mo` (de, es, fr, it, nl, pl, pt, ru, tr) |

---

## 2. Task-by-Task Findings

### TASK 1 — Media Library Scale Audit (100 / 1k / 5k / 10k images)

**Custom columns** (`LibraryIntegration::render_column`)
- The Vacuum column is rendered **per visible row only** (20–100 rows per page), so total library size does not affect column cost linearly. ✅
- **Per-row cost:** several `get_post_meta()` calls + one `get_attached_file()` + a `filesize()` **disk stat** on the original for every non-optimized row. At 100 rows/page this is up to ~100 `filesize()` syscalls per page load. Acceptable, but it is real disk I/O that scales with page size, not library size. **(Low)**
- Generated WebP/AVIF derivative rows short-circuit to a cheap provenance indicator. ✅

**Attachment actions** (`AttachmentActions::add_row_actions`)
- Adds **5 row actions to every image** with no DB queries (URL building only). No scale bottleneck. The links are shown even for already-optimized images (UI clutter, not a performance issue). ✅

**Generated WebP / AVIF attachments** (`DerivativeLibrary`)
- Each optimized source can spawn **one WebP + one AVIF Media Library attachment**. At 10k sources with AVIF enabled this means the `wp_posts` attachment count can grow to **~30k items**, inflating the Media Library grid/list, `attachment` queries, and any `WP_Query` over attachments site-wide. This is by design and dedup-protected (`_vio_*_attachment_id` link meta + `find_attachment_by_file`), but it is the single largest scale multiplier in the plugin. **(Medium — architectural, documented, not fixed per scope)**
- `StatsService::get_total_images()` correctly excludes derivative attachments via `NOT EXISTS (_vio_generated_by)`, so counts stay accurate. ✅

**Bottleneck summary:** derivative-attachment multiplication (3× row count) and per-row `filesize()` on the list table. Both bounded and acceptable through 10k sources; recommend documenting the attachment-count impact for very large libraries.

---

### TASK 2 — Queue Scale Audit

**Table growth** — `vio_queue` has indexes `KEY status_created (status, created_at)` and `KEY attachment_id`. `get_pending_jobs()` uses `WHERE status=? ORDER BY created_at,id LIMIT n` → index-served. ✅ Rows are never pruned after completion, so the table grows to one row per processed attachment and stays there. At 10k+ this is fine for queries (indexed) but the table is effectively a permanent ledger. **(Low — recommend optional "clear completed" maintenance.)**

**Retry system** — Retries are **manual only** (`retry_queue_job` AJAX → `retry_job` resets `failed`→`pending`). The processor never auto-re-queues a failed job. ✅ → **No infinite-retry risk.** `attempts` is incremented on each failure but is **never enforced as a ceiling**; a user can retry indefinitely. Harmless but unbounded. **(Low)**

**Failed jobs** — Captured with `error_message`, surfaced via `format_failed_jobs()` (limited to 20). ✅

**Duplicate prevention** — `add_attachment()` guards with `has_queue_entry()`. **Race condition:** there is **no UNIQUE constraint on `attachment_id`** in `vio_queue`; two concurrent scans/uploads can both pass the `SELECT COUNT(*)` check and insert duplicate rows. Low real-world probability (scan is admin-triggered, single-threaded in practice). **(Low)**

**Processing state transitions** — `pending → processing → completed|failed`; stale `processing` rows are reset to `pending` on start/resume (`reset_processing_jobs`). ✅

**Race condition (primary):** `QueueProcessor::process_batch()` does **`get_pending_jobs()` then `mark_processing()` without an atomic claim**. Two overlapping `wp_ajax_vio_process_batch` requests (e.g., a double-click, or a second browser tab) can fetch the **same pending rows** and process the same attachment twice. WebP/AVIF generation is idempotent (overwrites the same file, dedup attachment) so the outcome is *safe but wasteful* (duplicated CPU, doubled `completed` accounting for that batch). **(Medium — no data corruption, but real under concurrent triggering.)**

**Deadlocks** — None identified. All writes are single-row `UPDATE … WHERE id=?`; no multi-row transactions or lock ordering that could deadlock. ✅

---

### TASK 3 — Reports Scale Audit

**Dashboard summary** — `get_report_summary()` is cached in a 300s transient (`vio_report_summary`), so the heavy aggregates run at most once per 5 min. ✅

**Aggregate queries** — `get_optimization_impact()` joins **5 postmeta rows** per attachment; `get_optimization_rows()` joins **6**. These are meta-key self-joins on `wp_postmeta` (the largest table on most sites) with a filesort `ORDER BY`. With the transient cache they are tolerable at 1k–10k optimized images. **(Medium at 10k+, mitigated by cache for the dashboard.)**

**CSV export** — `ReportExporter::handle()` calls `get_optimization_rows('recent', 0)` with **`limit = 0` = no `LIMIT` clause**, then loads the **entire result set into PHP memory** via `$wpdb->get_results()` (the 6-join query) before streaming. The export is **not cached** and **not DB-streamed**.
- **1k optimized:** fine (sub-second, a few MB).
- **10k+ optimized:** the 6-way postmeta JOIN + filesort with no row cap risks **memory pressure and PHP execution-timeout** on shared hosting. This is the **single most likely scale failure point**. **(High for very large sites; Medium overall.)**

**Cached statistics** — Transient bundle is correct and invalidation is purely TTL-based (no explicit bust after optimization, so a dashboard figure can lag up to 5 min). Acceptable. ✅

**Estimates:**
| Library size | Dashboard (cached) | CSV export |
|---|---|---|
| 1k | <100 ms after warm cache | ~1 s, safe |
| 10k+ | first build ~ seconds, then cached | **at-risk: memory + timeout** |

---

### TASK 4 — Frontend Delivery Audit

`DeliveryEngine` rewrites URLs only when the derivative file **physically exists** next to the source — it never emits a broken URL. ✅ Correctly disabled for admin, REST, cron, XML-RPC, AJAX, feeds, and embeds.

**URL replacement** — `swap_url()` does a `file_exists()` + `is_file()` **disk stat per candidate URL per image per page render**. With AVIF+WebP "auto" mode that is up to **2 stats per image**, and srcset multiplies this by the number of candidates. On an image-heavy page (e.g. 30 images × 5 srcset sizes × 2 formats) that is **hundreds of stat calls per request**, uncached across requests. OS stat cache softens it but there is no in-process/persistent memoization. **(Medium — the main frontend cost.)**

**AVIF / WebP fallback** — `get_accepted_formats()` honors the `Accept` header and the preferred-format setting. In `auto` mode it tries AVIF then WebP then falls back to the original. In forced `avif`/`webp` mode it tries only that format then the original (no cross-fallback) — intended behavior. ✅

**srcset** — Each candidate is rewritten independently; a missing derivative for one size leaves that candidate as the original. This produces a **mixed-format srcset** (some `.webp`, some `.jpg`). Browsers handle this correctly (each candidate is an independent URL), so it is safe but cosmetically inconsistent. **(Low)**

**Edge cases identified:**
- `filter_content_img_tag()` rewrites only the `src` of content images, **not their inline `srcset`** — content-embedded responsive images keep original-format srcset candidates while the `src` is swapped. Harmless (browser picks a valid URL) but format coverage is partial. **(Low)**
- CDN / off-host uploads: rewriting is gated on the URL starting with the local uploads `baseurl`; offloaded media (S3 etc.) is silently skipped. Correct and safe. ✅

---

### TASK 5 — Backup & Restore Audit

**Backup storage growth** — When backups are enabled (default ON), `WebPGenerator::ensure_backup_copy()` writes a **full-size copy of every optimized original** under `uploads/vio-backups/`. This **doubles original-image storage** for the optimized set. **(Medium — expected, but unbounded.)**

**Orphaned cleanup cron (confirmed bug)** — `Installer::schedule_cron()` schedules a **daily `vio_cleanup_backups`** event, and the `vio_backups` table even has an `expires_at` index, but **no `add_action('vio_cleanup_backups', …)` handler exists anywhere in the codebase**. The event fires daily and does nothing; backups are **never auto-pruned**. **(Medium — dead cron + unbounded backup growth with no retention.)**

**Dead `vio_backups` table** — The `{$prefix}vio_backups` table is **created on activation and dropped on uninstall but never read from or written to**. Backup state is tracked entirely via the `_vio_backup_path` post-meta. The table is unused infrastructure. **(Low — harmless dead schema.)**

**Restore reliability** — `RestoreEngine::restore()` validates the backup file exists/readable, recreates the target dir if needed, and `copy()`s back. Solid. ✅

**Metadata cleanup on restore (edge case)** — After restore, `reset_optimization_metadata()` clears the source's optimization meta but:
1. **Does not delete the physical `.webp` / `.avif` derivative files** (it only sets `_vio_webp_path` to `''`).
2. **Does not trash the generated WebP/AVIF Media Library attachments** created by `DerivativeLibrary`.

Result: after a restore, the derivative **files remain on disk and the derivative attachments remain in the Media Library**, and `DeliveryEngine` (which keys off file existence, not meta) **will continue serving the stale derivative**. The source shows "pending" while still being delivered as WebP/AVIF on the frontend. **(Medium — restore is not fully reversible at the delivery/library layer.)**

---

### TASK 6 — Localization Audit

**Language switching** — `Plugin::filter_plugin_locale()` overrides the catalog locale only for this text domain when an interface language is selected; otherwise it defers to `determine_locale()`. Clean, scoped, no global side effects. ✅

**Fallback behavior** — Unknown/invalid stored language falls back to `wordpress` default in both `sanitize` and `get_resolved_locale()`. ✅

**Coverage** — POT = **258 strings**. All **9 shipped catalogs (de, es, fr, it, nl, pl, pt, ru, tr)** contain **258/258 translated strings, 0 fuzzy**, with only the standard empty PO header. **No missing translations in shipped languages.** ✅

**Tab labels** (`Router::get_tab_label`) and dashboard cards — all wrapped in `__()` with static, extractable strings. ✅

**Untranslated strings found & FIXED** — `AttachmentActions` declared its **5 Media Library row-action labels** (`Optimize`, `WebP`, `AVIF`, `Regenerate`, `Restore`) as **hardcoded English** rendered via `esc_html($label)` with no `__()`. These are user-facing in the Media Library. **Fixed** by adding a `get_action_labels()` map of `__()`-wrapped, extractable strings and rendering through it (English fallback preserved). The new strings should be picked up on the next POT regeneration. **(Low — fixed.)**

**Language vs. catalog parity** — The selector offers `en_US` (no `.mo`; source language, so correct) plus the 9 translated locales. Selector and shipped catalogs are consistent. ✅

---

### TASK 7 — Release Blockers (toward 1.0.0)

| Severity | Issue | Status |
|----------|-------|--------|
| **Critical** | *(none found)* | — |
| **High** | CSV export (`get_optimization_rows(…, 0)`) runs an unbounded 6-join postmeta query and buffers all rows in memory — memory/timeout risk on 10k+ optimized libraries. | Open (recommend cap/stream/batch) |
| **High** | `vio_cleanup_backups` cron has **no handler** → backups grow without bound and there is no retention path. | Open |
| **Medium** | `process_batch()` lacks an atomic job claim → concurrent triggers can double-process (wasteful, not corrupting). | Open |
| **Medium** | Restore leaves derivative files + derivative attachments behind; `DeliveryEngine` keeps serving them. | Open |
| **Medium** | Derivative attachments triple Media Library row count at scale. | Open (by design — document) |
| **Medium** | Heavy 5–6 join aggregate report queries (mitigated by 5-min transient). | Open (acceptable) |
| **Low** | No UNIQUE on `vio_queue.attachment_id` (duplicate-insert race). | Open |
| **Low** | Unused `vio_backups` table (dead schema). | Open |
| **Low** | `attempts` retry counter never enforced as a ceiling. | Open |
| **Low** | Per-row `filesize()` in list table; per-render `file_exists()` in delivery. | Open (acceptable) |
| **Low** | Media row-action labels untranslated. | **Fixed** |

**Blockers that must clear before 1.0.0:** the two **High** items (CSV export memory/timeout and the no-op backup-cleanup cron / unbounded backups). Everything else is shippable with documentation.

---

### TASK 8 — Production Readiness Score

| Dimension | Score | Notes |
|-----------|-------|-------|
| **Architecture** | 92% | Clean PSR-4, single-responsibility classes, reversible frontend delivery, good DI seams. Derivative-attachment multiplication is the one debatable design choice. |
| **Security** | 95% | Nonces + capability checks on every AJAX/admin-post/row action, prepared statements throughout, path validation on backups/restore, output escaped. No injection or auth gaps found. |
| **Scalability** | 78% | Fine to ~10k sources; CSV export and unbounded backups are the genuine ceilings. Queue/stats are indexed and cached. |
| **UX** | 90% | Consistent tabbed admin, clear Media Library status, manual retry, dismissible notices. Minor: stale figures up to 5 min; derivative rows clutter the grid. |
| **Documentation** | 88% | Strong internal docs (architecture, DB, engine, i18n status, troubleshooting). Missing: scale guidance (backup growth, large-library caveats). |
| **Release Readiness** | 85% | No critical/security blockers; two High scalability/lifecycle items to resolve. |

**Overall production readiness: ~88%.**

---

### TASK 9 — Final Report

This document (`docs/PRODUCTION_READINESS_REPORT.md`) constitutes the final report: findings, risks, recommendations, release blockers, and readiness score are all captured above and summarized below.

---

## 3. Issues Found (consolidated)

1. **High** — Unbounded CSV export query + full in-memory buffering (`ReportExporter` + `StatsService::get_optimization_rows(…, 0)`).
2. **High** — `vio_cleanup_backups` cron scheduled with no handler; backups never pruned, no retention.
3. **Medium** — Non-atomic queue claim in `process_batch()` → concurrent double-processing.
4. **Medium** — Restore does not remove derivative files/attachments; stale derivatives keep being delivered.
5. **Medium** — Derivative attachments multiply Media Library row count (~3× at scale).
6. **Medium** — Heavy 5–6 join report aggregates (mitigated by transient cache).
7. **Low** — No UNIQUE constraint on `vio_queue.attachment_id`.
8. **Low** — Unused `vio_backups` table.
9. **Low** — `attempts` never enforced as a retry ceiling.
10. **Low** — Per-row `filesize()` (list table) and per-render `file_exists()` (delivery).
11. **Low** — Untranslated Media Library row-action labels.

## 4. Issues Fixed (this audit)

- **#11** — `src/Media/AttachmentActions.php`: extracted row-action labels into a `__()`-wrapped, extractable `get_action_labels()` map; render path now uses it with the original English as fallback. Lint clean. *(Low-risk, no behavior change for English UI.)*

No other code was changed, per the audit-only scope.

## 5. Scalability Findings (summary)

- **Safe through ~10k source images** for browsing, queue processing, and the cached dashboard.
- **Two real ceilings:** (a) CSV export memory/timeout at 10k+ optimized rows; (b) unbounded backup directory growth with a no-op cleanup cron.
- **Frontend delivery** cost is dominated by per-render filesystem stats; fine for typical pages, watch on very image-dense templates.
- **Derivative attachments** inflate the Media Library row count up to 3× — accurate stats are preserved, but library/admin queries feel a larger `wp_posts`.

## 6. Production Readiness Score

**Overall: ~88%** (Architecture 92 / Security 95 / Scalability 78 / UX 90 / Docs 88 / Release Readiness 85). No critical or security blockers; two High scalability/lifecycle items remain.

## 7. Recommended Next Phase

**Phase 8.4 — Scale Hardening & Lifecycle Closure** (small, targeted, low-risk):

1. **CSV export:** cap rows or paginate/stream (e.g. `LIMIT` + offset batches, or write to a temp file in chunks) so 10k+ exports cannot exhaust memory/time.
2. **Backup lifecycle:** either implement the `vio_cleanup_backups` handler with an explicit, opt-in retention/expiry policy (using the existing `expires_at` column), or remove the orphaned cron schedule. Decide whether `vio_backups` becomes the source of truth or is dropped.
3. **Queue claim:** make `process_batch()` claim rows atomically (single `UPDATE … SET status='processing' WHERE status='pending' LIMIT n` then select the claimed ids, or add a `GET_LOCK`/transient guard) to eliminate concurrent double-processing.
4. **Restore completeness:** on restore, delete derivative files and trash the linked WebP/AVIF attachments so the operation is fully reversible at the delivery layer.
5. **Hardening niceties:** add `UNIQUE(attachment_id)` to `vio_queue`, enforce a max-`attempts` ceiling on retries, add a "clear completed queue rows" maintenance action, regenerate the POT to capture the newly translatable row-action labels, and document large-library backup/storage expectations.

These are all incremental and align with a clean 1.0.0 once the two High items are closed.

---

*End of report.*
