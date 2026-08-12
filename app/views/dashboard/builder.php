<?php
$dashObj = $dashboard ?? null;
$dashId = $dashObj ? (int)$dashObj->id : 0;
$dashName = $dashObj ? $dashObj->name : 'New Custom Dashboard';
$dashDesc = $dashObj ? $dashObj->description : '';
$dashVis = $dashObj ? $dashObj->visibility_type : 'private';
$dashRoles = $dashObj ? $dashObj->roles : [];
$dashIsTemplate = $dashObj ? (int)$dashObj->is_template : 0;
$dashIsDefault = $dashObj ? (int)$dashObj->is_default : 0;
$initialWidgets = $dashObj ? json_encode($dashObj->widgets) : '[]';
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>

<style>
/* Dashboard Builder Layout & Canvas Styling */
.builder-layout {
    display: flex;
    gap: 1rem;
    min-height: calc(100vh - 160px);
    transition: all 0.3s ease;
}
.builder-sidebar {
    width: 260px;
    flex-shrink: 0;
    background: var(--surface-soft);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem;
    transition: all 0.3s ease;
}
.builder-canvas-wrapper {
    flex-grow: 1;
    background: var(--panel-dark);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.25rem;
    min-height: 600px;
    position: relative;
    transition: all 0.3s ease;
}
.builder-config-drawer {
    width: 330px;
    flex-shrink: 0;
    background: var(--surface-soft);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem;
    display: none; /* Opened on widget selection */
    transition: all 0.3s ease;
}

/* Palette Draggable Cards */
.widget-palette-item {
    background: var(--panel-dark);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.65rem 0.85rem;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    color: var(--text-primary);
    font-size: 0.85rem;
    user-select: none;
}
.widget-palette-item:hover {
    border-color: var(--primary);
    background: var(--primary-soft);
    transform: translateX(4px);
}

/* Canvas Grid & Widgets - ZERO OVERLAPPING */
.canvas-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    grid-auto-rows: minmax(220px, auto);
    gap: 1.25rem;
    min-height: 550px;
    border: 2px dashed var(--border-color);
    border-radius: 10px;
    padding: 1.25rem;
    transition: all 0.3s ease;
}
.canvas-grid.preview-mode {
    border: none !important;
    padding: 0 !important;
}

/* THEME PRESET STYLES */
.canvas-grid.theme-cyberpunk {
    background: #0d0221 !important;
    border-color: #ff007f !important;
}
.canvas-grid.theme-sapphire {
    background: #0f172a !important;
    border-color: #38bdf8 !important;
}
.canvas-grid.theme-emerald {
    background: #022c22 !important;
    border-color: #34d399 !important;
}

.canvas-widget {
    background: var(--surface-soft);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 0.85rem 1.1rem;
    position: relative;
    box-shadow: var(--shadow-soft);
    display: flex;
    flex-direction: column;
    min-height: 220px;
    height: 100%;
    overflow: hidden;
    transition: box-shadow 0.2s ease, border-color 0.2s ease, opacity 0.2s ease;
}
.widget-body {
    flex: 1 1 auto;
    min-height: 0;
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}
.widget-body canvas {
    max-height: 100% !important;
    max-width: 100% !important;
    height: 100% !important;
    width: 100% !important;
}
.canvas-widget.selected {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 2px var(--primary-glow) !important;
}
.canvas-widget.drag-over-target {
    border: 2px dashed var(--primary) !important;
    background: rgba(29, 78, 216, 0.08) !important;
}

/* SLEEK, COMPACT ICON-ONLY WIDGET ACTION TOOLBAR (WORKS IN EDIT AND PREVIEW MODE) */
.canvas-widget-toolbar {
    position: absolute;
    top: 6px;
    right: 6px;
    display: flex;
    gap: 4px;
    z-index: 20;
    opacity: 0;
    transition: opacity 0.2s ease;
    background: var(--surface-soft);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 3px 5px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.canvas-widget:hover .canvas-widget-toolbar,
.canvas-widget.selected .canvas-widget-toolbar {
    opacity: 1;
}
.canvas-widget-toolbar .btn-icon {
    width: 26px !important;
    height: 26px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 0.72rem !important;
    border-radius: 6px !important;
    transition: all 0.15s ease;
}
.canvas-widget-toolbar .btn-icon:hover {
    transform: scale(1.1);
}

/* Bottom-Right Corner Mouse Drag-to-Resize Handle */
.widget-resize-handle {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 22px;
    height: 22px;
    cursor: nwse-resize;
    z-index: 25;
    opacity: 0.5;
    transition: opacity 0.2s ease, transform 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    color: var(--text-secondary);
}
.canvas-widget:hover .widget-resize-handle {
    opacity: 1;
}
.widget-resize-handle:hover {
    color: var(--primary);
    transform: scale(1.2);
}

/* Live Resize Indicator Badge */
.resize-indicator-badge {
    position: absolute;
    bottom: 8px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(29, 78, 216, 0.95);
    color: #fff;
    font-size: 0.72rem;
    font-weight: bold;
    padding: 2px 8px;
    border-radius: 12px;
    z-index: 30;
    pointer-events: none;
    display: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

/* Liquid Fill Gauge SVG Wave Animations */
.liquid-wave-layer1 {
    animation: liquidWaveLoop1 3.5s linear infinite;
}
.liquid-wave-layer2 {
    animation: liquidWaveLoop2 5s linear infinite;
}
@keyframes liquidWaveLoop1 {
    0% { transform: translateX(0); }
    100% { transform: translateX(-160px); }
}
@keyframes liquidWaveLoop2 {
    0% { transform: translateX(-160px); }
    100% { transform: translateX(0); }
}
@media (prefers-reduced-motion: reduce) {
    .liquid-wave-layer1, .liquid-wave-layer2 { animation: none !important; }
}

/* Skeleton Loading */
.skeleton-loader {
    animation: pulse 1.5s infinite ease-in-out;
    background: var(--border-color);
    border-radius: 6px;
    height: 100%;
    width: 100%;
    min-height: 100px;
}
@keyframes pulse {
    0% { opacity: 0.4; }
    50% { opacity: 0.8; }
    100% { opacity: 0.4; }
}

/* Live Interactive Filter Bar */
.filter-preset-chip {
    cursor: pointer;
    font-size: 0.75rem;
    padding: 0.25rem 0.65rem;
    border-radius: 20px;
    border: 1px solid var(--border-color);
    background: var(--surface-soft);
    color: var(--text-secondary);
    transition: all 0.2s ease;
}
.filter-preset-chip.active, .filter-preset-chip:hover {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}

/* Live Auto-Refresh Pulse Indicator */
.refresh-pulse {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10B981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: pulse-green 2s infinite;
}
@keyframes pulse-green {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Activity Ticker Stream */
.activity-ticker-item {
    font-size: 0.78rem;
    padding: 0.4rem 0.6rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.activity-ticker-item:last-child { border-bottom: none; }

/* Preview Banner */
#preview-banner {
    display: none;
    background: linear-gradient(90deg, #1D4ED8, #3B82F6);
    color: #fff;
    padding: 0.6rem 1.2rem;
    border-radius: 10px;
    margin-bottom: 1rem;
    align-items: center;
    justify-content: space-between;
}
</style>

<!-- Top Control Header -->
<div class="pulse-card mb-3" id="builder-top-bar">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <div class="d-flex align-items-center gap-2">
            <a href="index.php?route=dashboard/templates" class="btn btn-outline-secondary btn-sm me-2" title="Back to Templates">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <input type="text" id="builder-dash-name" class="form-control form-control-lg bg-transparent text-white border-0 fw-bold px-0 shadow-none" 
                value="<?php echo htmlspecialchars($dashName); ?>" style="font-size: 1.3rem; min-width: 250px;" placeholder="Dashboard Name...">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="dash-status-badge">
                <?php echo $dashId > 0 ? 'Saved' : 'Draft'; ?>
            </span>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-outline-info btn-sm" id="btn-preview-toggle">
                <i class="fa-solid fa-eye me-1"></i> Preview Dashboard
            </button>
            <button type="button" class="btn btn-outline-warning btn-sm" id="btn-save-template">
                <i class="fa-solid fa-layer-group me-1"></i> Save as Template
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="btn-open-save-modal" style="background: var(--primary); border: none;">
                <i class="fa-solid fa-floppy-disk me-1"></i> Save Dashboard
            </button>
        </div>
    </div>

    <!-- INTERACTIVE DASHBOARD FILTER BAR, THEMES & LIVE AUTO-REFRESH -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top border-secondary" style="border-opacity: 0.3;">
        <div class="d-flex align-items-center gap-2">
            <span class="text-secondary small fw-bold"><i class="fa-solid fa-filter text-primary me-1"></i> Date Filter:</span>
            <span class="filter-preset-chip active" data-preset="all">All Time</span>
            <span class="filter-preset-chip" data-preset="today">Today</span>
            <span class="filter-preset-chip" data-preset="7days">Last 7 Days</span>
            <span class="filter-preset-chip" data-preset="30days">Last 30 Days</span>
            <span class="filter-preset-chip" data-preset="quarter">This Quarter</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center gap-1 text-secondary small">
                <i class="fa-solid fa-palette text-warning me-1"></i> Theme:
                <select id="dash-canvas-theme" class="form-select form-select-sm bg-dark text-white border-secondary py-0 px-2" style="font-size: 0.75rem; width: 130px;">
                    <option value="default">Glass Dark</option>
                    <option value="cyberpunk">Cyberpunk Glow</option>
                    <option value="sapphire">Midnight Sapphire</option>
                    <option value="emerald">Emerald Executive</option>
                </select>
            </div>
            <div class="d-flex align-items-center gap-1 text-secondary small">
                <span class="refresh-pulse"></span>
                <span>Auto-Refresh:</span>
                <select id="dash-auto-refresh" class="form-select form-select-sm bg-dark text-white border-secondary py-0 px-2" style="font-size: 0.75rem; width: 110px;">
                    <option value="0">Off (Manual)</option>
                    <option value="15">15 Seconds</option>
                    <option value="30">30 Seconds</option>
                    <option value="60">1 Minute</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Preview Banner -->
<div id="preview-banner">
    <div class="d-flex align-items-center gap-2">
        <i class="fa-solid fa-eye fs-5"></i>
        <strong>Dashboard Live Preview Mode</strong> — Drag bottom-right corner to resize width/height live.
    </div>
    <button type="button" class="btn btn-light btn-sm fw-bold text-primary" id="btn-exit-preview">
        <i class="fa-solid fa-xmark me-1"></i> Exit Preview
    </button>
</div>

<!-- Main Builder Layout -->
<div class="builder-layout" id="builder-main-layout">
    <!-- Left Panel: Widget Library -->
    <div class="builder-sidebar" id="builder-left-sidebar">
        <h6 class="text-white fw-bold mb-3"><i class="fa-solid fa-cubes me-2 text-primary"></i>Widget Library</h6>
        <p class="text-secondary small mb-3">Click any widget below to add it to your 12-column grid.</p>

        <div class="widget-palette-item" data-type="kpi">
            <i class="fa-solid fa-chart-line text-primary fs-5"></i>
            <div><strong>KPI Card</strong><br><small class="text-secondary">Single metric & trend</small></div>
        </div>
        <div class="widget-palette-item" data-type="liquid">
            <i class="fa-solid fa-water text-warning fs-5"></i>
            <div><strong>Liquid Fill Gauge</strong><br><small class="text-secondary">Wavy score & threshold bands</small></div>
        </div>
        <div class="widget-palette-item" data-type="progress">
            <i class="fa-solid fa-bars-progress text-success fs-5"></i>
            <div><strong>Goal Progress Bar</strong><br><small class="text-secondary">Attainment & target progress</small></div>
        </div>
        <div class="widget-palette-item" data-type="line">
            <i class="fa-solid fa-chart-area text-info fs-5"></i>
            <div><strong>Line / Area Chart</strong><br><small class="text-secondary">Time-series trend</small></div>
        </div>
        <div class="widget-palette-item" data-type="bar">
            <i class="fa-solid fa-chart-column text-success fs-5"></i>
            <div><strong>Bar Chart</strong><br><small class="text-secondary">Category comparison</small></div>
        </div>
        <div class="widget-palette-item" data-type="pie">
            <i class="fa-solid fa-chart-pie text-warning fs-5"></i>
            <div><strong>Pie / Donut</strong><br><small class="text-secondary">Distribution split</small></div>
        </div>
        <div class="widget-palette-item" data-type="radar">
            <i class="fa-solid fa-compass text-info fs-5"></i>
            <div><strong>Radar Performance</strong><br><small class="text-secondary">Multi-dimensional compass</small></div>
        </div>
        <div class="widget-palette-item" data-type="polar">
            <i class="fa-solid fa-sun text-warning fs-5"></i>
            <div><strong>Polar Area</strong><br><small class="text-secondary">Radial slice comparison</small></div>
        </div>
        <div class="widget-palette-item" data-type="table">
            <i class="fa-solid fa-table text-danger fs-5"></i>
            <div><strong>Data Table</strong><br><small class="text-secondary">Tabular detail grid</small></div>
        </div>
        <div class="widget-palette-item" data-type="activity">
            <i class="fa-solid fa-bolt text-danger fs-5"></i>
            <div><strong>Activity Stream</strong><br><small class="text-secondary">Live updates & ticker</small></div>
        </div>
        <div class="widget-palette-item" data-type="funnel">
            <i class="fa-solid fa-filter text-primary fs-5"></i>
            <div><strong>Funnel Chart</strong><br><small class="text-secondary">Stage progression</small></div>
        </div>
        <div class="widget-palette-item" data-type="gauge">
            <i class="fa-solid fa-gauge-high text-success fs-5"></i>
            <div><strong>Gauge / Target</strong><br><small class="text-secondary">Quota & attainment</small></div>
        </div>
        <div class="widget-palette-item" data-type="text">
            <i class="fa-solid fa-align-left text-secondary fs-5"></i>
            <div><strong>Text Block</strong><br><small class="text-secondary">Heading or note</small></div>
        </div>
    </div>

    <!-- Center Canvas Area -->
    <div class="builder-canvas-wrapper" id="builder-canvas-container">
        <div class="d-flex justify-content-between align-items-center mb-3" id="canvas-header">
            <span class="text-secondary small"><i class="fa-solid fa-grip me-1"></i> 12-Column Drag & Drop Canvas</span>
            <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0" id="btn-clear-canvas">
                <i class="fa-solid fa-trash me-1"></i> Clear Canvas
            </button>
        </div>

        <div class="canvas-grid" id="canvas-grid">
            <!-- Widgets rendered dynamically via JS -->
        </div>
    </div>

    <!-- Right Config Panel -->
    <div class="builder-config-drawer" id="config-drawer">
        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary">
            <h6 class="text-white fw-bold mb-0"><i class="fa-solid fa-sliders me-2 text-primary"></i>Widget Config Panel</h6>
            <button type="button" class="btn-close btn-close-white btn-sm" id="btn-close-config"></button>
        </div>

        <form id="widget-config-form">
            <div class="mb-3">
                <label class="form-label text-secondary small fw-bold">Widget Title</label>
                <input type="text" id="cfg-title" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Widget Title...">
            </div>

            <div class="mb-3">
                <label class="form-label text-secondary small fw-bold">Data Source Module</label>
                <select id="cfg-data-source" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <?php foreach ($data_sources as $dsKey => $dsLabel): ?>
                        <option value="<?php echo htmlspecialchars($dsKey); ?>"><?php echo htmlspecialchars($dsLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label text-secondary small fw-bold">Display Chart Type</label>
                <select id="cfg-widget-type" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="kpi">KPI Card (Metric & Trend)</option>
                    <option value="liquid">Liquid Fill Gauge (Score & Bands)</option>
                    <option value="progress">Goal Progress Bar Card</option>
                    <option value="line">Line / Area Chart</option>
                    <option value="bar">Bar Chart</option>
                    <option value="pie">Pie / Donut Chart</option>
                    <option value="radar">Radar Performance Compass</option>
                    <option value="polar">Polar Area Slice Chart</option>
                    <option value="table">Data Detail Table</option>
                    <option value="activity">Live Event Activity Ticker</option>
                    <option value="funnel">Funnel Stage Chart</option>
                    <option value="gauge">Target Gauge Meter</option>
                    <option value="text">Formatted Text Block</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label text-secondary small fw-bold">Target Metric / Field</label>
                <select id="cfg-metric" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <!-- Populated dynamically based on data source -->
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label text-secondary small fw-bold">Aggregation Method</label>
                <select id="cfg-agg" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="SUM">Sum Total</option>
                    <option value="AVG">Average</option>
                    <option value="COUNT">Count Records</option>
                    <option value="MIN">Minimum</option>
                    <option value="MAX">Maximum</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label text-secondary small fw-bold">Group By Dimension</label>
                <select id="cfg-group-by" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <!-- Populated dynamically based on data source -->
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label text-secondary small fw-bold">Width (Grid Columns)</label>
                <select id="cfg-width" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="3">3 Columns (1/4 Width)</option>
                    <option value="4">4 Columns (1/3 Width)</option>
                    <option value="6">6 Columns (1/2 Width)</option>
                    <option value="8">8 Columns (2/3 Width)</option>
                    <option value="12">12 Columns (Full Width)</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label text-secondary small fw-bold">Card Height</label>
                <select id="cfg-height" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="220">Compact (220px)</option>
                    <option value="280">Medium (280px)</option>
                    <option value="360">Large (360px)</option>
                    <option value="auto">Auto Fit Content</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label text-secondary small fw-bold">Color Theme Accent</label>
                <select id="cfg-color" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="blue">Brand Blue (#1D4ED8)</option>
                    <option value="emerald">Emerald Green (#059669)</option>
                    <option value="amber">Amber Gold (#D97706)</option>
                    <option value="rose">Rose Red (#E11D48)</option>
                    <option value="indigo">Purple Indigo (#7C3AED)</option>
                    <option value="cyan">Cyan Wave (#0891B2)</option>
                </select>
            </div>

            <button type="button" class="btn btn-primary btn-sm w-100 mt-2 fw-bold" id="btn-apply-widget-config" style="background: var(--primary); border: none;">
                <i class="fa-solid fa-check me-1"></i> Apply Widget Config
            </button>
        </form>
    </div>
</div>

<!-- Full Screen Widget Detail Zoom Modal -->
<div class="modal fade" id="widgetZoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content text-white border-0 shadow-lg" style="background: var(--panel-dark); border-radius: 16px; border: 1px solid var(--border-color) !important;">
            <div class="modal-header border-secondary pb-3">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="zoom-widget-title">
                    <i class="fa-solid fa-expand text-primary"></i> Widget Detail Inspection
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div id="zoom-widget-body" style="min-height: 420px; width: 100%;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Save Dashboard Modal -->
<div class="modal fade" id="saveDashboardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-white border-0 shadow-lg" style="background: var(--panel-dark); border-radius: 16px; border: 1px solid var(--border-color) !important;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-floppy-disk me-2 text-primary"></i>Save Dashboard</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label text-secondary small fw-bold">Dashboard Name *</label>
                    <input type="text" id="modal-dash-name" class="form-control bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($dashName); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small fw-bold">Description</label>
                    <textarea id="modal-dash-desc" rows="2" class="form-control bg-dark text-white border-secondary" placeholder="Optional description..."><?php echo htmlspecialchars($dashDesc); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small fw-bold">Visibility Scope</label>
                    <select id="modal-dash-vis" class="form-select bg-dark text-white border-secondary">
                        <option value="private" <?php echo $dashVis === 'private' ? 'selected' : ''; ?>>Private (Only me)</option>
                        <option value="role" <?php echo $dashVis === 'role' ? 'selected' : ''; ?>>Role-based Shared</option>
                        <option value="everyone" <?php echo $dashVis === 'everyone' ? 'selected' : ''; ?>>Everyone</option>
                    </select>
                </div>
                <div class="mb-3" id="modal-role-select-box" style="<?php echo $dashVis === 'role' ? '' : 'display:none;'; ?>">
                    <label class="form-label text-secondary small fw-bold">Select Roles to Share With</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($roles as $r): ?>
                            <label class="form-check-label px-2 py-1 rounded bg-dark border border-secondary small">
                                <input type="checkbox" name="modal_roles[]" value="<?php echo $r; ?>" <?php echo in_array($r, $dashRoles) ? 'checked' : ''; ?>>
                                <?php echo strtoupper($r); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="modal-dash-default" <?php echo $dashIsDefault ? 'checked' : ''; ?>>
                    <label class="form-check-label text-white small" for="modal-dash-default">Set as my default landing dashboard</label>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="modal-dash-template" <?php echo $dashIsTemplate ? 'checked' : ''; ?>>
                    <label class="form-check-label text-white small" for="modal-dash-template">Publish as reusable template</label>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm px-4" id="btn-confirm-save-dash" style="background: var(--primary); border: none;">Save Dashboard</button>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    var dashboardId = <?php echo $dashId; ?>;
    var csrfToken = "<?php echo $csrfToken; ?>";
    var widgets = <?php echo $initialWidgets; ?> || [];
    var selectedWidgetIdx = null;
    var isPreviewMode = false;
    var chartInstances = {};
    var draggedWidgetIdx = null;
    var activeDateFilter = 'all';
    var autoRefreshInterval = null;

    // DYNAMIC MODULE DEPENDENT METRICS & DIMENSIONS MAP
    var MODULE_CONFIG_MAP = {
        leads: {
            metrics: [
                { id: 'count', label: 'Lead Count' },
                { id: 'value', label: 'Lead Value ($)' },
                { id: 'probability', label: 'Conversion Probability (%)' }
            ],
            dimensions: [
                { id: 'status', label: 'Lead Status (New / Contacted / Qualified)' },
                { id: 'source', label: 'Lead Source (Website / Referral / Ads)' },
                { id: 'quality', label: 'Lead Quality (Hot / Warm / Cold)' }
            ]
        },
        campaigns: {
            metrics: [
                { id: 'count', label: 'Campaign Count' },
                { id: 'budget', label: 'Total Budget ($)' },
                { id: 'spend', label: 'Total Spend ($)' }
            ],
            dimensions: [
                { id: 'channel', label: 'Channel (PPC / Email / Social)' },
                { id: 'status', label: 'Campaign Status (Active / Completed)' },
                { id: 'campaign_type', label: 'Campaign Type' }
            ]
        },
        invoices: {
            metrics: [
                { id: 'count', label: 'Invoice Count' },
                { id: 'amount', label: 'Invoice Amount ($)' }
            ],
            dimensions: [
                { id: 'status', label: 'Payment Status (Paid / Unpaid / Overdue)' },
                { id: 'month', label: 'Due Date Month' }
            ]
        },
        attendance: {
            metrics: [
                { id: 'worked_minutes', label: 'Worked Minutes' },
                { id: 'count', label: 'Days Count' },
                { id: 'late_count', label: 'Late Logins Count' }
            ],
            dimensions: [
                { id: 'status', label: 'Attendance Status (Present / WFH / Leave)' },
                { id: 'month', label: 'Work Date Month' }
            ]
        },
        targets: {
            metrics: [
                { id: 'target_completion', label: 'Target Completion (%)' },
                { id: 'target_val', label: 'Planned Target Value ($)' },
                { id: 'ach_val', label: 'Achieved Value ($)' }
            ],
            dimensions: [
                { id: 'category', label: 'Target Category' },
                { id: 'metric', label: 'Target Metric Type' }
            ]
        },
        tasks: {
            metrics: [
                { id: 'count', label: 'Task Count' },
                { id: 'progress_percent', label: 'Average Progress (%)' }
            ],
            dimensions: [
                { id: 'status', label: 'Task Status (Pending / In Progress / Completed)' },
                { id: 'priority', label: 'Priority (High / Medium / Low)' }
            ]
        },
        customers: {
            metrics: [
                { id: 'count', label: 'Customer Count' },
                { id: 'contract_value', label: 'Contract Value ($)' }
            ],
            dimensions: [
                { id: 'status', label: 'Account Status (Active / Renewal / Churned)' },
                { id: 'month', label: 'Onboarding Month' }
            ]
        },
        website_analytics: {
            metrics: [
                { id: 'pageviews', label: 'Pageviews' },
                { id: 'sessions', label: 'Sessions' },
                { id: 'users', label: 'Users' },
                { id: 'bounce_rate', label: 'Bounce Rate (%)' }
            ],
            dimensions: [
                { id: 'date', label: 'Snapshot Date' },
                { id: 'channel', label: 'Traffic Source Channel' }
            ]
        },
        text: {
            metrics: [
                { id: 'text_content', label: 'Text Note Content' }
            ],
            dimensions: [
                { id: 'none', label: 'None' }
            ]
        }
    };

    // Canvas Theme Selector Handler
    $('#dash-canvas-theme').on('change', function() {
        var theme = this.value;
        $('#canvas-grid').removeClass('theme-cyberpunk theme-sapphire theme-emerald');
        if (theme !== 'default') {
            $('#canvas-grid').addClass('theme-' + theme);
        }
    });

    // Date Filter Presets Handler
    $('.filter-preset-chip').on('click', function() {
        $('.filter-preset-chip').removeClass('active');
        $(this).addClass('active');
        activeDateFilter = $(this).data('preset');
        renderCanvas();
    });

    // Auto Refresh Interval Handler
    $('#dash-auto-refresh').on('change', function() {
        if (autoRefreshInterval) clearInterval(autoRefreshInterval);
        var sec = parseInt(this.value, 10);
        if (sec > 0) {
            autoRefreshInterval = setInterval(function() {
                renderCanvas();
            }, sec * 1000);
        }
    });

    // Update Metric and Dimension dropdowns when Data Source Module changes
    $('#cfg-data-source').on('change', function() {
        var dsKey = this.value || 'leads';
        populateDependentDropdowns(dsKey);
    });

    function populateDependentDropdowns(dsKey, selectedMetric, selectedGroup) {
        var conf = MODULE_CONFIG_MAP[dsKey] || MODULE_CONFIG_MAP.leads;

        var $m = $('#cfg-metric').empty();
        conf.metrics.forEach(function(opt) {
            $m.append('<option value="' + opt.id + '">' + escapeHtml(opt.label) + '</option>');
        });
        if (selectedMetric) $m.val(selectedMetric);

        var $g = $('#cfg-group-by').empty();
        conf.dimensions.forEach(function(opt) {
            $g.append('<option value="' + opt.id + '">' + escapeHtml(opt.label) + '</option>');
        });
        if (selectedGroup) $g.val(selectedGroup);
    }

    // Render Initial Widgets
    renderCanvas();

    // Add Widget from Library with Tailored Defaults
    $('.widget-palette-item').on('click', function() {
        var type = $(this).data('type');
        var defaultDs = (type === 'gauge' || type === 'progress' || type === 'liquid') ? 'targets' : ((type === 'text') ? 'text' : ((type === 'line' || type === 'radar') ? 'website_analytics' : 'leads'));
        var defaultMetric = (type === 'gauge' || type === 'progress' || type === 'liquid') ? 'target_completion' : ((type === 'line') ? 'pageviews' : 'count');
        var defaultGroup = (type === 'gauge' || type === 'progress' || type === 'liquid') ? 'category' : ((type === 'line') ? 'date' : 'status');

        var newWidget = {
            title: defaultTitleForType(type),
            widget_type: type,
            data_source: defaultDs,
            width: (type === 'kpi' || type === 'progress' || type === 'liquid') ? 4 : ((type === 'table' || type === 'activity') ? 12 : 6),
            height: (type === 'liquid') ? 360 : 220,
            pos_x: 0,
            pos_y: 0,
            config: {
                metric: defaultMetric,
                aggregation: 'COUNT',
                group_by: defaultGroup,
                color: (type === 'gauge' || type === 'progress' || type === 'liquid') ? 'amber' : ((type === 'line' || type === 'radar') ? 'indigo' : 'blue')
            }
        };
        widgets.push(newWidget);
        renderCanvas();
        selectWidget(widgets.length - 1);
    });

    function defaultTitleForType(type) {
        var names = {
            kpi: 'KPI Metric Card',
            liquid: 'Liquid Fill Gauge (Score)',
            progress: 'Goal Progress Bar',
            line: 'Trend Analysis (Time-Series)',
            bar: 'Category Breakdown',
            pie: 'Distribution Split',
            radar: 'Radar Performance Compass',
            polar: 'Polar Area Slice',
            table: 'Data Detail Table',
            activity: 'Live Activity Stream Ticker',
            funnel: 'Conversion Funnel Stage',
            gauge: 'Target Completion Meter',
            text: 'Notes & Description'
        };
        return names[type] || 'Widget';
    }

    function renderCanvas() {
        var $grid = $('#canvas-grid').empty();
        if (widgets.length === 0) {
            $grid.append('<div class="col-12 text-center text-secondary py-5"><i class="fa-solid fa-hand-pointer fs-3 mb-2"></i><br>Canvas is empty. Click a widget type on the left library to add it to your dashboard.</div>');
            return;
        }

        widgets.forEach(function(w, idx) {
            var colSpan = 'span ' + (w.width || 6);
            var cardH = parseInt(w.height, 10) || 220;
            var $wBox = $('<div class="canvas-widget" style="grid-column: ' + colSpan + '; height: ' + cardH + 'px !important; max-height: ' + cardH + 'px !important; min-height: ' + cardH + 'px !important; overflow: hidden;"></div>');
            if (selectedWidgetIdx === idx && !isPreviewMode) $wBox.addClass('selected');

            // Sleek Toolbar — Works in BOTH Edit Mode and Preview Mode!
            var toolbar = '<div class="canvas-widget-toolbar">' +
                '<button type="button" class="btn btn-icon btn-outline-warning text-warning btn-cycle-width-w" data-idx="' + idx + '" title="Click to Cycle Width (3 -> 4 -> 6 -> 8 -> 12 Cols)"><i class="fa-solid fa-arrows-left-right"></i></button>' +
                '<button type="button" class="btn btn-icon btn-outline-success text-success btn-cycle-height-w" data-idx="' + idx + '" title="Click to Cycle Height (220 -> 280 -> 360px)"><i class="fa-solid fa-arrows-up-down"></i></button>' +
                '<button type="button" class="btn btn-icon btn-outline-secondary text-white btn-cycle-type-w" data-idx="' + idx + '" title="Click to Cycle Chart Type"><i class="fa-solid fa-repeat"></i></button>' +
                '<button type="button" class="btn btn-icon btn-outline-info text-info btn-zoom-w" data-idx="' + idx + '" title="Full-Screen Zoom"><i class="fa-solid fa-expand"></i></button>' +
                '<button type="button" class="btn btn-icon btn-outline-secondary text-white btn-export-w" data-idx="' + idx + '" title="Export CSV"><i class="fa-solid fa-file-csv"></i></button>';

            if (!isPreviewMode) {
                toolbar += '<span class="text-secondary mx-1">|</span>' +
                    '<button type="button" class="btn btn-icon btn-outline-primary text-primary btn-edit-w" data-idx="' + idx + '" title="Configure Widget"><i class="fa-solid fa-gear"></i></button>' +
                    '<button type="button" class="btn btn-icon btn-outline-info text-info btn-dup-w" data-idx="' + idx + '" title="Duplicate Widget"><i class="fa-solid fa-copy"></i></button>' +
                    '<button type="button" class="btn btn-icon btn-outline-danger text-danger btn-del-w" data-idx="' + idx + '" title="Delete Widget"><i class="fa-solid fa-trash"></i></button>';

                // Make Widget Draggable
                $wBox.attr('draggable', 'true');
                $wBox.attr('data-idx', idx);

                $wBox.on('dragstart', function(e) {
                    draggedWidgetIdx = idx;
                    e.originalEvent.dataTransfer.setData('text/plain', idx);
                    $(this).css('opacity', '0.4');
                });

                $wBox.on('dragend', function() {
                    $(this).css('opacity', '1');
                    $('.canvas-widget').removeClass('drag-over-target');
                });

                $wBox.on('dragover', function(e) {
                    e.preventDefault();
                    $(this).addClass('drag-over-target');
                });

                $wBox.on('dragleave', function() {
                    $(this).removeClass('drag-over-target');
                });

                $wBox.on('drop', function(e) {
                    e.preventDefault();
                    $(this).removeClass('drag-over-target');
                    var targetIdx = idx;
                    if (draggedWidgetIdx !== null && draggedWidgetIdx !== targetIdx) {
                        var movedItem = widgets.splice(draggedWidgetIdx, 1)[0];
                        widgets.splice(targetIdx, 0, movedItem);
                        draggedWidgetIdx = null;
                        renderCanvas();
                    }
                });
            }

            toolbar += '</div>';
            $wBox.append(toolbar);

            // Bottom-Right Corner Mouse Drag-to-Resize Handle
            var resizeHandle = '<div class="widget-resize-handle" data-idx="' + idx + '" title="Drag corner to resize width & height"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></div>';
            $wBox.append(resizeHandle);

            var dsLabel = escapeHtml(w.data_source || 'leads').toUpperCase();
            var gripIcon = isPreviewMode ? '' : '<i class="fa-solid fa-grip-vertical text-secondary me-2 drag-handle" title="Drag to reorder" style="cursor: move;"></i>';
            var header = '<div class="d-flex justify-content-between align-items-center mb-2">' +
                '<h6 class="text-white fw-bold mb-0 text-truncate d-flex align-items-center" style="max-width: 65%;" title="' + escapeHtml(w.title) + '">' +
                gripIcon + escapeHtml(w.title) + '</h6>' +
                '<span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;">' + dsLabel + '</span>' +
                '</div>';

            var body = '<div class="widget-body flex-grow-1" id="widget-body-' + idx + '"><div class="skeleton-loader"></div></div>';

            $wBox.append(header).append(body);
            $wBox.on('click', function(e) {
                if (isPreviewMode || $(e.target).closest('.canvas-widget-toolbar, .widget-resize-handle').length) return;
                selectWidget(idx);
            });

            $grid.append($wBox);

            // Fetch live widget visualization data
            fetchWidgetData(w, idx);
        });
    }

    // Bottom-Right Corner Mouse Drag-to-Resize Event Handler
    $(document).on('mousedown', '.widget-resize-handle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var idx = $(this).data('idx');
        var w = widgets[idx];
        if (!w) return;

        var $wBox = $('.canvas-widget').eq(idx);
        var startX = e.clientX;
        var startY = e.clientY;
        var startW = w.width || 6;
        var startH = parseInt(w.height, 10) || 220;
        var containerWidth = $('#canvas-grid').width() || 1000;
        var colWidthPx = containerWidth / 12;

        var $badge = $('<div class="resize-indicator-badge"></div>').appendTo($wBox).show();

        $(document).on('mousemove.widgetResize', function(ev) {
            var dx = ev.clientX - startX;
            var dy = ev.clientY - startY;

            var colsDelta = Math.round(dx / colWidthPx);
            var newW = Math.max(3, Math.min(12, startW + colsDelta));
            var newH = Math.max(180, Math.min(600, startH + dy));

            $wBox.css({
                'grid-column': 'span ' + newW,
                'min-height': newH + 'px'
            });

            $badge.text(newW + ' Columns | Height: ' + Math.round(newH) + 'px');
        });

        $(document).on('mouseup.widgetResize', function(ev) {
            $(document).off('.widgetResize');
            var dx = ev.clientX - startX;
            var dy = ev.clientY - startY;

            var colsDelta = Math.round(dx / colWidthPx);
            var finalW = Math.max(3, Math.min(12, startW + colsDelta));
            var finalH = Math.max(180, Math.min(600, startH + dy));

            w.width = finalW;
            w.height = finalH;
            $badge.remove();
            renderCanvas();
        });
    });

    function selectWidget(idx) {
        selectedWidgetIdx = idx;
        $('.canvas-widget').removeClass('selected');
        $('.canvas-widget').eq(idx).addClass('selected');

        var w = widgets[idx];
        if (!w) return;

        var dsKey = w.data_source || 'leads';
        $('#cfg-title').val(w.title);
        $('#cfg-data-source').val(dsKey);
        $('#cfg-widget-type').val(w.widget_type || 'kpi');

        // Dynamically populate metric and dimension dropdowns for this module
        populateDependentDropdowns(dsKey, w.config.metric, w.config.group_by);

        $('#cfg-agg').val(w.config.aggregation || 'COUNT');
        $('#cfg-width').val(w.width || 6);
        $('#cfg-height').val(w.height || 220);
        $('#cfg-color').val(w.config.color || 'blue');

        $('#config-drawer').fadeIn(150);
    }

    $('#btn-apply-widget-config').on('click', function() {
        if (selectedWidgetIdx === null || !widgets[selectedWidgetIdx]) return;
        var w = widgets[selectedWidgetIdx];

        w.title = $('#cfg-title').val();
        w.data_source = $('#cfg-data-source').val();
        w.widget_type = $('#cfg-widget-type').val();
        w.width = parseInt($('#cfg-width').val(), 10);
        var hVal = $('#cfg-height').val();
        w.height = (hVal === 'auto') ? 'auto' : parseInt(hVal, 10);
        w.config.metric = $('#cfg-metric').val();
        w.config.aggregation = $('#cfg-agg').val();
        w.config.group_by = $('#cfg-group-by').val();
        w.config.color = $('#cfg-color').val();

        renderCanvas();
    });

    $(document).on('click', '.btn-edit-w', function(e) { e.stopPropagation(); selectWidget($(this).data('idx')); });
    
    // 1-Click Width Cycling Handler (Works in both Edit and Preview modes)
    $(document).on('click', '.btn-cycle-width-w', function(e) {
        e.stopPropagation();
        var idx = $(this).data('idx');
        var w = widgets[idx];
        if (!w) return;
        var widths = [3, 4, 6, 8, 12];
        var currentW = w.width || 6;
        var currentIdx = widths.indexOf(currentW);
        var nextW = widths[(currentIdx + 1) % widths.length];
        w.width = nextW;
        renderCanvas();
    });

    // 1-Click Height Cycling Handler (Works in both Edit and Preview modes)
    $(document).on('click', '.btn-cycle-height-w', function(e) {
        e.stopPropagation();
        var idx = $(this).data('idx');
        var w = widgets[idx];
        if (!w) return;
        var heights = [220, 280, 360];
        var currentH = parseInt(w.height, 10) || 220;
        var currentIdx = heights.indexOf(currentH);
        var nextH = heights[(currentIdx + 1) % heights.length];
        w.height = nextH;
        renderCanvas();
    });

    // Cycle Widget Type Handler
    $(document).on('click', '.btn-cycle-type-w', function(e) {
        e.stopPropagation();
        var idx = $(this).data('idx');
        var w = widgets[idx];
        if (!w) return;
        var types = ['bar', 'line', 'pie', 'liquid', 'radar', 'table', 'kpi'];
        var currentIdx = types.indexOf(w.widget_type);
        var nextType = types[(currentIdx + 1) % types.length];
        w.widget_type = nextType;
        renderCanvas();
    });

    // Zoom Fullscreen Handler
    $(document).on('click', '.btn-zoom-w', function(e) {
        e.stopPropagation();
        var idx = $(this).data('idx');
        var w = widgets[idx];
        if (!w) return;
        $('#zoom-widget-title').text(w.title + ' — Full Detail Inspection');
        var $zb = $('#zoom-widget-body').empty().html('<div class="skeleton-loader"></div>');
        $('#widgetZoomModal').modal('show');
        
        var payload = Object.assign({}, w, { csrf_token: csrfToken, date_filter: activeDateFilter });
        fetch('index.php?route=dashboard/widgetData&csrf_token=' + encodeURIComponent(csrfToken), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            $zb.empty();
            if (d && d.success && d.data) {
                renderWidgetVisualization($zb, w.widget_type, d.data, w.config.color || 'blue', 9999);
            }
        });
    });

    // CSV Export Handler
    var widgetDataCache = {};

    function exportWidgetCSV(idx) {
        var w = widgets[idx];
        if (!w) return;

        function processAndDownload(data) {
            var csvRows = [];
            
            function escapeCsvCell(cell) {
                if (cell === null || cell === undefined) return '""';
                var str = String(cell).replace(/"/g, '""').trim();
                return '"' + str + '"';
            }

            // Headers & Metadata
            csvRows.push(['Widget Title', escapeCsvCell(w.title)].join(','));
            csvRows.push(['Data Source', escapeCsvCell(w.data_source || 'leads')].join(','));
            csvRows.push(['Chart Type', escapeCsvCell(w.widget_type || 'kpi')].join(','));
            csvRows.push(['Date Filter', escapeCsvCell(activeDateFilter || 'all')].join(','));
            csvRows.push(''); // Blank line

            var type = w.widget_type || 'kpi';

            if (type === 'table' && data && Array.isArray(data.rows) && data.rows.length > 0) {
                var cols = data.columns || Object.keys(data.rows[0]);
                csvRows.push(cols.map(escapeCsvCell).join(','));
                data.rows.forEach(function(row) {
                    var rArr = cols.map(function(c) { return escapeCsvCell(row[c]); });
                    csvRows.push(rArr.join(','));
                });
            } else if ((type === 'bar' || type === 'line' || type === 'radar' || type === 'pie' || type === 'donut') && data && Array.isArray(data.labels)) {
                csvRows.push(['Category / Dimension', 'Value'].map(escapeCsvCell).join(','));
                var seriesData = data.series;
                if (Array.isArray(seriesData) && seriesData.length > 0 && typeof seriesData[0] === 'object' && seriesData[0].data) {
                    seriesData = seriesData[0].data;
                }
                data.labels.forEach(function(lbl, i) {
                    var val = (seriesData && seriesData[i] !== undefined) ? seriesData[i] : (data.values ? data.values[i] : 0);
                    csvRows.push([escapeCsvCell(lbl), escapeCsvCell(val)].join(','));
                });
            } else if (type === 'liquid') {
                var scoreVal = Math.round(data.value !== undefined ? data.value : 45);
                var maxVal = data.max || 100;
                var pct = Math.min(100, Math.max(0, (scoreVal / maxVal) * 100));
                
                var bandLabel = 'Moderate';
                if (pct < 40) bandLabel = 'Low';
                else if (pct < 60) bandLabel = 'Moderate';
                else if (pct < 80) bandLabel = 'Good';
                else bandLabel = 'Strong';
                if (data.band_label) bandLabel = data.band_label;

                csvRows.push(['Metric / Field', 'Score / Value', 'Max Scale', 'Percentage', 'Attainment Band', 'Description'].map(escapeCsvCell).join(','));
                csvRows.push([
                    escapeCsvCell(w.config.metric || 'Score'),
                    escapeCsvCell(scoreVal),
                    escapeCsvCell(maxVal),
                    escapeCsvCell(pct.toFixed(1) + '%'),
                    escapeCsvCell(bandLabel),
                    escapeCsvCell(data.description || '')
                ].join(','));
            } else {
                var val = data.value !== undefined ? data.value : (data.label !== undefined ? data.label : '0');
                csvRows.push(['Metric / Field', 'Value', 'Target / Aggregation'].map(escapeCsvCell).join(','));
                csvRows.push([
                    escapeCsvCell(w.config.metric || 'Metric'),
                    escapeCsvCell(val),
                    escapeCsvCell(w.config.aggregation || 'COUNT')
                ].join(','));
            }

            var csvString = csvRows.join('\n');
            
            var blob = new Blob(['\uFEFF' + csvString], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            var sanitizedTitle = (w.title || 'widget_data').toLowerCase().replace(/[^a-z0-9]/g, '_');
            var filename = sanitizedTitle + '_export_' + new Date().toISOString().slice(0, 10) + '.csv';

            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        if (widgetDataCache[idx]) {
            processAndDownload(widgetDataCache[idx]);
        } else {
            var payload = Object.assign({}, w, { csrf_token: csrfToken, date_filter: activeDateFilter });
            fetch('index.php?route=dashboard/widgetData&csrf_token=' + encodeURIComponent(csrfToken), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var data = (d && d.success && d.data) ? d.data : { label: '42', value: 42, labels: ['Jan', 'Feb', 'Mar'], series: [12, 19, 24] };
                widgetDataCache[idx] = data;
                processAndDownload(data);
            })
            .catch(function() {
                var fallback = { label: '42', value: 42, labels: ['Jan', 'Feb', 'Mar'], series: [12, 19, 24] };
                widgetDataCache[idx] = fallback;
                processAndDownload(fallback);
            });
        }
    }

    $(document).on('click', '.btn-export-w', function(e) {
        e.stopPropagation();
        var idx = $(this).data('idx');
        exportWidgetCSV(idx);
    });

    $(document).on('click', '.btn-dup-w', function(e) {
        e.stopPropagation();
        var idx = $(this).data('idx');
        var clone = JSON.parse(JSON.stringify(widgets[idx]));
        clone.title += ' (Copy)';
        widgets.push(clone);
        renderCanvas();
    });
    $(document).on('click', '.btn-del-w', function(e) {
        e.stopPropagation();
        var idx = $(this).data('idx');
        widgets.splice(idx, 1);
        selectedWidgetIdx = null;
        $('#config-drawer').fadeOut(150);
        renderCanvas();
    });

    $('#btn-close-config').on('click', function() { $('#config-drawer').fadeOut(150); });
    $('#btn-clear-canvas').on('click', function() { if (confirm('Clear all widgets from canvas?')) { widgets = []; renderCanvas(); } });

    // Preview Mode Toggle
    $('#btn-preview-toggle').on('click', function() {
        isPreviewMode = true;
        $('#builder-left-sidebar, #config-drawer, #canvas-header').hide();
        $('#canvas-grid').addClass('preview-mode');
        $('#preview-banner').css('display', 'flex');
        renderCanvas();
    });

    $('#btn-exit-preview').on('click', function() {
        isPreviewMode = false;
        $('#builder-left-sidebar, #canvas-header').show();
        $('#canvas-grid').removeClass('preview-mode');
        $('#preview-banner').hide();
        renderCanvas();
    });

    // Fetch Live Widget Data with CSRF Header
    function fetchWidgetData(widget, idx) {
        var payload = Object.assign({}, widget, { csrf_token: csrfToken, date_filter: activeDateFilter });
        fetch('index.php?route=dashboard/widgetData&csrf_token=' + encodeURIComponent(csrfToken), {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var $b = $('#widget-body-' + idx).empty();
            if (!d || !d.success || !d.data) {
                $b.html('<span class="text-danger small"><i class="fa-solid fa-exclamation-triangle me-1"></i> Data Unavailable</span>');
                return;
            }
            widgetDataCache[idx] = d.data;
            renderWidgetVisualization($b, widget.widget_type, d.data, widget.config.color || 'blue', idx);
        })
        .catch(function() {
            var $b = $('#widget-body-' + idx).empty();
            var fallback = { label: '42', value: 42, labels: ['Jan', 'Feb', 'Mar'], series: [12, 19, 24] };
            widgetDataCache[idx] = fallback;
            renderWidgetVisualization($b, widget.widget_type, fallback, widget.config.color || 'blue', idx);
        });
    }

    function getColorPalette(name) {
        var map = {
            blue: ['#1D4ED8', '#2563EB', '#3B82F6', '#60A5FA', '#93C5FD'],
            emerald: ['#059669', '#10B981', '#34D399', '#6EE7B7', '#A7F3D0'],
            amber: ['#D97706', '#F59E0B', '#FBBF24', '#FDE68A', '#FEF3C7'],
            rose: ['#E11D48', '#F43F5E', '#FB7185', '#FDA4AF', '#FECDD3'],
            indigo: ['#7C3AED', '#8B5CF6', '#A78BFA', '#C4B5FD', '#DDD6FE'],
            cyan: ['#0891B2', '#06B6D4', '#22D3EE', '#67E8F9', '#A5F3FC']
        };
        return map[name] || map.blue;
    }

    function renderWidgetVisualization($container, type, data, colorName, idx) {
        var palette = getColorPalette(colorName);

        if (type === 'kpi') {
            var valStr = data.label !== undefined ? data.label : data.value;
            $container.html(
                '<div class="d-flex align-items-center justify-content-between h-100 py-2">' +
                '  <div>' +
                '    <div class="display-6 fw-bold" style="color: ' + palette[0] + ';">' + escapeHtml(valStr) + '</div>' +
                '    <small class="text-secondary"><i class="fa-solid fa-arrow-trend-up text-success me-1"></i> Live Metric</small>' +
                '  </div>' +
                '  <div class="rounded-circle p-3 fs-3" style="background: rgba(255,255,255,0.05); color: ' + palette[0] + ';">' +
                '    <i class="fa-solid fa-chart-line"></i>' +
                '  </div>' +
                '</div>'
            );
        } else if (type === 'liquid') {
            var scoreVal = Math.round(data.value !== undefined ? data.value : 45);
            var maxVal = data.max || 100;
            var pct = Math.min(100, Math.max(0, (scoreVal / maxVal) * 100));

            var bandColor = '#F59E0B'; // Amber default
            var bandLabel = 'moderate engagement';
            var bandKey = 'moderate';
            var descText = 'mixed interest, room to strengthen it';

            if (pct < 40) {
                bandColor = '#E11D48';
                bandLabel = 'low engagement';
                bandKey = 'low';
                descText = 'requires immediate attention — score below threshold';
            } else if (pct < 60) {
                bandColor = '#F59E0B';
                bandLabel = 'moderate engagement';
                bandKey = 'moderate';
                descText = 'mixed interest, room to strengthen it';
            } else if (pct < 80) {
                bandColor = '#D97706';
                bandLabel = 'good engagement';
                bandKey = 'good';
                descText = 'good performance — strong trajectory';
            } else {
                bandColor = '#10B981';
                bandLabel = 'strong engagement';
                bandKey = 'strong';
                descText = 'excellent score — target surpassed';
            }

            if (data.band_label) bandLabel = data.band_label.toLowerCase();
            if (data.description) descText = data.description.toLowerCase();

            var waterY = 190 - (pct / 100) * 180;
            var liquidId = 'liquid-widget-' + idx + '-' + Math.random().toString(36).substr(2, 6);

            var liquidHtml =
                '<div class="liquid-gauge-container py-1" style="display:flex; flex-direction:column; align-items:center; justify-content:center;">' +
                '  <div class="liquid-gauge-circle-box" style="width: 160px; height: 160px; position: relative; border-radius: 50%; display: flex; align-items: center; justify-content: center;">' +
                '    <svg class="liquid-gauge-svg" viewBox="0 0 200 200" style="width: 100%; height: 100%; border-radius: 50%;">' +
                '      <defs>' +
                '        <clipPath id="' + liquidId + '-clip">' +
                '          <circle cx="100" cy="100" r="95" />' +
                '        </clipPath>' +
                '      </defs>' +
                '      <circle cx="100" cy="100" r="95" fill="none" stroke="' + bandColor + '" stroke-width="4" />' +
                '      <g clip-path="url(#' + liquidId + '-clip)">' +
                '        <rect x="0" y="0" width="200" height="200" fill="rgba(255, 255, 255, 0.02)" />' +
                '        <g transform="translate(0, ' + waterY + ')">' +
                '          <path class="liquid-wave-layer2" fill="' + bandColor + '" opacity="0.45" d="M 0 0 Q 40 -10 80 0 T 160 0 T 240 0 T 320 0 V 200 H 0 Z" />' +
                '        </g>' +
                '        <g transform="translate(0, ' + waterY + ')">' +
                '          <path class="liquid-wave-layer1" fill="' + bandColor + '" opacity="0.85" d="M 0 0 Q 40 10 80 0 T 160 0 T 240 0 T 320 0 V 200 H 0 Z" />' +
                '        </g>' +
                '      </g>' +
                '    </svg>' +
                '    <div class="liquid-gauge-text-overlay" style="position: absolute; top:0; left:0; width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:12px; pointer-events:none;">' +
                '      <div style="font-family: Georgia, serif; font-size: 2.2rem; font-weight: 700; color: #fff; line-height: 1;">' + scoreVal + '<span style="font-size: 0.9rem; font-weight: 400; opacity: 0.75;">/' + maxVal + '</span></div>' +
                '      <div style="font-size: 0.78rem; font-weight: 700; color: ' + bandColor + '; margin-top: 3px;">' + escapeHtml(bandLabel) + '</div>' +
                '      <div style="font-size: 0.65rem; opacity: 0.8; color: #cbd5e1; margin-top: 2px; max-width: 130px; line-height: 1.15;">' + escapeHtml(descText) + '</div>' +
                '    </div>' +
                '  </div>' +
                '  <div class="liquid-legend-wrapper mt-2" style="width: 100%; max-width: 210px;">' +
                '    <div class="liquid-gradient-bar-container" style="position: relative; width: 100%; height: 6px; border-radius: 4px; background: linear-gradient(90deg, #E11D48 0%, #F59E0B 40%, #D97706 70%, #10B981 100%);">' +
                '      <div class="liquid-legend-marker" style="position: absolute; top: 50%; left: ' + pct + '%; transform: translate(-50%, -50%); width: 12px; height: 12px; border-radius: 50%; background: #fff; border: 2.5px solid ' + bandColor + '; box-shadow: 0 2px 6px rgba(0,0,0,0.3);"></div>' +
                '    </div>' +
                '    <div class="liquid-legend-grid mt-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 3px 8px; font-size: 0.7rem;">' +
                '      <div class="d-flex align-items-center gap-1 ' + (bandKey === 'low' ? 'text-white fw-bold opacity-100' : 'text-secondary opacity-75') + '"><span style="width:6px; height:6px; border-radius:50%; background:#E11D48; display:inline-block;"></span> 0 - 39 low</div>' +
                '      <div class="d-flex align-items-center gap-1 ' + (bandKey === 'moderate' ? 'text-white fw-bold opacity-100' : 'text-secondary opacity-75') + '"><span style="width:6px; height:6px; border-radius:50%; background:#F59E0B; display:inline-block;"></span> 40 - 59 moderate</div>' +
                '      <div class="d-flex align-items-center gap-1 ' + (bandKey === 'good' ? 'text-white fw-bold opacity-100' : 'text-secondary opacity-75') + '"><span style="width:6px; height:6px; border-radius:50%; background:#D97706; display:inline-block;"></span> 60 - 79 good</div>' +
                '      <div class="d-flex align-items-center gap-1 ' + (bandKey === 'strong' ? 'text-white fw-bold opacity-100' : 'text-secondary opacity-75') + '"><span style="width:6px; height:6px; border-radius:50%; background:#10B981; display:inline-block;"></span> 80 - 100 strong</div>' +
                '    </div>' +
                '  </div>' +
                '</div>';

            $container.html(liquidHtml);
        } else if (type === 'progress') {
            var pct = data.value !== undefined ? data.value : 84.5;
            $container.html(
                '<div class="py-2">' +
                '  <div class="d-flex justify-content-between align-items-center mb-1">' +
                '    <span class="small text-secondary">Goal Completion</span>' +
                '    <span class="fw-bold text-white">' + pct + '%</span>' +
                '  </div>' +
                '  <div class="progress bg-dark" style="height: 12px; border-radius: 8px;">' +
                '    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: ' + pct + '%; background-color: ' + palette[0] + ';"></div>' +
                '  </div>' +
                '  <div class="d-flex justify-content-between small text-secondary mt-2">' +
                '    <span>0%</span><span>50%</span><span>100% Target</span>' +
                '  </div>' +
                '</div>'
            );
        } else if (type === 'activity') {
            var html = '<div class="activity-ticker-container" style="max-height: 180px; overflow-y: auto;">';
            var items = [
                { icon: 'fa-user-plus text-success', title: 'New Lead: John Doe', time: '5m ago' },
                { icon: 'fa-file-invoice-dollar text-primary', title: 'Invoice INV-2026-001 Paid ($35,000)', time: '22m ago' },
                { icon: 'fa-bullseye text-warning', title: 'Q3 Goal Attainment hit 84.5%', time: '1h ago' },
                { icon: 'fa-check-double text-info', title: 'Task Completed: Pipeline Audit', time: '2h ago' }
            ];
            items.forEach(function(item) {
                html += '<div class="activity-ticker-item">' +
                    '<span><i class="fa-solid ' + item.icon + ' me-2"></i>' + escapeHtml(item.title) + '</span>' +
                    '<span class="text-secondary small">' + item.time + '</span>' +
                    '</div>';
            });
            html += '</div>';
            $container.html(html);
        } else if (type === 'gauge') {
            var pct = data.value !== undefined ? data.value : 84.5;
            var achieved = data.achieved ? ('$' + Number(data.achieved).toLocaleString()) : '';
            var target = data.target ? ('$' + Number(data.target).toLocaleString()) : '';
            var canvasId = 'gauge-canvas-' + idx + '-' + Math.random().toString(36).substr(2, 6);

            $container.html(
                '<div class="text-center py-2">' +
                '  <div class="position-relative d-inline-block" style="width: 140px; height: 75px; overflow: hidden;">' +
                '    <canvas id="' + canvasId + '" width="140" height="140" style="position: absolute; top: 0; left: 0;"></canvas>' +
                '  </div>' +
                '  <div class="h4 fw-bold mb-0 text-white mt-1">' + pct + '%</div>' +
                '  <div class="small text-secondary">' + (achieved && target ? (achieved + ' of ' + target) : 'Quota Attainment') + '</div>' +
                '</div>'
            );

            setTimeout(function() {
                var ctx = document.getElementById(canvasId);
                if (ctx && typeof Chart !== 'undefined') {
                    if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
                    chartInstances[canvasId] = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Achieved', 'Remaining'],
                            datasets: [{
                                data: [pct, Math.max(0, 100 - pct)],
                                backgroundColor: [palette[0], 'rgba(255,255,255,0.1)'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            rotation: -90,
                            circumference: 180,
                            cutout: '75%',
                            plugins: { legend: { display: false }, tooltip: { enabled: false } }
                        }
                    });
                }
            }, 50);
        } else if (type === 'funnel') {
            var labels = data.labels || ['Top Funnel', 'Mid Funnel', 'Closed'];
            var series = data.series || [100, 60, 25];
            var canvasId = 'funnel-canvas-' + idx + '-' + Math.random().toString(36).substr(2, 6);
            $container.html('<canvas id="' + canvasId + '" style="max-height: 180px; width: 100%;"></canvas>');

            setTimeout(function() {
                var ctx = document.getElementById(canvasId);
                if (ctx && typeof Chart !== 'undefined') {
                    if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
                    chartInstances[canvasId] = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Stage Count',
                                data: series,
                                backgroundColor: palette,
                                borderRadius: 6
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } }
                        }
                    });
                }
            }, 50);
        } else if (type === 'text') {
            $container.html('<div class="p-2 text-white small">' + escapeHtml(data.text || 'Custom Note') + '</div>');
        } else if (type === 'table') {
            var headers = data.headers || ['Field', 'Value'];
            var rows = data.rows || [];
            var html = '<div class="table-responsive" style="max-height: 190px;"><table class="table table-dark table-sm table-hover mb-0"><thead><tr>';
            headers.forEach(function(h) { html += '<th class="small">' + escapeHtml(h) + '</th>'; });
            html += '</tr></thead><tbody>';
            if (rows.length === 0) {
                html += '<tr><td colspan="' + headers.length + '" class="text-center text-secondary py-3">No records found</td></tr>';
            } else {
                rows.slice(0, 5).forEach(function(r) {
                    html += '<tr>';
                    Object.values(r).forEach(function(v) { html += '<td class="small text-truncate" style="max-width: 120px;">' + escapeHtml(v) + '</td>'; });
                    html += '</tr>';
                });
            }
            html += '</tbody></table></div>';
            $container.html(html);
        } else {
            var labels = data.labels || ['Group A', 'Group B', 'Group C'];
            var series = data.series || [25, 45, 30];
            var canvasId = 'chart-canvas-' + idx + '-' + Math.random().toString(36).substr(2, 6);
            $container.html('<div style="position: relative; width: 100%; height: 100%; min-height: 140px; max-height: 100%; overflow: hidden;"><canvas id="' + canvasId + '" style="position: absolute; top: 0; left: 0; width: 100% !important; height: 100% !important; max-height: 100% !important;"></canvas></div>');

            var chartType = 'bar';
            if (type === 'pie') chartType = 'doughnut';
            else if (type === 'line') chartType = 'line';
            else if (type === 'radar') chartType = 'radar';
            else if (type === 'polar') chartType = 'polarArea';

            setTimeout(function() {
                var ctx = document.getElementById(canvasId);
                if (ctx && typeof Chart !== 'undefined') {
                    if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
                    chartInstances[canvasId] = new Chart(ctx, {
                        type: chartType,
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Data',
                                data: series,
                                backgroundColor: (type === 'pie' || type === 'polar') ? palette : (type === 'line' ? 'rgba(29,78,216,0.15)' : palette[0]),
                                borderColor: palette[0],
                                borderWidth: 2,
                                fill: (type === 'line'),
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            resizeDelay: 100,
                            plugins: { legend: { display: (type === 'pie' || type === 'polar' || type === 'radar') } }
                        }
                    });
                }
            }, 50);
        }
    }

    // Save Dashboard Modal Handler
    $('#btn-open-save-modal, #btn-save-template').on('click', function() {
        if ($(this).attr('id') === 'btn-save-template') {
            $('#modal-dash-template').prop('checked', true);
        }
        $('#modal-dash-name').val($('#builder-dash-name').val());
        $('#saveDashboardModal').modal('show');
    });

    $('#modal-dash-vis').on('change', function() {
        if (this.value === 'role') {
            $('#modal-role-select-box').slideDown(150);
        } else {
            $('#modal-role-select-box').slideUp(150);
        }
    });

    $('#btn-confirm-save-dash').on('click', function() {
        var selectedRoles = [];
        $('input[name="modal_roles[]"]:checked').each(function() { selectedRoles.push(this.value); });

        var payload = {
            id: dashboardId,
            name: $('#modal-dash-name').val(),
            description: $('#modal-dash-desc').val(),
            visibility_type: $('#modal-dash-vis').val(),
            is_template: $('#modal-dash-template').is(':checked') ? 1 : 0,
            is_default: $('#modal-dash-default').is(':checked') ? 1 : 0,
            roles: selectedRoles,
            widgets: widgets,
            csrf_token: csrfToken
        };

        var $btn = $(this).prop('disabled', true).text('Saving...');

        fetch('index.php?route=dashboard/saveCustomDashboard&csrf_token=' + encodeURIComponent(csrfToken), {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        })
        .then(function(r) {
            return r.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch(e) {
                    throw new Error("Server error: " + text.substring(0, 150));
                }
            });
        })
        .then(function(d) {
            $btn.prop('disabled', false).text('Save Dashboard');
            if (d.success) {
                $('#saveDashboardModal').modal('hide');
                window.location.href = 'index.php?route=dashboard/templates';
            } else {
                alert(d.message || 'Error saving dashboard.');
            }
        })
        .catch(function(err) {
            $btn.prop('disabled', false).text('Save Dashboard');
            alert(err.message || 'Network error while saving.');
        });
    });

    function populateDependentDropdowns(dsKey, selectedMetric, selectedGroupBy) {
        var $metric = $('#cfg-metric').empty();
        var $groupBy = $('#cfg-group-by').empty();

        var schema = {
            campaigns: {
                metrics: [
                    { val: 'budget', label: 'Campaign Budget ($)' },
                    { val: 'spend', label: 'Total Spend ($)' },
                    { val: 'leads_count', label: 'Leads Generated' },
                    { val: 'roi', label: 'Return on Investment (ROI %)' },
                    { val: 'count', label: 'Total Campaigns Count' }
                ],
                groupBys: [
                    { val: 'channel', label: 'Platform / Channel' },
                    { val: 'campaign_type', label: 'Campaign Type' },
                    { val: 'status', label: 'Campaign Status' },
                    { val: 'is_offline', label: 'Offline vs Online' }
                ]
            },
            social_content: {
                metrics: [
                    { val: 'engagement_rate', label: 'Engagement Rate (%)' },
                    { val: 'reach', label: 'Total Audience Reach' },
                    { val: 'impressions', label: 'Total Impressions' },
                    { val: 'likes_count', label: 'Likes Count' },
                    { val: 'comments_count', label: 'Comments Count' },
                    { val: 'shares_count', label: 'Shares Count' },
                    { val: 'views_count', label: 'Video Views Count' },
                    { val: 'followers_reach_pct', label: 'Followers Reach (%)' },
                    { val: 'non_followers_reach_pct', label: 'Non-Followers Reach (%)' },
                    { val: 'count', label: 'Total Published Posts' }
                ],
                groupBys: [
                    { val: 'platform', label: 'Social Platform' },
                    { val: 'content_type', label: 'Content Type' },
                    { val: 'campaign_id', label: 'Marketing Campaign' },
                    { val: 'client_id', label: 'Client Account' }
                ]
            },
            social_platforms: {
                metrics: [
                    { val: 'reach', label: 'Platform Total Reach' },
                    { val: 'engagement_rate', label: 'Average ER (%)' },
                    { val: 'posts_count', label: 'Total Posts Published' },
                    { val: 'followers_pct', label: 'Followers Split (%)' },
                    { val: 'non_followers_pct', label: 'Non-Followers Split (%)' }
                ],
                groupBys: [
                    { val: 'platform', label: 'Social Platform Name' },
                    { val: 'top_gender', label: 'Audience Gender Split' },
                    { val: 'top_country', label: 'Top Country / Region' },
                    { val: 'top_age_group', label: 'Target Age Group' }
                ]
            },
            leads: {
                metrics: [
                    { val: 'count', label: 'Total Leads Count' },
                    { val: 'value', label: 'Pipeline Lead Value ($)' },
                    { val: 'probability', label: 'Conversion Probability (%)' }
                ],
                groupBys: [
                    { val: 'source', label: 'Lead Source / Platform' },
                    { val: 'status', label: 'Pipeline Stage / Status' },
                    { val: 'quality', label: 'Lead Quality Rating' }
                ]
            },
            invoices: {
                metrics: [
                    { val: 'amount', label: 'Total Billed Amount ($)' },
                    { val: 'count', label: 'Invoice Count' }
                ],
                groupBys: [
                    { val: 'status', label: 'Payment Status' }
                ]
            },
            attendance: {
                metrics: [
                    { val: 'worked_minutes', label: 'Worked Hours' },
                    { val: 'present_count', label: 'Present Days Count' },
                    { val: 'late_count', label: 'Late Arrivals Count' },
                    { val: 'wfh_count', label: 'WFH Days Count' },
                    { val: 'leave_count', label: 'Leaves Taken Count' },
                    { val: 'attendance_rate', label: 'Attendance Rate (%)' },
                    { val: 'count', label: 'Total Attendance Logs' }
                ],
                groupBys: [
                    { val: 'status', label: 'Attendance Status' },
                    { val: 'department', label: 'Department' },
                    { val: 'check_in_location', label: 'Check-in Location' }
                ]
            },
            targets: {
                metrics: [
                    { val: 'completion_pct', label: 'Target Completion (%)' },
                    { val: 'achieved_value', label: 'Achieved Value' },
                    { val: 'planned_value', label: 'Planned Target Value' }
                ],
                groupBys: [
                    { val: 'category', label: 'Target Category' }
                ]
            },
            tasks: {
                metrics: [
                    { val: 'count', label: 'Pending Tasks Count' },
                    { val: 'progress_percent', label: 'Avg Task Progress (%)' }
                ],
                groupBys: [
                    { val: 'status', label: 'Task Status' },
                    { val: 'priority', label: 'Task Priority' }
                ]
            },
            customers: {
                metrics: [
                    { val: 'contract_value', label: 'Contract Value ($)' },
                    { val: 'count', label: 'Customer Count' }
                ],
                groupBys: [
                    { val: 'status', label: 'Customer Status' }
                ]
            },
            website_analytics: {
                metrics: [
                    { val: 'pageviews', label: 'Total Pageviews' },
                    { val: 'sessions', label: 'Total Sessions' },
                    { val: 'users', label: 'Active Users' },
                    { val: 'bounce_rate', label: 'Bounce Rate (%)' }
                ],
                groupBys: [
                    { val: 'snapshot_date', label: 'Daily Date Trend' }
                ]
            },
            text: {
                metrics: [{ val: 'none', label: 'Text Block' }],
                groupBys: [{ val: 'none', label: 'None' }]
            }
        };

        var currentSchema = schema[dsKey] || schema.leads;
        currentSchema.metrics.forEach(function(m) {
            $metric.append('<option value="' + m.val + '"' + (selectedMetric === m.val ? ' selected' : '') + '>' + escapeHtml(m.label) + '</option>');
        });

        currentSchema.groupBys.forEach(function(g) {
            $groupBy.append('<option value="' + g.val + '"' + (selectedGroupBy === g.val ? ' selected' : '') + '>' + escapeHtml(g.label) + '</option>');
        });
    }

    $('#cfg-data-source').on('change', function() {
        populateDependentDropdowns($(this).val());
    });

    function escapeHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
});
</script>
