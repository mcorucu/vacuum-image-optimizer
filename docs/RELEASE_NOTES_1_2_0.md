# Vacuum Image Optimizer 1.2.0

## Automatic modern image delivery

Vacuum can now use generated AVIF and WebP files in normal WordPress image output when Frontend Delivery is enabled. The resolver is attachment-aware and maps each responsive `srcset` candidate to the matching registered image-size derivative.

Delivery preference is AVIF, then WebP, then the original WordPress image. A derivative is used only when its file is readable, structurally valid, no larger than its source, and recorded against the current source hash. Missing or stale files fall back to the original immediately; expensive conversion never runs in an anonymous frontend request.

The implementation uses WordPress-native image filters and does not rewrite post content. It covers attachment images, featured images, Gutenberg image/gallery output, classic attachment output, responsive images, lazy loading, and WooCommerce integrations that use WordPress attachment APIs. The optional picture-element mode is intentionally deferred until it can be added without risking nested markup or gallery JavaScript compatibility.

## Upgrade and cache behavior

Existing settings, optimization metadata, backups, and originals are preserved. Delivery remains disabled for existing sites until an administrator enables it in Compression settings. New registered-size derivatives are generated only after a successful safe optimization pass.

Because browser support is negotiated through `Accept`, page caches and CDNs should vary image responses on `Accept`. Purge affected page/image caches after enabling delivery or regenerating derivatives. Vacuum does not purge third-party caches automatically.
