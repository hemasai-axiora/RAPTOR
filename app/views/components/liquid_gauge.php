<?php
/**
 * Standalone Liquid Fill Gauge Component (RAPTOR CRM)
 * 
 * Usage:
 * require_once APPROOT . '/views/components/liquid_gauge.php';
 * echo renderLiquidGauge([
 *     'value' => 45,
 *     'max' => 100,
 *     'title' => 'Customer Engagement Score',
 *     'description' => 'Mixed interest — room to strengthen engagement',
 *     'animate' => true
 * ]);
 */

if (!function_exists('renderLiquidGauge')) {
    function renderLiquidGauge(array $props = []): string {
        $value = isset($props['value']) ? (float)$props['value'] : 45;
        $max = isset($props['max']) ? (float)$props['max'] : 100;
        $title = $props['title'] ?? '';
        $customDesc = $props['description'] ?? '';
        $animate = !isset($props['animate']) || $props['animate'];

        $pct = min(100, max(0, ($max > 0 ? ($value / $max) * 100 : 0)));

        // Determine Band Color & Label based on 0-100 threshold
        if ($pct < 40) {
            $bandLabel = 'Low Engagement';
            $bandKey = 'low';
            $bandColor = '#E11D48'; // Red
            $defaultDesc = 'requires immediate attention — score below threshold';
        } elseif ($pct < 60) {
            $bandLabel = 'Moderate Engagement';
            $bandKey = 'moderate';
            $bandColor = '#F59E0B'; // Amber
            $defaultDesc = 'mixed interest, room to strengthen it';
        } elseif ($pct < 80) {
            $bandLabel = 'Good Engagement';
            $bandKey = 'good';
            $bandColor = '#D97706'; // Gold
            $defaultDesc = 'good performance — strong trajectory';
        } else {
            $bandLabel = 'Strong Engagement';
            $bandKey = 'strong';
            $bandColor = '#10B981'; // Green
            $defaultDesc = 'excellent score — target surpassed';
        }

        $description = !empty($customDesc) ? $customDesc : $defaultDesc;
        $uniqueId = 'lg-' . uniqid();

        // Water surface position in SVG coordinates (0-200 viewBox)
        $waterY = 190 - ($pct / 100) * 180;

        ob_start();
        ?>
        <style>
        .liquid-gauge-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--text-primary, #fff);
            padding: 8px;
            user-select: none;
        }
        .liquid-gauge-title {
            font-size: 0.92rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary, #fff);
        }
        .liquid-gauge-circle-box {
            position: relative;
            width: 190px;
            height: 190px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        .liquid-gauge-svg {
            width: 100%;
            height: 100%;
            border-radius: 50%;
        }
        .liquid-wave-layer1 {
            fill: <?php echo $bandColor; ?>;
            opacity: 0.85;
            transition: all 0.6s ease-out;
            <?php if ($animate): ?>
            animation: liquidWaveLoop1 3.5s linear infinite;
            <?php endif; ?>
        }
        .liquid-wave-layer2 {
            fill: <?php echo $bandColor; ?>;
            opacity: 0.45;
            transition: all 0.6s ease-out;
            <?php if ($animate): ?>
            animation: liquidWaveLoop2 5s linear infinite;
            <?php endif; ?>
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

        .liquid-gauge-text-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 16px;
            pointer-events: none;
            z-index: 10;
        }
        .liquid-gauge-score-value {
            font-family: "Georgia", "Times New Roman", serif;
            font-size: 2.6rem;
            font-weight: 700;
            line-height: 1;
            color: var(--text-primary, #ffffff);
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .liquid-gauge-score-max {
            font-size: 1rem;
            font-weight: 400;
            opacity: 0.75;
            margin-left: 2px;
        }
        .liquid-gauge-band-label {
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: lowercase;
            margin-top: 4px;
            letter-spacing: 0.3px;
        }
        .liquid-gauge-description {
            font-size: 0.68rem;
            opacity: 0.8;
            margin-top: 2px;
            max-width: 140px;
            line-height: 1.2;
        }

        /* Horizontal Gradient Legend Bar & Grid */
        .liquid-legend-wrapper {
            width: 100%;
            max-width: 220px;
            margin-top: 12px;
        }
        .liquid-gradient-bar-container {
            position: relative;
            width: 100%;
            height: 6px;
            border-radius: 4px;
            background: linear-gradient(90deg, #E11D48 0%, #F59E0B 40%, #D97706 70%, #10B981 100%);
        }
        .liquid-legend-marker {
            position: absolute;
            top: 50%;
            left: <?php echo round($pct, 1); ?>%;
            transform: translate(-50%, -50%);
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid <?php echo $bandColor; ?>;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            transition: left 0.6s ease-out;
        }
        .liquid-legend-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 10px;
            margin-top: 10px;
            font-size: 0.72rem;
        }
        .liquid-legend-item {
            display: flex;
            align-items: center;
            gap: 4px;
            opacity: 0.65;
            transition: opacity 0.2s ease;
        }
        .liquid-legend-item.active {
            opacity: 1;
            font-weight: 700;
        }
        .liquid-legend-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        </style>

        <div class="liquid-gauge-container" id="<?php echo $uniqueId; ?>">
            <?php if (!empty($title)): ?>
                <div class="liquid-gauge-title"><?php echo htmlspecialchars($title); ?></div>
            <?php endif; ?>

            <div class="liquid-gauge-circle-box">
                <svg class="liquid-gauge-svg" viewBox="0 0 200 200">
                    <defs>
                        <clipPath id="<?php echo $uniqueId; ?>-clip">
                            <circle cx="100" cy="100" r="95" />
                        </clipPath>
                    </defs>

                    <!-- Outer Circle Ring Stroke -->
                    <circle cx="100" cy="100" r="95" fill="none" stroke="<?php echo $bandColor; ?>" stroke-width="4" />

                    <!-- Liquid Fill Waves Clipped to Circle -->
                    <g clip-path="url(#<?php echo $uniqueId; ?>-clip)">
                        <rect x="0" y="0" width="200" height="200" fill="rgba(255, 255, 255, 0.02)" />

                        <!-- Wavy Layer 2 (Back Wave) -->
                        <g transform="translate(0, <?php echo $waterY; ?>)">
                            <path class="liquid-wave-layer2" d="M 0 0 Q 40 -10 80 0 T 160 0 T 240 0 T 320 0 V 200 H 0 Z" />
                        </g>

                        <!-- Wavy Layer 1 (Front Wave) -->
                        <g transform="translate(0, <?php echo $waterY; ?>)">
                            <path class="liquid-wave-layer1" d="M 0 0 Q 40 10 80 0 T 160 0 T 240 0 T 320 0 V 200 H 0 Z" />
                        </g>
                    </g>
                </svg>

                <!-- Center Text Overlay -->
                <div class="liquid-gauge-text-overlay">
                    <div class="liquid-gauge-score-value">
                        <?php echo round($value); ?><span class="liquid-gauge-score-max">/<?php echo round($max); ?></span>
                    </div>
                    <div class="liquid-gauge-band-label" style="color: <?php echo $bandColor; ?>;">
                        <?php echo htmlspecialchars(strtolower($bandLabel)); ?>
                    </div>
                    <?php if (!empty($description)): ?>
                        <div class="liquid-gauge-description">
                            <?php echo htmlspecialchars(strtolower($description)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Horizontal Gradient Legend Bar & Grid -->
            <div class="liquid-legend-wrapper">
                <div class="liquid-gradient-bar-container">
                    <div class="liquid-legend-marker"></div>
                </div>

                <div class="liquid-legend-grid">
                    <div class="liquid-legend-item <?php echo $bandKey === 'low' ? 'active' : ''; ?>">
                        <span class="liquid-legend-dot" style="background: #E11D48;"></span>
                        <span><strong>0 - 39</strong> low</span>
                    </div>
                    <div class="liquid-legend-item <?php echo $bandKey === 'moderate' ? 'active' : ''; ?>">
                        <span class="liquid-legend-dot" style="background: #F59E0B;"></span>
                        <span><strong>40 - 59</strong> moderate</span>
                    </div>
                    <div class="liquid-legend-item <?php echo $bandKey === 'good' ? 'active' : ''; ?>">
                        <span class="liquid-legend-dot" style="background: #D97706;"></span>
                        <span><strong>60 - 79</strong> good</span>
                    </div>
                    <div class="liquid-legend-item <?php echo $bandKey === 'strong' ? 'active' : ''; ?>">
                        <span class="liquid-legend-dot" style="background: #10B981;"></span>
                        <span><strong>80 - 100</strong> strong</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
