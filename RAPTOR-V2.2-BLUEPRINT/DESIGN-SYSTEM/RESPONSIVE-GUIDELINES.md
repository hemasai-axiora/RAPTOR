# Responsive Design Guidelines & Viewport Specifications

```
Document:             RESPONSIVE-GUIDELINES.md
Directory:            RAPTOR-V2.2-BLUEPRINT/DESIGN-SYSTEM/
Classification:       Responsive Layout & Mobile UX Standard
Target Release:       Raptor v2.2
```

---

## 1. Viewport Breakpoint Hierarchy

Raptor v2.2 establishes four primary responsive tiers aligned with enterprise hardware profiles:

| Tier | Breakpoint Query | Representative Devices | Layout Characteristics |
|---|---|---|---|
| **Desktop / Ultra-Wide** | `min-width: 1200px` | 24"-32" Monitors, iMacs | Fixed full sidebar (260px), multi-column data grids, expansive KPI cards, sticky table action headers. |
| **Laptop / Small Desktop**| `992px - 1199px` | 13"-16" MacBooks, ThinkPads | Compact sidebar (can auto-collapse to icon-only), 2-column dashboard widgets, horizontal scrolling on wide tables. |
| **Tablet / Foldable** | `768px - 991px` | iPad Pro/Air, Galaxy Tab | Off-canvas drawer sidebar triggered by hamburger button; tables convert to card stacks or horizontal touch swipe. |
| **Mobile Handheld** | `max-width: 767px` | iPhone 14/15/16, Pixel, Galaxy | Bottom navigation bar for primary tabs, full-screen slide-over drawers, 44px minimum touch targets, no horizontal page overflow. |

---

## 2. Core Mobile UX Transformations

### 2.1 Navigation on Mobile Devices
- **Off-Canvas Sidebar**: The 10-module desktop sidebar transforms into an off-canvas slide-out drawer from the left, backdrop-dimmed with `rgba(0, 0, 0, 0.6)`.
- **Bottom Quick-Bar**: High-frequency mobile actions (Clock In/Out, My Follow-ups, Search, Notifications) are pinned to a fixed bottom navigation bar (`height: 60px`) with safe-area insets (`env(safe-area-inset-bottom)`).

### 2.2 Table Transformation Patterns
Wide enterprise tables (e.g. Employee Directory, Payroll Runs, Lead Manager) cannot simply be shrunk. Raptor v2.2 provides two mobile rendering strategies:
1. **Responsive Card Stacking** (Default for simple entities):
   Table rows collapse into individual vertical cards with field labels displayed as bold metadata:
   ```html
   <!-- Mobile Card Pattern -->
   <div class="raptor-table-card d-md-none p-3 mb-2 rounded bg-dark border border-secondary border-opacity-25">
       <div class="d-flex justify-content-between mb-1">
           <span class="fw-bold text-white"><?php echo $lead->name; ?></span>
           <span class="badge bg-primary"><?php echo $lead->status; ?></span>
       </div>
       <div class="small text-secondary mb-2"><?php echo $lead->company; ?> &bull; <?php echo $lead->phone; ?></div>
       <div class="d-flex gap-2">
           <a href="..." class="btn btn-sm btn-outline-primary flex-fill">View Details</a>
           <a href="tel:..." class="btn btn-sm btn-outline-success"><i class="fa-solid fa-phone"></i></a>
       </div>
   </div>
   ```
2. **Pinned Column Horizontal Scroll** (For complex financial matrices):
   For Payroll Reconciliation and Leave Calendars, the primary column (Employee Name) is pinned to the left while secondary numerical columns scroll smoothly horizontally (`-webkit-overflow-scrolling: touch`).

---

## 3. Touch Target & Accessibility Requirements

- **Minimum Touch Target**: Any interactive button, select, tab, or checkbox must have a minimum clickable area of **44px by 44px** on touchscreens (`pointer: coarse`).
- **Form Input Zoom Prevention**: Mobile iOS zooms into inputs if font size is less than 16px. All inputs on viewports `< 768px` enforce `font-size: 16px` to prevent unexpected viewport shifts.
- **Gesture Support**:
  - Modal sheets and drawers support pull-down-to-dismiss gestures.
  - Toast notifications can be swiped away to the right.
