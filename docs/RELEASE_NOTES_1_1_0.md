# Release Notes - Vacuum Image Optimizer 1.1.0

**Release type:** Compatibility and workflow update  
**Version:** 1.1.0  
**Status:** Released on WordPress.org, GitHub, docs.mcorucu.com, and mcorucu.com

## Summary

Vacuum Image Optimizer 1.1.0 prepares the plugin for WordPress 7.0 and modern PHP runtimes while keeping the existing safety model intact. The update adds clearer environment diagnostics, makes automatic upload optimization wait for WordPress attachment metadata, and strengthens bulk and reporting workflows without altering existing settings or metadata keys.

## Highlights

- WordPress 7.0 compatibility metadata and PHP 8.3 readiness review.
- Expanded System Status: WordPress compatibility, PHP recommendation, GD/Imagick versions, WebP/AVIF support, writable uploads/backups, disk space, cron, REST, AJAX, and debug state.
- Path-safe Copy Diagnostics output for support requests.
- Metadata-timed upload automation with Safe Mode queue behavior and clearer automatic status labels.
- Bulk queue scan-all, retry-failed, clear-failed, cancel-pending, resumable state, last processed attachment, remaining count, batch size, and elapsed-time feedback.
- Expanded CSV export with attachment ID, filename, MIME type, status, sizes, savings, generated-format filename/state, date, and safe reason fields.
- Updated privacy documentation: all optimization and backup data stays local to WordPress; no external API is used.

## Compatibility

- Existing `vacimg_settings`, queue table, backup, derivative, and attachment metadata remain compatible with 1.0.1.
- The new queue resume fields use prefixed WordPress options only; no database schema migration is required.
- Requires PHP remains 8.1.

## QA Focus

- Activation and upgrade from 1.0.1 with preserved settings.
- Attachment-metadata upload automation, Safe Mode queuing, and automatic Media Library statuses.
- JPEG, PNG, WebP, SVG, animated GIF, backup, restore, queue, CSV export, and status diagnostics.
