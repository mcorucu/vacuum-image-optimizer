# Phase 6.5 — Hardening & QA Report

**Date:** 2026-06-18
**Scope:** Production-readiness pass (reliability, validation, safety, scalability, QA).
**Out of scope (per phase brief):** new user-facing features, frontend delivery, AVIF improvements, external APIs, architecture redesign.

---

## 1. Code Audit (6.5.1)

Files audited: `QueueManager`, `QueueProcessor`, `WebPGenerator`, `AVIFGenerator`, `UploadAutomation`, `AttachmentActions`, `CompressionSettings`, `StatsService` (plus `RestoreEngine`, `BackupManager`, `Installer`).

### Findings
| # | Severity | Area | Finding | Action |
|---|----------|------|---------|--------|
| 1 | Medium | RestoreEngine | Restore reset WebP metadata but left AVIF metadata (`_vio_avif_*`) stale after a restore — introduced in Phase 6. | **Fixed** — AVIF meta now cleared on restore. |
| 2 | Low | Installer | `set_default_options()` default array omitted `enable_avif` / `avif_quality`. Reads were safe (normalized at runtime) but the stored option was inconsistent on fresh installs. | **Fixed** — defaults added. |
| 3 | Info | WebP/AVIF generators | Parallel helper methods (`path_to_upload_url`, `build_*_path`, engine capability checks) are duplicated by design (parallel-format architecture). | **No change** — refactor would be architectural; explicitly out of scope. |
| 4 | Info | QueueManager | `has_queue_entry()` counts `completed`/`failed` rows as duplicates, so re-scans won't re-add them. Confirmed intentional (retry handled via `retry_job`, re-optimization gated by `_vio_status`). | **No change.** |
| 5 | Info | QueueManager | `mark_failed()` increments `attempts` with no automatic max-retry cap. Retries are manual/admin-driven, so no runaway loop. | **Documented** as a future option. |

No dead/unreachable code or missing-escaping issues were found in the audited admin output (all views use `esc_html`/`esc_attr`/`esc_url`). All AJAX and row-action entry points enforce nonce + capability checks.

---

## 2. Database Hardening (6.5.2)

**Table:** `{prefix}vio_queue`

Existing schema (via `dbDelta` in `Installer::create_tables()`):
- `PRIMARY KEY (id)`
- `KEY attachment_id (attachment_id)`
- `KEY status_created (status, created_at)`

### Assessment
- **Lookup efficiency:** `get_jobs_by_status()` filters `WHERE status = ? ORDER BY created_at` — fully served by the composite `status_created` index. `has_queue_entry()` / duplicate checks filter by `attachment_id` — served by `KEY attachment_id`. Statistics use a `GROUP BY status` scan, served by the leading column of `status_created`.
- **Conclusion:** The expected indexes (attachment_id, status, created_at) are already present (status + created_at as a composite, which is strictly better for the actual query shapes). **No migration needed.**
- `Installer::maybe_upgrade()` re-runs `dbDelta` on version change, so any future index additions apply safely without data loss.

### Duplicate prevention
Enforced at the application layer (`has_queue_entry()`), not by a DB `UNIQUE` constraint. A unique constraint on `attachment_id` was **deliberately not added**: `dbDelta` cannot reliably add a unique key to a populated table and would fail/destroy data if any duplicate rows already existed. Documented as a clean-install-only future enhancement.

---

## 3. File Safety Audit (6.5.3)

| Concern | WebPGenerator | AVIFGenerator | RestoreEngine | BackupManager |
|---------|---------------|---------------|---------------|---------------|
| Source existence/readable checks | ✅ | ✅ | ✅ (backup readable) | n/a |
| Output filename sanitization | ✅ `sanitize_file_name` | ✅ `sanitize_file_name` | n/a | ✅ |
| Path-traversal protection | ✅ backup path validated via `is_valid_backup_path` | ✅ (output derived from WP-controlled source path) | ⚠️ reads `_vio_backup_path` from our own meta | ✅ `is_valid_backup_path` enforces backup-dir prefix |
| Writable directory handling | ✅ (mkdir for backups) | writes beside source (upload dir already validated by WP) | ✅ creates original dir if needed | ✅ |
| No original modification / deletion | ✅ | ✅ | restores via `copy()` only | ✅ copy only |

**Outcome:** No unsafe behavior found. All file writes target validated upload/backup locations; originals are never modified or deleted. `RestoreEngine` reads its source path from plugin-written metadata (low risk) — flagged as a low-priority defense-in-depth item rather than an active vulnerability.

---

## 4. Error Handling Audit (6.5.4)

- **Generators** (`WebPGenerator`, `AVIFGenerator`): each engine call is wrapped in `try/catch (\Throwable)`; failures return a structured result with a stored error message rather than throwing.
- **Upload flow** (`UploadAutomation`): the whole handler is wrapped in `try/catch (\Throwable)`; an optimization failure can never break a media upload. AVIF runs in its own guarded path so an AVIF failure does not affect WebP success.
- **Queue** (`QueueProcessor`): per-job `try/catch`; one failing job is marked failed and processing continues. AVIF generation is a guarded "partial success" — an AVIF failure never fails the WebP job.
- **Error storage normalized:**
  - WebP/optimize failures → `_vio_status = error` + `_vio_error_message`.
  - AVIF failures → `_vio_avif_error_message` (kept separate, never overwrites WebP status).
  - Queue failures → `error_message` column on the row.

**Outcome:** Consistent and safe. No changes required.

---

## 5. Settings Audit (6.5.5)

Validated in `CompressionSettings::sanitize()` and `normalize()`:
- `quality` → clamped to **1–100**.
- `avif_quality` → clamped to **0–100**.
- `profile` → validated against the allowed profile set (falls back to `balanced`).
- `auto_optimize_mode` → validated against `queue`/`immediate`.
- `enable_avif` / `auto_optimize_uploads` → coerced to boolean.
- Queue state (`QueueManager::set_queue_state`) → validated against `idle`/`running`/`paused`; invalid values rejected.

Invalid values cannot be persisted. The only gap (Installer defaults missing AVIF keys) was **fixed**.

---

## 6. Metadata Audit (6.5.6)

| Group | Keys | Status |
|-------|------|--------|
| WebP | `_vio_webp_path`, `_vio_webp_url`, `_vio_webp_size`, `_vio_source_size`, `_vio_savings_bytes`, `_vio_savings_percent`, `_vio_engine_used`, `_vio_optimized_at`, `_vio_status`, `_vio_error_message` | Consistent |
| AVIF | `_vio_avif_path`, `_vio_avif_url`, `_vio_avif_size`, `_vio_avif_savings_bytes`, `_vio_avif_savings_percent`, `_vio_avif_engine_used`, `_vio_avif_generated_at`, `_vio_avif_error_message` | Consistent |
| Backup | `_vio_backup_path` | Consistent |
| Automation | `_vio_auto_processed`, `_vio_auto_processed_at` (+ option `vio_last_auto_processed_at`) | Consistent |
| Queue | row columns: `attachment_id`, `status`, `created_at`, `started_at`, `completed_at`, `attempts`, `error_message` | Consistent |

**Mismatch fixed:** restore now clears AVIF metadata (previously only WebP keys were reset). All `_vio_*` keys share a single prefix and are removed cleanly on uninstall.

---

## 7. Uninstall Review (6.5.7)

**Before:** No `uninstall.php` existed; only deactivation cleared cron. Plugin options, custom tables, and attachment metadata persisted after deletion.

**After:** Added a safe root `uninstall.php` (guarded by `WP_UNINSTALL_PLUGIN`) that:
- deletes plugin options (`vio_settings`, `vio_queue_state`, `vio_db_version`, `vio_phase5_queue_ready`, `vio_last_auto_processed_at`),
- drops the custom tables (`vio_queue`, `vio_stats`, `vio_backups`),
- deletes all `_vio_*` attachment metadata,
- clears the cleanup cron event.

**Safety guarantee:** It **never deletes image files** — originals, generated WebP/AVIF derivatives, and backup files on disk are all left intact. Cleanup is strictly database-level.

---

## 8. Scalability Review (6.5.8)

| Volume | Queue processing | Dashboard / stats | Library scan |
|--------|------------------|-------------------|--------------|
| 100 | Fine | Fine | Fine |
| 1,000 | Fine (batched, 10/req) | Fine | Fine |
| 10,000 | Fine | Noticeable — multiple aggregate meta queries per load | Slow — per-row INSERT/SELECT loop |
| 50,000 | Fine (work is bounded per batch) | Slow without caching | Slow / memory pressure |

### Bottlenecks identified
1. **`QueueManager::scan_library()`** loads all eligible IDs into memory, then performs one `SELECT` (duplicate check) + one `INSERT` per attachment. At 50k that is ~100k queries in a single request. **Recommendation:** chunked bulk inserts with `INSERT IGNORE` (requires the unique-constraint enhancement) or batched `INSERT ... VALUES` groups. *(Not changed — larger refactor, risk of altering behavior.)*
2. **Dashboard stats** (`StatsService`) issue several independent aggregate queries per page load, including a 5-way self-join in `get_optimization_impact()` and `CAST(...)`-based counts that can't use an index. Acceptable up to a few thousand attachments. **Recommendation:** cache results in a short-lived transient (e.g. 5 min) and invalidate on optimize/restore. *(Not changed — would introduce cache-staleness behavior.)*
3. **`get_recent_jobs()`** orders by `COALESCE(completed_at, started_at, created_at)` which cannot use an index; bounded by `LIMIT` so impact is small.

These are documented rather than fixed to honor the "no architecture redesign / fix simple issues only" constraint.

---

## 9. Production Readiness Indicators (6.5.10)

Added a **Production Readiness** card to System Status showing simple Available/Not Available badges for:
- Queue Table (live `SHOW TABLES` check via `SystemCheck::has_queue_table()`)
- Uploads Writable
- Backups Writable (`SystemCheck::is_backups_writable()`)
- WebP Available
- AVIF Available

---

## Summary

### Fixes applied
- RestoreEngine now clears AVIF metadata (consistency).
- Installer default options include `enable_avif` / `avif_quality`.
- New `SystemCheck::has_queue_table()` and `is_backups_writable()` plus a Production Readiness status card.
- New safe `uninstall.php` (DB-only cleanup, never deletes images).

### Risks remaining
- Large-scale `scan_library()` query volume (10k+ libraries).
- Uncached dashboard aggregate queries at large scale.
- No DB-level unique constraint on `vio_queue.attachment_id` (app-level guard only).
- Restore/uninstall intentionally leave generated derivative files on disk (by design — data-safety choice).

### Recommended future improvements
- Chunked bulk insert for library scans + `UNIQUE(attachment_id)` on clean installs.
- Transient caching for dashboard statistics with event-based invalidation.
- Optional automatic max-retry cap for queue jobs.
- Optional "also delete generated files" toggle for uninstall (off by default).

**Verification:** `php -l` passes for `vacuum-image-optimizer.php`, `uninstall.php`, and all files under `src/`.
