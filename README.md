# Vacuum Image Optimizer

[![WordPress plugin](https://img.shields.io/badge/WordPress.org-published-21759B)](https://wordpress.org/plugins/vacuum-image-optimizer/)
[![Version](https://img.shields.io/badge/version-1.2.1-059669)](https://github.com/mcorucu/vacuum-image-optimizer/releases/tag/v1.2.1)
[![Requires PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPLv2%20or%20later-111827)](https://www.gnu.org/licenses/gpl-2.0.html)

Local WebP and AVIF image optimization for WordPress, with queue-based bulk processing, upload automation, backup/restore tools, frontend delivery, reports, multilingual admin UI, and no external API dependency.

## Official Documentation

Read the full product documentation at:

<https://docs.mcorucu.com/vacuum-image-optimizer/>

Key docs:

- [Installation](https://docs.mcorucu.com/vacuum-image-optimizer/installation/)
- [Quick Start](https://docs.mcorucu.com/vacuum-image-optimizer/quick-start/)
- [Compression Settings](https://docs.mcorucu.com/vacuum-image-optimizer/compression-settings/)
- [WebP Source Optimization](https://docs.mcorucu.com/vacuum-image-optimizer/webp-source-optimization/)
- [Backup & Restore](https://docs.mcorucu.com/vacuum-image-optimizer/backup-restore/)
- [Troubleshooting](https://docs.mcorucu.com/vacuum-image-optimizer/troubleshooting/)

## Current Release

- Current version: `1.2.1`
- WordPress.org status: 1.2.0 published; 1.2.1 qualification pending
- Latest WordPress.org SVN revision: `3623840` (1.2.0)
- Stable tag: `1.2.1`
- Tested up to: WordPress `7.1`
- Requires at least: WordPress `6.2`
- Requires PHP: `8.1`

## Features

- Optional setup wizard with complete, skip, and relaunch flows.
- Safe Mode defaults for conservative production setup.
- Generate WebP files for JPEG and PNG sources using local Imagick or GD support.
- Safely recompress uploaded WebP source files with backup-first replacement only when smaller.
- Optionally generate AVIF files when the server supports AVIF.
- Bulk optimize existing Media Library images with scan-unoptimized, scan-all, pause, resume, safe cancellation, and retry/clear-failure controls.
- Generate missing modern formats for existing Media Library attachments with a readiness scan and resumable migration controls.
- Automatically process new uploads after WordPress generates attachment metadata; Safe Mode queues work conservatively.
- Preserve originals and optionally keep restorable backups.
- Restore original files from backups when available.
- Serve generated WebP/AVIF on the frontend with safe fallback to original images.
- Map responsive `srcset` candidates to matching per-size derivatives without rewriting stored post content.
- Inspect per-attachment delivery diagnostics, selected candidates, fallback state, queue state, and stale metadata.
- Apply native lazy loading through WordPress image output filters.
- Show per-image status, savings, skipped reasons, and actions in the Media Library.
- View storage savings, recent activity, top savings, and CSV exports with source status and generated-format details.
- Use a multilingual admin interface with bundled translations and an interface-language selector.
- Exclude risky sources by MIME type, file size, filename pattern, path pattern, SVG, or GIF.
- Run local WordPress 7.1 and PHP 8.3 readiness checks for image engines, uploads, backups, memory, execution time, disk space, cron, REST, AJAX, and debug mode.

## Screenshots

WordPress.org screenshots are available in the plugin listing:

<https://wordpress.org/plugins/vacuum-image-optimizer/#screenshots>

The local package also includes branding screenshots under `assets/branding/`.

## Requirements

- WordPress `6.2` or newer
- PHP `8.1` or newer
- Imagick or GD with WebP support for WebP generation
- Imagick or GD with AVIF support for optional AVIF generation
- Writable uploads directory
- Writable backup directory when backups are enabled

## Installation

For normal use, install from the WordPress.org plugin directory:

1. In WordPress, go to **Plugins -> Add New**.
2. Search for **Vacuum Image Optimizer**.
3. Install and activate the plugin.
4. Open **Media -> Vacuum Image Optimizer**.
5. Review **System Status** and complete or skip the setup wizard.

For a release ZIP:

1. Download the ZIP from WordPress.org or GitHub Releases.
2. Go to **Plugins -> Add New -> Upload Plugin**.
3. Upload and activate the ZIP.
4. Confirm the plugin version is `1.2.1`.

For local development, clone this repository into:

```text
wp-content/plugins/vacuum-image-optimizer/
```

The runtime plugin includes a lightweight PSR-4 fallback autoloader, so Composer is not required for normal WordPress installs.

## Development Notes

- Runtime namespace: `VacuumImageOptimizer`
- Constant prefix: `VACIMG_`
- Option, metadata, hook, and action prefix: `vacimg_`
- Text domain: `vacuum-image-optimizer`
- Main settings option: `vacimg_settings`
- Queue table suffix: `vacimg_queue`

Useful docs:

- [Architecture](https://docs.mcorucu.com/vacuum-image-optimizer/architecture/)
- [File Structure](https://docs.mcorucu.com/vacuum-image-optimizer/file-structure/)
- [Optimization Pipeline](https://docs.mcorucu.com/vacuum-image-optimizer/optimization-pipeline/)
- [Hooks & Filters](https://docs.mcorucu.com/vacuum-image-optimizer/hooks-filters/)
- [Developer Notes](https://docs.mcorucu.com/vacuum-image-optimizer/developer-notes/)

## Changelog

### 1.2.1

- Added compatibility with WordPress 7.1 client-side media processing.
- Hardened automatic upload processing for create/update attachment metadata passes.
- Reconciled client-generated registered sizes without duplicate optimization, backups, queue jobs, or modern derivatives.
- Added physical-file ownership, MIME, dimension, and concurrency checks for responsive derivatives.

### 1.2.0

- Automatic attachment-aware AVIF/WebP delivery with original fallback.
- Registered-size derivative generation for responsive `srcset` output.
- Existing-media migration with readiness scan, resumable controls, and idempotent reruns.
- Per-attachment Delivery Diagnostics using the same resolver as frontend delivery.
- Source-hash validation, cache-aware negotiation, and delivery diagnostics.
- Delivery remains reversible and never rewrites stored post content.

See:

- [Documentation changelog](https://docs.mcorucu.com/vacuum-image-optimizer/changelog/)
- [Release notes for 1.2.0](docs/RELEASE_NOTES_1_2_0.md)
- [Release notes for 1.2.1](docs/RELEASE_NOTES_1_2_1.md)
- [Release notes for 1.1.0](docs/RELEASE_NOTES_1_1_0.md)
- [Release notes for 1.0.1](docs/RELEASE_NOTES_1_0_1.md)
- [WordPress.org changelog](https://wordpress.org/plugins/vacuum-image-optimizer/#developers)

## Support

- User support: <https://wordpress.org/support/plugin/vacuum-image-optimizer/>
- GitHub issues: <https://github.com/mcorucu/vacuum-image-optimizer/issues>
- Project page: <https://mcorucu.com/en/projects/vacuum-image-optimizer>
- Documentation: <https://docs.mcorucu.com/vacuum-image-optimizer/>

When reporting an issue, include:

- WordPress version
- PHP version
- Plugin version
- Whether Imagick and GD are available
- Whether WebP and AVIF show as supported in System Status
- A short description of the source image format and action being attempted

## Contributing

Contributions should preserve the plugin's safety model:

- Do not introduce external image processing APIs without explicit product direction.
- Keep original files recoverable when source files can be changed.
- Use WordPress nonces and capability checks for admin actions.
- Sanitize input and escape output.
- Keep public identifiers consistently prefixed.
- Keep WordPress.org packaging clean and runtime-focused.

## Roadmap

See [docs/ROADMAP.md](docs/ROADMAP.md) for longer-term ideas. Near-term priorities are documentation quality, compatibility hardening, real-world QA, and careful iteration on image-processing safety.

## License

GPLv2 or later. See the [GNU General Public License v2.0](https://www.gnu.org/licenses/gpl-2.0.html).

## Privacy and Data Handling

Vacuum Image Optimizer processes images locally on the WordPress server. It does not use an external API and does not send image data to third-party services. Optimization metadata remains in WordPress; optional backups remain in the local uploads/plugin backup directory.
