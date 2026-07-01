# Vacuum Image Optimizer Roadmap

**Current public version:** 1.0.1
**Status:** Released on WordPress.org
**Official documentation:** <https://docs.mcorucu.com/vacuum-image-optimizer/>

This roadmap is intentionally conservative. Vacuum Image Optimizer handles user media, so future work should preserve backup-first behavior, clear compatibility checks, and WordPress.org review expectations.

## Current Focus

- Keep the official documentation complete and accurate.
- Gather real-world compatibility feedback from WordPress.org users.
- Harden edge cases around server image engines, file permissions, and large libraries.
- Improve support workflows without adding external service dependencies.

## Near-Term Priorities

### Documentation and Support

- Keep docs.mcorucu.com aligned with the released plugin.
- Add more troubleshooting examples based on real support issues.
- Keep README, WordPress.org readme, and project page links consistent.
- Improve screenshots when UI changes materially.

### Compatibility Hardening

- Test more GD and Imagick combinations across shared hosting environments.
- Improve messaging for partial WebP or AVIF support.
- Continue validating SVG, GIF, unreadable file, and exclusion handling.
- Review upload automation behavior on large media uploads.

### Queue and Reporting

- Keep batch defaults conservative.
- Improve failed/skipped explanations where they are unclear.
- Review report performance on larger Media Libraries.
- Consider additional CSV fields if they help support diagnostics.

## Future Ideas

These are not commitments. They should be evaluated against safety, maintainability, and WordPress.org expectations before implementation.

- More granular per-role access control for optimization actions.
- Optional WP-CLI commands for scanning and processing queues.
- Additional report filters for date range, MIME type, and status.
- More compatibility notes for WooCommerce and common gallery workflows.
- Better visual comparison tools for before-and-after review.

## Non-Goals

- Sending images to a third-party optimization API.
- Rewriting stored post content URLs permanently.
- Processing SVG as raster image content.
- Processing animated GIF files in a way that risks losing animation.
- Adding aggressive defaults that can surprise site owners.

## Release Discipline

Before each release:

1. Run local WordPress admin QA.
2. Verify PHP syntax.
3. Verify ZIP structure and forbidden file exclusions.
4. Validate WordPress.org readme metadata.
5. Confirm docs and README links.
6. Keep version numbers consistent across plugin header, constants, readme, docs, GitHub release, and WordPress.org SVN tag.
