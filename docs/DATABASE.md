# Vacuum Image Optimizer — Database Strategy

> Document Version: 1.0.0  
> Date: 2026-06-18  
> Status: Architecture Phase — No code yet

---

## 1. Philosophy

- Use **WordPress-native options** and **postmeta** for simple key/value and attachment-level data.
- Use **custom tables** only for high-volume relational or transactional data: optimization queue, detailed stats, and backup registry.
- Follow **dbDelta** conventions for table creation and upgrades.
- Never use direct SQL when WordPress APIs (`WP_Query`, `update_post_meta`, `get_option`) can satisfy the requirement.
- Always use `$wpdb->prepare()` for dynamic queries against custom tables.

---

## 2. Custom Tables

### 2.1 `{$wpdb->prefix}vio_queue`

Purpose: Track bulk optimization and restore jobs.

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `id` | `BIGINT UNSIGNED` | NO | `AUTO_INCREMENT` | Primary key |
| `attachment_id` | `BIGINT UNSIGNED` | NO | `0` | FK to `wp_posts` |
| `operation` | `VARCHAR(20)` | NO | `''` | `optimize`, `restore`, `regenerate` |
| `status` | `VARCHAR(20)` | NO | `pending` | `pending`, `processing`, `completed`, `failed`, `cancelled` |
| `profile` | `VARCHAR(20)` | NO | `balanced` | `lossless`, `balanced`, `aggressive`, `custom` |
| `attempts` | `TINYINT UNSIGNED` | NO | `0` | Retry counter |
| `error_code` | `VARCHAR(20)` | YES | `NULL` | Reference to error taxonomy |
| `error_message` | `TEXT` | YES | `NULL` | Human-readable error |
| `created_at` | `DATETIME` | NO | `CURRENT_TIMESTAMP` | Enqueue time |
| `started_at` | `DATETIME` | YES | `NULL` | Processing start |
| `completed_at` | `DATETIME` | YES | `NULL` | Completion time |

**Indexes:**
- `PRIMARY KEY (id)`
- `KEY attachment_id (attachment_id)`
- `KEY status_created (status, created_at)`
- `KEY operation_status (operation, status)`

---

### 2.2 `{$wpdb->prefix}vio_stats`

Purpose: Store per-attachment optimization history and metrics.

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `id` | `BIGINT UNSIGNED` | NO | `AUTO_INCREMENT` | Primary key |
| `attachment_id` | `BIGINT UNSIGNED` | NO | `0` | FK to `wp_posts` |
| `size_name` | `VARCHAR(50)` | NO | `full` | `full`, `thumbnail`, `medium`, etc. |
| `original_size` | `BIGINT UNSIGNED` | NO | `0` | Bytes |
| `optimized_size` | `BIGINT UNSIGNED` | NO | `0` | Bytes |
| `webp_size` | `BIGINT UNSIGNED` | YES | `NULL` | Bytes (NULL if not generated) |
| `avif_size` | `BIGINT UNSIGNED` | YES | `NULL` | Bytes (NULL if not generated) |
| `compression_ratio` | `DECIMAL(5,2)` | NO | `0.00` | Percentage saved |
| `profile` | `VARCHAR(20)` | NO | `balanced` | Profile used |
| `engine` | `VARCHAR(20)` | NO | `gd` | `gd`, `imagick` |
| `created_at` | `DATETIME` | NO | `CURRENT_TIMESTAMP` | |

**Indexes:**
- `PRIMARY KEY (id)`
- `UNIQUE KEY attachment_size (attachment_id, size_name)`
- `KEY attachment_id (attachment_id)`

---

### 2.3 `{$wpdb->prefix}vio_backups`

Purpose: Registry of backed-up original files for restore capability.

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| `id` | `BIGINT UNSIGNED` | NO | `AUTO_INCREMENT` | Primary key |
| `attachment_id` | `BIGINT UNSIGNED` | NO | `0` | FK to `wp_posts` |
| `size_name` | `VARCHAR(50)` | NO | `full` | `full`, `thumbnail`, etc. |
| `backup_path` | `TEXT` | NO | `''` | Absolute path to backup file |
| `original_path` | `TEXT` | NO | `''` | Absolute path to original (current) file |
| `file_hash` | `VARCHAR(64)` | NO | `''` | SHA-256 of backup file for integrity |
| `created_at` | `DATETIME` | NO | `CURRENT_TIMESTAMP` | Backup time |
| `expires_at` | `DATETIME` | YES | `NULL` | Cleanup threshold |

**Indexes:**
- `PRIMARY KEY (id)`
- `UNIQUE KEY attachment_size (attachment_id, size_name)`
- `KEY expires_at (expires_at)`
- `KEY attachment_id (attachment_id)`

---

## 3. WordPress Native Storage

### 3.1 Options (`wp_options`)

Stored via `get_option()` / `update_option()` with `vio_` prefix:

| Option Key | Type | Purpose |
|------------|------|---------|
| `vio_settings` | `ARRAY` (serialized) | All plugin settings: profiles, exclusions, lazy load config |
| `vio_version` | `STRING` | Plugin database version for migrations |
| `vio_stats_total` | `ARRAY` | Aggregated stats cache (total saved, total optimized) |
| `vio_bulk_job_id` | `STRING` | Active bulk job UUID for pause/resume tracking |
| `vio_cron_last_run` | `STRING` | Timestamp of last scheduled cleanup |

### 3.2 Post Meta (`wp_postmeta`)

Stored per attachment via `get_post_meta()` / `update_post_meta()`:

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_vio_optimized` | `INT (0/1)` | Whether this attachment has been optimized |
| `_vio_optimized_at` | `STRING` | ISO-8601 timestamp of last optimization |
| `_vio_profile` | `STRING` | Profile used for last optimization |
| `_vio_original_filesize` | `INT` | Original filesize in bytes (full size) |
| `_vio_has_webp` | `INT (0/1)` | Whether WebP variants exist |
| `_vio_has_avif` | `INT (0/1)` | Whether AVIF variants exist |
| `_vio_backup_path` | `STRING` | Absolute path to full-size backup (legacy, prefer custom table) |

---

## 4. Migration Strategy

### 4.1 Initial Install
- On `register_activation_hook`, run a schema install function.
- Use `dbDelta()` with `CREATE TABLE` statements for all three custom tables.
- Set `vio_version` to the current plugin version.

### 4.2 Schema Upgrades
- On `admin_init`, compare `vio_version` with the plugin's declared DB version.
- If mismatched, re-run `dbDelta()` with updated `CREATE TABLE` definitions.
- `dbDelta` handles `ALTER TABLE` additions automatically; never drop existing tables.
- Log migration steps to the error log for debugging.

### 4.3 Uninstall
- On uninstall (`uninstall.php`), provide option to preserve or delete data.
- If chosen, drop custom tables and delete all `vio_*` options and `_vio_*` postmeta.
- Leave original image files intact; only delete backups.

---

## 5. Data Retention & Cleanup

- Backup files older than the retention period are deleted by a WP-Cron job.
- The cleanup job runs daily and deletes both the physical file and the `vio_backups` row.
- Stats older than 2 years can be archived or truncated via a filter hook.
- Queue entries in `completed` or `cancelled` status older than 30 days are purged automatically.

---

## 6. Query Patterns

```sql
-- Fetch pending bulk jobs ordered by creation time
SELECT * FROM {$wpdb->prefix}vio_queue
WHERE status = 'pending'
ORDER BY created_at ASC
LIMIT %d;

-- Fetch stats for a specific attachment
SELECT * FROM {$wpdb->prefix}vio_stats
WHERE attachment_id = %d;

-- Fetch backup info for restore
SELECT * FROM {$wpdb->prefix}vio_backups
WHERE attachment_id = %d AND size_name = %s;

-- Cleanup expired backups
SELECT * FROM {$wpdb->prefix}vio_backups
WHERE expires_at < NOW();
```

All queries use `$wpdb->prepare()` with typed placeholders (`%d`, `%s`, `%f`).

---

## 7. ER Diagram (Textual)

```
wp_posts (ID)
  │
  ├── 1:N ── wp_postmeta (post_id) ── _vio_optimized, _vio_has_webp, etc.
  │
  ├── 1:N ── vio_queue (attachment_id)
  │
  ├── 1:N ── vio_stats (attachment_id)
  │
  └── 1:N ── vio_backups (attachment_id)
```

No foreign key constraints are enforced at the database level (MyISAM compatibility); referential integrity is maintained in application code.
