# Vacuum Image Optimizer — Development Roadmap

> Document Version: 1.0.0  
> Date: 2026-06-18  
> Status: Architecture Phase — No code yet

---

## Phase 1: MVP (Minimum Viable Product)

**Goal:** A stable, feature-complete free plugin ready for WordPress.org submission.

---

### Milestone 1.1 — Foundation & Bootstrap
**Estimated Complexity:** Low  
**Target Duration:** 1 week

**Deliverables:**
- [ ] `vacuum-image-optimizer.php` plugin header and bootstrap.
- [ ] `composer.json` with PSR-4 autoloading for `VacuumImageOptimizer\`.
- [ ] `Core\Plugin` singleton with `init()` and service wiring.
- [ ] `Core\Container` lightweight DI container.
- [ ] `Core\Hooks` central hook registration.
- [ ] `Core\Assets` admin asset enqueue skeleton.
- [ ] Activation hook (`Core\Installer`) creating custom tables via `dbDelta()`.
- [ ] Deactivation hook flushing cron and rewrite rules.

**Definition of Done:**
- Plugin activates without errors.
- Custom tables `vio_queue`, `vio_stats`, `vio_backups` are created.
- Namespace autoloading works (smoke test with a dummy class).

---

### Milestone 1.2 — Optimization Engine Core
**Estimated Complexity:** High  
**Target Duration:** 2 weeks

**Deliverables:**
- [ ] `Engine\ProfileManager` with default profiles (lossless, balanced, aggressive, custom).
- [ ] `Engine\Compressor` with GD and Imagick adapters.
- [ ] `Engine\Resizer` with max width/height and aspect ratio protection.
- [ ] `Engine\WebPGenerator` with backend detection.
- [ ] `Engine\AVIFGenerator` with backend detection.
- [ ] `Engine\Optimizer` orchestrating the full pipeline.
- [ ] `Media\AttachmentHandler` hooking into upload lifecycle.
- [ ] `Media\ExclusionManager` with folder, filename, size, post type, GIF, SVG rules.
- [ ] DTO: `DTO\OptimizationResult`.

**Definition of Done:**
- Uploading a JPEG auto-compresses and generates WebP.
- Uploading a PNG auto-compresses and generates WebP.
- AVIF generates only when server supports it.
- Exclusion rules correctly skip matching files.
- Failed optimizations leave the original file untouched.

---

### Milestone 1.3 — Backup & Restore System
**Estimated Complexity:** Medium  
**Target Duration:** 1 week

**Deliverables:**
- [ ] `Backup\BackupManager` with SHA-256 integrity checks.
- [ ] `Backup\RestoreManager` for individual and bulk restores.
- [ ] `Backup\CleanupTask` WP-Cron job for retention cleanup.
- [ ] Backup directory protection (`.htaccess`, `index.php`).
- [ ] Settings for enable/disable backups and retention period.

**Definition of Done:**
- Every optimized image has a verified backup.
- Restore returns the exact original file (hash match).
- Backups older than retention are auto-deleted.
- Disabling backups skips backup creation but still optimizes.

---

### Milestone 1.4 — Bulk Optimization UI
**Estimated Complexity:** High  
**Target Duration:** 2 weeks

**Deliverables:**
- [ ] `Admin\Menu` registering a single submenu under **Media → Vacuum Optimizer** with internal tab routing.
- [ ] `Admin\Dashboard` page with stat cards and system health.
- [ ] `Admin\AjaxHandler` with all dashboard and bulk endpoints.
- [ ] `Engine\QueueManager` for queue CRUD operations.
- [ ] Bulk scan, process, pause, resume, cancel logic.
- [ ] Real-time progress bar via AJAX polling.
- [ ] Error log panel with retry functionality.

**Definition of Done:**
- "Scan Media Library" correctly counts pending images.
- "Start Optimization" processes images in batches.
- Pause stops processing; Resume continues.
- Cancel clears the queue.
- Dashboard stats update after bulk job completes.

---

### Milestone 1.5 — Media Library Integration
**Estimated Complexity:** Medium  
**Target Duration:** 1 week

**Deliverables:**
- [ ] `Admin\MediaLibrary` adding custom columns.
- [ ] Inline row actions: Optimize, Restore, Regenerate.
- [ ] Bulk actions dropdown on Media Library list table.
- [ ] AJAX handlers for single-image actions.
- [ ] Column sorting and filtering support.

**Definition of Done:**
- Custom columns display correctly for all image attachments.
- Inline actions trigger the expected optimization/restore.
- Bulk actions work on selected items.
- No JavaScript errors in the Media Library.

---

### Milestone 1.6 — Frontend Output & Lazy Loading
**Estimated Complexity:** Medium  
**Target Duration:** 1 week

**Deliverables:**
- [ ] `Frontend\PictureElement` wrapping images in `<picture>` with `<source>` tags.
- [ ] `Media\SrcsetFilter` injecting modern format URLs into `srcset`.
- [ ] `Media\LazyLoader` injecting `loading="lazy"` with exclusions.
- [ ] `Frontend\CompatibilityLayer` for WooCommerce and page builders.
- [ ] Settings page tabs: Compression, Resize, Lazy Load, Exclusions, Backup.

**Definition of Done:**
- Frontend images render with `<picture>` when WebP/AVIF exists.
- `srcset` includes WebP variants.
- Lazy loading respects exclusion rules.
- WooCommerce product galleries display optimized images.

---

### Milestone 1.7 — Settings, System Status & Polish
**Estimated Complexity:** Low-Medium  
**Target Duration:** 1 week

**Deliverables:**
- [ ] `Admin\Settings` with WordPress Settings API integration.
- [ ] `Admin\Notices` for dismissible success/error/warning messages.
- [ ] `Admin\SystemStatus` page with server capability reports.
- [ ] `Utils\SystemCheck` with comprehensive capability detection.
- [ ] `Utils\Logger` for structured debug logging.
- [ ] `Utils\TransientCache` for dashboard stat caching.
- [ ] Final CSS/JS polish, responsive testing, accessibility audit.

**Definition of Done:**
- All settings save and persist correctly.
- Notices appear and dismiss properly.
- System Status page accurately reflects server capabilities.
- Plugin passes WordPress Coding Standards (`phpcs`).
- No console errors; accessible keyboard navigation.

---

### Milestone 1.8 — Testing & WordPress.org Preparation
**Estimated Complexity:** Medium  
**Target Duration:** 1 week

**Deliverables:**
- [ ] Unit tests for `ProfileManager`, `Compressor`, `Resizer`.
- [ ] Integration tests for upload optimization flow.
- [ ] Manual testing on fresh WordPress installs (PHP 8.1, 8.2, 8.3).
- [ ] Manual testing with GD-only and Imagick-only environments.
- [ ] README.txt for WordPress.org (markdown format).
- [ ] Screenshots for WordPress.org (5+ images).
- [ ] Banner and icon assets exported to `/assets/wordpress-org/`.

**Definition of Done:**
- All core features tested manually with real image uploads.
- Plugin passes `phpcs` with WordPress Coding Standards.
- Asset files meet WordPress.org specifications.
- Ready for SVN tagging and submission.

---

## Phase 2: Post-MVP Enhancements (Future)

### 2.1 Multisite Support
- Network-level settings page.
- Per-site override capability.
- Shared backup storage or per-blog isolation.

### 2.2 CDN Integration
- Automatic cache purge hooks for Cloudflare, BunnyCDN, KeyCDN.
- Filter for custom CDN purge integration.

### 2.3 Advanced Analytics
- Historical optimization charts (30/90/365 days).
- Per-page image weight reporting.
- Integration with Query Monitor.

### 2.4 Premium Extension Hooks
- Architecture already supports extension loading via `vio_premium_init` action.
- Cloud compression API adapters (future premium).
- PDF and video optimization (future premium).

---

## Timeline Summary

| Milestone | Duration | Cumulative |
|-----------|----------|------------|
| 1.1 Foundation | 1 week | Week 1 |
| 1.2 Engine Core | 2 weeks | Week 3 |
| 1.3 Backup & Restore | 1 week | Week 4 |
| 1.4 Bulk Optimization UI | 2 weeks | Week 6 |
| 1.5 Media Library | 1 week | Week 7 |
| 1.6 Frontend & Lazy Load | 1 week | Week 8 |
| 1.7 Settings & Polish | 1 week | Week 9 |
| 1.8 Testing & Release | 1 week | Week 10 |

**Total Estimated MVP Duration:** 10 weeks  
**Team:** 1 senior WordPress developer + 1 QA reviewer

---

## Risk Register

| Risk | Impact | Mitigation |
|------|--------|------------|
| Server lacks Imagick/GD support | High | Graceful degradation; clear status messaging |
| Large images exceed memory limit | High | Batch size auto-adjustment; chunk processing |
| Theme conflicts with `<picture>` element | Medium | Filter hooks for markup customization |
| WP-Cron disabled on host | Medium | Document manual cron setup; AJAX fallback |
| WordPress.org review delays | Low | Follow coding standards strictly; test thoroughly |
