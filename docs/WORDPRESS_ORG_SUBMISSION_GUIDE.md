# WordPress.org Submission Guide — Vacuum Image Optimizer

**Phase:** Post-publication record
**Date:** 2026-06-28
**Plugin version (frozen):** 0.9.0
**WordPress.org plugin page:** https://wordpress.org/plugins/vacuum-image-optimizer/
**WordPress.org SVN:** https://plugins.svn.wordpress.org/vacuum-image-optimizer
**Latest SVN revision:** 3588409
**Scope:** Listing assets, branding, screenshots, submission readiness, and publication record.
No plugin features, architecture, or PHP changes.

## Publication Record

Vacuum Image Optimizer 0.9.0 was published to the official WordPress.org Plugin Directory via SVN revision 3588409. The plugin page is available at:

https://wordpress.org/plugins/vacuum-image-optimizer/

> **Important:** WordPress.org listing assets (icon, banner, screenshots) live in
> the SVN repository's top-level **`/assets/`** directory — they are **not** part
> of the plugin ZIP. Files there are referenced by name and must use the exact
> naming conventions below.

---

## 1. WordPress.org Asset Audit

### Brand reference (from existing SVGs in `assets/branding/`)
- **Mark:** rounded gradient tile + white "sun over mountains" image glyph + purple optimization spark + subtle compression ring.
- **Gradient:** `#22C55E` (green) → `#3B82F6` (blue); banner extends to `#A855F7` (purple).
- **Palette:** Primary `#22C55E`, Secondary `#3B82F6`, Tertiary `#A855F7`, Ink `#111827`, Muted `#6B7280`, Surface `#F3F4F6`, White `#FFFFFF`.
- **Wordmark:** "Vacuum" (700) over "Image Optimizer" (600), system font stack (`-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`). No remote fonts.
- **Tagline:** "Lightning-fast image compression for WordPress."
- **Source SVGs available:** `icon.svg`, `admin-icon.svg`, `logo-main.svg`, `banner-concept.svg` (1544×500), `logo-monochrome.svg`, `favicon.svg`.

### Compliance status

| Asset | Required? | Spec | Status |
|-------|-----------|------|--------|
| `readme.txt` | Yes | Valid header + sections | ✅ present & valid (Stable tag 0.9.0, Tested up to 6.8, Requires PHP 8.1) |
| `icon-128x128.png` | Strongly recommended | 128×128 PNG | ❌ **missing** (SVG source exists) |
| `icon-256x256.png` (retina) | Strongly recommended | 256×256 PNG | ❌ **missing** (SVG source exists) |
| `banner-772x250.png` | Recommended | 772×250 PNG | ❌ **missing** (SVG concept exists) |
| `banner-1544x500.png` (retina) | Recommended | 1544×500 PNG | ❌ **missing** (SVG concept exists) |
| `screenshot-1.png` … `screenshot-7.png` | Required if referenced | PNG/JPG, matching readme order | ❌ **missing** (7 captions reference them) |
| `icon.svg` (vector icon) | Optional (preferred if provided) | square SVG | ⚠️ a runtime SVG exists but is not yet placed in SVN `/assets/` as `icon.svg` |

**Every missing asset (must be produced before public submission):**
1. `icon-128x128.png`
2. `icon-256x256.png`
3. `banner-772x250.png`
4. `banner-1544x500.png`
5. `screenshot-1.png` … `screenshot-7.png` (7 files)

> An SVG icon may also be supplied as `/assets/icon.svg`; if so, still provide the
> PNG fallbacks for older WordPress versions.

---

## 2. Screenshot Plan

Capture on a clean site with a few optimized images so numbers are non-zero.
**Recommended capture width: 1280px** (WordPress.org displays screenshots at up to
~1200px wide). Use 2× device pixel ratio if possible, then keep aspect ratio
consistent across all seven. Save as optimized PNG (or JPG for photo-heavy shots).

| File | Screen location | Purpose | On-image title (optional overlay) | Recommended dimensions | Notes |
|------|-----------------|---------|-----------------------------------|------------------------|-------|
| `screenshot-1.png` | Media → Vacuum Image Optimizer → **Dashboard** | Lead shot: savings, KPIs, feature status | "Dashboard at a glance" | 1280×800 | Hero. Show non-zero savings + KPI cards + recent activity. |
| `screenshot-2.png` | **Media Library** (list view) | Per-image WebP/AVIF status column + row actions | "Right inside your Media Library" | 1280×800 | Show the Vacuum column with Optimized/sizes and the row actions. |
| `screenshot-3.png` | **Bulk Optimize** tab | Queue with scan + start/pause/resume + progress | "Bulk-optimize with one click" | 1280×800 | Mid-run state with progress bar and a couple of failed/retry rows is ideal. |
| `screenshot-4.png` | **Compression** tab | Profiles, quality slider, WebP/AVIF + automation toggles | "Profiles and quality, your way" | 1280×800 | Show the four profiles and AVIF/auto-optimize toggles. |
| `screenshot-5.png` | **Reports** tab | Storage savings, recent activity, top savings, CSV export | "See exactly what you saved" | 1280×800 | Include the Export CSV button and a populated table. |
| `screenshot-6.png` | **System Status** tab | Engine support + production-readiness checks | "Know your server supports it" | 1280×800 | Show WebP/AVIF availability and writable-path checks. |
| `screenshot-7.png` | **Compression** tab (Interface Language) | Built-in language selector + localized UI | "Speak your language" | 1280×800 | Optionally show the UI in a non-English locale to prove localization. |

**General notes**
- Use the real admin UI; no fabricated screens.
- Keep chrome consistent (same browser zoom, same admin color scheme — default "Fresh").
- Crop to the plugin content area; avoid leaking unrelated admin menus where possible.
- Caption order in `readme.txt` must match file numbers exactly.

---

## 3. Icon Design Spec — `icon-128x128.png` / `icon-256x256.png`

Derived from `assets/branding/icon.svg` (already the canonical mark).

- **Canvas:** perfect square; export at 128×128 and 256×256 (same artwork, scaled).
- **Layout (256 grid):**
  - Rounded tile: inset 24px on all sides (tile ≈ 208×208), corner radius **64px** (~28% — matches the existing `rx 16/56`).
  - Image glyph (white): sun circle upper-left, mountain range across the lower third, centered within the tile.
  - Optimization spark: small purple (`#A855F7`) circle, upper-right of the tile.
  - Compression ring: thin white ring at ~35% opacity, optional at 256 (omit at 128 if it muddies).
- **Colors:** tile gradient `#22C55E` → `#3B82F6` (top-left → bottom-right, 45°); glyph `#FFFFFF`; spark `#A855F7`.
- **Typography:** **none** — the icon is mark-only (no text; text is illegible at 128px and against WordPress.org guidance).
- **Spacing:** keep the glyph within the central ~70% of the tile; maintain the 24px outer margin so the icon reads as a rounded app tile, not a full-bleed square.
- **Export requirements:**
  - PNG-24 with transparency **outside** the rounded tile (transparent corners).
  - sRGB color profile; no embedded EXIF.
  - Pixel-snap the artwork at each size; re-export from vector (don't upscale the 128).
  - Keep file size small (< 50 KB each is easily achievable).

---

## 4. Banner Design Spec — `banner-772x250.png` / `banner-1544x500.png`

Derived from `assets/branding/banner-concept.svg` (already 1544×500).

- **Background:** diagonal gradient `#22C55E` → `#3B82F6` → `#A855F7` (top-left → bottom-right) with a faint 40px white grid pattern at ~10% opacity.
- **Logo placement (left third):** the white rounded tile + mark, vertically centered, left margin ≈ 10% of width. On the 1544 canvas the mark sits around x≈160–360.
- **Title placement:** "Vacuum Image Optimizer", white, bold (700), ~72px on the 1544 canvas (~36px on 772), baseline in the upper-middle, starting just right of the logo (x≈360 on 1544).
- **Subtitle:** "Lightning-fast image compression for WordPress", white at ~90% opacity, regular (400), ~32px on 1544 (~16px on 772), directly below the title.
- **Visual hierarchy:** logo → title → subtitle → decorative speed lines (right side, white at ~30% opacity). Speed lines must never overlap text.
- **Safe zones:**
  - Keep all text and the logo within a **~7% inner margin** on every edge.
  - WordPress.org overlays the plugin **name and metadata** along the **lower-left** of the banner in some themes — keep the lower-left ~30% free of essential content (the current concept's text sits upper/middle-left, which is fine; just avoid critical elements in the bottom strip).
  - The 772 and 1544 versions must be the **same composition** scaled 2× — produce 1544 first, then downscale to 772.
- **Export requirements:** PNG-24 (or high-quality JPG if banding appears in the gradient), sRGB, no transparency needed (full-bleed), < 200 KB recommended.

---

## 5. Screenshot Caption Review

Captions in `readme.txt` were tightened this phase to be concise and parallel
(screen + one benefit each):

```
1. Dashboard — savings, key metrics, and feature status at a glance.
2. Media Library — per-image WebP/AVIF status and one-click actions.
3. Bulk Optimize — queue with start, pause, and resume controls.
4. Compression — profiles, quality, and WebP/AVIF options.
5. Reports — storage savings, recent activity, and top savings.
6. System Status — engine support and production-readiness checks.
7. Localization — built-in interface-language selector.
```

Each caption maps 1:1 to the screenshot plan above and to an existing admin screen.

---

## 6. Final WordPress.org Submission Checklist

### Code
- [x] No fatal errors; `php -l` clean across main file + all `src/` files (34/34).
- [x] No use of disallowed functions; prepared statements for all custom SQL.
- [x] Capability + nonce checks on every admin/AJAX action.
- [x] `uninstall.php` removes all options, tables, meta, transient, and cron (verified 8.6).
- [x] No external/remote calls; fully self-contained.
- [x] GPL-compatible license declared in header and readme.

### readme.txt
- [x] Valid header: Contributors, Tags (≤5), Requires at least, Tested up to, Requires PHP, Stable tag, License, License URI.
- [x] Description, Installation, FAQ, Screenshots, Changelog, Upgrade Notice sections present.
- [x] Stable tag (`0.9.0`) matches plugin header and `VIO_VERSION`.
- [ ] *(Optional)* Remove non-standard `Author URI` / `Plugin URI` lines from the readme header (they belong in the plugin PHP header; wp.org ignores them, so harmless).

### Assets (SVN `/assets/`)
- [ ] `icon-128x128.png`
- [ ] `icon-256x256.png`
- [ ] *(optional)* `icon.svg`
- [ ] `banner-772x250.png`
- [ ] `banner-1544x500.png`

### Screenshots (SVN `/assets/`)
- [ ] `screenshot-1.png` … `screenshot-7.png` (match readme order)

### Translations
- [x] `languages/` ships POT + 9 locales (`.po`/`.mo`).
- [ ] *(Pre-1.0 i18n pass)* Regenerate POT (`wp i18n make-pot`) to capture strings added in 8.3–8.4; localize the two JS strings (`No failed jobs.`, `Retry`). Non-blocking — English fallback works.

### Versioning
- [x] Header `Version: 0.9.0`, `VIO_VERSION '0.9.0'`, Stable tag `0.9.0`, changelog `0.9.0` — all consistent.
- [ ] Decide RC label vs public tag (see §7).

### Testing
- [x] Clean-install → activation → WebP → AVIF → queue → automation → delivery → reports → restore → uninstall validated (Phase 8.6).
- [ ] Re-run `docs/PRE_RELEASE_CHECKLIST.md` against the final candidate zip.

---

## 7. Release Recommendation

**Recommendation: (A) Release `0.9.0-rc.1` privately for final testing.**

**Reasoning:**
- The plugin is **feature-complete and code-validated** — no functional blockers remain.
- A **public WordPress.org listing cannot proceed** until the listing assets exist: the readme references 7 screenshots that aren't produced, and the icon/banner PNGs are missing. Submitting now would yield a broken/blank listing.
- A short **pre-1.0 i18n pass** (POT refresh + 2 JS strings) is still pending; English fallback works, so it's not urgent, but it's cleaner to land before a public `0.9.0`.
- Therefore: distribute `0.9.0-rc.1` privately (direct zip / GitHub release) to gather real-world feedback **while** the icon, banner, and screenshots are produced. Once assets land and the pre-release checklist passes, promote the **same code** to public **`0.9.0`** (Stable tag stays `0.9.0`; the `-rc.1` suffix is a packaging/tag label only — WordPress.org Stable tags do not accept pre-release suffixes).
- Option **B (public 0.9.0 now)** is premature — assets missing. Option **C (continue development)** is unwarranted — the plugin is feature-complete; remaining work is packaging/branding, not engineering.

---

## 8. Producing the Assets (quick how-to)

The SVG sources already match the brand. To generate the PNGs from this repo's SVGs:

```bash
# Icons (from assets/branding/icon.svg)
#   any SVG→PNG tool works; examples:
rsvg-convert -w 256 -h 256 assets/branding/icon.svg  > icon-256x256.png
rsvg-convert -w 128 -h 128 assets/branding/icon.svg  > icon-128x128.png

# Banner (from assets/branding/banner-concept.svg, 1544×500)
rsvg-convert -w 1544 -h 500 assets/branding/banner-concept.svg > banner-1544x500.png
rsvg-convert -w 772  -h 250 assets/branding/banner-concept.svg > banner-772x250.png
```

Then commit the PNGs (and optional `icon.svg`) into the plugin's SVN **`/assets/`**
directory — **not** the plugin trunk/zip — alongside `screenshot-1.png … screenshot-7.png`.
