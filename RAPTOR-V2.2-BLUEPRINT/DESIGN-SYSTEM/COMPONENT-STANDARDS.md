# Component Standards & Reusable Component Catalog

```
Document:             COMPONENT-STANDARDS.md
Directory:            RAPTOR-V2.2-BLUEPRINT/DESIGN-SYSTEM/
Classification:       UI Component Catalog & Implementation Rules
Target Release:       Raptor v2.2
```

---

## 1. Component Philosophy & Inventory

Raptor v2.2 replaces fragmented HTML view templates with **24 standardized components**. Every component adheres to:
1. **WCAG 2.2 Level AA Accessible Markup**: Proper `aria-*` tags, keyboard navigation, visible `:focus-visible` rings.
2. **Design Token Inheritance**: Powered strictly by CSS custom properties in `COLOR-TOKENS.css`.
3. **State Completeness**: Explicit designs for Default, Hover, Active, Focused, Disabled, Loading, and Empty states.

---

## 2. Core 24 Component Specifications

### 2.1 Buttons (`.raptor-btn`)
- **Variants**: `primary`, `secondary`, `outline`, `ghost`, `danger`, `glass`.
- **Sizes**: `sm` (30px height, 12px text), `md` (38px height, 14px text), `lg` (46px height, 16px text).
- **Rules**:
  - Focus Ring: `2px solid var(--raptor-border-focus)`, `2px offset`.
  - Loading State: Shows inline SVG spinner, maintains button dimensions, sets `disabled` and `aria-busy="true"`.
  - Touch Minimum: 44px x 44px on mobile viewports.

### 2.2 Icon Buttons (`.raptor-btn-icon`)
- Circular or squircle touch targets (36px x 36px).
- Mandatory `aria-label` or `title` tooltip for screen readers.

### 2.3 Cards (`.raptor-card`)
- Solid surface for high data volume (reports, tables).
- Padding options: `p-3` (compact 12px), `p-4` (standard 16px), `p-5` (executive 24px).
- Border: `1px solid var(--raptor-border-subtle)`.

### 2.4 Glass Cards (`.raptor-glass-card`)
- Elevation 2 surface with `backdrop-filter: blur(12px)`.
- Used for high-level summaries, KPI tiles, and marketing dashboards.

### 2.5 Form Inputs (`.raptor-input`)
- Heights: 38px (desktop standard), 44px (mobile).
- Border: `1px solid var(--raptor-border-medium)`.
- Active focus: Glow outline using `var(--raptor-primary-bg)`.
- Error state: `border-color: var(--raptor-danger)` with icon and helper text.

### 2.6 Form Selects (`.raptor-select`)
- Custom SVG chevron indicator.
- Keyboard accessible via standard Arrow keys and Enter/Space.

### 2.7 Search Boxes (`.raptor-search`)
- Leading magnifier icon (`fa-magnifying-glass`).
- Trailing `Ctrl + K` keyboard badge.
- Instant clear button (`fa-xmark`) when value is present.

### 2.8 Dropdowns & Popovers (`.raptor-dropdown`)
- Elevation 3 surface with subtle entry transition (scale 0.98 ➔ 1.0).
- Automatic boundary collision detection (flips upwards if bottom edge is clipped).
- Closes automatically on `Escape` key or clicking outside.

### 2.9 Modals (`.raptor-modal`)
- Backdrop: `rgba(0, 0, 0, 0.65)` with subtle blur.
- Focus trap: Focus is locked within modal; returns to trigger button upon close.
- Explicit header, scrollable body, and action footer.

### 2.10 Drawers / Slide-Over Panels (`.raptor-drawer`)
- Slides from the right edge for detailed views (e.g. Lead details, payslip breakdown).
- Width: 480px on desktop; 100% on mobile.

### 2.11 Toast Notifications (`.raptor-toast`)
- Floating glassmorphic containers in bottom-right corner.
- Semantic icons: Check (Success), Exclamation (Warning), Triangle (Danger), Info (Info).
- Progress bar indicator for auto-dismiss timer (default: 4000ms).
- Subtle Web Audio API chime (respects mute preference).

### 2.12 Data Tables (`.raptor-table`)
- Sticky table headers during vertical scrolling.
- Hover highlight on active row (`var(--raptor-surface-hover)`).
- Monospace font for numerical values, IDs, and amounts.
- Column visibility and HTML5 export buttons (CSV, Excel).

### 2.13 Table Pagination (`.raptor-pagination`)
- Shows current range (`Showing 1 to 25 of 1,420 entries`).
- Quick page jump input and page-size selector (10, 25, 50, 100).

### 2.14 Tabs & Segmented Controls (`.raptor-tabs`)
- Active tab underlined with sliding indicator (`var(--raptor-primary)`).
- Supports horizontal swipe gestures on touch devices.

### 2.15 Status Badges (`.raptor-badge`)
- Pill-shaped (`border-radius: var(--raptor-radius-full)`).
- Semantic tints:
  - `active` / `approved` / `sent`: Green (`var(--raptor-success)`)
  - `pending` / `queued`: Amber (`var(--raptor-warning)`)
  - `rejected` / `failed` / `bounced`: Red (`var(--raptor-danger)`)
  - `draft` / `offline`: Gray (`var(--raptor-text-muted)`)

### 2.16 Metric / KPI Tiles (`.raptor-stat-tile`)
- Big number display (`var(--raptor-text-2xl)`).
- Delta badge (`+12.4% vs last month` with arrow icon).
- Sparkline or trend visualization container.

### 2.17 Tooltips (`.raptor-tooltip`)
- Micro-interaction helper on hover or focus.
- Delayed by 200ms to prevent accidental triggers.

### 2.18 Empty States (`.raptor-empty-state`)
- Centered graphic/illustration.
- Plain-English title and explanation.
- Primary Call-to-Action button (e.g. "Create First Campaign").

### 2.19 Skeleton Loaders (`.raptor-skeleton`)
- Pulsing shimmer gradient over gray blocks while AJAX data is loading.
- Replaces jarring layout shifts with predictable content wireframes.

### 2.20 Error State Banners (`.raptor-error-banner`)
- Displays actionable error recovery steps, not just raw stack traces.
- Includes unique `X-Request-ID` correlation hash and 1-click "Copy Error Details" button for IT support.

### 2.21 Confirmation Dialogs (`.raptor-confirm-dialog`)
- Required for irreversible operations (Delete user, purge list, execute payroll run).
- Destructive actions require typing the resource name or explicit 2-step verification.

### 2.22 Global Command Palette (`.raptor-command-palette`)
- `Ctrl + K` / `Cmd + K` trigger.
- Instant fuzzy query matching across Staff, Leads, Campaigns, and Quick Navigation.
- Permission-filtered (never reveals records unauthorized to current user).

### 2.23 Breadcrumb Navigation (`.raptor-breadcrumb`)
- Clear contextual path (`Marketing > Campaigns > Spring Promo > Preflight`).
- Clickable ancestor links with chevron separators.

### 2.24 Sidebar Navigation (`.raptor-sidebar`)
- Accordion grouping across the 10 core modules.
- Active route indicator with left accent bar (`3px solid var(--raptor-primary)`).
- Collapse/expand state persisted in `localStorage`.

---

## 3. Implementation Directory Structure

All components are stored in `app/views/components/` and rendered via PHP helper:

```php
// Helper in app/core/Controller.php
public function component(string $name, array $props = []): void {
    $file = APPROOT . '/views/components/' . $name . '.php';
    if (file_exists($file)) {
        extract($props);
        include $file;
    }
}
```

