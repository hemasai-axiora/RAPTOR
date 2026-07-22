<!-- Customer Intelligence & Analytics Dashboard -->

<!-- Row 1: KPI Cards -->
<div class="row g-4 mb-4">
    <!-- Brand Sentiment -->
    <div class="col-xl-3 col-md-6">
        <div class="pulse-card card-glow">
            <div class="card-title">Brand Sentiment Score</div>
            <div class="d-flex align-items-baseline">
                <h2 class="mb-0 text-white font-weight-bold" style="font-size: 2.2rem; font-weight:700;">78/100</h2>
                <span class="ms-2 badge bg-success-subtle text-success border border-success-subtle">Positive</span>
            </div>
            <div class="text-secondary mt-1" style="font-size: 0.8rem;">Rolling 30-day sentiment analysis</div>
        </div>
    </div>
    
    <!-- Customer Satisfaction (CSAT) -->
    <div class="col-xl-3 col-md-6">
        <div class="pulse-card card-glow">
            <div class="card-title">Customer Satisfaction</div>
            <div class="d-flex align-items-baseline">
                <h2 class="mb-0 text-white font-weight-bold" style="font-size: 2.2rem; font-weight:700;">4.5 / 5</h2>
                <span class="ms-2 badge bg-success-subtle text-success border border-success-subtle">Excellent</span>
            </div>
            <div class="text-secondary mt-1" style="font-size: 0.8rem;">Post-conversion customer CSAT surveys</div>
        </div>
    </div>

    <!-- Lead Quality Score -->
    <div class="col-xl-3 col-md-6">
        <div class="pulse-card card-glow">
            <div class="card-title">Lead Quality Score</div>
            <div class="d-flex align-items-baseline">
                <h2 class="mb-0 text-white font-weight-bold" style="font-size: 2.2rem; font-weight:700;" id="kpi-lq-score">81/100</h2>
                <span class="ms-2 badge bg-success-subtle text-success border border-success-subtle" id="kpi-lq-label">High</span>
            </div>
            <div class="text-secondary mt-1" style="font-size: 0.8rem;">Ratio of Hot vs Cold incoming leads</div>
        </div>
    </div>

    <!-- Churn Risk Score -->
    <div class="col-xl-3 col-md-6">
        <div class="pulse-card card-glow">
            <div class="card-title">Churn Risk Score</div>
            <div class="d-flex align-items-baseline">
                <h2 class="mb-0 text-white font-weight-bold" style="font-size: 2.2rem; font-weight:700;">24/100</h2>
                <span class="ms-2 badge bg-success-subtle text-success border border-success-subtle">Low</span>
            </div>
            <div class="text-secondary mt-1" style="font-size: 0.8rem;">Customer churn probability metrics</div>
        </div>
    </div>
</div>

<!-- Row 2: Demographics & Sentiment Trend -->
<div class="row g-4 mb-4">
    <!-- Audience Demographics -->
    <div class="col-xl-6">
        <div class="pulse-card h-100">
            <div class="card-title">Audience Demographics</div>
            <div class="row mt-3">
                <div class="col-md-6">
                    <div id="demographics-gender-donut" style="min-height: 250px;"></div>
                </div>
                <div class="col-md-6">
                    <div id="demographics-device-bar" style="min-height: 250px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sentiment Trend Line -->
    <div class="col-xl-6">
        <div class="pulse-card h-100">
            <div class="card-title">Rolling Brand Sentiment Trend</div>
            <div id="sentiment-trend-chart" style="min-height: 250px;"></div>
        </div>
    </div>
</div>

<!-- Row 3: Touchpoint Attribution & Competitor Benchmarking -->
<div class="row g-4 mb-4">
    <!-- Attribution Model -->
    <div class="col-xl-6">
        <div class="pulse-card h-100">
            <div class="card-title d-flex justify-content-between align-items-center">
                <span>Multi-Touch Attribution</span>
                <select class="filter-select py-1 px-2" id="attribution-model-select">
                    <option value="first">First Touch Model</option>
                    <option value="last">Last Touch Model</option>
                    <option value="decay">Time Decay Model</option>
                    <option value="data">Data Driven Model</option>
                </select>
            </div>
            <div id="attribution-donut" style="min-height: 300px; margin-top: 15px;"></div>
        </div>
    </div>

    <!-- Competitor Benchmarks -->
    <div class="col-xl-6">
        <div class="pulse-card h-100">
            <div class="card-title">Competitor Benchmarking</div>
            <div class="table-responsive mt-3">
                <table class="table table-dark table-hover align-middle border-secondary mb-0">
                    <thead>
                        <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                            <th>Metric Name</th>
                            <th class="text-center">Our Value</th>
                            <th class="text-center">Industry Avg</th>
                            <th class="text-center">Variance</th>
                        </tr>
                    </thead>
                    <tbody id="benchmarks-tbody">
                        <!-- Loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Inline script to load and initialize customer intelligence dashboard -->
<script>
$(document).ready(function() {
    loadCustomerData();

    $('#client-selector, #date-range, #attribution-model-select').on('change', function() {
        loadCustomerData();
    });

    function loadCustomerData() {
        const client = $('#client-selector').val();
        const dateRange = $('#date-range').val();
        const model = $('#attribution-model-select').val();
        
        let start = '';
        let end = '';
        
        if (dateRange.includes('to')) {
            const dates = dateRange.split(' to ');
            start = dates[0].trim();
            end = dates[1].trim();
        }

        const url = `index.php?route=api/customer&client_id=${client}&start_date=${start}&end_date=${end}`;

        $.getJSON(url, function(data) {
            // 1. Lead Quality KPI
            let hot = 0;
            let warm = 0;
            let cold = 0;
            data.quality.forEach(function(q) {
                if (q.lead_quality === 'hot') hot = q.count;
                if (q.lead_quality === 'warm') warm = q.count;
                if (q.lead_quality === 'cold') cold = q.count;
            });
            const total = hot + warm + cold;
            const score = total > 0 ? Math.round(((hot + (warm * 0.5)) / total) * 100) : 81;
            $('#kpi-lq-score').text(score + '/100');
            $('#kpi-lq-label').text(score >= 75 ? 'High' : (score >= 50 ? 'Moderate' : 'Low'));

            // 2. Demographics Gender Donut
            const genderData = data.demographics.filter(d => d.dimension_type === 'gender');
            renderGenderDonut(genderData);

            // 3. Demographics Device Bar
            const deviceData = data.demographics.filter(d => d.dimension_type === 'device');
            renderDeviceBar(deviceData);

            // 4. Sentiment Trend Line
            renderSentimentLine(data.sentiments);

            // 5. Competitor Benchmarks Table
            let benchmarkHtml = '';
            data.benchmarks.forEach(function(b) {
                const isPositive = parseFloat(b.vs_competitor_percentage) >= 0;
                benchmarkHtml += `
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td class="text-white">${b.metric_name}</td>
                        <td class="text-center font-weight-bold">${b.our_metric_value}%</td>
                        <td class="text-center text-secondary">${b.competitor_avg_value}%</td>
                        <td class="text-center font-weight-bold text-${isPositive ? 'success' : 'danger'}">
                            ${isPositive ? '+' : ''}${parseFloat(b.vs_competitor_percentage).toFixed(1)}%
                        </td>
                    </tr>
                `;
            });
            $('#benchmarks-tbody').html(benchmarkHtml);

            // 6. Attribution Model Donut
            renderAttributionDonut(model);
        });
    }

    // Gender Donut Chart helper
    let genderChart = null;
    function renderGenderDonut(data) {
        $('#demographics-gender-donut').empty();

        const labels = data.map(d => d.dimension_label);
        const values = data.map(d => parseFloat(d.percentage));

        const options = {
            series: values.length > 0 ? values : [52.4, 46.1, 1.5],
            labels: labels.length > 0 ? labels : ['Male', 'Female', 'Non-binary'],
            chart: { type: 'donut', height: 250 },
            colors: ['#6366f1', '#a855f7', '#06b6d4'],
            legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
            stroke: { show: false },
            tooltip: { theme: 'dark' }
        };

        genderChart = new ApexCharts(document.querySelector('#demographics-gender-donut'), options);
        genderChart.render();
    }

    // Device Bar Chart helper
    let deviceChart = null;
    function renderDeviceBar(data) {
        $('#demographics-device-bar').empty();

        const labels = data.map(d => d.dimension_label);
        const values = data.map(d => parseFloat(d.percentage));

        const options = {
            series: [{ data: values.length > 0 ? values : [68.4, 28.1, 3.5] }],
            chart: { type: 'bar', height: 220, toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '40%' } },
            colors: ['#10b981'],
            xaxis: { categories: labels.length > 0 ? labels : ['Mobile', 'Desktop', 'Tablet'], labels: { formatter: (v) => v + '%' } },
            tooltip: { theme: 'dark' },
            grid: { show: false }
        };

        deviceChart = new ApexCharts(document.querySelector('#demographics-device-bar'), options);
        deviceChart.render();
    }

    // Sentiment Trend Line Chart
    let sentimentChart = null;
    function renderSentimentLine(data) {
        $('#sentiment-trend-chart').empty();

        const dates = data.map(d => d.recorded_date);
        const positive = data.map(d => parseFloat(d.positive_score));
        const neutral = data.map(d => parseFloat(d.neutral_score));
        const negative = data.map(d => parseFloat(d.negative_score));

        const options = {
            series: [
                { name: 'Positive Sentiment', data: positive },
                { name: 'Neutral Sentiment', data: neutral },
                { name: 'Negative Sentiment', data: negative }
            ],
            chart: { height: 250, type: 'line', toolbar: { show: false }, redrawOnWindowResize: true, redrawOnParentResize: true },
            responsive: [{ breakpoint: 768, options: { chart: { height: 220 }, legend: { position: 'bottom' } } }],
            colors: ['#10b981', '#64748b', '#ef4444'],
            stroke: { width: 3, curve: 'smooth' },
            xaxis: { type: 'datetime', categories: dates },
            yaxis: { max: 100, min: 0, labels: { formatter: (v) => v + '%' } },
            tooltip: { theme: 'dark' },
            legend: { labels: { colors: '#94a3b8' } }
        };

        sentimentChart = new ApexCharts(document.querySelector('#sentiment-trend-chart'), options);
        sentimentChart.render();
    }

    // Attribution model donut helper
    let attrChart = null;
    function renderAttributionDonut(model) {
        $('#attribution-donut').empty();

        // Weights variation based on attribution model
        let seriesValues = [];
        const labels = ['LinkedIn Ads', 'Google Ads', 'Meta Campaigns', 'Direct Website', 'Email Marketing'];
        
        switch(model) {
            case 'first':
                seriesValues = [35, 20, 15, 10, 20];
                break;
            case 'last':
                seriesValues = [15, 45, 20, 5, 15];
                break;
            case 'decay':
                seriesValues = [25, 30, 20, 10, 15];
                break;
            case 'data':
            default:
                seriesValues = [28, 38, 18, 6, 10];
                break;
        }

        const options = {
            series: seriesValues,
            labels: labels,
            chart: { type: 'donut', height: 300 },
            colors: ['#6366f1', '#10b981', '#a855f7', '#06b6d4', '#f59e0b'],
            legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
            stroke: { show: false },
            tooltip: { theme: 'dark' }
        };

        attrChart = new ApexCharts(document.querySelector('#attribution-donut'), options);
        attrChart.render();
    }
});
</script>
