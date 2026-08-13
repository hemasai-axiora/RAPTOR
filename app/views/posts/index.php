<?php $isAdmin = strtolower($_SESSION['user_role'] ?? '') === 'admin'; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="pulse-card mb-4">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-rectangle-list me-2 text-primary"></i>Content Management & Analytics</h4>
            <div class="text-secondary small">Track published content, post identity (Post ID), audience demographics (Followers split, Age, Gender, Top Countries), and engagement metrics</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-danger btn-sm px-3 py-2" id="btn-bulk-delete" style="display: none; border-radius: 8px;" title="Delete Selected Posts">
                    <i class="fa-solid fa-trash-can me-2"></i>Delete Selected (<span id="selected-posts-count">0</span>)
                </button>
            <?php endif; ?>
            <a href="index.php?route=calendar/index" class="btn btn-outline-secondary btn-sm px-3 py-2" style="border-radius: 8px;">
                <i class="fa-solid fa-calendar-days me-2"></i>Visual Calendar View
            </a>
            <?php if ($can_edit): ?>
                <a href="index.php?route=posts/add" class="btn btn-primary btn-sm px-3 py-2" style="background: var(--primary); border: none; border-radius: 8px;">
                    <i class="fa-solid fa-plus me-2"></i>Create Post
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters & Search Bar -->
        <div class="col-md-2">
            <select name="created_by_user_id" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="">All Authors</option>
                <?php if (!empty($creators)): ?>
                    <?php foreach ($creators as $creator): ?>
                        <option value="<?php echo $creator->user_id; ?>" <?php echo (string)($filters['created_by_user_id'] ?? '') === (string)$creator->user_id ? 'selected' : ''; ?>>
                            👤 <?php echo htmlspecialchars($creator->name); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="client_id" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="">All Clients</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client->client_id; ?>" <?php echo ($filters['client_id'] == $client->client_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($client->company_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="platform" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="">All Platforms</option>
                <?php 
                    $platforms = ['Instagram', 'LinkedIn', 'Facebook', 'X/Twitter', 'YouTube', 'TikTok', 'Pinterest', 'Blog', 'Other'];
                    foreach ($platforms as $p): 
                ?>
                    <option value="<?php echo $p; ?>" <?php echo ($filters['platform'] === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="content_type" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="">All Content Types</option>
                <?php 
                    $contentTypes = ['Image Post', 'Video', 'Reel/Short', 'Carousel', 'Story', 'Blog Article', 'Infographic', 'GIF', 'Live Stream', 'Other'];
                    foreach ($contentTypes as $ct): 
                ?>
                    <option value="<?php echo $ct; ?>" <?php echo ($filters['content_type'] === $ct) ? 'selected' : ''; ?>><?php echo $ct; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="sort" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="published_at" <?php echo ($filters['sort'] === 'published_at') ? 'selected' : ''; ?>>Sort by: Date</option>
                <option value="engagement_rate" <?php echo ($filters['sort'] === 'engagement_rate') ? 'selected' : ''; ?>>Sort by: Engagement Rate</option>
                <option value="reach" <?php echo ($filters['sort'] === 'reach') ? 'selected' : ''; ?>>Sort by: Reach</option>
            </select>
        </div>

        <div class="col-md-2">
            <div class="input-group">
                <input type="text" name="search" class="form-control bg-dark border-secondary text-white" placeholder="Search Post ID / Title" value="<?php echo htmlspecialchars($filters['search']); ?>">
                <button type="submit" class="btn btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
        </div>
    </form>

    <!-- Weighted Audience Demographics Rollup Bar -->
    <?php if (!empty($aggregated_demographics) && !empty($aggregated_demographics['summary']->total_reach)): ?>
        <?php 
            $aggSum = $aggregated_demographics['summary'];
            $aggF = round((float)($aggSum->weighted_followers_pct ?? 0), 1);
            $aggNF = round((float)($aggSum->weighted_non_followers_pct ?? 0), 1);
        ?>
        <div class="p-3 bg-dark border border-primary border-opacity-25 rounded-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-semibold text-primary">
                    <i class="fa-solid fa-chart-pie me-2"></i>Reach-Weighted Audience Demographics Rollup
                    <?php if ($filters['client_id']): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary ms-2">Filtered Client</span>
                    <?php endif; ?>
                </div>
                <div class="text-secondary small">Total Reach Evaluated: <strong><?php echo number_format((int)$aggSum->total_reach); ?></strong></div>
            </div>
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="text-secondary small mb-1">Followers vs Non-Followers Reach Split</div>
                    <div class="progress" style="height: 12px; border-radius: 6px; background: #2a2e3d;">
                        <div class="progress-bar bg-info" style="width: <?php echo $aggF; ?>%" title="Followers: <?php echo $aggF; ?>%"></div>
                        <div class="progress-bar bg-purple" style="width: <?php echo $aggNF; ?>%; background-color: #8a2be2;" title="Non-followers: <?php echo $aggNF; ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between small text-white mt-1" style="font-size:0.78rem;">
                        <span class="text-info"><i class="fa-solid fa-user-check me-1"></i>Followers: <?php echo $aggF; ?>%</span>
                        <span style="color:#b57edc;"><i class="fa-solid fa-user-plus me-1"></i>Non-Followers: <?php echo $aggNF; ?>%</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-secondary small mb-1">Top Age Brackets</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (!empty($aggregated_demographics['age'])): ?>
                            <?php foreach (array_slice($aggregated_demographics['age'], 0, 3) as $ab): ?>
                                <span class="badge bg-secondary-subtle text-white border border-secondary-subtle" style="font-size:0.75rem;">
                                    <?php echo htmlspecialchars($ab->age_bracket); ?>: <strong><?php echo $ab->weighted_pct; ?>%</strong>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-secondary small">No age data</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-secondary small mb-1">Top Geographic Reach</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (!empty($aggregated_demographics['countries'])): ?>
                            <?php foreach (array_slice($aggregated_demographics['countries'], 0, 3) as $cb): ?>
                                <span class="badge bg-dark border border-secondary text-warning" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-location-dot me-1"></i><?php echo htmlspecialchars($cb->country); ?>: <strong><?php echo $cb->weighted_pct; ?>%</strong>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-secondary small">No country data</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary" id="posts-table">
            <thead>
                <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                    <?php if ($isAdmin): ?>
                        <th style="width: 40px;" class="text-center">
                            <input type="checkbox" id="select-all-posts" class="form-check-input" title="Select All Posts">
                        </th>
                    <?php endif; ?>
                    <th>Post ID</th>
                    <th>Content & Title</th>
                    <th>Platform & Type</th>
                    <th>Client & Campaign</th>
                    <th>Created By</th>
                    <th class="text-center">Engagement At-a-Glance</th>
                    <th class="text-center">Audience Split (F / NF)</th>
                    <th class="text-center">Engagement Rate</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)): ?>
                    <tr>
                        <td colspan="<?php echo $isAdmin ? '11' : '10'; ?>" class="text-center py-4 text-secondary">No content posts found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <?php 
                            $platformLower = strtolower(explode('/', $post->platform)[0]);
                            $iconClass = 'fa-globe';
                            if (strpos($platformLower, 'instagram') !== false) $iconClass = 'fa-instagram text-danger';
                            elseif (strpos($platformLower, 'linkedin') !== false) $iconClass = 'fa-linkedin text-info';
                            elseif (strpos($platformLower, 'facebook') !== false) $iconClass = 'fa-facebook text-primary';
                            elseif (strpos($platformLower, 'youtube') !== false) $iconClass = 'fa-youtube text-danger';
                            elseif (strpos($platformLower, 'x') !== false || strpos($platformLower, 'twitter') !== false) $iconClass = 'fa-x-twitter text-white';

                            $er = (float)$post->current_engagement_rate;
                            $erClass = 'text-white';
                            if ($er >= 5.0) $erClass = 'text-success font-weight-bold';
                            elseif ($er >= 2.0) $erClass = 'text-info';
                            elseif ($er > 0) $erClass = 'text-warning';

                            $fPct = round((float)($post->current_followers_pct ?? 0));
                            $nfPct = round((float)($post->current_non_followers_pct ?? 0));
                            if ($fPct == 0 && $nfPct == 0) {
                                $fPct = 50;
                                $nfPct = 50;
                                $hasDemo = false;
                            } else {
                                $hasDemo = true;
                            }
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <?php if ($isAdmin): ?>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input post-select-checkbox" value="<?php echo $post->post_id; ?>" data-code="<?php echo htmlspecialchars($post->post_code ?: ('PST-' . date('Y') . '-' . sprintf('%05d', $post->post_id))); ?>">
                                </td>
                            <?php endif; ?>
                            <td>
                                <span class="badge bg-dark border border-secondary text-primary font-monospace" style="font-size:0.8rem;">
                                    <?php echo htmlspecialchars($post->post_code ?: ('PST-' . date('Y') . '-' . sprintf('%05d', $post->post_id))); ?>
                                </span>
                            </td>
                            <td>
                                <div class="font-weight-bold text-white mb-1"><?php echo htmlspecialchars($post->title ?: 'Untitled Post'); ?></div>
                                <div class="text-secondary small text-truncate" style="max-width: 220px;">
                                    <?php echo htmlspecialchars(mb_strimwidth($post->content ?? '', 0, 70, '...')); ?>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-brands <?php echo $iconClass; ?> fs-5"></i>
                                    <div>
                                        <div class="small fw-semibold text-white"><?php echo htmlspecialchars($post->platform); ?></div>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size:0.7rem;">
                                            <?php echo htmlspecialchars($post->content_type); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-white small fw-semibold"><?php echo htmlspecialchars($post->client_name ?: 'Global'); ?></div>
                                <?php if (!empty($post->campaign_name)): ?>
                                    <div class="text-secondary" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-bullhorn me-1 text-info"></i><?php echo htmlspecialchars($post->campaign_name); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(37, 99, 235, 0.15); color: #60a5fa; border: 1px solid rgba(37, 99, 235, 0.3); font-weight: 600; font-size: 0.78rem;">
                                    <i class="fa-solid fa-user me-1"></i><?php echo htmlspecialchars($post->creator_name ?: 'System Admin'); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-2 text-secondary small bg-dark p-1 px-2 rounded border border-secondary border-opacity-25" style="font-size: 0.78rem;">
                                    <span title="Reach"><i class="fa-solid fa-eye text-warning me-1"></i><?php echo number_format((int)$post->current_reach); ?></span>
                                    <span title="Likes"><i class="fa-solid fa-heart text-danger me-1"></i><?php echo number_format((int)$post->current_likes); ?></span>
                                    <span title="Comments"><i class="fa-solid fa-comment text-info me-1"></i><?php echo number_format((int)$post->current_comments); ?></span>
                                    <span title="Shares"><i class="fa-solid fa-share text-success me-1"></i><?php echo number_format((int)$post->current_shares); ?></span>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($hasDemo): ?>
                                    <div class="d-inline-block" style="min-width: 110px;">
                                        <div class="d-flex justify-content-between small mb-1" style="font-size:0.73rem;">
                                            <span class="text-info fw-semibold"><?php echo $fPct; ?>% F</span>
                                            <span style="color:#b57edc;" class="fw-semibold"><?php echo $nfPct; ?>% NF</span>
                                        </div>
                                        <div class="progress" style="height: 6px; border-radius: 3px; background: #2a2e3d;">
                                            <div class="progress-bar bg-info" style="width: <?php echo $fPct; ?>%"></div>
                                            <div class="progress-bar" style="width: <?php echo $nfPct; ?>%; background-color: #8a2be2;"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-dark text-secondary border border-secondary border-opacity-50" style="font-size:0.7rem;">Not Logged</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center <?php echo $erClass; ?> font-weight-bold fs-6">
                                <?php echo number_format($er, 2); ?>%
                            </td>
                            <td class="text-secondary small">
                                <?php echo $post->published_at ? date('M d, Y', strtotime($post->published_at)) : 'Scheduled'; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn btn-outline-warning btn-sm btn-update-engagement" 
                                            data-id="<?php echo $post->post_id; ?>"
                                            data-code="<?php echo htmlspecialchars($post->post_code ?: ('PST-2026-' . sprintf('%05d', $post->post_id))); ?>"
                                            data-likes="<?php echo (int)$post->current_likes; ?>"
                                            data-comments="<?php echo (int)$post->current_comments; ?>"
                                            data-shares="<?php echo (int)$post->current_shares; ?>"
                                            data-saves="<?php echo (int)$post->current_saves; ?>"
                                            data-reach="<?php echo (int)$post->current_reach; ?>"
                                            data-impressions="<?php echo (int)$post->current_impressions; ?>"
                                            data-clicks="<?php echo (int)$post->current_clicks; ?>"
                                            data-views="<?php echo (int)$post->current_video_views; ?>"
                                            data-fpct="<?php echo (float)($post->current_followers_pct ?? 0); ?>"
                                            data-nfpct="<?php echo (float)($post->current_non_followers_pct ?? 0); ?>"
                                            data-type="<?php echo htmlspecialchars($post->content_type); ?>"
                                            title="Update Engagement & Audience Demographics">
                                        <i class="fa-solid fa-chart-line"></i>
                                    </button>
                                    <a href="index.php?route=posts/detail/<?php echo $post->post_id; ?>" class="btn btn-outline-info btn-sm" title="View Insights & Audience Breakdown">
                                        <i class="fa-solid fa-chart-pie"></i>
                                    </a>
                                    <?php if ($can_edit): ?>
                                        <a href="index.php?route=posts/edit/<?php echo $post->post_id; ?>" class="btn btn-outline-light btn-sm" title="Edit Post">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (strtolower($_SESSION['user_role'] ?? '') === 'admin'): ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-delete-post" 
                                                data-id="<?php echo $post->post_id; ?>"
                                                data-code="<?php echo htmlspecialchars($post->post_code ?: ('PST-2026-' . sprintf('%05d', $post->post_id))); ?>"
                                                data-title="<?php echo htmlspecialchars($post->title ?: 'Untitled Post'); ?>"
                                                title="Delete Post (Admin Only)">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Update Engagement & Audience Demographics -->
<div class="modal fade" id="updateEngagementModal" tabindex="-1" aria-labelledby="updateEngagementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="updateEngagementModalLabel">
                    <i class="fa-solid fa-chart-line text-warning me-2"></i>Update Post Metrics & Insights
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="index.php?route=posts/updateEngagement" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="post_id" id="modal_post_id" value="">
                
                <div class="modal-body">
                    <div class="alert alert-dark border border-secondary text-secondary small mb-3">
                        Logging performance & demographic snapshot for Post <strong class="text-primary font-monospace" id="modal_post_code">PST-2026-00001</strong>.
                    </div>

                    <!-- Modal Nav Tabs -->
                    <ul class="nav nav-tabs border-secondary mb-3" id="modalTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active text-white" id="tab-metrics-btn" data-bs-toggle="tab" data-bs-target="#tab-metrics" type="button" role="tab">
                                <i class="fa-solid fa-chart-simple me-2 text-info"></i>Engagement Metrics
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-white" id="tab-audience-btn" data-bs-toggle="tab" data-bs-target="#tab-audience" type="button" role="tab">
                                <i class="fa-solid fa-users me-2 text-warning"></i>Audience Demographics
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="modalTabContent">
                        <!-- Tab 1: Engagement Metrics -->
                        <div class="tab-pane fade show active" id="tab-metrics" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <label for="modal_reach" class="form-label text-secondary"><i class="fa-solid fa-eye text-primary me-1"></i>Reach</label>
                                    <input type="number" name="reach" id="modal_reach" class="form-control bg-dark border-secondary text-white" value="0" min="0">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="modal_impressions" class="form-label text-secondary"><i class="fa-solid fa-chart-simple text-info me-1"></i>Impressions</label>
                                    <input type="number" name="impressions" id="modal_impressions" class="form-control bg-dark border-secondary text-white" value="0" min="0">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="modal_likes" class="form-label text-secondary"><i class="fa-solid fa-heart text-danger me-1"></i>Likes</label>
                                    <input type="number" name="likes" id="modal_likes" class="form-control bg-dark border-secondary text-white" value="0" min="0">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="modal_comments" class="form-label text-secondary"><i class="fa-solid fa-comment text-info me-1"></i>Comments</label>
                                    <input type="number" name="comments" id="modal_comments" class="form-control bg-dark border-secondary text-white" value="0" min="0">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="modal_shares" class="form-label text-secondary"><i class="fa-solid fa-share text-success me-1"></i>Shares</label>
                                    <input type="number" name="shares" id="modal_shares" class="form-control bg-dark border-secondary text-white" value="0" min="0">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="modal_saves" class="form-label text-secondary"><i class="fa-solid fa-bookmark text-warning me-1"></i>Saves</label>
                                    <input type="number" name="saves" id="modal_saves" class="form-control bg-dark border-secondary text-white" value="0" min="0">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="modal_clicks" class="form-label text-secondary"><i class="fa-solid fa-arrow-pointer text-secondary me-1"></i>Link Clicks</label>
                                    <input type="number" name="clicks" id="modal_clicks" class="form-control bg-dark border-secondary text-white" value="0" min="0">
                                </div>
                                <div class="col-6 col-md-3" id="modal_video_views_container">
                                    <label for="modal_video_views" class="form-label text-secondary"><i class="fa-solid fa-play text-danger me-1"></i>Video Views / Plays</label>
                                    <input type="number" name="video_views" id="modal_video_views" class="form-control bg-dark border-secondary text-white" value="0" min="0">
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Audience Demographics -->
                        <div class="tab-pane fade" id="tab-audience" role="tabpanel">
                            <!-- 1. Followers vs Non-Followers Split -->
                            <div class="p-3 bg-dark border border-secondary rounded mb-3">
                                <div class="fw-semibold text-white mb-2"><i class="fa-solid fa-users-between-lines me-2 text-info"></i>Followers vs Non-Followers Reach Split (%)</div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label for="modal_followers_pct" class="form-label text-secondary small">Followers (%)</label>
                                        <input type="number" step="0.1" name="followers_pct" id="modal_followers_pct" class="form-control bg-dark border-secondary text-white" value="35.0" min="0" max="100">
                                    </div>
                                    <div class="col-6">
                                        <label for="modal_non_followers_pct" class="form-label text-secondary small">Non-Followers (%)</label>
                                        <input type="number" step="0.1" name="non_followers_pct" id="modal_non_followers_pct" class="form-control bg-dark border-secondary text-white" value="65.0" min="0" max="100">
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Age Breakdown -->
                            <div class="p-3 bg-dark border border-secondary rounded mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="fw-semibold text-white"><i class="fa-solid fa-cake-candles me-2 text-warning"></i>Age Breakdown (%)</div>
                                    <span class="badge bg-secondary" id="age_sum_badge">Sum: 100%</span>
                                </div>
                                <div class="row g-2">
                                    <div class="col">
                                        <label class="form-label text-secondary small">13-17</label>
                                        <input type="number" step="0.1" name="age_13_17" class="form-control form-control-sm age-input bg-dark border-secondary text-white" value="2.0">
                                    </div>
                                    <div class="col">
                                        <label class="form-label text-secondary small">18-24</label>
                                        <input type="number" step="0.1" name="age_18_24" class="form-control form-control-sm age-input bg-dark border-secondary text-white" value="28.0">
                                    </div>
                                    <div class="col">
                                        <label class="form-label text-secondary small">25-34</label>
                                        <input type="number" step="0.1" name="age_25_34" class="form-control form-control-sm age-input bg-dark border-secondary text-white" value="45.0">
                                    </div>
                                    <div class="col">
                                        <label class="form-label text-secondary small">35-44</label>
                                        <input type="number" step="0.1" name="age_35_44" class="form-control form-control-sm age-input bg-dark border-secondary text-white" value="15.0">
                                    </div>
                                    <div class="col">
                                        <label class="form-label text-secondary small">45-54</label>
                                        <input type="number" step="0.1" name="age_45_54" class="form-control form-control-sm age-input bg-dark border-secondary text-white" value="6.0">
                                    </div>
                                    <div class="col">
                                        <label class="form-label text-secondary small">55-64</label>
                                        <input type="number" step="0.1" name="age_55_64" class="form-control form-control-sm age-input bg-dark border-secondary text-white" value="3.0">
                                    </div>
                                    <div class="col">
                                        <label class="form-label text-secondary small">65+</label>
                                        <input type="number" step="0.1" name="age_65_plus" class="form-control form-control-sm age-input bg-dark border-secondary text-white" value="1.0">
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Gender Breakdown -->
                            <div class="p-3 bg-dark border border-secondary rounded mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="fw-semibold text-white"><i class="fa-solid fa-venus-mars me-2 text-danger"></i>Gender Breakdown (%)</div>
                                    <span class="badge bg-secondary" id="gender_sum_badge">Sum: 100%</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-4">
                                        <label class="form-label text-secondary small">Men (%)</label>
                                        <input type="number" step="0.1" name="gender_men" id="gender_men" class="form-control gender-input bg-dark border-secondary text-white" value="58.0">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label text-secondary small">Women (%)</label>
                                        <input type="number" step="0.1" name="gender_women" id="gender_women" class="form-control gender-input bg-dark border-secondary text-white" value="40.0">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label text-secondary small">Other (%)</label>
                                        <input type="number" step="0.1" name="gender_other" id="gender_other" class="form-control gender-input bg-dark border-secondary text-white" value="2.0">
                                    </div>
                                </div>
                            </div>

                            <!-- 4. Top Countries Breakdown -->
                            <div class="p-3 bg-dark border border-secondary rounded">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="fw-semibold text-white"><i class="fa-solid fa-earth-americas me-2 text-primary"></i>Top Geographic Countries</div>
                                    <button type="button" class="btn btn-outline-info btn-sm" id="btn-add-country"><i class="fa-solid fa-plus me-1"></i>Add Country</button>
                                </div>
                                <div id="country-rows-container">
                                    <div class="row g-2 mb-2 country-row">
                                        <div class="col-7">
                                            <input type="text" name="country_name[]" class="form-control form-control-sm bg-dark border-secondary text-white" value="United States" placeholder="Country Name">
                                        </div>
                                        <div class="col-4">
                                            <input type="number" step="0.1" name="country_pct[]" class="form-control form-control-sm bg-dark border-secondary text-white" value="42.5" placeholder="Share %">
                                        </div>
                                        <div class="col-1 text-end">
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-remove-country"><i class="fa-solid fa-xmark"></i></button>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2 country-row">
                                        <div class="col-7">
                                            <input type="text" name="country_name[]" class="form-control form-control-sm bg-dark border-secondary text-white" value="India" placeholder="Country Name">
                                        </div>
                                        <div class="col-4">
                                            <input type="number" step="0.1" name="country_pct[]" class="form-control form-control-sm bg-dark border-secondary text-white" value="25.0" placeholder="Share %">
                                        </div>
                                        <div class="col-1 text-end">
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-remove-country"><i class="fa-solid fa-xmark"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-semibold px-4"><i class="fa-solid fa-save me-1"></i>Save Metrics & Insights</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.btn-update-engagement').on('click', function() {
        const btn = $(this);
        $('#modal_post_id').val(btn.data('id'));
        $('#modal_post_code').text(btn.data('code'));
        $('#modal_likes').val(btn.data('likes'));
        $('#modal_comments').val(btn.data('comments'));
        $('#modal_shares').val(btn.data('shares'));
        $('#modal_saves').val(btn.data('saves'));
        $('#modal_reach').val(btn.data('reach'));
        $('#modal_impressions').val(btn.data('impressions'));
        $('#modal_clicks').val(btn.data('clicks'));
        $('#modal_video_views').val(btn.data('views'));

        const fpct = parseFloat(btn.data('fpct')) || 35.0;
        const nfpct = parseFloat(btn.data('nfpct')) || 65.0;
        $('#modal_followers_pct').val(fpct);
        $('#modal_non_followers_pct').val(nfpct);

        const modal = new bootstrap.Modal(document.getElementById('updateEngagementModal'));
        modal.show();
    });

    // Auto-balance Followers vs Non-followers
    $('#modal_followers_pct').on('input', function() {
        const val = parseFloat($(this).val()) || 0;
        $('#modal_non_followers_pct').val((100 - val).toFixed(1));
    });

    // Sum calculation helpers
    function calcAgeSum() {
        let sum = 0;
        $('.age-input').each(function() { sum += parseFloat($(this).val()) || 0; });
        $('#age_sum_badge').text('Sum: ' + sum.toFixed(1) + '%').toggleClass('bg-danger', Math.abs(sum - 100) > 2).toggleClass('bg-success', Math.abs(sum - 100) <= 2);
    }
    $('.age-input').on('input', calcAgeSum);

    function calcGenderSum() {
        let sum = 0;
        $('.gender-input').each(function() { sum += parseFloat($(this).val()) || 0; });
        $('#gender_sum_badge').text('Sum: ' + sum.toFixed(1) + '%').toggleClass('bg-danger', Math.abs(sum - 100) > 2).toggleClass('bg-success', Math.abs(sum - 100) <= 2);
    }
    $('.gender-input').on('input', calcGenderSum);

    // Dynamic Country Rows
    $('#btn-add-country').on('click', function() {
        const rowHtml = `
            <div class="row g-2 mb-2 country-row">
                <div class="col-7">
                    <input type="text" name="country_name[]" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="Country Name">
                </div>
                <div class="col-4">
                    <input type="number" step="0.1" name="country_pct[]" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="Share %">
                </div>
                <div class="col-1 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove-country"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
        `;
        $('#country-rows-container').append(rowHtml);
    });

    $(document).on('click', '.btn-remove-country', function() {
        $(this).closest('.country-row').remove();
    });

    // Delete Post Modal Trigger (Admin Only)
    $(document).on('click', '.btn-delete-post', function() {
        var id = $(this).data('id');
        var code = $(this).data('code');
        var title = $(this).data('title');
        $('#modal_delete_post_id').val(id);
        $('#modal_delete_post_code').text(code);
        $('#modal_delete_post_title').text(title);
        $('#deletion_remarks').val('');
        $('#deletePostModal').modal('show');
    });

    // Select All Checkbox Handler
    $('#select-all-posts').on('change', function() {
        $('.post-select-checkbox').prop('checked', $(this).prop('checked')).trigger('change');
    });

    // Post Checkbox Change Handler
    $(document).on('change', '.post-select-checkbox', function() {
        const checkedCount = $('.post-select-checkbox:checked').length;
        const totalCount = $('.post-select-checkbox').length;
        $('#select-all-posts').prop('checked', totalCount > 0 && checkedCount === totalCount);
        
        if (checkedCount > 0) {
            $('#selected-posts-count').text(checkedCount);
            $('#btn-bulk-delete').fadeIn(150);
        } else {
            $('#btn-bulk-delete').fadeOut(150);
        }
    });

    // Bulk Delete Button Handler
    $('#btn-bulk-delete').on('click', function() {
        const selectedCheckboxes = $('.post-select-checkbox:checked');
        if (selectedCheckboxes.length === 0) return;

        let container = $('#bulk_post_ids_container').empty();
        let codes = [];

        selectedCheckboxes.each(function() {
            const id = $(this).val();
            const code = $(this).data('code');
            container.append('<input type="hidden" name="post_ids[]" value="' + id + '">');
            codes.push(code);
        });

        $('#bulk_delete_count_text').text(selectedCheckboxes.length);
        $('#bulk_delete_codes_list').text(codes.join(', '));
        $('#bulk_deletion_remarks').val('');
        $('#bulkDeletePostModal').modal('show');
    });
});
</script>

<!-- Modal: Single Delete Post (Admin Only with Mandatory Remarks) -->
<div class="modal fade" id="deletePostModal" tabindex="-1" aria-labelledby="deletePostModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-danger shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-danger">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="deletePostModalLabel">
                    <i class="fa-solid fa-trash-can text-danger fs-4"></i> Delete Content Post
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=posts/delete" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="post_id" id="modal_delete_post_id" value="">
                
                <div class="modal-body">
                    <div class="alert alert-danger bg-danger bg-opacity-10 border-danger text-danger mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> You are deleting Post <strong class="font-monospace text-white" id="modal_delete_post_code"></strong> (<span id="modal_delete_post_title"></span>). This action will permanently remove the record from active view.
                    </div>

                    <div class="mb-3">
                        <label for="deletion_remarks" class="form-label text-white fw-bold">Mandatory Deletion Remarks / Reason *</label>
                        <textarea name="deletion_remarks" id="deletion_remarks" class="form-control bg-dark text-white border-secondary" rows="3" placeholder="Specify mandatory reason for deletion (e.g. Duplicate entry, Client requested cancellation...)" required></textarea>
                        <small class="text-secondary mt-1 d-block"><i class="fa-solid fa-shield-halved text-info me-1"></i>Remarks will be permanently saved in the security audit log database (`deleted_posts_log`).</small>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold">
                        <i class="fa-solid fa-trash me-1"></i> Confirm & Delete Post
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Bulk Delete Posts (Admin Only with Mandatory Remarks) -->
<div class="modal fade" id="bulkDeletePostModal" tabindex="-1" aria-labelledby="bulkDeletePostModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-danger shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-danger">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="bulkDeletePostModalLabel">
                    <i class="fa-solid fa-trash-can text-danger fs-4"></i> Bulk Delete Content Posts
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=posts/deleteMultiple" method="POST" id="bulkDeleteForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div id="bulk_post_ids_container"></div>
                
                <div class="modal-body">
                    <div class="alert alert-danger bg-danger bg-opacity-10 border-danger text-danger mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> You are deleting <strong class="text-white" id="bulk_delete_count_text">0</strong> selected post(s): <span id="bulk_delete_codes_list" class="font-monospace text-warning"></span>. This action will permanently remove these records.
                    </div>

                    <div class="mb-3">
                        <label for="bulk_deletion_remarks" class="form-label text-white fw-bold">Mandatory Deletion Remarks / Reason *</label>
                        <textarea name="deletion_remarks" id="bulk_deletion_remarks" class="form-control bg-dark text-white border-secondary" rows="3" placeholder="Specify mandatory reason for bulk deletion (e.g. Bulk removal of outdated campaigns, Client requested cancellation...)" required></textarea>
                        <small class="text-secondary mt-1 d-block"><i class="fa-solid fa-shield-halved text-info me-1"></i>Remarks will be permanently saved in the security audit log database (`deleted_posts_log`).</small>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold">
                        <i class="fa-solid fa-trash me-1"></i> Confirm & Delete Selected Posts
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

