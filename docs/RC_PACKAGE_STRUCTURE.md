# RC Package Structure — `vacuum-image-optimizer-0.9.0-rc.1.zip`

**Phase:** 8.5 — RC Build & Packaging
**Date:** 2026-06-19
**Plugin version (frozen):** 0.9.0
**Package label:** 0.9.0-rc.1

This document defines exactly what the Release Candidate distribution archive
should contain. Everything ships under a single top-level folder named
`vacuum-image-optimizer/` so it extracts correctly into `wp-content/plugins/`.

---

## Included in the ZIP

```
vacuum-image-optimizer/
├── vacuum-image-optimizer.php        # Main plugin file — header, constants, bootstrap, hooks
├── uninstall.php                     # Database-only cleanup on delete (options, tables, _vio_* meta)
├── readme.txt                        # WordPress.org readme (stable tag, changelog, FAQ, screenshots)
├── README.md                         # GitHub/developer overview (optional in zip; see note)
├── composer.json                     # PSR-4 map; runtime uses a bundled fallback autoloader
│
├── assets/                           # Runtime admin assets only
│   ├── admin/
│   │   ├── css/admin.css             # Enqueued admin stylesheet (self-contained)
│   │   └── js/admin.js               # Enqueued admin/queue script
│   └── branding/
│       ├── admin-icon.svg            # Used: page-header logo (Router)
│       └── icon.svg                  # Used: dashboard hero logo (Dashboard)
│
├── languages/                        # Translations
│   ├── vacuum-image-optimizer.pot    # Template (258 strings)
│   └── vacuum-image-optimizer-{de_DE,es_ES,fr_FR,it_IT,nl_NL,pl_PL,pt_PT,ru_RU,tr_TR}.{po,mo}
│
└── src/                              # PSR-4 classes (VacuumImageOptimizer\)
    ├── Plugin.php                    # Bootstrap / hook registration
    ├── Admin/                        # Menu, Router, Assets, ReportExporter, Views/
    ├── Backup/                       # BackupManager, BackupPathHelper, BackupCleanup
    ├── Core/                         # Installer, Uninstaller
    ├── Engine/                       # WebPGenerator, AVIFGenerator, RestoreEngine
    ├── Frontend/                     # DeliveryEngine, LazyLoad
    ├── Media/                        # LibraryIntegration, AttachmentActions, DerivativeLibrary
    ├── Queue/                        # QueueManager, QueueProcessor, AjaxController
    ├── Settings/                     # CompressionSettings
    ├── Stats/                        # StatsService
    ├── Upload/                       # UploadAutomation
    └── Utils/                        # SystemCheck
```

**Approximate purpose by area**

| Path | Purpose |
|------|---------|
| `vacuum-image-optimizer.php` | Entry point: defines `VIO_*` constants, registers activation/deactivation, boots `Plugin`. |
| `uninstall.php` | Removes plugin options, custom tables, and `_vio_*` meta. Leaves image/backup files in place by design. |
| `readme.txt` | Canonical WordPress.org metadata and user-facing description. |
| `assets/admin/` | The only CSS/JS actually enqueued (admin + Media Library screens). |
| `assets/branding/` | The two SVGs referenced by the admin UI at runtime. |
| `languages/` | POT + 9 compiled locales (`.mo`) and their sources (`.po`). |
| `src/` | All plugin logic, PSR-4 autoloaded; no build step required. |

---

## Excluded from the ZIP

These exist in the source repository but must **not** ship in the distributable:

| Item | Reason |
|------|--------|
| Local development tooling config directories | Editor/local machine configuration. Never distribute. |
| `docs/` | Internal architecture, phase, and audit documents — not needed by end users. |
| `assets/branding/*` except `admin-icon.svg`, `icon.svg` | Brand/source + `.org` listing concepts (`banner-concept.svg`, `logo-main.svg`, `logo-monochrome.svg`, `favicon.svg`, `admin-logo.svg`, `icon-wordpress.svg`, `wordpress-icon.svg`) are unreferenced at runtime. |
| `composer.lock`, `vendor/` | Not present; runtime uses the bundled fallback autoloader. If `composer install` is ever run, exclude `vendor/` dev tooling from the zip. |
| Any `.git*`, `node_modules/`, OS files (`.DS_Store`) | Development artifacts. |

> **README.md note:** `README.md` may be included or excluded. WordPress.org uses
> `readme.txt`; `README.md` is developer-facing. Keeping it is harmless.

---

## WordPress.org listing assets (separate from the ZIP)

These belong in the SVN `/assets/` directory of the wp.org repo, **not** inside
the plugin zip, and still need to be produced before public submission:

- `icon-256x256.png` (and `icon-128x128.png`)
- `banner-772x250.png` (and `banner-1544x500.png`)
- `screenshot-1.png` … `screenshot-7.png` (match the 7 entries in `readme.txt`)

---

## Build notes

- Removed during 8.5 packaging cleanup: `assets/css/admin-common.css` (dead duplicate
  stylesheet) and the empty `admin/`, `includes/`, `templates/`, `tests/`,
  `assets/images/`, `assets/js/`, `assets/css/` directories.
- The package contains **no build pipeline**: PHP is PSR-4 autoloaded, CSS/JS are
  plain static files. Zipping the folder as-is (minus the exclusions) is the build.
- Recommended zip name for this candidate: `vacuum-image-optimizer-0.9.0-rc.1.zip`.
