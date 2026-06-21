# Phase 7 — Frontend Delivery Engine

**Date:** 2026-06-18
**Goal:** Serve generated AVIF/WebP derivatives on the frontend without modifying original media, database URLs, the uploads directory, or server config.

---

## Chosen Architecture

**URL replacement via WordPress-native filters, gated by HTTP `Accept` content negotiation and per-file existence validation.**

The engine (`src/Frontend/DeliveryEngine.php`) hooks core image-rendering filters and, for each image URL, attempts to substitute a validated derivative file that physically exists next to the original. Nothing is written, moved, or persisted — the substitution happens only in the generated HTML at render time, so the feature is **fully reversible**: turning the setting off removes all rewriting on the next page load.

### Why not `<picture>` mode (Picture Mode decision — 7.4)

`<picture>`/`<source>` generation was **evaluated and rejected** as the primary mechanism in favor of URL replacement, for these reasons:

1. **Uniform coverage is impossible via attribute filters.** `<picture>` wrapping requires rewriting full image *HTML*, which is only available for in-content images (`wp_content_img_tag`). Template-rendered images — featured images, block-theme bindings, WooCommerce gallery/product images — are produced by `wp_get_attachment_image()` and never pass through a full-HTML filter, so they could not be wrapped consistently.
2. **Layout / lazy-load / srcset fragility.** Injecting `<picture>` around theme markup frequently breaks CSS that targets `img` descendants, lazy-loading scripts, and responsive `sizes` handling. That conflicts with the broad compatibility target (classic themes, block themes, WooCommerce).
3. **Single-derivative reality.** The optimizer generates one full-size derivative beside the original (`image.jpg → image.webp` / `image.avif`); it does not generate per-thumbnail derivatives. URL replacement with per-URL existence checks naturally serves the derivative only where it dimensionally matches, which is exactly what is safe.

URL replacement, by contrast, flows through the same core filters that virtually all WordPress image output already uses, giving universal coverage with minimal risk. This is the documented fallback strategy from 7.4, chosen deliberately as the safest implementation.

---

## Filters Used

| Filter | Purpose | Coverage |
|--------|---------|----------|
| `wp_get_attachment_image_attributes` (priority 20) | Rewrite `src` and remap the `srcset` string of `wp_get_attachment_image()` output. | Featured images, block images, WooCommerce product/gallery images, any theme using core image functions. |
| `wp_calculate_image_srcset` (priority 20) | Remap each responsive `srcset` candidate URL to its derivative when the sibling file exists. | Content images, block images, featured images — anywhere WordPress builds a srcset. |
| `wp_content_img_tag` (priority 20) | Rewrite the base `src` attribute of in-content `<img>` tags. | Classic editor / `the_content` images. |

All three are read-only transformations of output HTML/attributes.

---

## Format Selection Logic (7.3)

For each request the engine computes an ordered list of *acceptable* formats from the `preferred_format` setting intersected with browser support (parsed from the `Accept` request header — `image/avif`, `image/webp`):

- **auto** → try AVIF, then WebP, then original.
- **avif** → try AVIF, then original.
- **webp** → try WebP, then original.

A format is only attempted if the browser advertises support for it via `Accept`. For each image URL, the engine walks the accepted list and returns the first derivative whose file **physically exists**; otherwise it returns the original URL unchanged.

This guarantees:
- **No broken URLs** — a URL is only swapped after `file_exists()` confirms the target.
- **Correct dimensions** — only the full-size original has a sibling derivative, so thumbnails are left as-is automatically.
- **Always falls back to the original** when no derivative exists or the browser doesn't accept the format.

---

## Attachment Integration (7.5)

The engine is **delivery-only**. It never regenerates files or triggers optimization. Derivative existence is determined by checking the sibling file on disk (which corresponds to the `_vio_webp_path` / `_vio_avif_path` metadata produced by the WebP/AVIF engines). The dashboard's "Estimated Optimized Images Available" count is derived from the `_vio_webp_size` / `_vio_avif_size` metadata.

---

## Safety (7.9)

- **Admin untouched:** `register()` bails when `is_admin()`.
- **Never in REST / CLI / cron / XML-RPC / AJAX:** each is explicitly checked before any filter is added.
- **Never in feeds or embeds:** every callback short-circuits on `is_feed()` / `is_embed()`, preserving canonical original URLs in syndicated output.
- **No effect on uploads, optimization, or the queue:** the engine adds only read-time output filters on the frontend and shares no code paths with generation/queue logic.
- **No broken output:** every swap is guarded by `file_exists()` + `is_file()`.
- **Reversible:** disabling `enable_frontend_delivery` stops all rewriting immediately.

---

## Compatibility Notes

- **Classic themes:** covered via `wp_content_img_tag` + `wp_calculate_image_srcset`.
- **Block themes:** covered via `wp_get_attachment_image_attributes` (blocks render through core image functions).
- **WooCommerce:** product and gallery images use `wp_get_attachment_image()`, so they are covered by the attributes + srcset filters.
- **Standard WP image functions:** `wp_get_attachment_image()`, `the_post_thumbnail()`, and content images all flow through the hooked filters.
- **Mixed-format srcset is valid HTML** — where only the full-size derivative exists, the browser may pick the derivative for large viewports and the original for smaller sizes; all candidate URLs are validated.

---

## Known Limitations

1. **Full-size derivatives only.** The optimizer generates one derivative per attachment (full size). Intermediate thumbnail sizes are served as the original format. Generating per-size derivatives would require optimizer changes (out of scope for Phase 7).
2. **Content negotiation depends on the `Accept` header.** Page caches that vary by URL but not by `Accept` could serve a cached AVIF/WebP page to a non-supporting client. Mitigation: configure the page cache to add `Vary: Accept` for HTML, or rely on the near-universal modern WebP support. (No server config is added by the plugin per the phase constraints.)
3. **No `<picture>` element / no per-request `Vary` header injection.** By design, to remain WordPress-native and avoid markup/caching side effects.
4. **Base `src` of content images vs. srcset.** When a responsive `srcset` is present, the browser may select a srcset candidate over the rewritten `src`; both are handled, but the served size depends on viewport.

---

## Files

- **Created:** `src/Frontend/DeliveryEngine.php`, `docs/PHASE_7_FRONTEND_DELIVERY.md`
- **Modified:** `src/Settings/CompressionSettings.php`, `src/Core/Installer.php`, `src/Plugin.php`, `src/Admin/Views/Compression.php`, `src/Admin/Views/SystemStatus.php`, `src/Admin/Views/Dashboard.php`, `src/Stats/StatsService.php`
