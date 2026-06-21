# Vacuum Image Optimizer — User Guide

A friendly, step-by-step guide to optimizing your images. No technical knowledge required.

Everything lives under **Media → Vacuum Image Optimizer** in your WordPress admin.

---

## Your First Optimization

1. Go to **Media → Vacuum Image Optimizer**.
2. Open the **System Status** tab and check that **WebP Available** shows *Available*. (If not, ask your host to enable the Imagick or GD image library.)
3. Open the **Compression** tab and pick a **profile** (Balanced is a great default) and a **quality** level.
4. Open the **Bulk Optimize** tab, click **Scan Library**, then **Start Queue**.
5. Watch the progress bar — that's it! Your images now have optimized copies.

## WebP Generation

WebP is a modern image format that's much smaller than JPEG or PNG at similar quality.

- WebP copies are created automatically when you optimize an image (bulk, per-image, or on upload).
- To optimize a single image, go to **Media Library** (list view), hover an image, and click **WebP**.
- Your original file is never changed — the WebP copy is saved next to it.

## AVIF Generation

AVIF is an even newer format that is often smaller than WebP.

1. Open the **Compression** tab.
2. Turn on **Enable AVIF Generation** and choose an **AVIF quality**.
3. From then on, optimizing an image also creates an AVIF copy (when your server supports it).
- You can also create AVIF for a single image with the **AVIF** action in the Media Library.
- If your server can't make AVIF, WebP and originals keep working normally.

## Bulk Optimization

Use this to optimize many images at once.

1. Open the **Bulk Optimize** tab.
2. Click **Scan Library** to find eligible JPEG and PNG images.
3. Click **Start Queue** to begin.
4. You can **Pause** and **Resume** at any time. The queue works in small, safe batches, so you can leave the page and come back.
5. Any items that fail are listed so you can **Retry** them.

## Upload Automation

Let Vacuum optimize images automatically as you upload them.

1. Open the **Compression** tab.
2. Turn on **Enable auto optimization for new uploads**.
3. Choose a mode:
   - **Queue new uploads** — new images are added to the queue to process later from Bulk Optimize.
   - **Optimize immediately** — new images are optimized right after upload (larger uploads may take a little longer).

## Frontend Delivery

This serves the optimized formats to your visitors automatically.

1. Open the **Compression** tab.
2. Turn on **Enable Frontend Delivery**.
3. Choose a **Preferred Format**:
   - **Auto** — serve AVIF when possible, otherwise WebP, otherwise the original.
   - **AVIF** — prefer AVIF, fall back to the original.
   - **WebP** — prefer WebP, fall back to the original.

Visitors whose browsers support the modern format get the smaller file; everyone else gets your original. Your media URLs are not changed, so you can turn this off any time.

## Reports

Open the **Reports** tab to see how much you've saved.

- **Overview** — totals for images, optimized images, generated WebP/AVIF, and queue results.
- **Storage Savings** — original vs. optimized sizes and average savings.
- **Recent Activity** and **Top Savings** — per-image details.
- **Format Distribution** — how many images have WebP/AVIF copies.
- **Export CSV** — download a spreadsheet-friendly report.

A quick summary also appears on the **Dashboard**.

## Restoring Originals

Changed your mind, or want the original back?

1. Make sure **Enable Backups** is on (Backup & Restore tab) *before* optimizing — this keeps a safe copy.
2. In the **Media Library** (list view), hover the image and click **Restore**.
3. The original is put back and its optimization data is cleared.

> Tip: Backups must be enabled at the time of optimization for a restore to be available for that image.
