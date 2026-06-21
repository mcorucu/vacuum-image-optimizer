# Vacuum Image Optimizer — Technical Specification

> Document Version: 1.0.0  
> Date: 2026-06-18  
> Status: Architecture Phase — No code yet

---

## 1. Project Identity

| Property | Value |
|----------|-------|
| Plugin Name | Vacuum Image Optimizer |
| Plugin Slug | `vacuum-image-optimizer` |
| PHP Namespace | `VacuumImageOptimizer\` |
| Function Prefix | `vio_` |
| Text Domain | `vacuum-image-optimizer` |
| Minimum WordPress | 6.2 |
| Minimum PHP | 8.1 |
| License | GPLv2 or later |

---

## 2. Functional Requirements

### 2.1 Dashboard
- Display aggregate statistics: total images, optimized count, pending count, space saved, compression ratio, WebP count, AVIF count.
- Show recent optimization activity log (last 50 entries).
- Render system health indicators (PHP version, memory limit, GD/Imagick availability).
- All dashboard data served via AJAX to avoid full page reloads.

### 2.2 Automatic Upload Optimization
- Hook into `wp_handle_upload` and `add_attachment` to intercept new uploads.
- Compress the uploaded image and all auto-generated thumbnails.
- Generate WebP variants for all applicable sizes.
- Generate AVIF variants if the server supports `imageavif()` or Imagick AVIF.
- Preserve the original file in a backup location.
- Store optimization metadata in `wp_postmeta` and custom tables.

### 2.3 Bulk Optimization
- Scan the entire Media Library for unoptimized images.
- Process images in configurable batch sizes (default: 5 per request).
- Support pause, resume, and cancel operations via background state.
- Real-time progress reporting via AJAX long-polling or Server-Sent Events (SSE).
- Comprehensive error logging per image with retry logic.

### 2.4 WebP Conversion
- Supported source formats: JPEG, PNG.
- Generate WebP for full-size image and all registered thumbnail sizes.
- Maintain `srcset` compatibility by appending WebP sources to responsive image markup.
- Fallback to original format when WebP is not supported (handled via `<picture>` element or content filtering).

### 2.5 AVIF Conversion
- Supported source formats: JPEG, PNG.
- Generate AVIF only when server capability is confirmed.
- Fallback chain: AVIF → WebP → original format.
- Browser compatibility detection via `HTTP_ACCEPT` header parsing.

### 2.6 Compression Profiles
| Profile | Quality (JPEG) | Quality (WebP) | Quality (AVIF) | Resize |
|---------|---------------|----------------|----------------|--------|
| Lossless | 100 | 100 | 100 | No |
| Balanced | 82 | 80 | 75 | No |
| Aggressive | 60 | 65 | 60 | No |
| Custom | User-defined | User-defined | User-defined | User-defined |

### 2.7 Resize Engine
- Configurable maximum width and maximum height.
- Maintain aspect ratio (never distort).
- Optional Retina (@2x) generation for supported sizes.
- Skip resize if image is already within bounds.

### 2.8 Lazy Loading
- Inject `loading="lazy"` on frontend images.
- Support exclusion lists: specific image URLs, CSS classes, post types, above-the-fold selectors.
- Extend lazy loading to `<iframe>` elements.
- Respect native WordPress `wp_lazy_loading_enabled` filter.

### 2.9 Media Library Integration
- Add custom columns: optimization status, original size, optimized size, savings percentage, WebP availability.
- Inline row actions: Optimize Now, Restore Original, Regenerate Variants.
- Bulk actions dropdown: Optimize Selected, Restore Selected, Delete Backups.

### 2.10 Backup & Restore
- Backup originals before any modification.
- Store backups in `wp-content/uploads/vio-backups/{year}/{month}/`.
- Individual restore: replace optimized file with original, regenerate metadata.
- Bulk restore: queue all optimized images for restoration.
- Automatic backup cleanup: delete backups older than configured retention period (default: 30 days).

### 2.11 Exclusion Rules
- Exclude by folder path pattern (regex or glob).
- Exclude by filename pattern.
- Exclude specific registered image sizes.
- Exclude by post type (e.g., do not optimize images attached to `product` if configured).
- Never optimize animated GIFs (detect frame count).
- Never optimize SVG files.

### 2.12 System Status
- PHP version and SAPI.
- WordPress version.
- Memory limit (`WP_MEMORY_LIMIT`).
- Upload max filesize.
- GD extension: loaded version and WebP/AVIF support flags.
- Imagick extension: loaded version and format support list.
- Server write permissions test on upload directory.

---

## 3. Security Model

### 3.1 Capability Checks
- Every admin AJAX action verifies `current_user_can( 'manage_options' )` or `current_user_can( 'upload_files' )` depending on context.
- Media library inline actions check `current_user_can( 'edit_post', $attachment_id )`.

### 3.2 Nonces
- All AJAX endpoints require a valid `vio_nonce` created with `wp_create_nonce( 'vio_admin_nonce' )`.
- All form submissions include `vio_nonce` and verify with `wp_verify_nonce()`.

### 3.3 Escaping & Sanitization
- All settings stored via `sanitize_option` callbacks.
- Output in templates uses `esc_html()`, `esc_attr()`, `esc_url()`, and `wp_kses_post()` as appropriate.
- File paths sanitized with `sanitize_file_name()` and validated against `wp_upload_dir()` base path.

### 3.4 Secure File Handling
- Use `WP_Filesystem` for all file read/write operations; fall back to native PHP only after `WP_Filesystem` initialization.
- Validate MIME types via `wp_check_filetype()` before processing.
- Restrict backup directory with `.htaccess` deny rules and `index.php` silencer.

### 3.5 Database Security
- Use `$wpdb->prepare()` for all dynamic SQL queries.
- Never execute direct SQL when equivalent WP API functions exist (e.g., `get_posts()`, `wp_insert_post()`, `update_post_meta()`).

---

## 4. Performance Considerations

- Optimization runs in background via AJAX to prevent HTTP timeouts.
- Batch size defaults to 5 images per request; adjustable based on server memory.
- Use transients to cache dashboard statistics (TTL: 5 minutes).
- Offload heavy operations to WP-Cron for truly headless processing.
- Implement file locking (`flock`) during concurrent optimization of the same attachment.

---

## 5. Compatibility

- WordPress Multisite: defer network-level activation to a post-MVP phase.
- WooCommerce: respect product gallery hooks; exclude product images if configured.
- Page builders (Elementor, Beaver Builder): ensure content filter runs after builder render.
- CDNs: provide filter hooks for cache purging after image regeneration.
- Object cache (Redis/Memcached): invalidate relevant transients on optimization events.

---

## 6. Error Handling Strategy

| Error Code | Description | User Action |
|------------|-------------|-------------|
| VIO-E001 | File not readable | Check permissions |
| VIO-E002 | Image format unsupported | Verify source format |
| VIO-E003 | Memory limit exceeded | Reduce batch size |
| VIO-E004 | WebP generation failed | Check GD/Imagick WebP support |
| VIO-E005 | AVIF generation failed | Check AVIF server support |
| VIO-E006 | Backup write failed | Check disk space |
| VIO-E007 | Restore failed (original missing) | Backup expired or deleted |

---

## 7. WordPress API Usage Patterns

- **Settings:** `register_setting()`, `add_settings_section()`, `add_settings_field()` for the settings page.
- **Admin Menu:** `add_submenu_page( 'upload.php', ... )` with `manage_options`. No top-level menu.
- **Media Library:** `manage_media_columns`, `manage_media_custom_column`, `media_row_actions`.
- **Upload Hooks:** `wp_handle_upload_prefilter`, `add_attachment`, `intermediate_image_sizes_advanced`.
- **Frontend:** `wp_calculate_image_srcset`, `wp_get_attachment_image_src`, `the_content` filter.
- **Cron:** `wp_schedule_event()` for scheduled cleanup tasks.
- **Capabilities:** Custom capability `vio_manage_optimizer` mapped to `manage_options`.

---

## 8. Design System Compliance

All admin UI must follow the **ChatBubble Design System**:

| Token | Value |
|-------|-------|
| Primary | `#22C55E` |
| Secondary | `#3B82F6` |
| Tertiary | `#A855F7` |
| Background | `#FFFFFF` |
| Surface | `#F3F4F6` |
| Success | `#22C55E` |
| Warning | `#F59E0B` |
| Error | `#EF4444` |
| Info | `#3B82F6` |
| Border Radius | `20px` for cards, `8px` for buttons |
| Touch Target | Minimum `44px` |
| Shadows | None or very subtle (`0 1px 3px rgba(0,0,0,0.05)` max) |
