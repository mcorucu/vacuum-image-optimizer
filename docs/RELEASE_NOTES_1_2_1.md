# Vacuum Image Optimizer 1.2.1

## WordPress 7.1 compatibility and media pipeline hardening

Vacuum Image Optimizer 1.2.1 keeps the 1.2.0 feature set and hardens automatic media processing for WordPress 7.1.

### Changes

- Handles both `create` and finalized `update` passes of `wp_generate_attachment_metadata`.
- Defers automatic work until client-side-generated registered sizes have been persisted.
- Reconciles finalized source, thumbnail, built-in, custom, and registered-size files without re-optimizing an already completed source.
- Prevents duplicate automatic queue jobs, backups, modern derivatives, and premature delivery-ready state.
- Validates derivative ownership within the uploads directory, physical MIME, dimensions, and existing files before reuse.
- Serializes concurrent responsive derivative generation for the same output path.
- Preserves server-side fallback processing and existing settings, metadata, backups, originals, and minimum PHP requirement.

### Compatibility audit

The plugin does not depend on `wp_image_editors`, `image_memory_limit`, or `image_make_intermediate_size` for upload correctness. It does not inject code into the block editor iframe, use `@wordpress/components`, depend on jQuery UI, or integrate with the persistent toolbar, SVG Icon API, or Abilities API.

WordPress 7.1 may process supported media in the browser before upload. Vacuum continues to process locally on the server, does not send images to third-party services, and supports the server-side fallback path.

### Qualification status

This release note records the implementation scope. Public release remains gated on isolated WordPress 7.1 Release Candidate client-side and server-side hands-on qualification, Plugin Check review, and final package validation.
