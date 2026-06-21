# Vacuum Image Optimizer — Troubleshooting

Quick fixes for the most common issues. Most answers start on the **System Status** tab
(**Media → Vacuum Image Optimizer → System Status**), which shows what your server supports.

---

## WebP is not generating

**Symptoms:** images stay "Not optimized", or you see a WebP error.

Check:
1. **System Status → WebP Available** should be *Available*. If not, ask your host to enable the **Imagick** extension (preferred) or **GD** with WebP support.
2. **System Status → Uploads Writable** should be *Available* — WebP files are written next to the originals.
3. Confirm the image is a **JPEG or PNG**. Other formats (GIF, SVG, etc.) are not converted.
4. Re-run from **Bulk Optimize → Scan Library → Start Queue**, or use the per-image **WebP** action.

## AVIF is not generating

**Symptoms:** WebP works but AVIF does not.

Check:
1. **System Status → AVIF Support** (and *AVIF via Imagick* / *AVIF via GD*). AVIF needs Imagick built with AVIF, or a GD build that supports `imageavif()`.
2. Make sure **Enable AVIF Generation** is turned on in the **Compression** tab.
3. AVIF is optional and parallel — if the server can't produce it, WebP and originals still work. Ask your host to enable AVIF support if you need it.

## Queue is not processing

**Symptoms:** the queue shows pending items but nothing completes.

Check:
1. Make sure you clicked **Start Queue** (or **Resume Queue** if paused).
2. The queue runs in your browser via admin AJAX — keep the **Bulk Optimize** tab open while it runs.
3. **System Status → Queue Table** should be *Available*. If not, deactivate and reactivate the plugin to recreate the table.
4. Check the **Failed Jobs** list and use **Retry**. A security plugin or aggressive caching can block admin AJAX — temporarily disable to test.

## Restore is unavailable

**Symptoms:** the **Restore** action is missing or reports no backup.

Check:
1. Restore needs a backup made **before** optimization. Open **Backup & Restore** and confirm **Enable Backups** is on, then re-optimize.
2. Images optimized while backups were **off** have no restore point (existing backups are never deleted, but none were created).
3. **System Status → Backups Writable** should be *Available*.

## Frontend delivery is disabled / not serving WebP-AVIF

**Symptoms:** visitors still receive the original JPEG/PNG.

Check:
1. Turn on **Enable Frontend Delivery** in the **Compression** tab and pick a **Preferred Format**.
2. The image must already have a generated derivative — optimize it first (Dashboard/Reports show how many are available).
3. The visitor's browser must support the format (sent via the `Accept` header). Modern browsers support WebP; AVIF support varies.
4. If you use a **page cache**, configure it to vary on the `Accept` header, or the cached page may be served to all visitors regardless of support.
5. Delivery is intentionally skipped in the admin, feeds, REST, and AJAX contexts.

## Interface language is not changing

**Symptoms:** the admin stays in English after selecting a language.

Check:
1. Set **Compression → Interface Language** and **Save**.
2. The bundled `.mo` translation files must be present in the plugin's `languages/` folder (they ship with the plugin).
3. Untranslated strings intentionally fall back to **English** — this is expected behavior, not an error.
4. Confirm on **System Status → Localization Status**: *Text Domain Loaded* should be *Available*, and *Current Locale* should match your selection.

---

### Still stuck?

Open **System Status**, use **Copy to Clipboard** to capture the environment report, and include it with any support request to `mcorucu@gmail.com` along with your WordPress and PHP versions.
