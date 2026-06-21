# Release Candidate Audit — Vacuum Image Optimizer

**Audit date:** 2026-06-19
**Audited version:** 0.9.0
**Scope:** Architecture, performance, security, accessibility, and WordPress-standards review prior to Release Candidate. Audit-first; only low-risk fixes applied.

---

## Strengths

- **Clean, modular architecture.** PSR-4 namespaced services under `VacuumImageOptimizer\` with clear separation: `Engine` (WebP/AVIF/Restore), `Queue` (Manager/Processor/Ajax), `Media`, `Frontend`, `Settings`, `Stats`, `Backup`, `Admin/Views`, `Utils`, `Core`.
- **Safety-first engines.** Generators never modify or delete originals, wrap engine calls in `try/catch (\Throwable)`, and store structured results; AVIF failures are partial-successes that never fail WebP or the queue.
- **Robust settings layer.** `CompressionSettings` centralizes defaults, merge-based sanitization, and normalization, so partial per-tab forms can't wipe other settings; all values are validated/clamped.
- **Security posture.** AJAX endpoints verify nonce **and** capability; admin actions use `check_admin_referer` + capability + per-attachment nonces; option/hook/meta names are consistently `vio_`-prefixed; output is escaped and input sanitized throughout.
- **Non-destructive frontend delivery & native lazy loading** gated to frontend only (not admin/REST/CLI/cron/feed), with English fallback and file-existence validation.
- **Complete i18n.** 257-string catalog, 9 locales at 100% coverage, compiled `.mo`, domain-scoped locale override.
- **Safe DB lifecycle.** `dbDelta` migrations with adequate indexes; `uninstall.php` does DB-only cleanup and never deletes user images.

## Weaknesses (non-blocking)

- **Dashboard query volume.** A dashboard load issues ~15 `StatsService`/queue queries, including a 5-way self-join (`get_optimization_impact`) and `CAST(...)`-based counts; `get_pending_images()` re-runs the optimized-images query. Fine for small/medium libraries; could be cached at large scale.
- **No `readme.txt`.** WordPress.org listing requires a `readme.txt` (stable tag, tested-up-to, changelog). Not a code blocker but required before public submission.
- **Empty scaffold directories** (`admin/`, `includes/`, `templates/`, `tests/`) remain; no test suite is present despite a configured `Tests` autoload.
- **Restore trusts plugin-written meta.** `RestoreEngine` reads `_vio_backup_path` from postmeta (set only by the plugin) without re-validating it lies inside the backup dir — defense-in-depth, not an active vulnerability.

## Issues Fixed In This Audit (low-risk)

1. **Removed dead legacy code:** `src/Core/Plugin.php`, `src/Core/Container.php`, `src/Core/Hooks.php` — an earlier container/hooks bootstrap, fully unreferenced (active bootstrap uses root `VacuumImageOptimizer\Plugin`). 35 → 32 source files.
2. **Fixed `composer.json` lint scripts** that pointed at the now-empty `admin/` path → `src/` + main file.

## Blockers

- **None at the code level.** All PHP lints clean; no fatal-risk patterns; security/i18n/safety checks pass.
- **Pre-distribution (not code blockers):** add `readme.txt`; optionally add a minimal test suite.

## Performance Findings

- Queue processing is correctly batched (AJAX, bounded batch size) — no N+1 in the hot path.
- Media Library column renders from postmeta per row (expected WP pattern); no per-row extra queries beyond `get_post_meta`.
- Recommendation (deferred, not applied to avoid staleness): cache `StatsService` aggregates in a short transient invalidated on optimize/restore; deduplicate `get_pending_images()`'s repeated optimized-count query.

## Security Findings

- AJAX (`AjaxController`): `check_ajax_referer( 'vio_queue_ajax' )` + `current_user_can( 'manage_options' )` on every endpoint. ✅
- Attachment actions (`AttachmentActions`): per-action nonce, `upload_files` capability, attachment validation, safe redirects. ✅
- Admin page (`Menu`/`Router`): `manage_options` gate, `sanitize_key` on tab input, escaped output. ✅
- Settings: validated/clamped via `CompressionSettings::sanitize()`. ✅
- `uninstall.php`: guarded by `WP_UNINSTALL_PLUGIN`; prepared queries; no file deletion. ✅
- No new issues requiring fixes. One documented defense-in-depth note (restore path validation).

## Accessibility Findings

- Toggle component is keyboard-operable with a visible `:focus-visible` ring and proper disabled state; meaning is conveyed by text + color (not color alone).
- Headings follow a correct hierarchy (`h1` → section `h2` → card `h3`/`h4`); progress bar exposes `role="progressbar"` with `aria-valuenow/min/max` and a text label; decorative logo is `aria-hidden`, hero logo has meaningful `alt`.
- Tab nav uses `role="tablist"`/`role="tab"`/`aria-selected`. Buttons keep ≥44px targets. No minor issues warranting code changes were found.

## WordPress Standards Findings

- Text domain `vacuum-image-optimizer` consistent across 100% of strings; loaded via `load_plugin_textdomain` from `/languages/`.
- Plugin header complete (Name, URI, Description, Version, Author, License, Requires at least/PHP, Text Domain, Domain Path).
- Option names (`vio_settings`, `vio_queue_state`, `vio_db_version`, `vio_phase5_queue_ready`, `vio_last_auto_processed_at`) and hooks (`vio_cleanup_backups`, `wp_ajax_vio_*`) consistently prefixed.
- Fixed: composer lint script path. Outstanding (non-code): `readme.txt`.

## Estimated Readiness

**~92% release-candidate ready.**
Code, security, i18n, accessibility, and safety are RC-grade with no blockers. The remaining ~8% is distribution packaging (`readme.txt`), optional automated tests, and optional large-scale stats caching — none of which block an RC build.

## Version Recommendation

`0.9.0` is **appropriate** for a Release Candidate / late beta: feature-complete and audited, but pending `readme.txt`, a test pass, and final release validation. Suggested path: tag the first candidate as **`0.9.0-rc.1`** (or keep `0.9.0` as the beta line), then promote to **`1.0.0`** once `readme.txt` and final QA land. Do not jump to `1.0.0` yet.
