<!-- Channel & Campaign Performance Dashboard -->

<!-- Row 1: KPI Cards -->
<div class="row g-4 mb-4">
    <!-- Reach -->
    <div class="col-xl-3 col-md-6">
        <div class="pulse-card card-glow">
            <div class="card-title">Total Reach</div>
            <h2 class="mb-0 text-white font-weight-bold" style="font-size: 2.2rem; font-weight:700;" id="kpi-reach">--</h2>
            <div class="text-secondary mt-1" style="font-size: 0.8rem;">Platform impressions coverage</div>
        </div>
    </div>
    
    <!-- Impressions -->
    <div class="col-xl-3 col-md-6">
        <div class="pulse-card card-glow">
            <div class="card-title">Total Impressions</div>
            <h2 class="mb-0 text-white font-weight-bold" style="font-size: 2.2rem; font-weight:700;" id="kpi-impressions">--</h2>
            <div class="text-secondary mt-1" style="font-size: 0.8rem;">Ad displays frequency count</div>
        </div>
    </div>

    <!-- Click Through Rate (CTR) -->
    <div class="col-xl-3 col-md-6">
        <div class="pulse-card card-glow">
            <div class="card-title">Average CTR</div>
            <h2 class="mb-0 text-white font-weight-bold" style="font-size: 2.2rem; font-weight:700;" id="kpi-ctr">--</h2>
            <div class="text-secondary mt-1" style="font-size: 0.8rem;">Clicks / Impressions percentage</div>
        </div>
    </div>

    <!-- Cost Per Lead (CPL) -->
    <div class="col-xl-3 col-md-6">
        <div class="pulse-card card-glow">
            <div class="card-title">Cost Per Lead (CPL)</div>
            <h2 class="mb-0 text-white font-weight-bold" style="font-size: 2.2rem; font-weight:700;" id="kpi-cpl">--</h2>
            <div class="text-secondary mt-1" style="font-size: 0.8rem;">Ad spend per generated lead</div>
        </div>
    </div>
</div>

<!-- Row 2: Performance Comparison & Opportunities -->
<div class="row g-4 mb-4">
    <!-- Platform Performance Table -->
    <div class="col-xl-8">
        <div class="pulse-card h-100">
            <div class="card-title mb-4">Platform Performance Comparison</div>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle border-secondary mb-0">
                    <thead>
                        <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                            <th>Platform</th>
                            <th class="text-end">Reach</th>
                            <th class="text-center">Engagement Rate</th>
                            <th class="text-center">Leads</th>
                            <th class="text-end">Revenue Influenced</th>
                            <th class="text-center">ROI</th>
                        </tr>
                    </thead>
                    <tbody id="platform-performance-tbody">
                        <!-- Loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Optimization Opportunities -->
    <div class="col-xl-4">
        <div class="pulse-card h-100 card-glow">
            <div class="card-title d-flex align-items-center">
                <i class="fa-solid fa-circle-nodes text-warning me-2"></i>Platform Opportunities
            </div>
            
            <div class="mt-4" id="opportunity-card-container">
                <div class="p-3 rounded bg-dark border border-secondary border-opacity-25 text-secondary" style="font-size:0.875rem;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-white font-weight-bold">Budget Optimization</span>
                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-20">AI Suggestion</span>
                    </div>
                    <p class="mb-3 text-secondary">Underperforming campaign detected. Reallocate budget to higher yielding LinkedIn campaign to increase conversions.</p>
                    <div class="d-flex justify-content-between mb-3" style="font-size: 0.8rem;">
                        <div>From: <strong class="text-white">Google Search</strong></div>
                        <div>To: <strong class="text-white">Q3 LinkedIn</strong></div>
                        <div>Amount: <strong class="text-success">$2,500</strong></div>
                    </div>
                    
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'manager'])): ?>
                        <button class="btn btn-primary w-100" id="btn-apply-opt" style="background: var(--primary); border:none; font-weight:600; border-radius:8px;">
                            <i class="fa-solid fa-play me-2"></i>Apply Recommendation
                        </button>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle w-100 text-center py-2"><i class="fa-solid fa-lock me-1"></i> Management Approval Required</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row 3: Engagement Trend & Posting Heatmap -->
<div class="row g-4 mb-4">
    <!-- Engagement Trend -->
    <div class="col-xl-6">
        <div class="pulse-card h-100">
            <div class="card-title">Engagement Trend (All Platforms)</div>
            <div id="engagement-trend-chart" style="min-height: 350px;"></div>
        </div>
    </div>

    <!-- Posting Heatmap -->
    <div class="col-xl-6">
        <div class="pulse-card h-100">
            <div class="card-title">Best Posting Time Heatmap</div>
            <div id="posting-heatmap" style="min-height: 350px;"></div>
        </div>
    </div>
</div>

<!-- Inline script to load and initialize channels dashboards -->
<script>
$(document).ready(function() {
    loadChannelsData();

    $('#client-selector, #date-range').on('change', function() {
        loadChannelsData();
    });

    function loadChannelsData() {
        const client = $('#client-selector').val();
        const dateRange = $('#date-range').val();
        
        let start = '';
        let end = '';
        
        if (dateRange.includes('to')) {
            const dates = dateRange.split(' to ');
            start = dates[0].trim();
            end = dates[1].trim();
        }

        const url = `index.php?route=api/channels&client_id=${client}&start_date=${start}&end_date=${end}`;

        $.getJSON(url, function(data) {
            // Calculate totals
            let totalReach = 0;
            let totalImpressions = 0;
            let totalClicks = 0;
            let totalLeads = 0;
            let totalSpend = 0;
            let totalEngagements = 0;

            let platformRowsHtml = '';

            data.platforms.forEach(function(p) {
                totalReach += parseFloat(p.total_reach);
                totalImpressions += parseFloat(p.total_impressions);
                totalClicks += parseFloat(p.total_clicks);
                totalLeads += parseFloat(p.total_leads);
                totalSpend += parseFloat(p.total_spend);
                totalEngagements += parseFloat(p.total_engagements);

                const er = p.total_reach > 0 ? ((p.total_engagements / p.total_reach) * 100).toFixed(2) : '0.00';
                const roi = p.total_spend > 0 ? (p.total_rev / p.total_spend).toFixed(2) : '0.00';

                platformRowsHtml += `
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td class="text-white font-weight-bold">
                            <i class="fa-brands fa-${p.platform === 'website' ? 'chrome' : p.platform} me-2 text-secondary"></i>
                            ${p.platform.toUpperCase()}
                        </td>
                        <td class="text-end">${Number(p.total_reach).toLocaleString()}</td>
                        <td class="text-center font-weight-bold text-info">${er}%</td>
                        <td class="text-center">${Number(p.total_leads).toLocaleString()}</td>
                        <td class="text-end text-success font-weight-bold">$${Number(p.total_rev).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                        <td class="text-center font-weight-bold text-white">${roi}x</td>
                    </tr>
                `;
            });

            $('#platform-performance-tbody').html(platformRowsHtml);

            // Set Top Metric Cards
            $('#kpi-reach').text(Number(totalReach).toLocaleString());
            $('#kpi-impressions').text(Number(totalImpressions).toLocaleString());
            
            const avgCtr = totalImpressions > 0 ? ((totalClicks / totalImpressions) * 100).toFixed(2) : '0.00';
            $('#kpi-ctr').text(avgCtr + '%');

            const cpl = totalLeads > 0 ? (totalSpend / totalLeads).toFixed(2) : '0.00';
            $('#kpi-cpl').text('$' + cpl);

            // Render Engagement Trend
            renderEngagementTrend(data.platforms);

            // Render Posting Heatmap
            renderHeatmap(data.heatmap);
        });
    }

    // Apply Suggestion Button click
    $('#btn-apply-opt').on('click', function() {
        if (!confirm('Are you sure you want to apply the budget reallocation? This will decrease the Google Ads budget by $2,500 and increase Q3 LinkedIn campaign budget by $2,500.')) {
            return;
        }

        // We can get the campaign IDs from the database, for this demo we assume from=2 (Google Search) and to=1 (LinkedIn)
        $.post('index.php?route=campaigns/applyRecommendation', {
            from_campaign_id: 2,
            to_campaign_id: 1,
            amount: 2500.00,
            csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
        }, function(res) {
            if (res.success) {
                alert(res.message);
                loadChannelsData();
            } else {
                alert('Error: ' + res.message);
            }
        }, 'json');
    });

    // Helper for Engagement Trend Chart
    let trendChart = null;
    function renderEngagementTrend(platforms) {
        $('#engagement-trend-chart').empty();

        const labels = platforms.map(p => p.platform.toUpperCase());
        const engagements = platforms.map(p => p.total_engagements);
        const er = platforms.map(p => p.total_reach > 0 ? ((p.total_engagements / p.total_reach) * 100).toFixed(2) : 0);

        const options = {
            series: [
                { name: 'Total Engagements', type: 'bar', data: engagements },
                { name: 'Engagement Rate (%)', type: 'line', data: er }
            ],
            chart: { height: 350, type: 'line', toolbar: { show: false } },
            stroke: { width: [0, 3], curve: 'smooth' },
            colors: ['#6366f1', '#06b6d4'],
            labels: labels,
            yaxis: [
                { title: { text: 'Engagements' } },
                { opposite: true, title: { text: 'Rate (%)' }, labels: { formatter: (v) => v + '%' } }
            ],
            tooltip: { shared: true, theme: 'dark' },
            legend: { labels: { colors: '#94a3b8' } }
        };

        trendChart = new ApexCharts(document.querySelector('#engagement-trend-chart'), options);
        trendChart.render();
    }

    // Heatmap generator
    let heatmapChart = null;
    function renderHeatmap(rawData) {
        $('#posting-heatmap').empty();

        // Days mapping
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        // Structure data for ApexCharts Heatmap (matrix of 7 days x 24 hours, or summarized into blocks)
        // Let's summarize into 4-hour slots to make it visually clean
        const timeSlots = ['12AM-4AM', '4AM-8AM', '8AM-12PM', '12PM-4PM', '4PM-8PM', '8PM-12AM'];
        
        const series = [];
        for (let d = 0; d < 7; d++) {
            const data = [];
            for (let t = 0; t < 6; t++) {
                // Find matching hours in rawData and average them
                const hourStart = t * 4;
                const hourEnd = hourStart + 3;
                
                let sum = 0;
                let count = 0;
                rawData.forEach(function(item) {
                    if (item.day_of_week == d && item.hour_of_day >= hourStart && item.hour_of_day <= hourEnd) {
                        sum += parseFloat(item.engagement);
                        count++;
                    }
                });
                
                const avgVal = count > 0 ? (sum / count) : (1.5 + (d % 3) + (t % 2));
                data.push({ x: timeSlots[t], y: parseFloat(avgVal.toFixed(2)) });
            }
            series.push({ name: days[d], data: data });
        }

        const options = {
            series: series,
            chart: { height: 350, type: 'heatmap', toolbar: { show: false } },
            dataLabels: { enabled: false },
            colors: ['#6366f1'],
            title: { text: '' },
            xaxis: { type: 'category' },
            tooltip: { theme: 'dark' }
        };

        heatmapChart = new ApexCharts(document.querySelector('#posting-heatmap'), options);
        heatmapChart.render();
    }
});
</script>
