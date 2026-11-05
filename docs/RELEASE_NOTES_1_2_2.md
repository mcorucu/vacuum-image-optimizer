# Vacuum Image Optimizer 1.2.2

## Reliability and security update

Version 1.2.2 focuses on validated image delivery, backup integrity, and attachment-level safety.

### Changes

- Rejects registered derivatives when their file size no longer matches the recorded size and regenerates stale tracked files through a temporary file and atomic rename.
- Parses `Accept` image tokens case-insensitively, honors quality values, and falls back to the source when no acceptable, valid derivative exists.
- Verifies the backup file before reuse, keeps existing backup files when a source is replaced, and writes a new backup for the new source.
- Restores only from files inside the plugin backup directory and the site's uploads tree; publishes restore files atomically.
- Removes the plugin's registered responsive derivatives when restoring or deleting a source attachment, while leaving backup files intact.
- Restricts Media Library actions to users who can edit the specific attachment, validates the physical image MIME, neutralizes formula-leading CSV text, and serializes duplicate queue insertion.
- Recompresses WebP source files only after backup verification and uses atomic replacement when a smaller result is available.

### Image behavior

JPEG and PNG originals remain unchanged while WebP/AVIF derivatives are generated. Uploaded WebP sources may be replaced only after the original bytes have been backed up and the recompressed result is smaller. Frontend output varies by `Accept` and includes `Vary: Accept`; page caches and CDNs should respect that header or vary their cache key by `Accept`.

### Compatibility

- Requires WordPress 6.2 or newer.
- Requires PHP 8.1 or newer.
- Tested up to WordPress 7.1.4 after stable qualification.
- WordPress 7.2 Beta 4 remains a separate forward-compatibility preview and is not listed as a stable compatibility target.
