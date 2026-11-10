# CHAPTER 4 — Enterprise Design System Specification

```
Document:             DESIGN-SYSTEM.md
Directory:            RAPTOR-V2.2-BLUEPRINT/DESIGN-SYSTEM/
Classification:       UI/UX Design System Specification
Target Release:       Raptor v2.2
```

---

## 1. Visual Identity & Design Principles

Raptor v2.2 establishes an enterprise design language engineered for executive density, visual clarity, and sub-second rendering performance.

### Core Principles
1. **Selective Glassmorphism (Not Blur-Everywhere)**:
   - High-elevation floating elements (Modals, Toasts, Command Palette, Dropdowns, Metric Cards) utilize calibrated backdrop filters (`backdrop-filter: blur(12px)`).
   - High-density data tables and long list views utilize **solid surfaces with subtle border contrast** (`rgba(255, 255, 255, 0.08)` or `#1E293B`) to eliminate scroll stutter and GPU battery drain on laptops and mobile devices.
2. **Typography Hierarchy**:
   - Primary Interface Font: **Plus Jakarta Sans** (with fallback to Inter, `-apple-system`, `Segoe UI`, `Roboto`).
   - Monospace Font (IDs, DSNs, JSON, Hashes): **JetBrains Mono** or `Fira Code`.
   - Payslip Document Font: **Times New Roman** (Strict compliance with Axiora corporate standards).
3. **Spatial Scale & Density**:
   - 4px baseline grid scale: `2px`, `4px`, `8px`, `12px`, `16px`, `24px`, `32px`, `48px`, `64px`.
   - Compact enterprise row height (38px–42px) for data tables to maximize vertical data density without feeling crowded.
4. **Elevation & Shadows**:
   - `elevation-0`: Flat (inputs, table headers)
   - `elevation-1`: Subtle (`0 1px 3px 0 rgba(0, 0, 0, 0.25)`)
   - `elevation-2`: Raised Cards (`0 4px 12px 0 rgba(0, 0, 0, 0.35)`)
   - `elevation-3`: Modals & Command Palette (`0 20px 40px -8px rgba(0, 0, 0, 0.6)`)

---

## 2. Typography Specification

```css
/* Typography Scale */
--raptor-text-xs:    0.75rem;    /* 12px - Badges, table meta, timestamps */
--raptor-text-sm:    0.875rem;   /* 14px - Body secondary, form labels, tooltips */
--raptor-text-base:  1.000rem;   /* 16px - Body default, table cells, buttons */
--raptor-text-lg:    1.125rem;   /* 18px - Sub-section headers, stat labels */
--raptor-text-xl:    1.250rem;   /* 20px - Card titles, module section titles */
--raptor-text-2xl:   1.500rem;   /* 24px - Modal titles, dashboard KPI values */
--raptor-text-3xl:   1.875rem;   /* 30px - Page title headers */

/* Font Weights */
--raptor-font-normal:   400;
--raptor-font-medium:   500;
--raptor-font-semibold: 600;
--raptor-font-bold:     700;
```

---

## 3. Glassmorphic Surface Specifications

```css
/* Calibrated Hardware-Accelerated Glass Surfaces */
.raptor-glass-card {
    background: var(--raptor-glass-bg);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--raptor-glass-border);
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.25);
    border-radius: var(--raptor-radius-lg);
}

.raptor-glass-modal {
    background: var(--raptor-glass-modal-bg);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--raptor-border-subtle);
    box-shadow: 0 24px 64px 0 rgba(0, 0, 0, 0.55);
}

/* Fallback for low-spec devices / reduced-transparency */
@media (prefers-reduced-transparency: reduce) {
    .raptor-glass-card,
    .raptor-glass-modal {
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        background: var(--raptor-surface) !important;
    }
}
```

---

## 4. Theme Architecture & Persistence

Raptor v2.2 supports three theme modes:
1. **Dark Theme** (`data-theme="dark"`): Default executive dark palette (`#0F172A` deep navy background, `#1E293B` cards).
2. **Light Theme** (`data-theme="light"`): High-contrast clean executive daylight palette (`#F8FAFC` background, `#FFFFFF` cards, `#0F172A` text).
3. **System Theme** (`data-theme="system"`): Automatically synchronizes with OS preference (`prefers-color-scheme`).

### Flash of Unstyled Theme (FOUT) Prevention
To ensure zero white/dark flash during page loads, the theme is resolved synchronously in the document `<head>` before CSS and body rendering:

```html
<!-- In public/index.php <head> prior to stylesheet imports -->
<script>
    (function() {
        const savedTheme = localStorage.getItem('raptor-theme') || 'dark';
        let resolved = savedTheme;
        if (savedTheme === 'system') {
            resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', resolved);
    })();
</script>
```

---

## 5. Animation & Motion Standards

- Transitions must not exceed **200ms** for hover and state changes.
- Modal appearances utilize cubic bezier ease-out (`cubic-bezier(0.16, 1, 0.3, 1)`).
- Strict adherence to `prefers-reduced-motion`:
  ```css
  @media (prefers-reduced-motion: reduce) {
      *, ::before, ::after {
          animation-duration: 0.01ms !important;
          animation-iteration-count: 1 !important;
          transition-duration: 0.01ms !important;
          scroll-behavior: auto !important;
      }
  }
  ```

