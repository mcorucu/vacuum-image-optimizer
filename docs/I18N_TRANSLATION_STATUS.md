# Internationalization & Translation Status

**Text domain:** `vacuum-image-optimizer`
**Catalog location:** `languages/`
**Default / fallback language:** English (source strings — no translation file required)
**Total translatable strings (POT):** 257 (extracted with `xgettext`)

## Coverage by Language

| Language | Locale | Translated | Total | MO |
|----------|--------|-----------:|------:|----|
| English (default) | `en_US` | source | 257 | n/a |
| Turkish | `tr_TR` | 257 | 257 | ✅ |
| German | `de_DE` | 257 | 257 | ✅ |
| French | `fr_FR` | 257 | 257 | ✅ |
| Spanish | `es_ES` | 257 | 257 | ✅ |
| Italian | `it_IT` | 257 | 257 | ✅ |
| Portuguese (PT) | `pt_PT` | 257 | 257 | ✅ |
| Russian | `ru_RU` | 257 | 257 | ✅ |
| Dutch | `nl_NL` | 257 | 257 | ✅ |
| Polish | `pl_PL` | 257 | 257 | ✅ |

Every supported locale is **100% translated** (0 empty `msgstr`). Verified with `msgfmt --statistics`.

## Files

- `languages/vacuum-image-optimizer.pot` — full template (257 msgids) generated from source with `xgettext`.
- `languages/vacuum-image-optimizer-{locale}.po` — complete human-readable catalog per locale.
- `languages/vacuum-image-optimizer-{locale}.mo` — compiled binary catalog consumed by WordPress (built with `msgfmt --check`).

## How the Language Override Works

1. The setting `vio_settings['interface_language']` stores `wordpress` (default) or a specific locale.
2. On `plugins_loaded`, `Plugin::filter_plugin_locale()` hooks WordPress's `plugin_locale` filter, **scoped to the `vacuum-image-optimizer` text domain only** (no global locale side effects).
3. Resolution order: plugin override language → WordPress site locale.
   - **Override:** the chosen locale is returned, so `load_plugin_textdomain()` loads `vacuum-image-optimizer-{locale}.mo`.
   - **WordPress mode:** the unmodified site locale from `determine_locale()` is used.

## Non-Extractable Patterns Fixed

Tab labels were previously rendered via `__( $label, … )` (a runtime variable, not extractable and not maintainable). They are now produced by `Router::get_tab_label()`, a **static map** of literal `__( 'Dashboard', … )` calls, so xgettext extracts them and they translate reliably.

## Fallback Behavior

- A missing `.mo`, a missing locale, or any untranslated string falls back to the **English source string** — the UI never shows raw keys or blanks.
- English / WordPress default require no file.

## Known Limitations

- The 257-string catalog covers the full admin UI surface extracted from the source. If new user-facing strings are added later, regenerate with: `xgettext … -o languages/vacuum-image-optimizer.pot <files>`, then `msgmerge --update <locale>.po vacuum-image-optimizer.pot`, translate the new entries, and `msgfmt --check <locale>.po -o <locale>.mo`.
- Plural forms are declared per locale (Russian/Polish use 3-form rules); the one plural string (`%d image(s) added to queue.`) is translated with all required forms.
