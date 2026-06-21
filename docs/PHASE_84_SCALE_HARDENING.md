# Phase 8.4 — Scale Hardening & Lifecycle Closure

**Date:** 2026-06-19
**Plugin version:** 0.9.0 (pre-RC)
**Scope:** Resolve the release blockers and lifecycle gaps identified in
`docs/PRODUCTION_READINESS_REPORT.md`. Targeted fixes only — no architecture
redesign, no new major features, existing UI and database schema preserved.

---

## Issues Fixed

| # | Severity (8.3) | Issue | Resolution |
|---|----------------|-------|------------|
| 1 | High | `vio_cleanup_backups` cron scheduled with no handler; backups grew unbounded. | New `BackupCleanup` service handles the cron, deletes only expired backups, opt-in retention. |
| 2 | Medium | Restore left WebP/AVIF files + derivative attachments behind; stale derivatives kept being delivered. | `RestoreEngine` now removes derivative files, attachment records, and link metadata after a successful restore. |
| 3 | High | CSV export ran an unbounded 6-join query and buffered every row in memory. | `ReportExporter` streams the report page-by-page via a new paginated query. |
| 4 | Medium | `process_batch()` claimed jobs non-atomically → concurrent double-processing. | New atomic `QueueManager::claim_job()` (conditional `pending → processing`). |
| 5 | Low | `attempts` retry counter was never enforced. | Retry limit (default 3) enforced in `retry_job()`; exhausted jobs stay failed with a clear reason. |

---

## Task 1 — Backup Cleanup Cron

**New file:** `src/Backup/BackupCleanup.php`
**Registered in:** `src/Plugin.php` (in all contexts — cron runs outside admin).

Behaviour:

- Hooks `vio_cleanup_backups` (the previously orphaned daily event).
- **Retention disabled (0 days) → no-op.** Default is `0`, so out of the box
  every backup is still kept forever exactly as before. Retention is opt-in.
- When enabled, computes `cutoff = now − retention_days` and inspects up to
  `VIO_BACKUP_CLEANUP_BATCH` (500) attachments per run that still carry a
  `_vio_backup_path`.
- **Safety guarantees:**
  - Only files validated by `BackupManager::is_valid_backup_path()` (i.e. inside
    the plugin backup directory) are ever deleted → **originals, which live in
    the uploads root, can never be removed.**
  - Only the backup *file* is deleted and the `_vio_backup_path` meta cleared —
    **attachment posts are never deleted**, so active media is untouched.
  - Expiry is measured from the backup file's own `filemtime()` (the moment it
    was copied), falling back to `_vio_optimized_at`. If age cannot be
    determined the backup is **skipped, never deleted**.
  - Orphaned references (meta points at a missing file) are cleared, not deleted.
- **Logging:** each run records `vio_last_backup_cleanup`
  (`time`, `retention_days`, `examined`, `deleted`, `skipped`) and, when
  `WP_DEBUG` is on, writes a one-line `error_log` summary.

**Settings:** `CompressionSettings` gains `backup_retention_days`
(default `0`, range `0–3650`, filterable via `vio_backup_retention_days`) with
`get_backup_retention_days()` / `is_backup_retention_enabled()`. A "Backup
Retention (days)" field was added to the existing Backup & Restore tab — no
layout restructuring.

**Schema:** unchanged. The dormant `vio_backups` table was intentionally **not**
wired up (that would be an architecture change); file mtime + retention is the
expiry signal, which keeps the fix minimal and schema-preserving.

---

## Task 2 — Restore Cleanup

**File:** `src/Engine/RestoreEngine.php`

After the original is copied back from backup, `remove_derivatives()` now runs:

- Deletes the linked WebP and AVIF **Media Library attachments**
  (`_vio_webp_attachment_id` / `_vio_avif_attachment_id`) via
  `wp_delete_attachment( …, true )`, but **only** when the target is confirmed to
  be a plugin-generated derivative of that exact format
  (`_vio_generated_by` check) — never an unrelated attachment.
- Deletes the derivative **files**: both the path recorded in meta and the
  sibling file next to the original (covers untracked files). A file is only
  deleted if its extension matches the expected derivative format
  (`.webp` / `.avif`) — a hard guard against ever touching the original.
- Clears the derivative link metadata.

`reset_optimization_metadata()` was extended to fully clear WebP + optimization
tracking (`_vio_webp_*`, `_vio_source_size`, `_vio_optimized_at`,
`_vio_savings_*`, `_vio_engine_used`) and all AVIF metadata, and sets
`_vio_status = pending`. The backup pointer (`_vio_backup_path`) is deliberately
**kept** so the image can be re-optimized and restored again.

**Result:** a restored image carries no derivative files, no derivative library
items, and no optimization metadata — the frontend delivery engine (which keys
off sibling-file existence) has nothing left to serve, so it behaves like a
fresh upload.

---

## Task 3 — CSV Export Hardening

**Files:** `src/Stats/StatsService.php`, `src/Admin/ReportExporter.php`

- New `StatsService::get_optimization_rows_page( $order_by, $offset, $limit )` —
  the same report query with `LIMIT … OFFSET …` and a stable
  `ORDER BY …, p.ID ASC` tiebreaker for deterministic paging.
- `ReportExporter::handle()` now streams the CSV in pages of 500
  (`EXPORT_PAGE_SIZE`), writing + `flush()`ing each page and releasing it before
  fetching the next. The previous `get_optimization_rows( 'recent', 0 )`
  call that loaded the **entire** result set into memory is gone.
- **CSV structure is unchanged** — identical header row and column order.

Memory now stays flat (~one page) regardless of whether the site has 1k or 100k
optimized images.

---

## Task 4 — Queue Claim Safety

**Files:** `src/Queue/QueueManager.php`, `src/Queue/QueueProcessor.php`

- New `QueueManager::claim_job( $queue_id )` performs a **conditional** update:
  `UPDATE … SET status='processing' … WHERE id=? AND status='pending'` and
  returns true only when a pending row was actually transitioned.
- `QueueProcessor::process_batch()` now calls `claim_job()` instead of
  `mark_processing()`; if the claim fails (a concurrent batch already took the
  job) it skips the row. Because only the first UPDATE matches the pending row,
  **a job can be claimed and processed exactly once**, even when two AJAX batch
  requests overlap (double-click, second tab, retried request).
- Minimal change: no new tables, no columns, no locks. `mark_processing()` is
  retained for compatibility but no longer on the batch path.

---

## Task 5 — Queue Retry Limit

**Files:** `vacuum-image-optimizer.php`, `src/Queue/QueueManager.php`,
`src/Queue/AjaxController.php`

- New constant `VIO_MAX_RETRIES` (default **3**, overridable).
- `retry_job()` now resets a failed job to pending **only while
  `attempts < VIO_MAX_RETRIES`**. Once the limit is reached the job stays
  `failed`.
- New `is_retry_exhausted()` + `get_max_attempts()` helpers. The retry AJAX
  endpoint returns a specific reason when the limit is hit
  (*"This job reached the maximum of 3 attempts and will stay failed."*).
- The failed-jobs payload now includes `max_attempts` and an `exhausted` flag so
  the existing UI can show the reason without markup changes.

---

## Architecture Impact

- **No architecture redesign.** All changes are additive methods, one new
  single-responsibility service (`BackupCleanup`), and one cron registration.
- **Database schema unchanged.** No tables created, altered, or dropped. The fix
  set relies on existing post-meta and file mtime.
- **UI preserved.** Only one field added to the existing Backup & Restore tab;
  no tabs, cards, or layouts were restructured.
- **Backwards compatible defaults.** Backup retention defaults to `0`
  (keep forever) and the retry limit (3) only constrains an already-manual
  action, so existing installs see no behavioural change until they opt in.
- **New settings key** `backup_retention_days` is added to defaults and
  normalized on read, so existing stored options upgrade transparently.

---

## Remaining Risks

| Risk | Severity | Notes |
|------|----------|-------|
| Derivative attachments still multiply Media Library row count (~3× at scale). | Medium | By design; out of scope for 8.4 (would be an architecture change). Documented in 8.3. |
| Report **aggregate** queries (5–6 postmeta joins) remain heavy at 10k+. | Low | Mitigated by the 5-minute transient cache; the unbounded *export* path — the real risk — is now fixed. |
| `vio_queue` has no `UNIQUE(attachment_id)`; a concurrent *scan* could still insert duplicate rows. | Low | Processing is now race-safe (Task 4); duplicate *enqueue* is cosmetic. Adding the constraint was deferred to avoid a schema migration. |
| Backup cleanup inspects at most `VIO_BACKUP_CLEANUP_BATCH` (500) rows/day, ordered by post ID. | Low | Oldest IDs (most likely expired) are drained first across successive daily runs; raise the constant for very large libraries. |
| Frontend delivery still does per-render `file_exists()` stats. | Low | Acceptable; unchanged this phase. |

**No critical or security issues introduced.** All previously identified
**High** blockers (CSV export memory, backup cleanup cron) are resolved.

---

## Validation

```
php -l vacuum-image-optimizer.php                         → No syntax errors
find src -maxdepth 5 -name "*.php" -exec php -l {} \;     → 34/34 files, No syntax errors
```

---

## Files Touched

**Created**
- `src/Backup/BackupCleanup.php`
- `docs/PHASE_84_SCALE_HARDENING.md`

**Modified**
- `vacuum-image-optimizer.php` (constants: `VIO_MAX_RETRIES`, `VIO_BACKUP_CLEANUP_BATCH`)
- `src/Plugin.php` (register cleanup cron handler)
- `src/Core/Installer.php` (default `backup_retention_days`)
- `src/Settings/CompressionSettings.php` (retention setting + getters)
- `src/Admin/Views/BackupRestore.php` (retention field)
- `src/Engine/RestoreEngine.php` (derivative cleanup)
- `src/Stats/StatsService.php` (paginated rows method)
- `src/Admin/ReportExporter.php` (streamed export)
- `src/Queue/QueueManager.php` (atomic claim + retry limit)
- `src/Queue/QueueProcessor.php` (use atomic claim)
- `src/Queue/AjaxController.php` (retry-limit reason + payload flags)
