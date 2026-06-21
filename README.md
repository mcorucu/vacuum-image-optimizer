# Vacuum Image Optimizer

> Lightning-fast image compression and modern format generation for WordPress.

---

## Overview

**Vacuum Image Optimizer** is a completely original, production-grade WordPress plugin designed to reduce image file sizes, generate WebP and AVIF variants, and improve overall page speed — all from a modern, user-friendly admin interface.

Unlike commercial alternatives, Vacuum Image Optimizer is built from the ground up with strict adherence to WordPress coding standards, object-oriented architecture, and a clean design system optimized for accessibility and usability.

---

## Features

### Phase 1 (MVP)

- **Dashboard** — Real-time statistics, compression charts, system health.
- **Automatic Upload Optimization** — Compress, generate WebP/AVIF, and backup on every upload.
- **Bulk Optimization** — Scan the entire Media Library with pause, resume, and progress tracking.
- **WebP Conversion** — Full support for JPEG and PNG sources with `srcset` compatibility.
- **AVIF Conversion** — Next-gen format generation as a parallel format with automatic fallback handling.
- **Compression Profiles** — Lossless, Balanced, Aggressive, and Ultra quality profiles with adjustable quality.
- **Lazy Loading** — Native browser lazy loading (adds `loading="lazy"`, no JavaScript).
- **Media Library Integration** — Custom columns and inline actions for optimization status.
- **Backup & Restore** — Automatic original backups with configurable retention and one-click restore.
- **Exclusion Rules** — Skip GIF and SVG files from optimization eligibility.
- **System Status** — Comprehensive server capability report.

---

## Architecture

### Tech Stack
- **PHP 8.1+**
- **WordPress 6.2+**
- **OOP with Namespaces & PSR-4 Autoloading**
- **Vanilla JavaScript & CSS** (no build pipeline for MVP)

### Project Structure

```
vacuum-image-optimizer/
├── assets/
│   ├── admin/              # Enqueued admin CSS and JS
│   └── branding/           # In-admin SVG logos and icons
├── docs/                   # Architecture, planning, and release documents
├── languages/              # Translation files (.pot, .po, .mo)
├── src/                    # Core PHP classes (PSR-4)
│   ├── Admin/              # Menu, router, assets, views, exporter
│   ├── Backup/             # Backup paths, manager, cleanup
│   ├── Core/               # Installer / uninstaller
│   ├── Engine/             # WebP / AVIF / restore engines
│   ├── Frontend/           # Delivery engine and lazy loading
│   ├── Media/              # Media Library integration
│   ├── Queue/              # Bulk queue manager, processor, AJAX
│   ├── Settings/           # Compression settings
│   ├── Stats/              # Reporting service
│   ├── Upload/             # Upload automation
│   └── Utils/              # System checks
├── composer.json           # PSR-4 autoloading (runtime falls back to a bundled autoloader)
├── readme.txt              # WordPress.org readme
├── vacuum-image-optimizer.php
├── uninstall.php
└── README.md
```

### Namespaces
- Root: `VacuumImageOptimizer\`
- Core: `VacuumImageOptimizer\Core\`
- Admin: `VacuumImageOptimizer\Admin\`
- Engine: `VacuumImageOptimizer\Engine\`
- Media: `VacuumImageOptimizer\Media\`
- Backup: `VacuumImageOptimizer\Backup\`
- Frontend: `VacuumImageOptimizer\Frontend\`
- Queue: `VacuumImageOptimizer\Queue\`
- Settings: `VacuumImageOptimizer\Settings\`
- Stats: `VacuumImageOptimizer\Stats\`
- Upload: `VacuumImageOptimizer\Upload\`
- Utils: `VacuumImageOptimizer\Utils\`

---

## Design System

The admin UI follows the **ChatBubble Design System**:

| Token | Value |
|-------|-------|
| Primary | `#22C55E` |
| Secondary | `#3B82F6` |
| Tertiary | `#A855F7` |
| Background | `#FFFFFF` |
| Surface | `#F3F4F6` |
| Border Radius | `20px` (cards), `8px` (buttons) |
| Touch Target | Minimum `44px` |

---

## Security

- Capability checks on every admin action.
- Nonce verification on all AJAX endpoints and forms.
- Output escaping with `esc_html()`, `esc_attr()`, and `esc_url()`.
- Backup and restore paths validated and constrained to the plugin's backup directory.
- Prepared statements for all custom SQL queries.

---

## Installation

1. Download or clone this repository into `wp-content/plugins/vacuum-image-optimizer/`.
2. Run `composer install` to install autoloading and development tools.
3. Activate the plugin through the WordPress admin "Plugins" menu.
4. Navigate to **Media → Vacuum Image Optimizer** to get started.

---

## Development

### Coding Standards

This project follows the **WordPress Coding Standards**. Run the linter with:

```bash
composer run phpcs
```

Auto-fix issues with:

```bash
composer run phpcbf
```

### Testing

Run PHPUnit tests:

```bash
composer run phpunit
```

---

## Documentation

Detailed architecture documents are located in `/docs/`:

- `TECH_SPEC.md` — Functional requirements, security model, and API usage.
- `DATABASE.md` — Custom table schemas, migration strategy, and data retention.
- `CLASS_ARCHITECTURE.md` — Full class hierarchy, namespaces, and DTOs.
- `ENGINE.md` — Optimization pipeline, compression profiles, and bulk processing.
- `UI_ARCHITECTURE.md` — Admin pages, design tokens, and responsive behavior.
- `ROADMAP.md` — Development milestones and timeline.

---

## Roadmap

| Phase | Focus | ETA |
|-------|-------|-----|
| Phase 1 | MVP — Core optimization, bulk UI, Media Library, settings | 10 weeks |
| Phase 2 | Multisite, CDN integration, advanced analytics | Post-launch |
| Phase 3 | Premium extensions, cloud compression, video/PDF | Future |

See `docs/ROADMAP.md` for the full milestone breakdown.

---

## License

GPL-2.0-or-later. See the [GNU General Public License v2.0](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

## Credits

All branding assets, code, and documentation are 100% original. No part of this plugin is derived from Smush, ShortPixel, Imagify, EWWW Image Optimizer, Optimole, TinyPNG, or any other commercial image optimization plugin.
