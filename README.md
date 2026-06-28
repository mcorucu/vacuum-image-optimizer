# Vacuum Image Optimizer

Local WebP and AVIF image optimization for WordPress, with bulk processing, upload automation, backup/restore tools, frontend delivery, reports, and no external API dependency.

## Current Release

- Current version: `0.9.0`
- WordPress.org status: published via SVN
- Latest WordPress.org SVN revision: `3588409`
- Stable tag: `0.9.0`
- Tested up to: WordPress `7.0`
- Requires PHP: `8.1`

## Features

- Generate WebP files for JPEG and PNG uploads using local Imagick or GD support.
- Optionally generate AVIF files when the server supports AVIF.
- Bulk optimize existing Media Library images with a start, pause, resume queue.
- Automatically process new uploads in queue or immediate mode.
- Preserve originals and optionally keep restorable backups.
- Serve generated WebP/AVIF on the frontend with safe fallback to original images.
- View storage savings, recent activity, top savings, and export reports as CSV.
- Use a multilingual admin interface with bundled translations.

## Links

- WordPress.org plugin page: <https://wordpress.org/plugins/vacuum-image-optimizer/>
- WordPress.org SVN: <https://plugins.svn.wordpress.org/vacuum-image-optimizer>
- GitHub release: <https://github.com/mcorucu/vacuum-image-optimizer/releases/tag/v0.9.0>
- Project page: <https://mcorucu.com/en/projects/vacuum-image-optimizer>

Download the installable plugin from WordPress.org once the directory page has finished propagating.

## Requirements

- WordPress `6.2` or newer
- PHP `8.1` or newer
- Imagick or GD with WebP support for WebP generation
- Imagick or GD with AVIF support for optional AVIF generation

## Installation

For normal use, install from the WordPress.org plugin directory:

1. In WordPress, go to **Plugins -> Add New**.
2. Search for **Vacuum Image Optimizer**.
3. Install and activate the plugin.
4. Open **Media -> Vacuum Image Optimizer**.

For local development, clone this repository into:

```text
wp-content/plugins/vacuum-image-optimizer/
```

The runtime plugin includes a lightweight PSR-4 fallback autoloader, so Composer is not required for normal WordPress installs.

## Development / Release Notes

- Public version `0.9.0` was published to WordPress.org in SVN revision `3588409`.
- The WordPress.org ZIP is intentionally runtime-focused and does not include GitHub-only documentation.
- GitHub documentation in `docs/` is maintained for architecture, release history, and contributor context.

## License

GPLv2 or later. See the [GNU General Public License v2.0](https://www.gnu.org/licenses/gpl-2.0.html).
