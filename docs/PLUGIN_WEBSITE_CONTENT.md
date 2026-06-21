# Vacuum Image Optimizer — Plugin Website Content

Prepared for: <https://mcorucu.com/vacuum-image-optimizer/>

---

## 1. Hero Section

### Headline

Optimize WordPress images with WebP, AVIF, automation, and safe frontend delivery.

### Subheadline

Vacuum Image Optimizer is a modern image optimization plugin for WordPress. Generate WebP and AVIF versions, bulk-optimize your Media Library, automate new uploads, serve lighter images on the frontend, and keep every original file safe.

### Primary Call to Action

Download Vacuum Image Optimizer

### Secondary Call to Action

View Setup Guide

### Hero Highlights

- Generate WebP and AVIF images from JPEG and PNG uploads.
- Process existing media in safe, resumable batches.
- Automatically optimize new uploads.
- Serve modern formats on the frontend without changing stored media URLs.
- Keep originals protected with optional backups and restore actions.
- Review savings, activity, formats, and automation stats from built-in reports.

### Trust Copy

Built for WordPress 6.2+ and PHP 8.1+. No external image service, no account requirement, and no destructive edits to original media files.

---

## 2. Features Section

### Section Headline

Everything you need to reduce image weight in WordPress.

### Section Intro

Vacuum Image Optimizer brings the core image optimization workflow into one clean WordPress admin experience: format generation, bulk processing, upload automation, frontend delivery, reports, backups, restore tools, and system checks.

### Feature Cards

#### WebP Generation

Create optimized WebP versions of JPEG and PNG images using available server image engines such as Imagick or GD.

#### AVIF Generation

Optionally generate AVIF copies as a parallel next-generation format when your server supports AVIF output.

#### Bulk Optimization

Scan your Media Library and optimize eligible images through safe, batched queue processing with start, pause, and resume controls.

#### Upload Automation

Automatically optimize new JPEG and PNG uploads so new media enters your library ready for modern delivery.

#### Frontend Delivery

Serve generated WebP or AVIF files to visitors when supported, with automatic fallback to original images.

#### Reports Dashboard

Track total savings, recent activity, top savings, format distribution, automation stats, and export report data to CSV.

#### Media Library Actions

View optimization status in the Media Library and run per-image actions such as WebP generation, AVIF generation, regeneration, and restore.

#### Backup and Restore

Keep optional backups of original images before optimization and restore originals when needed.

#### System Status

Check PHP, WordPress, GD, Imagick, WebP, AVIF, queue table, upload path, backup path, and localization readiness from one screen.

#### Localization

Use the bundled interface-language selector and included translations for a more comfortable admin experience.

---

## 3. WebP Section

### Headline

Generate WebP images directly inside WordPress.

### Copy

WebP is widely supported by modern browsers and often reduces image size while preserving visual quality. Vacuum Image Optimizer creates WebP files alongside your originals, so your existing media records remain intact.

### Key Points

- Supports JPEG and PNG source images.
- Uses available server capabilities through Imagick or GD.
- Works with manual actions, bulk optimization, and upload automation.
- Refreshes optimization metadata when an existing WebP file is found.
- Never replaces or overwrites the original image URL stored in WordPress.

### Suggested CTA

Start generating WebP images

---

## 4. AVIF Section

### Headline

Add AVIF when your server supports it.

### Copy

AVIF can deliver even smaller files than WebP for many images. Vacuum Image Optimizer treats AVIF as an optional parallel format, so you can enable it when your server is ready while keeping WebP and original fallback behavior available.

### Key Points

- Optional AVIF generation with adjustable quality.
- Uses Imagick AVIF or GD AVIF support when available.
- Runs alongside WebP instead of replacing it.
- Fails safely when AVIF is unavailable, leaving WebP and originals unaffected.
- Helps prepare image delivery for modern browsers and future performance needs.

### Suggested CTA

Check AVIF server readiness

---

## 5. Frontend Delivery Section

### Headline

Serve lighter image formats without changing your stored media URLs.

### Copy

Frontend Delivery swaps eligible image URLs at render time when a generated WebP or AVIF file exists and the visitor's browser supports it. If a modern derivative is unavailable, the original image is served automatically.

### Key Points

- Browser-aware delivery for AVIF and WebP.
- Original-image fallback for safety and compatibility.
- No database URL rewrites.
- Can be enabled or disabled from plugin settings.
- Designed to work with standard WordPress image output and common theme patterns.

### Note

For best cache compatibility, page cache configurations should account for browser format support when caching HTML that contains optimized image URLs.

### Suggested CTA

Enable frontend delivery

---

## 6. Upload Automation Section

### Headline

Optimize new uploads automatically.

### Copy

Vacuum Image Optimizer can process new JPEG and PNG uploads as they enter the Media Library. Choose queue mode for controlled background processing or immediate mode when you want new uploads optimized right away.

### Key Points

- Automatically handles eligible new JPEG and PNG uploads.
- Queue mode adds new images to the optimization queue.
- Immediate mode optimizes during the upload workflow.
- Tracks upload automation activity in dashboard and reports.
- Keeps automation settings reversible and easy to manage.

### Suggested CTA

Automate new image optimization

---

## 7. Reports Section

### Headline

See what you saved.

### Copy

Reports make optimization results visible. Review total original size, optimized size, storage saved, average savings, format counts, recent activity, top savings, and automation statistics without leaving WordPress.

### Key Points

- Storage savings overview.
- Recent optimization activity.
- Top-saving images.
- WebP and AVIF format distribution.
- Upload automation statistics.
- CSV export for external review or reporting.

### Suggested CTA

Open optimization reports

---

## 8. Localization Section

### Headline

Use Vacuum Image Optimizer in your preferred admin language.

### Copy

Vacuum Image Optimizer is fully internationalized and includes bundled translations plus an in-plugin interface-language selector. You can follow the WordPress default language or choose a plugin-specific interface language.

### Bundled Languages

- English
- Turkish
- German
- French
- Spanish
- Italian
- Portuguese
- Russian
- Dutch
- Polish

### Key Points

- Translation-ready with the `vacuum-image-optimizer` text domain.
- Bundled `.po` and `.mo` language files.
- Interface-language selector in plugin settings.
- Missing translations fall back to English.

### Suggested CTA

Choose your interface language

---

## 9. FAQ

### Does Vacuum Image Optimizer replace my original images?

No. Originals are kept safe. Generated WebP and AVIF files are written alongside the originals, and frontend delivery falls back to originals when needed.

### Does it support WebP?

Yes. Vacuum Image Optimizer generates WebP versions for eligible JPEG and PNG images when your server supports WebP through Imagick or GD.

### Does it support AVIF?

Yes. AVIF generation is optional and available when your server supports AVIF through Imagick or GD.

### Can I bulk-optimize an existing Media Library?

Yes. Use the Bulk Optimize screen to scan eligible media and process images through a safe queue with start, pause, and resume controls.

### Can new uploads be optimized automatically?

Yes. Upload automation can add new eligible uploads to the queue or optimize them immediately, depending on your selected mode.

### Will frontend delivery change image URLs in my database?

No. Frontend Delivery works at render time and does not rewrite stored media URLs in the database.

### What happens if a visitor's browser does not support AVIF or WebP?

The original image is served automatically. Vacuum Image Optimizer is designed around safe fallback behavior.

### Can I restore original images?

Yes, when backups are enabled before optimization. The plugin includes restore actions for images with available backups.

### What are the minimum requirements?

Vacuum Image Optimizer requires WordPress 6.2+ and PHP 8.1+. WebP and AVIF generation depend on server image-engine support.

### Does it use an external optimization API?

No. Image generation runs inside your WordPress environment using available server image extensions.

---

## 10. Screenshots Section

### Section Headline

Explore the Vacuum Image Optimizer admin experience.

### Screenshot Captions

1. **Dashboard** — savings, key metrics, feature status, queue overview, and recent activity at a glance.
2. **Media Library** — per-image WebP/AVIF status and one-click image actions.
3. **Bulk Optimize** — scan the Media Library and process the queue with start, pause, and resume controls.
4. **Compression** — configure profiles, quality, upload automation, AVIF generation, frontend delivery, and interface language.
5. **Reports** — review storage savings, recent activity, top savings, format distribution, and export CSV reports.
6. **System Status** — verify server image-engine support, writable paths, queue table readiness, delivery configuration, and localization status.
7. **Localization** — choose the plugin interface language independently of the WordPress default when needed.

### Suggested Screenshot Intro

The plugin is designed to keep optimization workflows clear and approachable, with dedicated screens for setup, processing, delivery, reporting, and troubleshooting.

---

## 11. Download Section

### Headline

Download Vacuum Image Optimizer 0.9.0.

### Copy

Get the first public release candidate of Vacuum Image Optimizer and start generating WebP and AVIF images from your WordPress Media Library.

### Requirements

- WordPress 6.2 or newer.
- PHP 8.1 or newer.
- Imagick or GD for WebP generation.
- Imagick or GD with AVIF support for AVIF generation.

### Installation Steps

1. Download the plugin ZIP.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP file and activate the plugin.
4. Open **Media → Vacuum Image Optimizer**.
5. Visit **System Status** to verify server support.
6. Configure settings, then run **Bulk Optimize** or enable upload automation.

### Suggested CTA

Download version 0.9.0

---

## 12. Support Section

### Headline

Need help with Vacuum Image Optimizer?

### Copy

For support, include your WordPress version, PHP version, active image engines, and the copied System Status report from the plugin.

### Support Email

mcorucu@gmail.com

### Helpful Details to Include

- WordPress version.
- PHP version.
- Whether GD and/or Imagick are available.
- WebP and AVIF support status.
- Upload and backup path writable status.
- A short description of the image, action, or workflow that needs help.

---

## 13. Changelog Section

### Version 0.9.0

Initial public release candidate.

#### Highlights

- WebP generation engine with Imagick/GD support.
- Optional AVIF generation as a parallel format.
- Bulk optimization queue with start, pause, resume, and batched AJAX processing.
- Upload automation with queue and immediate modes.
- Non-destructive frontend delivery with original fallback.
- Native browser lazy loading.
- Media Library integration with per-image actions, status column, and derivative attachments.
- Optional original backups and restore actions.
- Compression profiles and adjustable quality settings.
- GIF and SVG eligibility exclusions.
- Reports dashboard with CSV export.
- System status and production-readiness checks.
- Full internationalization with bundled languages and interface-language selector.
