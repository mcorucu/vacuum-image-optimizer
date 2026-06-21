# Final Release Package Specification

**Project:** Vacuum Image Optimizer  
**Version:** 0.9.0  
**Phase:** 9.3 — Final Release Build Preparation  
**Date:** 2026-06-21

This document defines the final release archive structure for public GitHub source distribution and WordPress.org plugin submission. It does not change plugin functionality, UI, database schema, optimization behavior, queue behavior, or translations.

---

## 1. Release Targets

### GitHub public repository / source archive

Purpose: public source distribution for developers, auditors, contributors, and release history.

Recommended archive name:

```text
vacuum-image-optimizer-0.9.0-source.zip
```

### WordPress.org plugin ZIP

Purpose: installable plugin package for WordPress users.

Recommended archive name:

```text
vacuum-image-optimizer.0.9.0.zip
```

The WordPress.org ZIP must extract to a single top-level directory:

```text
vacuum-image-optimizer/
```

---

## 2. GitHub Source Package

### Include

```text
vacuum-image-optimizer/
├── .gitignore
├── README.md
├── composer.json
├── readme.txt
├── uninstall.php
├── vacuum-image-optimizer.php
├── assets/
│   ├── admin/
│   │   ├── css/admin.css
│   │   └── js/admin.js
│   └── branding/
│       ├── admin-icon.svg
│       ├── admin-logo.svg
│       ├── banner-concept.svg
│       ├── favicon.svg
│       ├── icon-wordpress.svg
│       ├── icon.svg
│       ├── logo-main.svg
│       ├── logo-monochrome.svg
│       └── wordpress-icon.svg
├── docs/
│   ├── CLASS_ARCHITECTURE.md
│   ├── DATABASE.md
│   ├── ENGINE.md
│   ├── FINAL_RELEASE_PACKAGE.md
│   ├── FINAL_RELEASE_REVIEW.md
│   ├── I18N_TRANSLATION_STATUS.md
│   ├── LAUNCH_READINESS_REVIEW.md
│   ├── PHASE_7_FRONTEND_DELIVERY.md
│   ├── PHASE_65_HARDENING_REPORT.md
│   ├── PHASE_84_SCALE_HARDENING.md
│   ├── PRE_RELEASE_CHECKLIST.md
│   ├── PRODUCTION_READINESS_REPORT.md
│   ├── PROJECT_SANITIZATION_REPORT.md
│   ├── RC_PACKAGE_STRUCTURE.md
│   ├── RC_VALIDATION_REPORT.md
│   ├── RELEASE_CANDIDATE_AUDIT.md
│   ├── RELEASE_NOTES_0_9_0.md
│   ├── ROADMAP.md
│   ├── TECH_SPEC.md
│   ├── TROUBLESHOOTING.md
│   ├── UI_ARCHITECTURE.md
│   ├── USER_GUIDE.md
│   └── WORDPRESS_ORG_SUBMISSION_GUIDE.md
├── languages/
│   ├── vacuum-image-optimizer.pot
│   ├── vacuum-image-optimizer-de_DE.mo
│   ├── vacuum-image-optimizer-de_DE.po
│   ├── vacuum-image-optimizer-es_ES.mo
│   ├── vacuum-image-optimizer-es_ES.po
│   ├── vacuum-image-optimizer-fr_FR.mo
│   ├── vacuum-image-optimizer-fr_FR.po
│   ├── vacuum-image-optimizer-it_IT.mo
│   ├── vacuum-image-optimizer-it_IT.po
│   ├── vacuum-image-optimizer-nl_NL.mo
│   ├── vacuum-image-optimizer-nl_NL.po
│   ├── vacuum-image-optimizer-pl_PL.mo
│   ├── vacuum-image-optimizer-pl_PL.po
│   ├── vacuum-image-optimizer-pt_PT.mo
│   ├── vacuum-image-optimizer-pt_PT.po
│   ├── vacuum-image-optimizer-ru_RU.mo
│   ├── vacuum-image-optimizer-ru_RU.po
│   ├── vacuum-image-optimizer-tr_TR.mo
│   └── vacuum-image-optimizer-tr_TR.po
└── src/
    ├── Admin/
    ├── Backup/
    ├── Core/
    ├── Engine/
    ├── Frontend/
    ├── Media/
    ├── Queue/
    ├── Settings/
    ├── Stats/
    ├── Upload/
    ├── Utils/
    └── Plugin.php
```

### Exclude

```text
.git/
.DS_Store
._*
Thumbs.db
Desktop.ini
.idea/
.vscode/
.history/
.cache/
.tmp/
.env
.env.*
*.tmp
*.temp
*.bak
*.orig
*.log
vendor/
node_modules/
dist/
build/
coverage/
*.zip
*.tar
*.tar.gz
*.tgz
exports/
reports/
```

---

## 3. WordPress.org Plugin ZIP

### Include

The WordPress.org installable ZIP should be minimal and runtime-focused:

```text
vacuum-image-optimizer/
├── vacuum-image-optimizer.php
├── uninstall.php
├── readme.txt
├── composer.json
├── assets/
│   ├── admin/
│   │   ├── css/
│   │   │   └── admin.css
│   │   └── js/
│   │       └── admin.js
│   └── branding/
│       ├── admin-icon.svg
│       └── icon.svg
├── languages/
│   ├── vacuum-image-optimizer.pot
│   ├── vacuum-image-optimizer-de_DE.mo
│   ├── vacuum-image-optimizer-de_DE.po
│   ├── vacuum-image-optimizer-es_ES.mo
│   ├── vacuum-image-optimizer-es_ES.po
│   ├── vacuum-image-optimizer-fr_FR.mo
│   ├── vacuum-image-optimizer-fr_FR.po
│   ├── vacuum-image-optimizer-it_IT.mo
│   ├── vacuum-image-optimizer-it_IT.po
│   ├── vacuum-image-optimizer-nl_NL.mo
│   ├── vacuum-image-optimizer-nl_NL.po
│   ├── vacuum-image-optimizer-pl_PL.mo
│   ├── vacuum-image-optimizer-pl_PL.po
│   ├── vacuum-image-optimizer-pt_PT.mo
│   ├── vacuum-image-optimizer-pt_PT.po
│   ├── vacuum-image-optimizer-ru_RU.mo
│   ├── vacuum-image-optimizer-ru_RU.po
│   ├── vacuum-image-optimizer-tr_TR.mo
│   └── vacuum-image-optimizer-tr_TR.po
└── src/
    ├── Plugin.php
    ├── Admin/
    │   ├── Assets.php
    │   ├── Menu.php
    │   ├── ReportExporter.php
    │   ├── Router.php
    │   └── Views/
    │       ├── BackupRestore.php
    │       ├── BulkOptimize.php
    │       ├── Compression.php
    │       ├── Dashboard.php
    │       ├── Exclusions.php
    │       ├── Formats.php
    │       ├── LazyLoad.php
    │       ├── Reports.php
    │       └── SystemStatus.php
    ├── Backup/
    │   ├── BackupCleanup.php
    │   ├── BackupManager.php
    │   └── BackupPathHelper.php
    ├── Core/
    │   ├── Installer.php
    │   └── Uninstaller.php
    ├── Engine/
    │   ├── AVIFGenerator.php
    │   ├── RestoreEngine.php
    │   └── WebPGenerator.php
    ├── Frontend/
    │   ├── DeliveryEngine.php
    │   └── LazyLoad.php
    ├── Media/
    │   ├── AttachmentActions.php
    │   ├── DerivativeLibrary.php
    │   └── LibraryIntegration.php
    ├── Queue/
    │   ├── AjaxController.php
    │   ├── QueueManager.php
    │   └── QueueProcessor.php
    ├── Settings/
    │   └── CompressionSettings.php
    ├── Stats/
    │   └── StatsService.php
    ├── Upload/
    │   └── UploadAutomation.php
    └── Utils/
        └── SystemCheck.php
```

### Exclude

```text
.git/
.gitignore
README.md
docs/
assets/branding/admin-logo.svg
assets/branding/banner-concept.svg
assets/branding/favicon.svg
assets/branding/icon-wordpress.svg
assets/branding/logo-main.svg
assets/branding/logo-monochrome.svg
assets/branding/wordpress-icon.svg
composer.lock
vendor/
node_modules/
dist/
build/
coverage/
.DS_Store
._*
Thumbs.db
Desktop.ini
.idea/
.vscode/
.history/
.cache/
.tmp/
.env
.env.*
*.tmp
*.temp
*.bak
*.orig
*.log
*.zip
*.tar
*.tar.gz
*.tgz
exports/
reports/
```

---

## 4. WordPress.org Listing Assets

The following files are not part of the plugin ZIP. They belong in the WordPress.org SVN `/assets/` directory:

```text
assets/
├── icon-128x128.png
├── icon-256x256.png
├── banner-772x250.png
├── banner-1544x500.png
├── screenshot-1.png
├── screenshot-2.png
├── screenshot-3.png
├── screenshot-4.png
├── screenshot-5.png
├── screenshot-6.png
└── screenshot-7.png
```

The screenshot files should match the seven screenshot captions already declared in `readme.txt`.

---

## 5. Build Verification Checklist

- [ ] Archive extracts to a single top-level `vacuum-image-optimizer/` folder.
- [ ] Main plugin file is present at `vacuum-image-optimizer/vacuum-image-optimizer.php`.
- [ ] `readme.txt` is present at the package root.
- [ ] `uninstall.php` is present at the package root.
- [ ] `assets/admin/css/admin.css` is present.
- [ ] `assets/admin/js/admin.js` is present.
- [ ] `assets/branding/admin-icon.svg` is present.
- [ ] `assets/branding/icon.svg` is present.
- [ ] `languages/` contains the POT plus bundled PO/MO files.
- [ ] `src/` contains all PHP source classes.
- [ ] No local environment, cache, archive, IDE, VCS, dependency, or build-output files are included.
- [ ] `php -l vacuum-image-optimizer.php` passes.
- [ ] `find src -maxdepth 5 -name "*.php" -print -exec php -l {} ;` passes.
