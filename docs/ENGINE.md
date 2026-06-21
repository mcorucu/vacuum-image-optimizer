# Vacuum Image Optimizer — Optimization Engine Architecture

> Document Version: 1.0.0  
> Date: 2026-06-18  
> Status: Architecture Phase — No code yet

---

## 1. Engine Overview

The optimization engine is the heart of the plugin. It transforms uploaded and existing media into smaller, modern-format variants while preserving visual quality and ensuring safe rollback.

### 1.1 Design Goals
- **Deterministic:** Same input + same profile = same output.
- **Recoverable:** Every destructive operation is preceded by a verified backup.
- **Pluggable:** Compression backends (GD, Imagick) are swappable via adapter pattern.
- **Observable:** Every step emits WordPress actions for logging, analytics, and third-party integrations.

---

## 2. Pipeline Flow

```
Upload / Bulk Request
        │
        ▼
┌─────────────────┐
│ Exclusion Check │ ── Skip? → Log & Exit
└─────────────────┘
        │
        ▼
┌─────────────────┐
│  Backup Create  │ ── Fail? → Abort & Error
└─────────────────┘
        │
        ▼
┌─────────────────┐
│  Resize (opt)   │ ── Skip if within bounds
└─────────────────┘
        │
        ▼
┌─────────────────┐
│   Compress      │ ── Lossy/Lossless per profile
└─────────────────┘
        │
        ▼
┌─────────────────┐
│  WebP Generate  │ ── Skip if unsupported
└─────────────────┘
        │
        ▼
┌─────────────────┐
│  AVIF Generate  │ ── Skip if unsupported
└─────────────────┘
        │
        ▼
┌─────────────────┐
│  Update Meta    │ ── Stats, flags, timestamps
└─────────────────┘
        │
        ▼
   Success / Log
```

---

## 3. Upload Interception

### 3.1 Hook: `wp_handle_upload`
- Validate MIME type and file integrity before WordPress moves the file.
- Reject unsupported formats early (only JPEG and PNG proceed to optimization).
- Return modified file array if resize-on-upload is enabled.

### 3.2 Hook: `add_attachment`
- Fire after WordPress generates intermediate sizes.
- Iterate over all size variants (including `full`).
- Run the full pipeline per size.

### 3.3 Hook: `intermediate_image_sizes_advanced`
- Remove excluded sizes from the generation list to avoid wasted work.
- Filter applied per attachment based on `ExclusionManager` rules.

---

## 4. Compression Profiles

### 4.1 Profile Definitions

| Profile | JPEG Quality | PNG Compression | WebP Quality | AVIF Quality | Resize | Description |
|---------|-------------|-----------------|--------------|--------------|--------|-------------|
| `lossless` | 100 | 0 (none) | 100 | 100 | No | Archival quality |
| `balanced` | 82 | 5 | 80 | 75 | No | Best speed/size ratio |
| `aggressive` | 60 | 7 | 65 | 60 | No | Maximum size reduction |
| `custom` | User | User | User | User | User | Fine-grained control |

### 4.2 ProfileManager Responsibilities
- Store default profiles in code (not database) to prevent corruption.
- Store only the user-defined `custom` profile in `vio_settings`.
- Expose filter: `vio_compression_profile`.

### 4.3 PNG Handling
- PNGs use lossless compression levels (0–9) via zlib.
- No quality slider for PNG; only compression level and optional color quantization.

---

## 5. Resize Engine

### 5.1 Resize Rules
- Configurable `max_width` and `max_height` (default: 2560×2560).
- Maintain aspect ratio via `imagesx() / imagesy()` ratio check.
- Never upscale: if original is smaller than bounds, skip resize.
- Apply resize **before** compression to improve quality at lower file sizes.

### 5.2 Retina Support
- If Retina is enabled and the image is large enough, generate a `@2x` variant.
- `@2x` variant uses the same compression profile but is stored separately.
- Retina variants are registered as custom image sizes (`vio_retina_{size}`).

### 5.3 Image Size Registration
- The plugin does not register new default sizes; it hooks into existing sizes.
- Custom sizes added by themes/plugins are respected unless excluded.

---

## 6. WebP Generation

### 6.1 Generation Rules
- Generate WebP for every size variant that has a JPEG or PNG source.
- WebP filename: `{original_name}-{size}.webp`.
- Store in the same upload subfolder as the original.

### 6.2 Backend Support

| Backend | Function / Method | Minimum Version |
|---------|-------------------|-----------------|
| GD | `imagewebp()` | PHP 5.4+ with WebP support |
| Imagick | `$imagick->setImageFormat('webp')` | Imagick 3.0+ with libwebp |

### 6.3 Quality Mapping
- WebP quality is mapped directly from the active profile.
- GD `imagewebp()` quality: 0–100.
- Imagick WebP quality: 0–100.

### 6.4 Fallback Strategy
- If WebP generation fails for one size, log the error and continue with other sizes.
- Do not abort the entire attachment optimization for a single size failure.

---

## 7. AVIF Generation

### 7.1 Generation Rules
- Generate AVIF only if `AVIFGenerator::is_supported()` returns true.
- AVIF filename: `{original_name}-{size}.avif`.
- Store in the same upload subfolder.

### 7.2 Backend Support

| Backend | Function / Method | Minimum Version |
|---------|-------------------|-----------------|
| GD | `imageavif()` | PHP 8.1+ with AVIF support |
| Imagick | `$imagick->setImageFormat('avif')` | Imagick 3.0+ with libavif |

### 7.3 Quality & Speed
- AVIF quality: mapped from profile (default 75 for balanced).
- AVIF speed: fixed at 6 (balanced encode speed vs. size).
- Expose filter: `vio_avif_speed`.

### 7.4 Fallback Strategy
- AVIF is the highest-priority format in the `<picture>` element.
- If AVIF is missing, the browser falls back to WebP, then original.

---

## 8. Responsive Image & srcset Compatibility

### 8.1 Filter: `wp_calculate_image_srcset`
- Inspect the `$sources` array.
- For each source URL, check if a matching WebP or AVIF file exists.
- Append WebP/AVIF sources to the srcset array with appropriate descriptors.

### 8.2 Filter: `wp_get_attachment_image`
- Wrap the output `<img>` in a `<picture>` element when modern formats are available.
- Insert `<source srcset="..." type="image/avif">` first, then `<source srcset="..." type="image/webp">`, then the original `<img>`.

### 8.3 Content Filtering
- On `the_content`, parse `<img>` tags and apply the same `<picture>` wrapping.
- Skip images already inside `<picture>` elements.
- Skip images with `data-vio-skip` attribute.

---

## 9. Bulk Optimization Flow

### 9.1 Scan Phase
1. Query all attachments with `post_type = 'attachment'` and `post_mime_type LIKE 'image/%'`.
2. Exclude attachments already optimized (check `_vio_optimized` meta).
3. Exclude attachments matching exclusion rules.
4. Populate `vio_queue` table with `operation = 'optimize'`.

### 9.2 Processing Phase
1. AJAX request fetches the next batch from `vio_queue` where `status = 'pending'`.
2. For each queue item, call `Optimizer::run( $attachment_id )`.
3. Update queue status to `processing`, then `completed` or `failed`.
4. Return progress JSON: `processed`, `total`, `percentage`, `current_file`.

### 9.3 Pause & Resume
- Pause: set a transient `vio_bulk_paused` to true.
- Resume: clear the transient; next AJAX request continues from pending items.
- Cancel: delete all pending queue items for the current `bulk_job_id`.

### 9.4 Batch Size
- Default: 5 images per AJAX request.
- Adjustable in Settings → Performance.
- Calculated based on `memory_limit` and average image size.

---

## 10. Error Recovery & Rollback

### 10.1 Single Image Failure
- If any step fails (compress, WebP, AVIF), restore the original from backup.
- Update queue status to `failed` with error code.
- Log the failure via `Logger`.

### 10.2 Batch Failure
- If the AJAX request itself fails (timeout, memory), the queue items remain in `processing` status.
- A cron-based health check resets `processing` items older than 10 minutes back to `pending`.
- Retry limit: 3 attempts per queue item.

### 10.3 Backup Integrity
- After backup creation, compute SHA-256 hash and store in `vio_backups`.
- Before restore, verify the hash matches.
- If hash mismatch, mark backup as corrupted and abort restore.

---

## 11. Action & Filter Hooks

### Actions
- `vio_before_optimize` — Fires before any optimization step.
- `vio_after_optimize` — Fires after successful optimization.
- `vio_optimize_failed` — Fires on any failure.
- `vio_backup_created` — Fires after backup verification.
- `vio_restore_completed` — Fires after successful restore.

### Filters
- `vio_compression_profile` — Modify profile settings at runtime.
- `vio_max_width` — Override resize max width.
- `vio_max_height` — Override resize max height.
- `vio_webp_quality` — Override WebP quality per image.
- `vio_avif_quality` — Override AVIF quality per image.
- `vio_picture_element_markup` — Modify the `<picture>` HTML output.
- `vio_should_optimize_attachment` — Return false to skip an attachment.

---

## 12. Performance Targets

| Metric | Target |
|--------|--------|
| Single image optimization | < 3 seconds for a 2MB JPEG |
| Bulk processing throughput | > 60 images/minute on standard shared hosting |
| Memory per image | < 64MB peak |
| WebP size vs original | 25–35% smaller |
| AVIF size vs original | 40–55% smaller |
| Dashboard stats load | < 200ms with AJAX |

---

## 13. Future Extensibility

- **CDN Integration:** Hook into `vio_after_optimize` to trigger cache purge.
- **External APIs:** Adapter interface for cloud compression services (premium).
- **Video Optimization:** Extend pipeline to MP4/WebM (future phase).
- **PDF Optimization:** Extend pipeline to PDF compression (future phase).
