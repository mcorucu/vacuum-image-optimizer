# Vacuum Image Optimizer — UI Architecture

> Document Version: 1.0.0  
> Date: 2026-06-18  
> Status: Architecture Phase — No code yet

---

## 1. Design System Compliance

All admin UI components follow the **ChatBubble Design System** exactly.

### 1.1 Color Tokens

| Token | Hex | Usage |
|-------|-----|-------|
| Primary | `#22C55E` | Buttons, active states, success indicators, progress bars |
| Secondary | `#3B82F6` | Links, info badges, secondary actions |
| Tertiary | `#A855F7` | Charts, highlights, premium features (future) |
| Background | `#FFFFFF` | Page background, card surfaces |
| Surface | `#F3F4F6` | Card backgrounds, table stripes, input backgrounds |
| Success | `#22C55E` | Success messages, optimized status |
| Warning | `#F59E0B` | Warnings, pending states |
| Error | `#EF4444` | Errors, failed optimizations |
| Info | `#3B82F6` | Informational notices |

### 1.2 Layout Tokens

| Token | Value |
|-------|-------|
| Card Border Radius | `20px` |
| Button Border Radius | `8px` |
| Input Border Radius | `8px` |
| Table Row Border Radius | `8px` (on hover/select) |
| Touch Target Minimum | `44px` |
| Shadow | `0 1px 3px rgba(0,0,0,0.05)` (subtle only) |
| Content Max Width | `1200px` |
| Card Padding | `24px` |
| Grid Gap | `24px` |

### 1.3 Typography

| Element | Size | Weight | Color |
|---------|------|--------|-------|
| Page Title | `28px` | `700` | `#111827` |
| Card Title | `18px` | `600` | `#1F2937` |
| Body | `14px` | `400` | `#374151` |
| Label | `12px` | `600` | `#6B7280` |
| Stat Number | `36px` | `700` | `#22C55E` |

---

## 2. Admin Menu Structure

The plugin lives under the **Media** admin section. There is no top-level menu.

```
Media
└── Vacuum Optimizer
    ├── Dashboard              ← Default landing
    ├── Bulk Optimize
    ├── WebP & AVIF
    ├── Compression
    ├── Lazy Load
    ├── Backup & Restore
    ├── Exclusions
    ├── Reports
    └── System Status
```

### 2.1 Menu Registration
- Parent slug: `upload.php`
- Menu label: `Vacuum Optimizer`
- Page title: `Vacuum Image Optimizer`
- Capability: `manage_options`
- Main admin page slug: `vacuum-image-optimizer`
- Registration function: `add_submenu_page( 'upload.php', ... )`
- All internal screens are rendered as tabs within the single `vacuum-image-optimizer` page. No separate admin page slugs are registered for tabs.

---

## 3. Dashboard Tab (`/wp-admin/upload.php?page=vacuum-image-optimizer&tab=dashboard`)

### 3.1 Layout

```
┌──────────────────────────────────────────────────────────────┐
│  Vacuum Image Optimizer                    [Optimize All]   │
├──────────────────────────────────────────────────────────────┤
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐       │
│  │ 1,240    │ │   892    │ │   348    │ │  42.3 MB │       │
│  │ Total    │ │ Optimized│ │ Pending  │ │ Saved    │       │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘       │
├──────────────────────────────────────────────────────────────┤
│  ┌────────────────────┐  ┌────────────────────┐             │
│  │ Compression Chart  │  │ Format Breakdown   │             │
│  │ (ratio over time)  │  │ (WebP vs AVIF)     │             │
│  └────────────────────┘  └────────────────────┘             │
├──────────────────────────────────────────────────────────────┤
│  ┌────────────────────┐  ┌────────────────────┐             │
│  │ Recent Activity    │  │ System Health      │             │
│  │ (last 10 items)    │  │ (checks & badges)  │             │
│  └────────────────────┘  └────────────────────┘             │
└──────────────────────────────────────────────────────────────┘
```

### 3.2 Stat Cards
- Rounded cards (`20px`) with `Surface` background.
- Number uses `Stat Number` typography.
- Label uses `Label` typography.
- Color-coded: Total (gray), Optimized (green), Pending (orange), Saved (blue).

### 3.3 Charts
- Use Chart.js (lightweight, MIT license) or inline SVG charts.
- No heavy external dependencies.
- Compression ratio chart: line chart, last 30 days.
- Format breakdown: donut chart, WebP vs AVIF vs Original.

### 3.4 Recent Activity
- Table with columns: Time, File, Operation, Status, Size Change.
- Status badge: green (completed), orange (pending), red (failed).
- Load via AJAX on page load.

### 3.5 System Health
- Grid of check badges:
  - PHP Version (green if >= 8.1)
  - WordPress Version (green if >= 6.2)
  - Memory Limit (green if >= 128M)
  - GD Support (green if loaded)
  - Imagick Support (green if loaded)
  - WebP Support (green if supported)
  - AVIF Support (green/yellow/gray)
- Each badge: icon + label + status dot.

---

## 4. Bulk Optimize Tab (`/wp-admin/upload.php?page=vacuum-image-optimizer&tab=bulk`)

### 4.1 Layout States

#### State: Idle (No Job Running)
```
┌──────────────────────────────────────────────────────────────┐
│  Bulk Optimization                                           │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│     [ Scan Media Library ]                                   │
│                                                              │
│     Found: 0 images pending optimization                     │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

#### State: Scanning
- Show spinner (CSS animation, no GIF).
- Progress text: "Scanning… {current} / {total} attachments checked."

#### State: Ready to Optimize
```
│  ┌────────────────────────────────────────────────────────┐  │
│  │  348 images ready to optimize                          │  │
│  │  Estimated time: ~12 minutes                           │  │
│  │                                                        │  │
│  │  [ Start Optimization ]                                │  │
│  └────────────────────────────────────────────────────────┘  │
```

#### State: Optimizing
```
│  ┌────────────────────────────────────────────────────────┐  │
│  │  Optimizing…                                           │  │
│  │  ████████████░░░░░░░░  142 / 348 (41%)                │  │
│  │  Current: my-photo.jpg                                 │  │
│  │  [ Pause ]  [ Cancel ]                                 │  │
│  └────────────────────────────────────────────────────────┘  │
```

#### State: Paused
- Progress bar frozen at last percentage.
- Buttons: `[ Resume ] [ Cancel ]`.

#### State: Complete
```
│  ┌────────────────────────────────────────────────────────┐  │
│  │  Optimization Complete                                 │  │
│  │  ✅ 348 images optimized                               │  │
│  │  💾 42.3 MB saved                                      │  │
│  │  ⚠️ 3 errors (see log)                                 │  │
│  │  [ View Dashboard ]                                    │  │
│  └────────────────────────────────────────────────────────┘  │
```

### 4.2 Progress Bar
- Height: `12px`, border radius: `6px`.
- Background: `Surface`, fill: `Primary` gradient.
- Smooth CSS transition on width change.

### 4.3 Error Log Panel
- Collapsible panel below progress area.
- Lists failed images with error code and message.
- Action: `[ Retry Failed ]`.

---

## 5. Settings Tabs

Settings are split into dedicated tabs under the main page:

### 5.1 Compression Tab (`/wp-admin/upload.php?page=vacuum-image-optimizer&tab=compression`)

### 5.1 Compression Tab Content

```
┌──────────────────────────────────────────────────────────────┐
│  [ Dashboard ] [ Bulk Optimize ] [ WebP & AVIF ] [ Compression ] [ Lazy Load ] [ Backup & Restore ] [ Exclusions ] [ Reports ] [ System Status ]│
├──────────────────────────────────────────────────────────────┤
```

- **Profile Selector:** Radio buttons with description cards.
  - Lossless, Balanced (default), Aggressive, Custom.
- **Custom Profile Panel:** Sliders for JPEG, WebP, AVIF quality (0–100).
- **Engine Preference:** Auto / GD / Imagick.

### 5.2 Resize Tab (`/wp-admin/upload.php?page=vacuum-image-optimizer&tab=resize`)
- **Max Width:** Number input (`px`).
- **Max Height:** Number input (`px`).
- **Retina Support:** Toggle switch.
- **Preserve Aspect Ratio:** Always on (displayed as read-only check).

### 5.3 Lazy Load Tab (`/wp-admin/upload.php?page=vacuum-image-optimizer&tab=lazyload`)
- **Enable Lazy Loading:** Toggle switch.
- **Exclude by Class:** Tag input (comma-separated CSS classes).
- **Exclude by Post Type:** Multi-checkboxes.
- **Exclude Above-the-Fold:** Number input (first N images).
- **Lazy Load iframes:** Toggle switch.

### 5.4 Exclusions Tab (`/wp-admin/upload.php?page=vacuum-image-optimizer&tab=exclusions`)
- **Exclude Folders:** Textarea (one pattern per line).
- **Exclude Filenames:** Textarea (one pattern per line).
- **Exclude Sizes:** Multi-checkboxes of registered image sizes.
- **Exclude GIF Animations:** Toggle switch (default: on).
- **Exclude SVG:** Toggle switch (default: on).

### 5.5 Backup & Restore Tab (`/wp-admin/upload.php?page=vacuum-image-optimizer&tab=backup`)
- **Enable Backups:** Toggle switch.
- **Retention Period:** Number input + dropdown (days/weeks/months).
- **Backup Location:** Read-only path display.
- **[ Clean Old Backups Now ]** Button.
- **Bulk Restore:** Button to restore all optimized images to originals.

---

## 6. WebP & AVIF Tab (`/wp-admin/upload.php?page=vacuum-image-optimizer&tab=formats`)

### 6.1 Layout

- **Generate WebP:** Toggle switch with server capability badge.
- **Generate AVIF:** Toggle switch with server capability badge.
- **WebP Quality Override:** Slider (0–100) when Custom profile is active.
- **AVIF Quality Override:** Slider (0–100) when Custom profile is active.
- **AVIF Speed:** Slider (0–10, default 6).
- **Fallback Chain Info:** Read-only display showing the browser fallback order (AVIF → WebP → Original).
- **Format Statistics:** Cards showing total WebP generated, total AVIF generated, estimated space saved per format.

---

## 7. Reports Tab (`/wp-admin/upload.php?page=vacuum-image-optimizer&tab=reports`)

### 7.1 Layout

- **Optimization History:** Line chart of images optimized over time (7/30/90 days).
- **Space Saved Report:** Bar chart of MB saved per week.
- **Top Savings:** Table of attachments with highest individual savings.
- **Format Adoption:** Pie chart showing percentage of images with WebP vs AVIF vs Original only.
- **Export Data:** `[ Download CSV ]` button for raw stats.

---

## 8. System Status Tab (`/wp-admin/upload.php?page=vacuum-image-optimizer&tab=status`)

### 8.1 Layout

```
┌──────────────────────────────────────────────────────────────┐
│  System Status                                               │
├──────────────────────────────────────────────────────────────┤
│  ┌────────────────────┐  ┌────────────────────┐             │
│  │ Server Environment │  │ Image Engines      │             │
│  │ - PHP 8.2.4        │  │ - GD 2.3.3 ✅      │             │
│  │ - WP 6.5.2         │  │ - Imagick 3.7.0 ✅ │             │
│  │ - Memory 512M      │  │ - WebP ✅          │             │
│  │ - Upload 64M       │  │ - AVIF ❌          │             │
│  └────────────────────┘  └────────────────────┘             │
│  ┌────────────────────┐  ┌────────────────────┐             │
│  │ Active Settings    │  │ Debug Info         │             │
│  │ (read-only summary)│  │ (for support)      │             │
│  └────────────────────┘  └────────────────────┘             │
└──────────────────────────────────────────────────────────────┘
```

### 8.2 Debug Info
- Collapsible textarea with system info formatted for support tickets.
- `[ Copy to Clipboard ]` button.

---

## 9. Media Library Integration

### 7.1 Custom Columns

Added to `wp-admin/upload.php` list table:

| Column | Width | Content |
|--------|-------|---------|
| VIO Status | `100px` | Badge: Optimized (green), Pending (orange), Excluded (gray) |
| Original Size | `100px` | Filesize (e.g., `1.2 MB`) |
| Optimized Size | `100px` | Filesize or `—` |
| Savings | `80px` | Percentage or `—` |
| WebP | `60px` | Checkmark or dash |

### 7.2 Inline Row Actions

When hovering a row, show:
- `Optimize Now` — if pending.
- `Restore Original` — if optimized.
- `Regenerate Variants` — always.

### 7.3 Bulk Actions Dropdown

- `Optimize Selected`
- `Restore Selected`
- `Delete Backups`

---

## 10. Admin Notices

### 10.1 Notice Types

| Type | Color | Usage |
|------|-------|-------|
| Success | `#22C55E` | Optimization complete, settings saved |
| Error | `#EF4444` | Optimization failed, permission denied |
| Warning | `#F59E0B` | AVIF not supported, memory low |
| Info | `#3B82F6` | New features, tips |

### 10.2 Dismissible Behavior
- Notices use WordPress core `.notice` class with `.is-dismissible`.
- Dismissal state stored in user meta (`vio_notice_dismissed_{id}`).

---

## 11. Responsive Behavior

### 11.1 Breakpoints

| Breakpoint | Width | Adjustments |
|------------|-------|-------------|
| Desktop | `> 1024px` | Full grid, side-by-side cards |
| Tablet | `768–1024px` | 2-column grid, stacked charts |
| Mobile | `< 768px` | Single column, full-width cards, hidden chart legends |

### 11.2 Mobile Considerations
- All buttons minimum `44px` height.
- Tables become horizontally scrollable.
- Progress bar full width.
- Settings tabs become a vertical accordion or dropdown.

---

## 12. Accessibility Requirements

- All interactive elements have visible focus states (`outline: 2px solid #22C55E`).
- Color alone does not convey meaning (icons + text labels on badges).
- Progress bar uses `role="progressbar"` with `aria-valuenow`, `aria-valuemin`, `aria-valuemax`.
- All form inputs have associated `<label>` elements.
- Toggle switches are keyboard operable and announce state changes.
- Admin pages use `h1` for page title, `h2` for card titles, proper heading hierarchy.

---

## 13. JavaScript Architecture (Vanilla JS)

No build pipeline for MVP. All admin JS is vanilla ES6+.

### 13.1 File Organization

```
assets/js/
├── admin-dashboard.js      # Stat cards, charts, recent activity
├── admin-bulk.js           # Scan, progress, pause/resume
├── admin-settings.js       # Tab switching, conditional fields
├── admin-media.js          # Inline actions, column interactions
└── admin-common.js         # Notices, AJAX helpers, utilities
```

### 13.2 AJAX Patterns
- All requests use `fetch()` with `FormData` or JSON payload.
- Consistent error handling: network errors show a dismissible notice.
- AbortController for cancellable requests (bulk cancel).

### 13.3 CSS Architecture

```
assets/css/
├── admin-dashboard.css
├── admin-bulk.css
├── admin-settings.css
├── admin-media.css
└── admin-common.css      # Variables, utilities, reset
```

### 13.4 CSS Variables

```css
:root {
  --vio-primary: #22C55E;
  --vio-secondary: #3B82F6;
  --vio-tertiary: #A855F7;
  --vio-bg: #FFFFFF;
  --vio-surface: #F3F4F6;
  --vio-success: #22C55E;
  --vio-warning: #F59E0B;
  --vio-error: #EF4444;
  --vio-info: #3B82F6;
  --vio-radius: 20px;
  --vio-radius-sm: 8px;
}
```

---

## 14. Asset Loading Strategy

- Dashboard page: `admin-common.css`, `admin-dashboard.css`, `admin-dashboard.js`.
- Bulk page: `admin-common.css`, `admin-bulk.css`, `admin-bulk.js`.
- Settings page: `admin-common.css`, `admin-settings.css`, `admin-settings.js`.
- Media Library: `admin-common.css`, `admin-media.css`, `admin-media.js`.
- All assets minified in production builds (future build pipeline).
