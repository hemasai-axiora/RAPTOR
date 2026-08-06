<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Post Header Card -->
        <div class="pulse-card mb-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-dark border border-secondary text-primary font-monospace fs-6">
                            <?php echo htmlspecialchars($post->post_code ?: ('PST-' . date('Y') . '-' . sprintf('%05d', $post->post_id))); ?>
                        </span>
                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                            <?php echo htmlspecialchars($post->platform); ?>
                        </span>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                            <?php echo htmlspecialchars($post->content_type); ?>
                        </span>
                    </div>
                    <h3 class="text-white fw-bold mb-2"><?php echo htmlspecialchars($post->title ?: 'Untitled Post'); ?></h3>
                    <div class="text-secondary small">
                        <i class="fa-solid fa-building me-1"></i>Client: <strong><?php echo htmlspecialchars($post->client_name ?: 'Global'); ?></strong>
                        <?php if (!empty($post->campaign_name)): ?>
                            <span class="mx-2">•</span>
                            <i class="fa-solid fa-bullhorn me-1 text-info"></i>Campaign: <strong><?php echo htmlspecialchars($post->campaign_name); ?></strong>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="index.php?route=posts/index" class="btn btn-outline-light btn-sm px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to Registry
                </a>
            </div>

            <?php if (!empty($post->content)): ?>
                <div class="p-3 bg-dark border border-secondary border-opacity-25 rounded text-white my-3" style="font-size:0.95rem;">
                    <?php echo nl2br(htmlspecialchars($post->content)); ?>
                </div>
            <?php endif; ?>

            <!-- Current Summary Metric Bar -->
            <div class="row g-3 mt-2">
                <div class="col-md-2 col-4">
                    <div class="p-2 bg-dark rounded border border-secondary text-center">
                        <div class="text-secondary small"><i class="fa-solid fa-heart text-danger me-1"></i>Likes</div>
                        <div class="text-white fw-bold fs-5"><?php echo number_format((int)$post->current_likes); ?></div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="p-2 bg-dark rounded border border-secondary text-center">
                        <div class="text-secondary small"><i class="fa-solid fa-comment text-info me-1"></i>Comments</div>
                        <div class="text-white fw-bold fs-5"><?php echo number_format((int)$post->current_comments); ?></div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="p-2 bg-dark rounded border border-secondary text-center">
                        <div class="text-secondary small"><i class="fa-solid fa-share text-success me-1"></i>Shares</div>
                        <div class="text-white fw-bold fs-5"><?php echo number_format((int)$post->current_shares); ?></div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="p-2 bg-dark rounded border border-secondary text-center">
                        <div class="text-secondary small"><i class="fa-solid fa-bookmark text-warning me-1"></i>Saves</div>
                        <div class="text-white fw-bold fs-5"><?php echo number_format((int)$post->current_saves); ?></div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="p-2 bg-dark rounded border border-secondary text-center">
                        <div class="text-secondary small"><i class="fa-solid fa-eye text-primary me-1"></i>Reach</div>
                        <div class="text-white fw-bold fs-5"><?php echo number_format((int)$post->current_reach); ?></div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="p-2 bg-dark rounded border border-warning text-center">
                        <div class="text-warning small"><i class="fa-solid fa-chart-line me-1"></i>ER (%)</div>
                        <div class="text-warning fw-bold fs-5"><?php echo number_format((float)$post->current_engagement_rate, 2); ?>%</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audience Demographics & Native Insights Breakdown Card -->
        <div class="pulse-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-white mb-1"><i class="fa-solid fa-chart-pie me-2 text-warning"></i>Audience Insights & Demographics</h5>
                    <div class="text-secondary small">Native platform audience breakdown snapshot</div>
                </div>
                <?php if (!empty($demographics_history) && count($demographics_history) > 1): ?>
                    <?php 
                        $firstDemo = $demographics_history[0];
                        $lastDemo = end($demographics_history);
                        $nfDiff = (float)$lastDemo->non_followers_pct - (float)$firstDemo->followers_pct;
                        $diffSign = $nfDiff >= 0 ? '+' : '';
                    ?>
                    <span class="badge bg-info bg-opacity-10 text-info border border-info">
                        <i class="fa-solid fa-arrow-trend-up me-1"></i>Non-follower reach shifted <?php echo $diffSign . number_format($nfDiff, 1); ?>% over history
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($demographics)): ?>
                <?php 
                    $fPct = (float)$demographics->followers_pct;
                    $nfPct = (float)$demographics->non_followers_pct;
                ?>
                <!-- Followers vs Non-Followers Stacked Bar -->
                <div class="p-3 bg-dark border border-secondary border-opacity-50 rounded-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fw-semibold text-white">Viewer Type Split</div>
                        <div class="text-secondary small">Recorded at <?php echo formatToLocalTime($demographics->captured_at, 'M d, Y H:i'); ?></div>
                    </div>
                    <div class="progress" style="height: 16px; border-radius: 8px; background: #2a2e3d;">
                        <div class="progress-bar bg-info" style="width: <?php echo $fPct; ?>%" title="Followers: <?php echo $fPct; ?>%"></div>
                        <div class="progress-bar" style="width: <?php echo $nfPct; ?>%; background-color: #8a2be2;" title="Non-followers: <?php echo $nfPct; ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between text-white mt-2 font-weight-bold" style="font-size:0.88rem;">
                        <span class="text-info"><i class="fa-solid fa-user-check me-1"></i>Followers: <?php echo $fPct; ?>%</span>
                        <span style="color:#b57edc;"><i class="fa-solid fa-user-plus me-1"></i>Non-Followers: <?php echo $nfPct; ?>%</span>
                    </div>
                </div>

                <!-- Pill-button Navigation: Age | Country | Gender -->
                <div class="d-flex justify-content-center mb-3">
                    <div class="btn-group" role="group" aria-label="Audience Demographic Toggle">
                        <button type="button" class="btn btn-outline-info active py-2 px-4 demo-toggle-btn" data-target="#demo-age">
                            <i class="fa-solid fa-cake-candles me-2"></i>Age
                        </button>
                        <button type="button" class="btn btn-outline-info py-2 px-4 demo-toggle-btn" data-target="#demo-country">
                            <i class="fa-solid fa-earth-americas me-2"></i>Country
                        </button>
                        <button type="button" class="btn btn-outline-info py-2 px-4 demo-toggle-btn" data-target="#demo-gender">
                            <i class="fa-solid fa-venus-mars me-2"></i>Gender
                        </button>
                    </div>
                </div>

                <!-- Demographic View Panes -->
                <div class="p-3 bg-dark border border-secondary border-opacity-25 rounded-3">
                    <!-- 1. Age Pane -->
                    <div id="demo-age" class="demo-pane">
                        <h6 class="text-secondary mb-3">Age Distribution Brackets</h6>
                        <?php if (!empty($demographics->age)): ?>
                            <div class="row g-3">
                                <?php foreach ($demographics->age as $ab): ?>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between small text-white mb-1">
                                            <span>Age <?php echo htmlspecialchars($ab->age_bracket); ?></span>
                                            <strong><?php echo number_format((float)$ab->percentage, 1); ?>%</strong>
                                        </div>
                                        <div class="progress" style="height: 10px; border-radius: 5px; background: #2a2e3d;">
                                            <div class="progress-bar bg-warning" style="width: <?php echo (float)$ab->percentage; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-secondary small text-center py-3">No age breakdown recorded for this post.</div>
                        <?php endif; ?>
                    </div>

                    <!-- 2. Country Pane -->
                    <div id="demo-country" class="demo-pane" style="display: none;">
                        <h6 class="text-secondary mb-3">Top Geographic Reach</h6>
                        <?php if (!empty($demographics->countries)): ?>
                            <div class="row g-3">
                                <?php foreach ($demographics->countries as $cb): ?>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between small text-white mb-1">
                                            <span><i class="fa-solid fa-location-dot text-primary me-2"></i><?php echo htmlspecialchars($cb->country); ?></span>
                                            <strong><?php echo number_format((float)$cb->percentage, 1); ?>%</strong>
                                        </div>
                                        <div class="progress" style="height: 10px; border-radius: 5px; background: #2a2e3d;">
                                            <div class="progress-bar bg-primary" style="width: <?php echo (float)$cb->percentage; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-secondary small text-center py-3">No geographic country breakdown recorded for this post.</div>
                        <?php endif; ?>
                    </div>

                    <!-- 3. Gender Pane -->
                    <div id="demo-gender" class="demo-pane" style="display: none;">
                        <h6 class="text-secondary mb-3">Gender Distribution</h6>
                        <?php if (!empty($demographics->gender)): ?>
                            <div class="row g-3">
                                <?php foreach ($demographics->gender as $gb): ?>
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between small text-white mb-1">
                                            <span><?php echo htmlspecialchars($gb->gender); ?></span>
                                            <strong><?php echo number_format((float)$gb->percentage, 1); ?>%</strong>
                                        </div>
                                        <div class="progress" style="height: 10px; border-radius: 5px; background: #2a2e3d;">
                                            <div class="progress-bar bg-danger" style="width: <?php echo (float)$gb->percentage; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-secondary small text-center py-3">No gender breakdown recorded for this post.</div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="text-center py-4 text-secondary">
                    <i class="fa-solid fa-users-slash fs-2 mb-2 d-block"></i>
                    No demographic snapshot recorded yet for this post. Click <strong>Update Engagement</strong> on the registry list to log audience insights.
                </div>
            <?php endif; ?>
        </div>

        <!-- Historical Engagement Snapshots Log -->
        <div class="pulse-card">
            <h5 class="text-white mb-3">
                <i class="fa-solid fa-clock-rotate-left me-2 text-warning"></i>Time-Series Engagement Snapshots History
            </h5>
            <div class="text-secondary small mb-4">Chronological log of manual & automated performance snapshots</div>

            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle border-secondary">
                    <thead>
                        <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                            <th>Timestamp</th>
                            <th class="text-end">Likes</th>
                            <th class="text-end">Comments</th>
                            <th class="text-end">Shares</th>
                            <th class="text-end">Saves</th>
                            <th class="text-end">Reach</th>
                            <th class="text-end">Impressions</th>
                            <th class="text-center">Engagement Rate</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-secondary">No engagement snapshots recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $h): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td class="text-white font-monospace"><?php echo formatToLocalTime($h->captured_at, 'Y-m-d H:i'); ?></td>
                                    <td class="text-end text-danger fw-bold"><?php echo number_format((int)$h->likes); ?></td>
                                    <td class="text-end text-info"><?php echo number_format((int)$h->comments); ?></td>
                                    <td class="text-end text-success"><?php echo number_format((int)$h->shares); ?></td>
                                    <td class="text-end text-warning"><?php echo number_format((int)$h->saves); ?></td>
                                    <td class="text-end text-white fw-bold"><?php echo number_format((int)$h->reach); ?></td>
                                    <td class="text-end text-secondary"><?php echo number_format((int)$h->impressions); ?></td>
                                    <td class="text-center text-warning fw-bold"><?php echo number_format((float)$h->engagement_rate, 2); ?>%</td>
                                    <td class="text-secondary small"><?php echo htmlspecialchars($h->recorded_by_name ?: 'System'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.demo-toggle-btn').on('click', function() {
        $('.demo-toggle-btn').removeClass('active');
        $(this).addClass('active');

        const target = $(this).data('target');
        $('.demo-pane').hide();
        $(target).fadeIn(200);
    });
});
</script>
