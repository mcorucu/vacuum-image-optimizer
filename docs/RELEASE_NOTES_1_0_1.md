# Release Notes - Vacuum Image Optimizer 1.0.1

**Release type:** Feature and safety update  
**Version:** 1.0.1  
**Status:** Prepared for QA and packaging

## Summary

Vacuum Image Optimizer 1.0.1 improves the first-run experience, expands source image support, and makes server capability feedback clearer. The update keeps the 0.9.0 data model compatible while adding safer defaults and more explicit handling for unsupported or risky files.

## Highlights

- Added an optional setup wizard with skip, complete, and relaunch flows.
- Added WebP source optimization for uploaded `.webp` files, with backup-first replacement only when the optimized result is smaller.
- Improved supported source handling for JPEG, PNG, and WebP.
- Added clearer skipping for unsupported formats, SVG files, animated GIF files, unreadable files, and exclusion matches.
- Improved System Status checks for WordPress/PHP versions, writable upload and backup directories, Imagick/GD availability, WebP/AVIF read/write support, execution time, memory, and disk free space.
- Improved Media Library status with compact format availability, savings, skipped reasons, and direct Optimize/WebP/AVIF/Restore actions.
- Improved bulk optimization labels and dashboard counters for skipped images, generated formats, total savings, and last optimization date.
- Added safer defaults: Safe Mode enabled, backups enabled, lazy loading enabled, WebP enabled, JPEG/WebP quality 82, AVIF quality 60.

## Compatibility

- Existing `vacimg_settings` data is normalized with new defaults when missing.
- Existing backup, queue, stats, and derivative metadata keys remain unchanged.
- No database schema change is required for this update.

## QA Focus

- Activation and setup wizard completion/skip.
- Settings save and wizard relaunch.
- JPEG, PNG, and WebP source optimization.
- Unsupported SVG and animated GIF skipping.
- Backup creation before WebP source replacement.
- Restore original from backup.
- Bulk queue scan/start/pause/resume/retry.
- Media Library Vacuum column actions.
- System Status checks on servers with different GD/Imagick support.
