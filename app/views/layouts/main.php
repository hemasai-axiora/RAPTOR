<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        $page_title = isset($title) ? htmlspecialchars($title) : 'RAPTOR';
        $page_title = str_ireplace('Raptor CRM', 'RAPTOR', $page_title);
    ?>
    <title><?php echo $page_title; ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo URLROOT; ?>/logo.png">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Charting & UI Library CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- Select2 Searchable Dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container--default .select2-selection--single {
            background-color: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 8px !important;
            height: 38px !important;
            display: flex;
            align-items: center;
        }
        [data-theme="dark"] .select2-container--default .select2-selection--single {
            background-color: rgba(0, 0, 0, 0.25) !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #333 !important;
            padding-left: 12px !important;
        }
        [data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #fff !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
            right: 8px !important;
        }
        .select2-dropdown {
            background-color: #ffffff !important;
            border: 1px solid var(--border-color) !important;
            color: #333 !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            z-index: 9999 !important;
        }
        [data-theme="dark"] .select2-dropdown {
            background-color: #1a1c29 !important;
            color: #fff !important;
        }
        .select2-search__field {
            background-color: #fff !important;
            border: 1px solid var(--border-color) !important;
            color: #333 !important;
            border-radius: 6px !important;
        }
        [data-theme="dark"] .select2-search__field {
            background-color: #0d0e12 !important;
            color: #fff !important;
        }
        .select2-container--default .select2-results__option {
            color: #333 !important;
            background-color: #fff !important;
        }
        [data-theme="dark"] .select2-container--default .select2-results__option {
            color: #fff !important;
            background-color: #1a1c29 !important;
        }
        .select2-container--default .select2-results__option--highlighted {
            background-color: var(--primary) !important;
            color: #ffffff !important;
        }
        .select2-container--default .select2-results__option--selected,
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: var(--primary) !important;
            color: #ffffff !important;
        }
    </style>

    <!-- Script Libraries loaded in Head to support inline view scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        /* Apply saved theme preference (defaults to light) */
        var _savedTheme = localStorage.getItem('raptor_theme') || 'light';
        document.documentElement.setAttribute('data-theme', _savedTheme);
    </script>

    <style>
        /* ═══════════════════════════════════════════════════════════
           RAPTOR HRMS — Professional Light Theme
           Primary: #2563EB | Background: #F8FAFC | Cards: #FFFFFF
           ═══════════════════════════════════════════════════════════ */
        :root {
            color-scheme: light;
            /* Surfaces */
            --bg-dark:        #F8FAFC;
            --panel-dark:     #FFFFFF;
            --surface-muted:  #EFF6FF;
            --surface-soft:   #F1F5F9;
            --border-color:   #E2E8F0;
            --border-strong:  #CBD5E1;

            /* Typography */
            --text-primary:   #1E293B;
            --text-secondary: #64748B;
            --text-muted:     #94A3B8;

            /* Brand Colors */
            --primary:        #2563EB;
            --primary-strong: #1D4ED8;
            --primary-soft:   #EFF6FF;
            --primary-glow:   rgba(37, 99, 235, 0.10);
            --success:        #10B981;
            --warning:        #F59E0B;
            --danger:         #EF4444;
            --info:           #06B6D4;

            /* Shadows */
            --shadow-soft:  0 1px 3px rgba(0,0,0,0.06), 0 4px 16px rgba(37,99,235,0.07);
            --shadow-hover: 0 4px 20px rgba(37,99,235,0.15);

            /* Layout */
            --sidebar-width:  260px;
            --topbar-height:  70px;
        }

        html[data-theme="dark"] {
            color-scheme: dark;
            /* Surfaces */
            --bg-dark:        #090F1D;
            --panel-dark:     #111827;
            --surface-muted:  #172033;
            --surface-soft:   #0F172A;
            --border-color:   rgba(255, 255, 255, 0.08);
            --border-strong:  rgba(255, 255, 255, 0.16);

            /* Typography */
            --text-primary:   #F8FAFC;
            --text-secondary: #A8B3C7;
            --text-muted:     #718096;

            /* Brand Colors */
            --primary:        #4A8DDB;
            --primary-strong: #1F5FAE;
            --primary-soft:   rgba(74, 141, 219, 0.13);
            --primary-glow:   rgba(74, 141, 219, 0.18);

            /* Shadows */
            --shadow-soft:  0 12px 32px rgba(0, 0, 0, 0.22);
            --shadow-hover: 0 18px 42px rgba(0, 0, 0, 0.32);
        }

        /* ── Dark Theme Badge Colours ── */
        html[data-theme="dark"] .badge.bg-primary-subtle { color: #93C5FD !important; background-color: rgba(74, 141, 219, 0.2) !important; border-color: rgba(74, 141, 219, 0.3) !important; }
        html[data-theme="dark"] .badge.bg-success-subtle { color: #86EFAC !important; background-color: rgba(16, 185, 129, 0.2) !important; border-color: rgba(16, 185, 129, 0.3) !important; }
        html[data-theme="dark"] .badge.bg-danger-subtle  { color: #FCA5A5 !important; background-color: rgba(239, 68, 68, 0.2) !important; border-color: rgba(239, 68, 68, 0.3) !important; }
        html[data-theme="dark"] .badge.bg-warning-subtle { color: #FDE047 !important; background-color: rgba(245, 158, 129, 0.2) !important; border-color: rgba(245, 158, 129, 0.3) !important; }
        html[data-theme="dark"] .badge.bg-info-subtle    { color: #67E8F9 !important; background-color: rgba(6, 182, 212, 0.2) !important; border-color: rgba(6, 182, 212, 0.3) !important; }
        html[data-theme="dark"] .badge.bg-secondary-subtle { color: #A8B3C7 !important; background-color: rgba(255, 255, 255, 0.05) !important; border-color: rgba(255, 255, 255, 0.1) !important; }


        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-primary);
            overflow-x: hidden;
            margin: 0;
        }

        /* Layout Grid */
        #wrapper {
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        #sidebar {
            width: var(--sidebar-width);
            flex-shrink: 0;
            background-color: var(--panel-dark);
            border-right: 1px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            height: 100vh;
        }

        #sidebar.collapsed {
            margin-left: calc(-1 * var(--sidebar-width));
        }

        .sidebar-brand {
            height: auto;
            display: flex;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-brand-icon {
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, #00b4d8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-right: 0.75rem;
        }

        .sidebar-brand-text {
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -0.5px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1.25rem 0.5rem;
            margin: 0;
            flex-grow: 1;
        }

        /* Menu Section & Accordion Styling */
        .menu-section {
            margin-bottom: 0.35rem;
            list-style: none;
            padding: 0;
        }

        .menu-section-header {
            display: flex;
            align-items: center;
            padding: 0.7rem 0.75rem;
            color: var(--text-secondary);
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
            user-select: none;
        }

        .menu-section-header:hover {
            background-color: var(--primary-soft);
            color: var(--text-primary);
        }

        .menu-section-header .section-icon {
            margin-right: 0.75rem;
            font-size: 1.05rem;
            width: 20px;
            text-align: center;
            color: var(--primary);
        }

        .menu-section-header .section-text {
            flex-grow: 1;
        }

        .menu-section-header .section-arrow {
            font-size: 0.7rem;
            transition: transform 0.2s ease;
            color: var(--text-muted);
        }

        .menu-section.expanded .menu-section-header .section-arrow {
            transform: rotate(90deg);
        }

        .menu-section-items {
            list-style: none;
            padding-left: 0;
            margin: 0;
            display: none; /* Collapsed by default */
        }

        .menu-item a {
            display: flex;
            align-items: center;
            padding: 0.6rem 0.75rem 0.6rem 2.25rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-bottom: 0.15rem;
        }

        .menu-item a i {
            margin-right: 0.75rem;
            font-size: 1rem;
            width: 18px;
            text-align: center;
            opacity: 0.7;
        }

        .menu-item a:hover {
            color: var(--text-primary);
            background-color: var(--primary-soft);
        }

        .menu-item.active a {
            color: var(--primary-strong);
            background: linear-gradient(90deg, var(--primary-soft) 0%, rgba(255,255,255,0) 100%);
            border-left: 3px solid var(--primary);
            padding-left: calc(2.25rem - 3px);
            font-weight: 600;
        }

        .sidebar-footer {
            padding: 0.75rem;
            border-top: 1px solid var(--border-color);
            background-color: var(--surface-soft);
        }

        .user-widget {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 0.75rem;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 0.15rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: capitalize;
        }

        /* Topbar Styling */
        #content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
        }

        #topbar {
            min-height: var(--topbar-height);
            height: auto;
            background-color: var(--panel-dark);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .topbar-left {
            display: flex;
            align-items: center;
        }

        .btn-toggle-sidebar {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.25rem;
            margin-right: 1.5rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .btn-toggle-sidebar:hover {
            color: var(--text-primary);
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        @media (max-width: 1199.98px) {
            .topbar-right {
                flex-grow: 1;
                justify-content: flex-end;
            }
        }

        .filter-select {
            background-color: var(--surface-soft);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 8px;
            font-size: 0.85rem;
            padding: 0.45rem 1rem;
        }

        .filter-select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 4px var(--primary-glow);
        }

        /* Content Container */
        .main-content {
            padding: 1.5rem;
            flex-grow: 1;
        }

        /* Axiora Pulse Card Styling */
        .pulse-card {
            background-color: var(--panel-dark);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.5rem;
            box-shadow: var(--shadow-soft);
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .pulse-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .card-glow::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary) 0%, #4a8ddb 100%);
        }

        .card-title {
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        /* Alerts and Toasts */
        .toast-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(22, 25, 41, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(239, 68, 68, 0.2);
            padding: 1rem;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Theme-aware Bootstrap utility remap.
           Existing views used dark utility classes heavily; these semantic
           overrides make the whole frontend follow the white/blue theme while
           still supporting dark mode from one toggle. */
        .text-white { color: var(--text-primary) !important; }
        .text-secondary, .text-secondary-emphasis, .text-muted { color: var(--text-secondary) !important; }
        .bg-dark { background-color: var(--surface-muted) !important; }
        .bg-opacity-25, .bg-opacity-35, .bg-opacity-50 { --bs-bg-opacity: 1; }
        .border-secondary, .border-secondary-subtle { border-color: var(--border-color) !important; }
        .bg-secondary-subtle { background-color: var(--surface-muted) !important; }
        .text-primary { color: var(--primary) !important; }
        .btn-primary {
            --bs-btn-bg: var(--primary);
            --bs-btn-border-color: var(--primary);
            --bs-btn-hover-bg: var(--primary-strong);
            --bs-btn-hover-border-color: var(--primary-strong);
        }
        /* ── Light Theme Badge Colours (WCAG AA contrast) ── */
        .badge.bg-primary-subtle { color: #1D4ED8 !important; background-color: #DBEAFE !important; border-color: #BFDBFE !important; }
        .badge.bg-success-subtle { color: #065F46 !important; background-color: #D1FAE5 !important; border-color: #A7F3D0 !important; }
        .badge.bg-danger-subtle  { color: #991B1B !important; background-color: #FEE2E2 !important; border-color: #FECACA !important; }
        .badge.bg-warning-subtle { color: #92400E !important; background-color: #FEF3C7 !important; border-color: #FDE68A !important; }
        .badge.bg-info-subtle    { color: #155E75 !important; background-color: #CFFAFE !important; border-color: #A5F3FC !important; }
        .badge.bg-secondary-subtle { color: #1E293B !important; background-color: #F1F5F9 !important; border-color: #E2E8F0 !important; }
        
        .btn-outline-light, .btn-outline-secondary {
            --bs-btn-color: var(--primary);
            --bs-btn-border-color: var(--border-strong);
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: var(--primary);
            --bs-btn-hover-border-color: var(--primary);
        }
        .form-control, .form-select {
            background-color: var(--surface-soft) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
            border-radius: 8px;
        }
        /* Prevent dropdown option truncation */
        .form-select, select.form-select {
            min-width: 140px !important;
            width: 100% !important;
        }
        
        /* Centering logo in collapsed sidebar */
        #sidebar.collapsed .sidebar-brand {
            align-items: center !important;
            justify-content: center !important;
            padding: 1.25rem 0 !important;
        }
        #sidebar.collapsed .sidebar-brand div {
            justify-content: center !important;
            gap: 0 !important;
        }
        #sidebar.collapsed .user-widget a.text-danger {
            display: none !important;
        }
        
        /* Topbar spacing and truncation guards */
        .topbar-left {
            display: flex;
            align-items: center;
            min-width: 0;
            flex-grow: 1;
        }
        .page-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px var(--primary-glow) !important;
        }
        .form-control::placeholder { color: var(--text-muted); }
        .table, .table-dark {
            --bs-table-bg: var(--panel-dark);
            --bs-table-color: var(--text-primary);
            --bs-table-border-color: var(--border-color);
            --bs-table-striped-bg: var(--surface-soft);
            --bs-table-hover-bg: var(--primary-soft);
            color: var(--text-primary);
        }
        .list-group-item {
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        .dropdown-menu, .modal-content {
            background-color: var(--panel-dark);
            color: var(--text-primary);
            border-color: var(--border-color);
        }
        .theme-toggle {
            border: 1px solid var(--border-color);
            background: var(--surface-soft);
            color: var(--text-primary);
            border-radius: 999px;
            height: 38px;
            min-width: 42px;
            padding: 0 0.8rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
        }
        .theme-toggle:hover { color: var(--primary); border-color: var(--primary); }

        /* Custom Table Pagination Styles */
        .table-pagination-nav .page-link {
            background-color: var(--surface-soft) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
            cursor: pointer;
        }
        .table-pagination-nav .page-link:hover {
            background-color: var(--primary-strong) !important;
            color: #fff !important;
        }
        .table-pagination-nav .page-item.active .page-link {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
            color: #fff !important;
        }
        .table-pagination-nav .page-item.disabled .page-link {
            opacity: 0.4;
            pointer-events: none;
        }

        /* Mobile drawer overlay (hidden on desktop) */
        #sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 99;
        }

        /* Bottom navigation (mobile only) */
        #bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: var(--panel-dark);
            border-top: 1px solid var(--border-color);
            z-index: 90;
            justify-content: space-around;
            align-items: center;
        }
        #bottom-nav a {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.65rem;
            flex: 1;
            padding: 4px 0;
        }
        #bottom-nav a i { font-size: 1.15rem; }
        #bottom-nav a.active { color: var(--primary); }

        /* Responsive helper: any wide table/element scrolls inside its box
           instead of breaking the page width. Wrap tables in .table-scroll. */
        .table-scroll { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }

        /* ===================== RESPONSIVE BREAKPOINTS ===================== */

        /* Tablet and below: tighten paddings */
        @media (max-width: 991.98px) {
            /* Sidebar becomes an off-canvas drawer */
            #sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                margin-left: calc(-1 * var(--sidebar-width));
                box-shadow: 4px 0 30px rgba(0,0,0,0.4);
                overflow-y: auto;
            }
            #sidebar.mobile-open { margin-left: 0; }
            #sidebar.mobile-open + #sidebar-overlay,
            body.drawer-open #sidebar-overlay { display: block; }

            #content-wrapper { width: 100%; }
            .main-content { padding: 1rem; padding-bottom: 80px; /* clear bottom nav */ }

            #bottom-nav { display: flex; }

            /* Hide desktop-only topbar filters to save space */
            .topbar-right { gap: 0.5rem; }
            #client-selector, #date-range { max-width: 130px; }
        }

        /* Phones */
        @media (max-width: 575.98px) {
            :root { --topbar-height: 60px; }
            .page-title { font-size: 1.05rem; }
            .topbar-right { display: none; } /* filters move into page content on phones */
            .pulse-card { padding: 1.1rem; border-radius: 12px; }
            /* Stack Bootstrap tables into cards when marked .table-stack */
            table.table-stack thead { display: none; }
            table.table-stack, table.table-stack tbody, table.table-stack tr, table.table-stack td {
                display: block; width: 100%;
            }
            table.table-stack tr {
                margin-bottom: 0.75rem;
                border: 1px solid var(--border-color);
                border-radius: 10px;
                padding: 0.5rem 0.75rem;
                background: var(--panel-dark);
            }
            table.table-stack td {
                display: flex; justify-content: space-between; gap: 1rem;
                border: none; padding: 0.35rem 0; text-align: right;
            }
            table.table-stack td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--text-secondary);
                text-align: left;
            }
        }

        /* Desktop: never show mobile chrome */
        @media (min-width: 992px) {
            #bottom-nav, #sidebar-overlay { display: none !important; }

            #sidebar.collapsed {
                width: 70px !important;
                margin-left: 0 !important;
            }
            #sidebar.collapsed + #content-wrapper {
                margin-left: 70px !important;
            }
            #sidebar.collapsed .sidebar-brand {
                align-items: center !important;
                justify-content: center !important;
                padding: 1.25rem 0 !important;
            }
            #sidebar.collapsed .sidebar-brand-text,
            #sidebar.collapsed .sidebar-brand-desc,
            #sidebar.collapsed .section-text,
            #sidebar.collapsed .section-arrow,
            #sidebar.collapsed .user-name,
            #sidebar.collapsed .user-role,
            #sidebar.collapsed .menu-item a span,
            #sidebar.collapsed .sidebar-footer-text {
                display: none !important;
            }
            #sidebar.collapsed .menu-section-header {
                justify-content: center !important;
                padding: 0.75rem 0 !important;
            }
            #sidebar.collapsed .menu-section-header .section-icon {
                margin-right: 0 !important;
                font-size: 1.25rem !important;
            }
            #sidebar.collapsed .menu-section-items {
                display: none !important; /* Force collapse submenus when sidebar is collapsed */
            }
            #sidebar.collapsed .user-widget {
                justify-content: center !important;
            }
            #sidebar.collapsed .user-avatar {
                margin-right: 0 !important;
            }
            #sidebar.collapsed .menu-item a {
                justify-content: center !important;
                padding: 0.75rem 0 !important;
            }
            #sidebar.collapsed .menu-item a i {
                margin-right: 0 !important;
                font-size: 1.15rem !important;
            }
        }
    </style>
</head>
<body>
    <?php
        $role = $_SESSION['user_role'] ?? '';
        $isEmployee = Policy::isEmployee();
        $salesRoles = ['admin', 'manager', 'team_leader', 'employee', 'sales_person', 'hr', 'finance'];
    ?>
    <div id="wrapper">
        <!-- Sidebar Navigation -->
        <nav id="sidebar">
            <div class="sidebar-brand d-flex flex-column align-items-start justify-content-center" style="padding: 1.25rem 1.5rem; height: auto; border-bottom: 1px solid var(--border-color);">
                <div class="d-flex align-items-center justify-content-between w-100">
                    <div class="d-flex align-items-center gap-2">
                        <img src="<?php echo URLROOT; ?>/logo.png" alt="Raptor Logo" class="brand-logo-img" style="height: 32px; width: auto; object-fit: contain;">
                        <span class="sidebar-brand-text" style="font-size: 1.15rem; font-weight: 700; letter-spacing: -0.5px; color: var(--text-primary);">RAPTOR</span>
                    </div>
                    <button type="button" class="btn-close d-lg-none" id="close-sidebar-btn" aria-label="Close" style="font-size: 0.8rem;"></button>
                </div>
                <span class="sidebar-brand-desc text-secondary mt-1" style="font-size: 0.72rem; font-weight: 500;">Digital Marketing Hub</span>
            </div>
            
            <ul class="sidebar-menu">
                <!-- 1. Dashboard Accordion Group -->
                <li class="menu-section" data-section="dashboard">
                    <div class="menu-section-header">
                        <i class="fa-solid fa-chart-line section-icon"></i>
                        <span class="section-text">Dashboard</span>
                        <i class="fa-solid fa-chevron-right section-arrow"></i>
                    </div>
                    <ul class="menu-section-items">
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'dashboard_module' && (!isset($data['title']) || strpos($data['title'], 'Templates') === false)) ? 'active' : ''; ?>">
                            <a href="index.php?route=dashboard/index">
                                <i class="fa-solid fa-table-columns"></i><span>Dashboard Module</span>
                            </a>
                        </li>
                        <?php if (Policy::canCreateDashboardTemplate()): ?>
                        <li class="menu-item <?php echo (isset($data['title']) && strpos($data['title'], 'Templates') !== false) ? 'active' : ''; ?>">
                            <a href="index.php?route=dashboard/templates">
                                <i class="fa-solid fa-sliders"></i><span>Dashboard Templates</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'employer'])): ?>
                        <li class="menu-item <?php echo ($active_tab === 'executive') ? 'active' : ''; ?>">
                            <a href="index.php?route=dashboard/executive">
                                <i class="fa-solid fa-house-chimney"></i><span>Executive Overview</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'analyst'])): ?>
                        <li class="menu-item <?php echo ($active_tab === 'channels') ? 'active' : ''; ?>">
                            <a href="index.php?route=dashboard/channels">
                                <i class="fa-solid fa-chart-simple"></i><span>Campaign Performance</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'analyst'])): ?>
                        <li class="menu-item <?php echo ($active_tab === 'customer') ? 'active' : ''; ?>">
                            <a href="index.php?route=dashboard/customer">
                                <i class="fa-solid fa-users-viewfinder"></i><span>Customer Intelligence</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>

                <!-- 2. My Day Accordion Group -->
                <?php if (in_array($role, $salesRoles, true)): ?>
                <li class="menu-section" data-section="myday">
                    <div class="menu-section-header">
                        <i class="fa-solid fa-calendar-day section-icon"></i>
                        <span class="section-text">My Day</span>
                        <i class="fa-solid fa-chevron-right section-arrow"></i>
                    </div>
                    <ul class="menu-section-items">
                        <?php if ($role !== 'admin'): ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'attendance') ? 'active' : ''; ?>">
                            <a href="index.php?route=attendance/index">
                                <i class="fa-solid fa-fingerprint"></i><span>My Attendance</span>
                            </a>
                        </li>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'leaves') ? 'active' : ''; ?>">
                            <a href="index.php?route=leaves/index">
                                <i class="fa-solid fa-calendar-day"></i><span>My Leaves</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'followups') ? 'active' : ''; ?>">
                            <a href="index.php?route=followups/index">
                                <i class="fa-solid fa-bell"></i><span>My Follow-ups</span>
                            </a>
                        </li>
                        <?php if ($isEmployee || $role === 'team_leader'): ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'myroute') ? 'active' : ''; ?>">
                            <a href="index.php?route=location/myday">
                                <i class="fa-solid fa-route"></i><span>My Route</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 3. Monitoring Accordion Group -->
                <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader', 'hr'], true)): ?>
                <li class="menu-section" data-section="monitoring">
                    <div class="menu-section-header">
                        <i class="fa-solid fa-desktop section-icon"></i>
                        <span class="section-text">Monitoring</span>
                        <i class="fa-solid fa-chevron-right section-arrow"></i>
                    </div>
                    <ul class="menu-section-items">
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'monitoring_dashboard') ? 'active' : ''; ?>">
                            <a href="index.php?route=dashboard/monitoring">
                                <i class="fa-solid fa-tower-observation"></i><span>Command Center</span>
                            </a>
                        </li>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'attendance_approvals') ? 'active' : ''; ?>">
                            <a href="index.php?route=attendance/approvals">
                                <i class="fa-solid fa-user-check"></i><span>Attendance Approvals</span>
                            </a>
                        </li>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'leave_approvals') ? 'active' : ''; ?>">
                            <a href="index.php?route=leaves/approvals">
                                <i class="fa-solid fa-file-signature"></i><span>Leave Approvals</span>
                            </a>
                        </li>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'leaves_calendar') ? 'active' : ''; ?>">
                            <a href="index.php?route=leaves/calendar">
                                <i class="fa-solid fa-calendar-check"></i><span>Leave Calendar</span>
                            </a>
                        </li>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'attendance_report') ? 'active' : ''; ?>">
                            <a href="index.php?route=attendance/report">
                                <i class="fa-solid fa-calendar-check"></i><span>Attendance Report</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 4. Operations Accordion Group -->
                <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'], true)): ?>
                <li class="menu-section" data-section="operations">
                    <div class="menu-section-header">
                        <i class="fa-solid fa-briefcase section-icon"></i>
                        <span class="section-text">Operations</span>
                        <i class="fa-solid fa-chevron-right section-arrow"></i>
                    </div>
                    <ul class="menu-section-items">
                        <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'analyst'])): ?>
                        <li class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'] ?? '', 'clients/') !== false || (isset($_GET['route']) && strpos($_GET['route'], 'clients') !== false)) ? 'active' : ''; ?>">
                            <a href="index.php?route=clients/index">
                                <i class="fa-solid fa-briefcase"></i><span>Clients Directory</span>
                            </a>
                        </li>
                        <li class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'] ?? '', 'campaigns/') !== false || (isset($_GET['route']) && strpos($_GET['route'], 'campaigns') !== false)) ? 'active' : ''; ?>">
                            <a href="index.php?route=campaigns/index">
                                <i class="fa-solid fa-bullhorn"></i><span>Campaign Registry</span>
                            </a>
                        </li>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'calendar') ? 'active' : ''; ?>">
                            <a href="index.php?route=calendar/index">
                                <i class="fa-solid fa-calendar-days"></i><span>Content Calendar</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (in_array($role, $salesRoles, true)): ?>
                        <li class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'] ?? '', 'leads/') !== false || (isset($_GET['route']) && strpos($_GET['route'], 'leads') !== false)) ? 'active' : ''; ?>">
                            <a href="index.php?route=leads/index">
                                <i class="fa-solid fa-address-book"></i><span>Leads Manager</span>
                            </a>
                        </li>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'communications') ? 'active' : ''; ?>">
                            <a href="index.php?route=communications/index">
                                <i class="fa-solid fa-comments"></i><span>Communications</span>
                            </a>
                        </li>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'meetings') ? 'active' : ''; ?>">
                            <a href="index.php?route=meetings/index">
                                <i class="fa-solid fa-handshake"></i><span>Meetings & Demos</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <li class="menu-item <?php echo ($data['title'] === 'Task Board | Raptor CRM') ? 'active' : ''; ?>">
                            <a href="index.php?route=tasks/index">
                                <i class="fa-solid fa-list-check"></i><span>Task Board</span>
                            </a>
                        </li>
                        <?php if (in_array($role, $salesRoles, true)): ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'targets') ? 'active' : ''; ?>">
                            <a href="index.php?route=targets/index">
                                <i class="fa-solid fa-bullseye"></i><span>Targets</span>
                            </a>
                        </li>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'performance') ? 'active' : ''; ?>">
                            <a href="index.php?route=performance/index">
                                <i class="fa-solid fa-ranking-star"></i><span>Performance</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 5. Finance Accordion Group -->
                <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'finance'], true)): ?>
                <li class="menu-section" data-section="finance">
                    <div class="menu-section-header">
                        <i class="fa-solid fa-file-invoice-dollar section-icon"></i>
                        <span class="section-text">Finance</span>
                        <i class="fa-solid fa-chevron-right section-arrow"></i>
                    </div>
                    <ul class="menu-section-items">
                        <li class="menu-item <?php echo (strpos($data['title'], 'Invoice') !== false || $data['title'] === 'Billing Ledger | Raptor CRM') ? 'active' : ''; ?>">
                            <a href="index.php?route=invoices/index">
                                <i class="fa-solid fa-file-invoice-dollar"></i><span>Invoicing</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 6. Marketing Accordion Group -->
                <?php if (isset($_SESSION['user_role'])): ?>
                <li class="menu-section" data-section="marketing">
                    <div class="menu-section-header">
                        <i class="fa-solid fa-share-nodes section-icon"></i>
                        <span class="section-text">Marketing</span>
                        <i class="fa-solid fa-chevron-right section-arrow"></i>
                    </div>
                    <ul class="menu-section-items">
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'social_update') ? 'active' : ''; ?>">
                            <a href="index.php?route=social/update">
                                <i class="fa-solid fa-pen-to-square"></i><span>Update Social Stats</span>
                            </a>
                        </li>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'social_leads') ? 'active' : ''; ?>">
                            <a href="index.php?route=social/leads">
                                <i class="fa-solid fa-user-plus"></i><span>Lead Generation</span>
                            </a>
                        </li>
                        <?php if (in_array($_SESSION['user_role'], ['admin', 'manager'])): ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'social_manager') ? 'active' : ''; ?>">
                            <a href="index.php?route=social/manager">
                                <i class="fa-solid fa-user-gear"></i><span>Manager Performance</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'social_admin') ? 'active' : ''; ?>">
                            <a href="index.php?route=social/admin">
                                <i class="fa-solid fa-folder-tree"></i><span>Accounts Directory</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?> <!-- end Marketing -->

                <!-- 7. Reports Accordion Group -->
                <?php if (!Policy::isEmployee()): ?>
                <li class="menu-section" data-section="reports">
                    <div class="menu-section-header">
                        <i class="fa-solid fa-chart-pie section-icon"></i>
                        <span class="section-text">Reports</span>
                        <i class="fa-solid fa-chevron-right section-arrow"></i>
                    </div>
                    <ul class="menu-section-items">
                        <?php if (!Policy::isEmployee()): ?>
                        <li class="menu-item <?php echo ($data['title'] === 'Reports Center | Raptor CRM') ? 'active' : ''; ?>">
                            <a href="index.php?route=reports/index">
                                <i class="fa-solid fa-folder-open"></i><span>Reports Module</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'social_history') ? 'active' : ''; ?>">
                            <a href="index.php?route=social/history">
                                <i class="fa-solid fa-clock-rotate-left"></i><span>Analytics History</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?> <!-- end Reports -->

                <!-- 8. HR & Payroll Accordion Group -->
                <?php if (in_array($role, ['admin', 'ceo', 'manager', 'team_leader', 'hr', 'finance', 'analyst', 'employee', 'sales_person'], true)): ?>
                <li class="menu-section" data-section="hr">
                    <div class="menu-section-header">
                        <i class="fa-solid fa-users-gear section-icon"></i>
                        <span class="section-text">HR & Payroll</span>
                        <i class="fa-solid fa-chevron-right section-arrow"></i>
                    </div>
                    <ul class="menu-section-items">
                        <?php if (Policy::canManageEmployees()): ?>
                        <li class="menu-item <?php echo ($data['title'] === 'Employee Management | Raptor CRM') ? 'active' : ''; ?>">
                            <a href="index.php?route=users/index">
                                <i class="fa-solid fa-users"></i><span>Employee Directory</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Policy::canApproveDataEdit() || Policy::canRequestDataEdit()): ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'edit_requests') ? 'active' : ''; ?>">
                            <a href="index.php?route=editrequests/index">
                                <i class="fa-solid fa-file-pen"></i><span>Data Edit Requests</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (in_array($role, ['admin', 'ceo', 'manager', 'analyst'], true)): ?>
                        <li class="menu-item <?php echo ($data['title'] === 'Organization | Raptor CRM') ? 'active' : ''; ?>">
                            <a href="index.php?route=teams/index">
                                <i class="fa-solid fa-sitemap"></i><span>Organization</span>
                            </a>
                        </li>
                        <?php endif; ?>

                         <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'hrms_dashboard') ? 'active' : ''; ?>">
                            <a href="index.php?route=hrms/dashboard">
                                <i class="fa-solid fa-chart-line"></i><span>HRMS Dashboard</span>
                            </a>
                        </li>
                        <?php if (in_array($role, ['admin', 'ceo', 'hr', 'analyst'], true)): ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'hrms_reports') ? 'active' : ''; ?>">
                            <a href="index.php?route=hrms/reports">
                                <i class="fa-solid fa-file-invoice"></i><span>HRMS Reports</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- New Payroll Items -->
                        <?php if (in_array($role, ['admin', 'ceo', 'hr', 'finance', 'manager', 'analyst'], true)): ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'payroll_dashboard') ? 'active' : ''; ?>">
                            <a href="index.php?route=payroll/dashboard">
                                <i class="fa-solid fa-chart-pie"></i><span>Payroll Dashboard</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (in_array($role, ['admin', 'ceo', 'hr', 'analyst'], true)): ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'payroll_structures') ? 'active' : ''; ?>">
                            <a href="index.php?route=payroll/structures">
                                <i class="fa-solid fa-calculator"></i><span>Salary Structures</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (in_array($role, ['admin', 'ceo', 'hr', 'finance', 'analyst'], true)): ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'payroll_processing') ? 'active' : ''; ?>">
                            <a href="index.php?route=payroll/processing">
                                <i class="fa-solid fa-file-invoice-dollar"></i><span>Payroll Runs</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'payroll_payslips') ? 'active' : ''; ?>">
                            <a href="index.php?route=payroll/payslips">
                                <i class="fa-solid fa-receipt"></i><span>My Payslips</span>
                            </a>
                        </li>

                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'payroll_reimbursements') ? 'active' : ''; ?>">
                            <a href="index.php?route=payroll/reimbursements">
                                <i class="fa-solid fa-hand-holding-dollar"></i><span>Reimbursements</span>
                            </a>
                        </li>

                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'payroll_bonuses') ? 'active' : ''; ?>">
                            <a href="index.php?route=payroll/bonuses">
                                <i class="fa-solid fa-gift"></i><span>Bonuses & Perks</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 9. Settings Accordion Group -->
                <li class="menu-section" data-section="settings">
                    <div class="menu-section-header">
                        <i class="fa-solid fa-sliders section-icon"></i>
                        <span class="section-text">Settings</span>
                        <i class="fa-solid fa-chevron-right section-arrow"></i>
                    </div>
                    <ul class="menu-section-items">
                        <li class="menu-item <?php echo (isset($_GET['route']) && $_GET['route'] === 'hrms/profile/' . $_SESSION['user_id']) ? 'active' : ''; ?>">
                            <a href="index.php?route=hrms/profile/<?php echo $_SESSION['user_id']; ?>">
                                <i class="fa-solid fa-address-card"></i><span>My Profile</span>
                            </a>
                        </li>
                        <?php if (Policy::can('settings', 'manage')): ?>
                        <li class="menu-item <?php echo ($data['title'] === 'Global Settings | Raptor CRM') ? 'active' : ''; ?>">
                            <a href="index.php?route=settings/index">
                                <i class="fa-solid fa-sliders"></i><span>Global Settings</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (Policy::can('roles', 'manage')): ?>
                        <li class="menu-item <?php echo (strpos($_GET['route'] ?? '', 'roles') !== false) ? 'active' : ''; ?>">
                            <a href="index.php?route=roles/index">
                                <i class="fa-solid fa-user-shield"></i><span>Roles & Permissions</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (Policy::can('settings', 'manage')): ?>
                        <li class="menu-item <?php echo (isset($_GET['route']) && ($_GET['route'] === 'settings/accessControl' || strpos($_GET['route'], 'settings/userAccess') !== false)) ? 'active' : ''; ?>">
                            <a href="index.php?route=settings/accessControl">
                                <i class="fa-solid fa-user-lock"></i><span>User Access Control</span>
                            </a>
                        </li>
                        <li class="menu-item <?php echo (isset($_GET['route']) && $_GET['route'] === 'settings/auditLogs') ? 'active' : ''; ?>">
                            <a href="index.php?route=settings/auditLogs">
                                <i class="fa-solid fa-list-check"></i><span>System Audit Logs</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="menu-item <?php echo (isset($active_tab) && $active_tab === 'notifications') ? 'active' : ''; ?>">
                            <a href="index.php?route=notifications/index">
                                <i class="fa-solid fa-bell"></i><span>Notifications</span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>


            <div class="sidebar-footer">
                <div class="user-widget">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?>
                    </div>
                    <div class="flex-grow-1 overflow-hidden" style="margin-right: 0.5rem;">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars(Policy::roleLabel()); ?></div>
                    </div>
                    <a href="index.php?route=auth/logout" class="text-danger" title="Logout" style="font-size: 1.1rem;">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                </div>
                <div class="sidebar-footer-text text-center text-secondary small mt-3" style="font-size: 0.68rem; line-height: 1.4;">
                    &copy; 2026 RAPTOR<br>Powered by RAPTOR
                </div>
            </div>
        </nav>

        <!-- Mobile drawer overlay (tap to close) -->
        <div id="sidebar-overlay"></div>

        <!-- Main Content Area -->
        <div id="content-wrapper" class="d-flex flex-column min-vh-100">
            <!-- Top Header Bar -->
            <header id="topbar">
                <div class="topbar-left d-flex align-items-center">
                    <button class="btn-toggle-sidebar" id="toggle-btn" title="Toggle Sidebar">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <!-- Small header logo -->
                    <div class="d-flex align-items-center gap-2 ms-2">
                        <img src="<?php echo URLROOT; ?>/logo.png" alt="Logo" style="height: 28px; width: auto; object-fit: contain;">
                    </div>
                    <h2 class="page-title ms-3 border-start ps-3" style="font-size: 1rem; font-weight: 600; margin-bottom: 0; border-color: var(--border-strong) !important; color: var(--text-primary);">
                        <?php 
                            $display_title = isset($title) ? htmlspecialchars($title) : 'Dashboard';
                            $display_title = preg_replace('/\s*\|\s*Raptor CRM\s*/i', '', $display_title);
                            $display_title = preg_replace('/\s*\|\s*RAPTOR\s*/i', '', $display_title);
                            $display_title = str_ireplace(['Raptor CRM', 'RAPTOR'], '', $display_title);
                            echo trim($display_title, " |");
                        ?>
                    </h2>
                </div>

                <div class="topbar-right d-flex align-items-center gap-2">
                    <!-- Global Filters -->
                    <select class="filter-select d-none d-lg-block" id="client-selector">
                        <option value="all">All Clients</option>
                        <option value="1">Axiora Tech</option>
                    </select>
                    <input type="text" class="filter-select d-none d-lg-block" id="date-range" placeholder="Date Range">
                    
                    <!-- Notification Bell Dropdown -->
                    <?php
                    $notifUser = $_SESSION['user_id'] ?? 0;
                    $unreadNotifs = [];
                    $totalUnreadCount = 0;
                    if ($notifUser > 0) {
                        try {
                            $dbConnection = Database::getInstance()->getConnection();
                            // Total unread count for badge
                            $stmtCount = $dbConnection->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
                            $stmtCount->execute([':uid' => $notifUser]);
                            $totalUnreadCount = (int) $stmtCount->fetchColumn();

                            // Top 5 unread notifications for dropdown
                            $stmtNotif = $dbConnection->prepare("SELECT * FROM notifications WHERE user_id = :uid AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
                            $stmtNotif->execute([':uid' => $notifUser]);
                            $unreadNotifs = $stmtNotif->fetchAll(PDO::FETCH_OBJ) ?: [];
                        } catch (Exception $e) { 
                            $unreadNotifs = []; 
                            $totalUnreadCount = 0;
                        }
                    }
                    ?>
                    <div class="dropdown">
                        <button type="button" class="theme-toggle position-relative shadow-none border-0 bg-transparent" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications" style="color: var(--text-primary); font-size: 1.1rem; padding: 0 8px;">
                            <i class="fa-solid fa-bell"></i>
                            <?php if ($totalUnreadCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.55rem; padding: 0.25em 0.5em; top: 2px !important;"><?php echo $totalUnreadCount; ?></span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end py-0 shadow-sm" aria-labelledby="notificationsDropdown" style="width: 320px; min-width: 320px; background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 12px;">
                            <li class="p-3 border-bottom border-secondary d-flex justify-content-between align-items-center">
                                <span class="fw-bold small" style="color: var(--text-primary);">Notifications Center</span>
                                <a href="index.php?route=notifications/index" class="text-primary small text-decoration-none" style="font-size: 0.75rem;">View All</a>
                            </li>
                            <div style="max-height: 250px; overflow-y: auto;">
                                <?php if (empty($unreadNotifs)): ?>
                                    <li class="p-3 text-center text-secondary small">No new notifications.</li>
                                <?php else: ?>
                                    <?php foreach ($unreadNotifs as $notif): ?>
                                        <li class="p-3 border-bottom" style="border-color: var(--border-color) !important; background: var(--surface-soft);">
                                            <a href="<?php echo htmlspecialchars($notif->action_url ?: '#'); ?>" class="text-decoration-none d-block">
                                                <div class="d-flex justify-content-between">
                                                    <span class="fw-bold small d-block" style="color: var(--text-primary);"><?php echo htmlspecialchars($notif->title); ?></span>
                                                    <small class="text-secondary" style="font-size: 0.7rem;"><?php echo date('M d', strtotime($notif->created_at)); ?></small>
                                                </div>
                                                <span class="text-secondary small d-block mt-1" style="font-size: 0.78rem; line-height: 1.3;"><?php echo htmlspecialchars($notif->message); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </ul>
                    </div>

                    <!-- Theme Toggle Button -->
                    <button type="button" class="theme-toggle" id="theme-toggle" title="Toggle Dark / Light Mode">
                        <i class="fa-solid fa-moon" id="theme-toggle-icon"></i>
                    </button>

                </div>
            </header>

            <!-- Inner View Content -->
            <main class="main-content flex-grow-1">
                <?php echo $content; ?>
            </main>

            <!-- Sticky branded page footer -->
            <footer class="footer py-3 border-top" style="border-color: var(--border-color) !important; background-color: var(--panel-dark);">
                <div class="container-fluid d-flex justify-content-between align-items-center px-4" style="font-size: 0.8rem; color: var(--text-secondary);">
                    <span>&copy; 2026 <strong>RAPTOR</strong></span>
                    <span>Powered by <strong>RAPTOR</strong></span>
                </div>
            </footer>
        </div>
    </div>

    <?php
        // Role-aware default dashboard target for the bottom nav "Home".
        $homeRoute = Policy::isEmployee()
            ? 'attendance/index'
            : 'dashboard/index';
        $isOps = $role !== 'employer';
        $canLeads = in_array($role, $salesRoles, true);
        $salesSide = in_array($role, $salesRoles, true);
    ?>
    <!-- Bottom navigation (mobile only) -->
    <nav id="bottom-nav">
        <a href="index.php?route=<?php echo $homeRoute; ?>">
            <i class="fa-solid fa-house-chimney"></i><span>Home</span>
        </a>
        <?php if ($salesSide && $role !== 'admin'): ?>
        <a href="index.php?route=attendance/index">
            <i class="fa-solid fa-fingerprint"></i><span>Attend</span>
        </a>
        <?php endif; ?>
        <?php if ($canLeads): ?>
        <a href="index.php?route=leads/index">
            <i class="fa-solid fa-address-book"></i><span>Leads</span>
        </a>
        <?php endif; ?>
        <?php if ($isOps): ?>
        <a href="index.php?route=tasks/index">
            <i class="fa-solid fa-list-check"></i><span>Tasks</span>
        </a>
        <?php endif; ?>
        <?php if ($salesSide): ?>
        <a href="index.php?route=targets/index">
            <i class="fa-solid fa-bullseye"></i><span>Targets</span>
        </a>
        <?php endif; ?>
        <?php if (!Policy::isEmployee()): ?>
        <a href="index.php?route=reports/index">
            <i class="fa-solid fa-chart-pie"></i><span>Reports</span>
        </a>
        <?php endif; ?>
        <a href="#" id="bottom-nav-more">
            <i class="fa-solid fa-bars"></i><span>More</span>
        </a>
    </nav>

    <script>
        $(document).ready(function() {
            var isMobile = function () { return window.matchMedia('(max-width: 991.98px)').matches; };

            // Restore sidebar collapsed state from sessionStorage (desktop only)
            if (!isMobile() && sessionStorage.getItem('sidebar_collapsed') === '1') {
                $('#sidebar').addClass('collapsed');
            }

            function openDrawer() { $('#sidebar').addClass('mobile-open'); $('body').addClass('drawer-open'); }
            function closeDrawer() { $('#sidebar').removeClass('mobile-open'); $('body').removeClass('drawer-open'); }

            $('#close-sidebar-btn').on('click', closeDrawer);

            // Accordion Logic for Sidebar Category Drawers
            $('.menu-section-header').on('click', function() {
                if ($('#sidebar').hasClass('collapsed') && !isMobile()) {
                    return;
                }

                var section = $(this).closest('.menu-section');
                var isOpen = section.hasClass('expanded');

                // Collapse all other sections (Accordion style)
                $('.menu-section').not(section).removeClass('expanded').find('.menu-section-items').slideUp(200);

                if (isOpen) {
                    section.removeClass('expanded');
                    section.find('.menu-section-items').slideUp(200);
                } else {
                    section.addClass('expanded');
                    section.find('.menu-section-items').slideDown(200);
                }
            });

            // Expand active section on load
            var activeLink = $('.menu-section-items .menu-item.active');
            if (activeLink.length > 0) {
                var activeSection = activeLink.closest('.menu-section');
                activeSection.addClass('expanded');
                activeSection.find('.menu-section-items').show();
            } else {
                $('.menu-section[data-section="dashboard"]').addClass('expanded').find('.menu-section-items').show();
            }

            // Topbar hamburger: on mobile open the drawer, on desktop collapse.
            $('#toggle-btn').on('click', function() {
                if (isMobile()) {
                    $('#sidebar').hasClass('mobile-open') ? closeDrawer() : openDrawer();
                } else {
                    var sidebar = $('#sidebar');
                    sidebar.toggleClass('collapsed');
                    if (sidebar.hasClass('collapsed')) {
                        $('.menu-section-items').hide();
                        sessionStorage.setItem('sidebar_collapsed', '1');
                    } else {
                        sessionStorage.setItem('sidebar_collapsed', '0');
                        var activeSec = $('.menu-section-items .menu-item.active').closest('.menu-section');
                        if (activeSec.length > 0) {
                            activeSec.addClass('expanded');
                            activeSec.find('.menu-section-items').show();
                        } else {
                            $('.menu-section[data-section="dashboard"]').addClass('expanded').find('.menu-section-items').show();
                        }
                    }
                }
            });

            // Bottom-nav "More" opens the full menu drawer.
            $('#bottom-nav-more').on('click', function(e) { e.preventDefault(); openDrawer(); });

            // Tap overlay or a menu link closes the drawer.
            $('#sidebar-overlay').on('click', closeDrawer);
            $('#sidebar .menu-item a').on('click', function() { if (isMobile()) closeDrawer(); });

            // Reset drawer state when crossing the breakpoint.
            $(window).on('resize', function() { if (!isMobile()) closeDrawer(); });

            function syncThemeButton() {
                var theme = document.documentElement.getAttribute('data-theme') || 'light';
                $('#theme-toggle i')
                    .toggleClass('fa-moon', theme !== 'dark')
                    .toggleClass('fa-sun', theme === 'dark');
            }

            syncThemeButton();
            $('#theme-toggle').on('click', function() {
                var current = document.documentElement.getAttribute('data-theme') || 'light';
                var next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('raptor_theme', next);
                syncThemeButton();
            });

            // Initialize Date Picker
            flatpickr("#date-range", {
                mode: "range",
                dateFormat: "Y-m-d",
                defaultDate: [
                    new Date(new Date().setDate(new Date().getDate() - 30)),
                    new Date()
                ]
            });

            // Global Table Pagination: 10 rows per page
            function paginateTables() {
                $('table').each(function() {
                    var $table = $(this);
                    if ($table.data('paginated')) return;
                    
                    var $tbody = $table.find('tbody');
                    if ($tbody.length === 0) return;
                    
                    var $rows = $tbody.children('tr');
                    // Skip if table has a single row showing "No ... found" or similar empty messages
                    if ($rows.length === 1 && $rows.find('td[colspan]').length > 0) {
                        return;
                    }
                    
                    var limit = 10;
                    var totalRows = $rows.length;
                    if (totalRows <= limit) return;
                    
                    $table.data('paginated', true);
                    var totalPages = Math.ceil(totalRows / limit);
                    
                    var $nav = $('<nav class="mt-3 table-pagination-nav"><ul class="pagination pagination-sm justify-content-end mb-0"></ul></nav>');
                    var $ul = $nav.find('ul');
                    
                    function showPage(page) {
                        var start = (page - 1) * limit;
                        var end = start + limit;
                        
                        $rows.hide().slice(start, end).show();
                        
                        $ul.find('li.page-item').removeClass('active');
                        $ul.find('li.page-item[data-page="' + page + '"]').addClass('active');
                        
                        $ul.find('.prev-btn').toggleClass('disabled', page === 1);
                        $ul.find('.next-btn').toggleClass('disabled', page === totalPages);
                        
                        $table.data('current-page', page);
                    }
                    
                    var $prevLi = $('<li class="page-item prev-btn"><a class="page-link" href="#" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>');
                    $prevLi.on('click', function(e) {
                        e.preventDefault();
                        var cur = $table.data('current-page') || 1;
                        if (cur > 1) showPage(cur - 1);
                    });
                    $ul.append($prevLi);
                    
                    for (var i = 1; i <= totalPages; i++) {
                        var $li = $('<li class="page-item" data-page="' + i + '"><a class="page-link" href="#">' + i + '</a></li>');
                        $li.on('click', (function(pageNumber) {
                            return function(e) {
                                e.preventDefault();
                                showPage(pageNumber);
                            };
                        })(i));
                        $ul.append($li);
                    }
                    
                    var $nextLi = $('<li class="page-item next-btn"><a class="page-link" href="#" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>');
                    $nextLi.on('click', function(e) {
                        e.preventDefault();
                        var cur = $table.data('current-page') || 1;
                        if (cur < totalPages) showPage(cur + 1);
                    });
                    $ul.append($nextLi);
                    
                    var $parent = $table.parent();
                    if ($parent.hasClass('table-responsive')) {
                        $parent.after($nav);
                    } else {
                        $table.after($nav);
                    }
                    
                    showPage(1);
                });
            }
            
            paginateTables();
            $(document).on('shown.bs.modal shown.bs.tab ajaxComplete', function() {
                paginateTables();
            });
        });
    </script>

    <?php if (Policy::isEmployee() || $role === 'team_leader'): ?>
    <!-- Foreground location tracker (field roles). Stores only while on duty;
         server is authoritative and tells us to stop when the shift ends. -->
    <script>
    (function () {
        if (!navigator.geolocation) return;
        var CSRF = <?php echo json_encode($_SESSION['csrf_token']); ?>;
        var FLUSH_MS = 120000;      // send batched points every 2 minutes
        var buffer = [], watchId = null, timer = null, stopped = false;

        function stop() {
            stopped = true;
            if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
            if (timer) { clearInterval(timer); timer = null; }
        }

        function flush() {
            if (stopped || buffer.length === 0) return;
            var batch = buffer.splice(0, buffer.length);
            fetch('index.php?route=location/ping', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ points: batch })
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                // Server says off duty (not checked in / checked out) → stop tracking.
                if (d && d.data && d.data.on_duty === false) { stop(); }
            })
            .catch(function () { /* keep buffering; retry next flush */ });
        }

        watchId = navigator.geolocation.watchPosition(function (pos) {
            buffer.push({
                lat: +pos.coords.latitude.toFixed(7),
                lng: +pos.coords.longitude.toFixed(7),
                accuracy: Math.round(pos.coords.accuracy),
                ts: pos.timestamp,
                source: 'periodic'
            });
            if (buffer.length > 60) buffer.splice(0, buffer.length - 60);
        }, function () { /* permission denied / unavailable — do nothing */ },
        { enableHighAccuracy: true, maximumAge: 30000, timeout: 20000 });

        setTimeout(flush, 20000);   // early first flush so the map shows a fix soon
        timer = setInterval(flush, FLUSH_MS);
        window.addEventListener('pagehide', flush);
    })();
    </script>
    <?php endif; ?>
    <script>
    // Timezone detection and cookie setter
    (function() {
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (tz) {
            document.cookie = "user_timezone=" + encodeURIComponent(tz) + "; path=/; max-age=31536000; SameSite=Lax";
        }
    })();
    </script>
</body>
</html>
