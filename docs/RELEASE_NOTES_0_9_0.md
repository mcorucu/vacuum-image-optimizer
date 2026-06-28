# Release Notes — Vacuum Image Optimizer 0.9.0

**Release type:** First public WordPress.org release
**Date:** 2026-06-28
**Requires:** WordPress 6.2+ · PHP 8.1+
**License:** GPLv2 or later
**WordPress.org plugin page:** https://wordpress.org/plugins/vacuum-image-optimizer/
**WordPress.org SVN:** https://plugins.svn.wordpress.org/vacuum-image-optimizer
**SVN revision:** 3588409

---

## Overview

Version 0.9.0 is the first public WordPress.org release of Vacuum Image Optimizer — a complete, self-contained image optimization toolkit for WordPress. It generates modern WebP and AVIF formats, optimizes your library in bulk, automates new uploads, and serves optimized images on the frontend, all while keeping your originals untouched.

## Publication Status

- Published to WordPress.org SVN in revision 3588409.
- Public plugin page: https://wordpress.org/plugins/vacuum-image-optimizer/
- GitHub release: https://github.com/mcorucu/vacuum-image-optimizer/releases/tag/v0.9.0

## New Features

- **WebP engine** — generates optimized WebP derivatives from JPEG/PNG using Imagick or GD.
- **AVIF engine** — generates AVIF as a parallel format when the server supports it; failures never affect WebP.
- **Bulk optimization queue** — scan the media library and process eligible images in safe AJAX batches you can start, pause, and resume.
- **Upload automation** — automatically optimize new JPEG/PNG uploads in **queue** or **immediate** mode.
- **Frontend delivery** — serve WebP/AVIF at render time with automatic fallback to originals; database URLs are never changed.
- **Native lazy loading** — adds `loading="lazy"` to frontend images using the browser's built-in behavior (no JavaScript).
- **Media Library integration** — per-image WebP/AVIF/Restore actions, an optimization status column, and generated derivatives registered as Media Library items.
- **Backups & restore** — optional original backups with one-click restore.
- **Reports** — storage savings, recent activity, top savings, format distribution, automation stats, and CSV export.
- **System status** — engine capabilities and a production-readiness panel.
- **Localization** — fully translatable with 9 bundled languages and an interface-language selector.

## Highlights

- **Non-destructive:** originals are never modified or deleted; optimized files are written alongside them.
- **Reversible:** frontend delivery and automation can be toggled off with no lasting changes to your media.
- **Self-contained:** no external services, APIs, or accounts; all processing is local.
- **Accessible, responsive admin UI** following a consistent design system.

## Known Limitations

- WebP/AVIF generation requires Imagick or GD with the relevant format support (verify on **System Status**).
- A single full-size derivative is generated per image; intermediate thumbnail sizes are served in their original format.
- Frontend delivery relies on the request `Accept` header; page caches should vary on `Accept` (or rely on near-universal WebP support).
- Report summary figures are cached briefly (about 5 minutes) and may lag immediately after large operations.
- AVIF availability and speed depend on the server's image libraries.

## Upgrade Notes

- This is the initial release; no migration steps are required.
- After activating, visit **Media → Vacuum Image Optimizer → System Status** to confirm WebP/AVIF support, then use **Bulk Optimize** to process existing images.
- Uninstalling performs database-only cleanup (options, plugin tables, and `_vacimg_*` metadata). Image files, including generated WebP/AVIF and backups, are intentionally left in place.
