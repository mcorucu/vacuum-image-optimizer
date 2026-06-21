# Vacuum Image Optimizer — Class Architecture

> Document Version: 1.0.0  
> Date: 2026-06-18  
> Status: Architecture Phase — No code yet

---

## 1. Namespace & Autoloading

| Property | Value |
|----------|-------|
| Root Namespace | `VacuumImageOptimizer\` |
| PSR-4 Path | `src/` |
| Composer Autoload | `"VacuumImageOptimizer\\": "src/"` |

All classes reside under `src/` and are namespaced according to their directory path.

---

## 2. Directory Layout (src/)

```
src/
├── Core/
│   ├── Plugin.php
│   ├── Container.php
│   ├── Installer.php
│   ├── Uninstaller.php
│   ├── Hooks.php
│   └── Assets.php
├── Admin/
│   ├── Menu.php
│   ├── Dashboard.php
│   ├── Settings.php
│   ├── MediaLibrary.php
│   ├── AjaxHandler.php
│   └── Notices.php
├── Engine/
│   ├── Optimizer.php
│   ├── Compressor.php
│   ├── WebPGenerator.php
│   ├── AVIFGenerator.php
│   ├── Resizer.php
│   ├── ProfileManager.php
│   └── QueueManager.php
├── Media/
│   ├── AttachmentHandler.php
│   ├── SrcsetFilter.php
│   ├── LazyLoader.php
│   └── ExclusionManager.php
├── Backup/
│   ├── BackupManager.php
│   ├── RestoreManager.php
│   └── CleanupTask.php
├── Frontend/
│   ├── PictureElement.php
│   ├── ScriptInjector.php
│   └── CompatibilityLayer.php
└── Utils/
    ├── SystemCheck.php
    ├── Logger.php
    ├── TransientCache.php
    └── FormatHelper.php
```

---

## 3. Core Classes

### 3.1 `VacuumImageOptimizer\Core\Plugin`
- **Responsibility:** Plugin bootstrap. Single entry point after `plugins_loaded`.
- **Pattern:** Singleton (only to prevent double-init; no global state).
- **Methods:**
  - `init()` — Wire all subsystems.
  - `register_services( Container $container )` — Bind class instances.
  - `activate()` — Delegate to `Installer`.
  - `deactivate()` — Flush rewrite rules, clear scheduled events.

### 3.2 `VacuumImageOptimizer\Core\Container`
- **Responsibility:** Lightweight dependency injection container.
- **Pattern:** Service container with lazy resolution.
- **Methods:**
  - `get( string $id )`
  - `set( string $id, callable $factory )`
  - `has( string $id )`

### 3.3 `VacuumImageOptimizer\Core\Installer`
- **Responsibility:** Activation hook logic.
- **Methods:**
  - `install()` — Create custom tables, set default options, schedule cron.
  - `check_requirements()` — PHP version, WordPress version, extension checks.

### 3.4 `VacuumImageOptimizer\Core\Hooks`
- **Responsibility:** Central registration of all WordPress hooks (actions & filters).
- **Methods:**
  - `register()` — Bind all hooks via `add_action()` / `add_filter()`.
- **Rationale:** Keeps hook registration explicit and testable; avoids scattered `add_action()` calls.

### 3.5 `VacuumImageOptimizer\Core\Assets`
- **Responsibility:** Enqueue CSS and JS for admin and frontend.
- **Methods:**
  - `enqueue_admin_assets( string $hook )`
  - `enqueue_frontend_assets()`
  - `register_block_assets()` (future)

---

## 4. Admin Classes

### 4.1 `VacuumImageOptimizer\Admin\Menu`
- **Responsibility:** Register the plugin as a single submenu under **Media**.
- **Capabilities:** `manage_options`.
- **Registration Pattern:**
  ```php
  add_submenu_page(
      'upload.php',
      __( 'Vacuum Image Optimizer', 'vacuum-image-optimizer' ),
      __( 'Vacuum Optimizer', 'vacuum-image-optimizer' ),
      'manage_options',
      'vacuum-image-optimizer',
      [ $this, 'render_page' ]
  );
  ```
- **Internal Tabs:** Dashboard, Bulk Optimize, WebP & AVIF, Compression, Lazy Load, Backup & Restore, Exclusions, Reports, System Status.
- No top-level menu is registered. No separate admin page slugs for tabs.

### 4.2 `VacuumImageOptimizer\Admin\Dashboard`
- **Responsibility:** Render the main dashboard page and widget data.
- **Dependencies:** `SystemCheck`, `TransientCache`, `StatsRepository`.

### 4.3 `VacuumImageOptimizer\Admin\Settings`
- **Responsibility:** Register settings sections, fields, and sanitize callbacks.
- **Settings Groups:**
  - `vio_compression_settings`
  - `vio_resize_settings`
  - `vio_lazyload_settings`
  - `vio_exclusion_settings`
  - `vio_backup_settings`

### 4.4 `VacuumImageOptimizer\Admin\MediaLibrary`
- **Responsibility:** Add custom columns, inline actions, and bulk actions.
- **Hooks:**
  - `manage_media_columns`
  - `manage_media_custom_column`
  - `media_row_actions`
  - `bulk_actions-upload`

### 4.5 `VacuumImageOptimizer\Admin\AjaxHandler`
- **Responsibility:** Handle all `wp_ajax_vio_*` endpoints.
- **Nonce Verification:** All methods call `check_ajax_referer( 'vio_admin_nonce', 'nonce' )`.
- **Endpoints:**
  - `vio_dashboard_stats`
  - `vio_bulk_scan`
  - `vio_bulk_process`
  - `vio_bulk_pause`
  - `vio_optimize_single`
  - `vio_restore_single`
  - `vio_regenerate_single`
  - `vio_system_status`

### 4.6 `VacuumImageOptimizer\Admin\Notices`
- **Responsibility:** Display dismissible admin notices (success, warning, error, info).
- **Storage:** Dismissal state stored in user meta.

---

## 5. Engine Classes

### 5.1 `VacuumImageOptimizer\Engine\Optimizer`
- **Responsibility:** Orchestrate the full optimization pipeline for a single attachment.
- **Pipeline:**
  1. Validate attachment.
  2. Create backup via `BackupManager`.
  3. Compress each size via `Compressor`.
  4. Generate WebP via `WebPGenerator`.
  5. Generate AVIF via `AVIFGenerator` (if supported).
  6. Update metadata and stats.
- **Return:** `OptimizationResult` DTO.

### 5.2 `VacuumImageOptimizer\Engine\Compressor`
- **Responsibility:** Lossy/lossless compression using GD or Imagick.
- **Dependencies:** `ProfileManager` (for quality settings).
- **Methods:**
  - `compress( string $file_path, string $profile ): array`
  - `get_supported_engines(): array`

### 5.3 `VacuumImageOptimizer\Engine\WebPGenerator`
- **Responsibility:** Generate WebP variants for all image sizes.
- **Methods:**
  - `generate( string $source_path, string $size_name ): string|false`
  - `is_supported(): bool`

### 5.4 `VacuumImageOptimizer\Engine\AVIFGenerator`
- **Responsibility:** Generate AVIF variants for all image sizes.
- **Methods:**
  - `generate( string $source_path, string $size_name ): string|false`
  - `is_supported(): bool`

### 5.5 `VacuumImageOptimizer\Engine\Resizer`
- **Responsibility:** Resize images to configured max dimensions before compression.
- **Methods:**
  - `resize( string $file_path, int $max_width, int $max_height ): bool`
  - `supports_retina(): bool`

### 5.6 `VacuumImageOptimizer\Engine\ProfileManager`
- **Responsibility:** Manage compression profiles and quality mappings.
- **Profiles:** `lossless`, `balanced`, `aggressive`, `custom`.
- **Methods:**
  - `get_profile( string $name ): array`
  - `get_active_profile(): string`
  - `update_custom_profile( array $settings ): void`

### 5.7 `VacuumImageOptimizer\Engine\QueueManager`
- **Responsibility:** CRUD for the optimization queue (`vio_queue` table).
- **Methods:**
  - `enqueue( int $attachment_id, string $operation, string $profile ): int`
  - `fetch_batch( int $limit ): array`
  - `update_status( int $queue_id, string $status, string $error = '' ): void`
  - `clear_completed(): void`

---

## 6. Media Classes

### 6.1 `VacuumImageOptimizer\Media\AttachmentHandler`
- **Responsibility:** Hook into upload and attachment lifecycle.
- **Hooks:**
  - `wp_handle_upload` — validate pre-upload.
  - `add_attachment` — trigger automatic optimization.
  - `intermediate_image_sizes_advanced` — respect size exclusions.

### 6.2 `VacuumImageOptimizer\Media\SrcsetFilter`
- **Responsibility:** Inject WebP/AVIF URLs into `srcset` and `sizes` attributes.
- **Hooks:**
  - `wp_calculate_image_srcset`
  - `wp_get_attachment_image_src`

### 6.3 `VacuumImageOptimizer\Media\LazyLoader`
- **Responsibility:** Inject `loading="lazy"` and handle exclusions.
- **Hooks:**
  - `wp_lazy_loading_enabled`
  - `wp_get_attachment_image_attributes`
  - `the_content` (for inline images and iframes)

### 6.4 `VacuumImageOptimizer\Media\ExclusionManager`
- **Responsibility:** Determine if an image or attachment should be skipped.
- **Checks:**
  - Folder path pattern.
  - Filename pattern.
  - Image size name.
  - Post type.
  - Animated GIF detection.
  - SVG detection.

---

## 7. Backup Classes

### 7.1 `VacuumImageOptimizer\Backup\BackupManager`
- **Responsibility:** Create and validate backups before optimization.
- **Methods:**
  - `backup( int $attachment_id, string $size_name ): bool`
  - `verify_integrity( string $backup_path ): bool`

### 7.2 `VacuumImageOptimizer\Backup\RestoreManager`
- **Responsibility:** Restore original files from backups.
- **Methods:**
  - `restore( int $attachment_id, string $size_name ): bool`
  - `restore_all( int $attachment_id ): bool`

### 7.3 `VacuumImageOptimizer\Backup\CleanupTask`
- **Responsibility:** WP-Cron callback for backup retention cleanup.
- **Schedule:** Daily via `wp_schedule_event()`.

---

## 8. Frontend Classes

### 8.1 `VacuumImageOptimizer\Frontend\PictureElement`
- **Responsibility:** Wrap images in `<picture>` elements with WebP/AVIF `<source>` tags.
- **Hooks:**
  - `the_content`
  - `post_thumbnail_html`
  - `widget_text_content`

### 8.2 `VacuumImageOptimizer\Frontend\ScriptInjector`
- **Responsibility:** Inject minimal frontend JS for lazy loading fallback or AVIF detection.
- **Future:** May be empty if native lazy loading is sufficient.

### 8.3 `VacuumImageOptimizer\Frontend\CompatibilityLayer`
- **Responsibility:** Integration with third-party plugins and themes.
- **Integrations:** WooCommerce, Elementor, Beaver Builder, CDNs.

---

## 9. Utility Classes

### 9.1 `VacuumImageOptimizer\Utils\SystemCheck`
- **Responsibility:** Report server capabilities and requirements.
- **Methods:**
  - `get_php_info(): array`
  - `get_image_engine_info(): array`
  - `is_writable_upload_dir(): bool`

### 9.2 `VacuumImageOptimizer\Utils\Logger`
- **Responsibility:** Structured logging for optimization events and errors.
- **Output:** WordPress debug.log when `WP_DEBUG` is enabled; otherwise silent.
- **Methods:**
  - `log( string $level, string $message, array $context = [] )`

### 9.3 `VacuumImageOptimizer\Utils\TransientCache`
- **Responsibility:** Wrapper around `get_transient()` / `set_transient()` with namespacing.
- **Methods:**
  - `get( string $key )`
  - `set( string $key, $value, int $ttl = 300 )`
  - `delete( string $key )`

### 9.4 `VacuumImageOptimizer\Utils\FormatHelper`
- **Responsibility:** Filesize formatting, ratio calculations, and safe array access.
- **Methods:**
  - `format_bytes( int $bytes ): string`
  - `calculate_ratio( int $original, int $optimized ): float`

---

## 10. Data Transfer Objects (DTOs)

### 10.1 `VacuumImageOptimizer\DTO\OptimizationResult`
```php
class OptimizationResult {
    public bool $success;
    public int $attachment_id;
    public string $size_name;
    public int $original_size;
    public int $optimized_size;
    public ?int $webp_size;
    public ?int $avif_size;
    public string $profile;
    public string $engine;
    public ?string $error_code;
    public ?string $error_message;
}
```

---

## 11. Hook Registration Strategy

All hooks are registered in `Core\Hooks::register()`. Example mapping:

```php
add_action( 'plugins_loaded', [ $plugin, 'init' ] );
add_action( 'admin_menu', [ $menu, 'register_media_submenu' ] );
add_action( 'wp_ajax_vio_bulk_process', [ $ajax, 'handle_bulk_process' ] );
add_filter( 'wp_handle_upload', [ $attachment_handler, 'intercept_upload' ] );
add_filter( 'wp_calculate_image_srcset', [ $srcset_filter, 'inject_webp_srcset' ], 10, 5 );
add_filter( 'wp_lazy_loading_enabled', [ $lazy_loader, 'maybe_disable' ], 10, 3 );
```

---

## 12. Design Principles

1. **Single Responsibility:** Each class handles one domain concern.
2. **Dependency Injection:** Core classes receive dependencies via the `Container`.
3. **No Global State:** Avoid `global` variables; use the container for shared services.
4. **Testability:** All engine classes accept interfaces for GD/Imagick adapters (future).
5. **Fail-Safe:** Any optimization step failure rolls back to the backup state.
6. **Filterable:** All quality settings, exclusions, and output markup expose WordPress filter hooks.
