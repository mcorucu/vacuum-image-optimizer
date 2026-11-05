=== Vacuum Image Optimizer ===
Contributors: mcorucu
Author URI: https://mcorucu.com
Plugin URI: https://mcorucu.com/en/projects/vacuum-image-optimizer
Tags: image optimization, webp, avif, compression, lazy load
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local WebP and AVIF image optimization with safe bulk processing, backup and restore tools, diagnostics, CSV reports, and no external API.

== Description ==

Vacuum Image Optimizer generates WebP and optional AVIF images locally on your WordPress server. JPEG and PNG source files remain unchanged during derivative generation. Uploaded WebP sources can be recompressed in place only after a verified backup has been created, and only when the result is smaller.

Everything happens inside a clean, friendly admin interface — no command line, no external services, and no account required.

Vacuum writes generated formats alongside source files and serves them on the frontend without changing stored media URLs. WebP source recompression is the exception to derivative-only processing: Vacuum first keeps a restorable copy, then replaces that WebP source only when recompression reduces its size.

**What it does**

* **WebP generation and source optimization** — Create optimized WebP copies of JPEG/PNG images and safely recompress uploaded WebP source files when the result is smaller.
* **AVIF generation** — Optionally generate AVIF, a newer format that is often even smaller than WebP, as a parallel format when the server supports it.
* **Bulk optimization** — Scan your media library and process eligible images in safe, batched background steps.
* **Queue processing** — A reliable queue runs work in small WordPress AJAX batches with pause, resume, safe cancellation, retry, and scan-all controls.
* **Upload automation** — Automatically optimize new JPEG, PNG, and WebP uploads after WordPress creates metadata; Safe Mode queues work conservatively.
* **Frontend delivery** — Serve generated WebP/AVIF on the frontend with automatic, safe fallback to the original image — your media URLs in the database are never changed.
* **Reports** — See storage savings, recent activity, top savings, format distribution, and automation stats, with one-click CSV export.
* **Localization** — Fully translatable, shipping with 9 bundled languages plus an in-plugin interface-language selector.
* **Local processing** — No external API dependency; optimization runs on your WordPress server.

**Safe by design**

JPEG and PNG originals are preserved while WebP and AVIF derivatives are generated. When an uploaded WebP source is recompressed, Vacuum verifies a backup before it can replace the source, and only publishes a smaller result. Restore copies the saved source back and removes plugin-owned derivatives while keeping backup files. Safe Mode uses conservative upload processing and keeps originals available for fallback.

**Privacy and data handling**

Images are optimized locally on your own WordPress server. Vacuum does not use an external API and does not send image data to third-party services. Optimization metadata and optional backups stay in your local WordPress installation.

== Features ==

* WebP image generation (Imagick or GD)
* WebP source-file recompression with backup-first safety
* AVIF image generation as a parallel format
* Optional setup wizard with Safe Mode defaults
* Bulk optimization with scan, scan-all, start, pause, resume, safe cancellation, retry, and clear-failed controls
* Automatic optimization of new uploads after attachment metadata generation
* Non-destructive frontend delivery with original fallback
* Native browser lazy loading (no JavaScript)
* Per-image WebP/AVIF actions in the Media Library
* Generated derivatives registered as Media Library items
* Original backups and one-click restore
* Expanded exclusions for MIME types, file sizes, filenames, and paths
* Local processing with no external API dependency
* Compression profiles and adjustable quality
* GIF and SVG eligibility exclusions
* Reports dashboard with detailed CSV export
* WordPress 7.1.4 compatibility, PHP 8.1–8.3 readiness, and copyable System Status diagnostics
* Interface language selector with 9 bundled translations
* Clean, accessible, mobile-responsive admin UI

== Installation ==

1. Upload the `vacuum-image-optimizer` folder to the `/wp-content/plugins/` directory, or install the plugin through the **Plugins → Add New** screen in WordPress.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Media → Vacuum Image Optimizer** to open the dashboard.
4. Complete or skip the optional setup wizard.
5. Open the **Compression** tab to choose a compression profile and quality, and to optionally enable AVIF, upload automation, and frontend delivery.
6. Open the **Bulk Optimize** tab, click **Optimize all unoptimized images**, then **Start Queue** to optimize existing images.

== Frequently Asked Questions ==

= Does Vacuum replace my original images? =
JPEG and PNG files are left unchanged while derivatives are generated. Uploaded WebP sources may be recompressed in place only after a backup is created and verified, and only if the result is smaller. Frontend delivery uses a generated derivative when the browser accepts it and otherwise falls back to the source image.

= Does it support AVIF? =
Yes. AVIF generation is available as a parallel format when your server supports it (via Imagick or a GD build with AVIF). If AVIF is unavailable, WebP and originals continue to work normally.

= Can I restore my originals? =
Yes. When backups are enabled, each original is copied before optimization, and you can restore it at any time from the per-image **Restore** action in the Media Library.

= Does it support bulk optimization? =
Yes. The Bulk Optimize tab scans eligible JPEG, PNG, and WebP images and processes them in safe, batched steps that you can start, pause, and resume.

= Does it optimize uploaded WebP files? =
Yes. Existing WebP source files can be recompressed safely. Vacuum creates a backup first and replaces the source only when the optimized WebP is smaller.

= Does it work with WooCommerce? =
Yes. Frontend delivery hooks into the standard WordPress image functions used by WooCommerce product and gallery images, so generated formats are served automatically with fallback to originals.

= Does it support multilingual sites? =
The admin interface is fully translatable and ships with 9 languages. You can also pick a specific interface language in the Compression settings, independent of the site language.

= Will it change my image URLs in the database? =
No. Frontend delivery selects a validated WebP or AVIF derivative at render time based on the request's Accept header. Vacuum sends `Vary: Accept`; configure page caches and CDNs to respect that header or to vary their cache key by Accept, so one browser's image response is not served to another. Stored attachment URLs are not rewritten.

= What are the requirements? =
WordPress 6.2+ and PHP 8.1+. WebP/AVIF generation requires the Imagick or GD extension with the relevant format support, which you can verify on the System Status tab.

== Screenshots ==

1. Dashboard — savings, key metrics, and feature status at a glance.
2. Media Library — per-image WebP/AVIF status and one-click actions.
3. Bulk Optimize — queue with start, pause, and resume controls.
4. Compression — profiles, quality, and WebP/AVIF options.
5. Reports — storage savings, recent activity, and top savings.
6. System Status — engine support and production-readiness checks.
7. Localization — built-in interface-language selector.

== Changelog ==

= 1.2.2 =
* Revalidates registered derivative byte sizes and regenerates stale derivatives atomically after source changes.
* Honors Accept media tokens and quality values and keeps original fallback for missing or corrupt files.
* Verifies backup content, creates a new backup when a source changes, and restores only from the owned backup directory.
* Removes registered responsive derivatives when restoring or deleting source attachments while preserving backup files.
* Requires attachment-specific edit capability for Media Library actions and neutralizes spreadsheet formulas in CSV text cells.
* Validates physical image MIME and serializes duplicate queue insertion.

= 1.2.1 =
* Added compatibility with WordPress 7.1 client-side media processing.
* Hardened automatic upload processing for create/update attachment metadata passes.
* Reconciled client-generated registered sizes without duplicate optimization, backups, queue jobs, or modern derivatives.
* Added physical-file ownership, MIME, dimension, and concurrency checks for responsive derivatives.

= 1.2.0 =
* Added attachment-aware automatic frontend delivery for AVIF and WebP with original fallback.
* Added size-specific derivatives for registered WordPress image sizes and responsive srcset candidates.
* Added source-hash validation, cache negotiation, missing-file fallback, and delivery diagnostics.
* Added safe upgrade-preserving delivery settings and documented rollback/cache behavior.

= 1.1.0 =
* Confirmed compatibility with WordPress 7.0 and improved PHP 8.3 runtime readiness.
* Expanded System Status with WordPress compatibility, image engine versions, debug, cron, REST, AJAX, and safe filesystem diagnostics plus Copy Diagnostics.
* Improved automatic upload optimization to wait for attachment metadata and preserve Safe Mode queue behavior.
* Added scan-all, safe cancel-pending, retry-failed, clear-failed, resume state, remaining, batch-size, and elapsed-time bulk controls.
* Expanded CSV reports with attachment metadata, status, generated WebP/AVIF filenames and state, dates, and safe failure reasons.
* Improved Media Library status for automatic pending, optimized, skipped, and retry states.
* Updated privacy and local-data handling notes.

= 1.0.1 =
* Added optional setup wizard with skip/complete flow and settings relaunch.
* Added WebP source optimization for uploaded WebP files with backup-first replacement only when smaller.
* Improved image format handling for JPEG, PNG, WebP, AVIF capability checks, GIF skipping, and SVG exclusion.
* Improved server compatibility checks for WordPress/PHP versions, writable directories, Imagick/GD, WebP/AVIF read/write support, execution time, memory, and disk free space.
* Improved backup and restore safety with clearer status and metadata handling.
* Improved bulk optimization workflow labels, skipped counts, retry/pause/resume visibility, and WebP source eligibility.
* Improved Media Library status/actions with compact format availability, savings, skipped reasons, optimize, WebP/AVIF, and restore links.
* Added safer default settings and Safe Mode improvements.

= 0.9.0 =
* Initial public release candidate.
* WebP generation engine (Imagick/GD).
* AVIF generation engine as a parallel format.
* Bulk optimization with a start/pause/resume queue and batched AJAX processing.
* Upload automation with queue and immediate modes.
* Non-destructive frontend delivery with original fallback and native lazy loading.
* Media Library integration: per-image actions, status column, and registered derivative attachments.
* Original backups and one-click restore.
* Compression profiles, adjustable quality, and GIF/SVG exclusions.
* Reports dashboard with storage savings, recent activity, top savings, format distribution, and CSV export.
* System status and production-readiness checks.
* Full internationalization with 9 bundled languages and an interface-language selector.

== Upgrade Notice ==

= 1.2.2 =
Hardens derivative validation and regeneration, backup/restore behavior, Accept negotiation, CSV exports, queue insertion, and per-attachment authorization.

= 1.2.0 =
Adds automatic modern image delivery with responsive-size mapping, safe original fallback, cache-aware negotiation, and delivery diagnostics. Existing settings and originals are preserved; enable delivery from Compression settings.

= 1.1.0 =
Adds WordPress 7.0 compatibility, PHP 8.3 readiness, improved diagnostics, safer upload automation, resilient bulk queue controls, and expanded CSV reports.

= 1.0.1 =
Adds optional setup, WebP source optimization, expanded format handling, improved server checks, safer defaults, and Media Library/bulk workflow improvements.

= 0.9.0 =
First public release candidate of Vacuum Image Optimizer. Generate WebP/AVIF, bulk-optimize, automate uploads, and deliver modern formats locally. JPEG/PNG sources remain unchanged during derivative generation; WebP sources can be recompressed after backup.
