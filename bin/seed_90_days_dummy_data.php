<?php
// Comprehensive 90-Day Dummy Data Seeder for Raptor CRM
// Run via CLI: php bin/seed_90_days_dummy_data.php

require_once dirname(dirname(__FILE__)) . '/app/config/config.php';
require_once dirname(dirname(__FILE__)) . '/app/core/Database.php';

echo "=== Raptor CRM 90-Day Dummy Data Seeder ===\n";

try {
    $db = Database::getInstance()->getConnection();
    echo "[OK] Connected to database.\n";

    // Disable foreign key checks for clearing tables
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    echo "[INFO] Cleaning old database tables...\n";
    $tablesToTruncate = [
        'users', 'employees', 'clients', 'campaigns', 'leads', 
        'customer_journey_log', 'social_accounts', 'posts', 
        'post_analytics', 'channel_daily_metrics', 'best_posting_time_metrics', 
        'audience_demographics_snapshots', 'audience_interests_snapshots', 
        'brand_sentiment_logs', 'competitor_benchmarks', 'web_behavior_sessions', 
        'attribution_touchpoints', 'tasks', 'invoices', 'payments', 
        'smart_alerts', 'notifications', 'calendar_events', 'daily_activity_logs'
    ];
    foreach ($tablesToTruncate as $t) {
        $db->exec("TRUNCATE TABLE $t;");
    }
    
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "[OK] Cleaned database tables.\n";

    // Fetch Role IDs
    $roleIds = [];
    $rolesQuery = $db->query("SELECT role_id, role_name FROM roles")->fetchAll(PDO::FETCH_OBJ);
    foreach ($rolesQuery as $r) {
        $roleIds[$r->role_name] = $r->role_id;
    }

    // 1. SEED USER HIERARCHY
    echo "[INFO] Seeding user hierarchy (1 Manager, 10 Executives)...\n";
    
    // Create 1 Admin: Admin User
    $userStmt = $db->prepare("INSERT INTO users (role_id, name, email, password, status) VALUES (:rid, :name, :email, :pass, 'active')");
    $adminPass = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10]);
    $userStmt->execute([
        ':rid' => $roleIds['admin'],
        ':name' => 'Admin User',
        ':email' => 'admin@raptor.com',
        ':pass' => $adminPass
    ]);
    
    // Create 1 Manager: Sarah Jenkins
    $managerPass = password_hash('manager123', PASSWORD_BCRYPT, ['cost' => 10]);
    $userStmt->execute([
        ':rid' => $roleIds['manager'],
        ':name' => 'Sarah Jenkins',
        ':email' => 'manager@raptor.com',
        ':pass' => $managerPass
    ]);
    $managerUserId = $db->lastInsertId();

    $employeeStmt = $db->prepare("INSERT INTO employees (user_id, employee_code, department, job_title, reporting_manager_id, hire_date, status) 
                                  VALUES (:uid, :code, :dept, :title, :mgr, :date, 'active')");
    $employeeStmt->execute([
        ':uid' => $managerUserId,
        ':code' => 'EMP000',
        ':dept' => 'Digital Marketing',
        ':title' => 'Digital Marketing Manager',
        ':mgr' => null,
        ':date' => '2025-01-15'
    ]);

    // Create 10 Digital Marketing Executives
    $executives = [
        ['name' => 'Alex Rivera', 'email' => 'alex.r@raptor.com', 'phone' => '+1-555-0111', 'date' => '2025-06-10'],
        ['name' => 'Brian Chen', 'email' => 'brian.c@raptor.com', 'phone' => '+1-555-0122', 'date' => '2025-07-15'],
        ['name' => 'Chloe Smith', 'email' => 'chloe.s@raptor.com', 'phone' => '+1-555-0133', 'date' => '2025-08-20'],
        ['name' => 'David Kim', 'email' => 'david.k@raptor.com', 'phone' => '+1-555-0144', 'date' => '2025-09-05'],
        ['name' => 'Emma Watson', 'email' => 'emma.w@raptor.com', 'phone' => '+1-555-0155', 'date' => '2025-10-12'],
        ['name' => 'Fiona Gallagher', 'email' => 'fiona.g@raptor.com', 'phone' => '+1-555-0166', 'date' => '2025-11-01'],
        ['name' => 'George Brooks', 'email' => 'george.b@raptor.com', 'phone' => '+1-555-0177', 'date' => '2025-11-18'],
        ['name' => 'Hannah Abbott', 'email' => 'hannah.a@raptor.com', 'phone' => '+1-555-0188', 'date' => '2025-12-05'],
        ['name' => 'Ian Malcolm', 'email' => 'ian.m@raptor.com', 'phone' => '+1-555-0199', 'date' => '2026-01-10'],
        ['name' => 'Julia Roberts', 'email' => 'julia.r@raptor.com', 'phone' => '+1-555-0200', 'date' => '2026-02-15']
    ];

    $execPass = password_hash('executive123', PASSWORD_BCRYPT, ['cost' => 10]);
    $executiveUserIds = [];
    $execIndex = 1;
    foreach ($executives as $exec) {
        $userStmt->execute([
            ':rid' => $roleIds['analyst'], // Analyst role functions as the executive system profile
            ':name' => $exec['name'],
            ':email' => $exec['email'],
            ':pass' => $execPass
        ]);
        $uid = $db->lastInsertId();
        $executiveUserIds[] = $uid;

        $empCode = sprintf("EMP%03d", $execIndex++);
        $employeeStmt->execute([
            ':uid' => $uid,
            ':code' => $empCode,
            ':dept' => 'Digital Marketing',
            ':title' => 'Digital Marketing Executive',
            ':mgr' => $managerUserId,
            ':date' => $exec['date']
        ]);
    }
    echo "[OK] Seeded 1 Manager and 10 Digital Marketing Executives.\n";

    // 2. SEED CLIENTS & CAMPAIGNS
    echo "[INFO] Seeding clients and campaigns...\n";
    $clients = [
        [
            'company_name' => 'Axiora Tech', 'email' => 'marketing@axiora.com', 
            'phone' => '+1-555-1000', 'start' => '2025-01-01', 'end' => '2026-12-31', 
            'pkg' => 'Premium retainer - $8,000/mo', 'addr' => '100 Innovation Way, Tech District'
        ],
        [
            'company_name' => 'Global Retail Corp', 'email' => 'info@globalretail.com', 
            'phone' => '+1-555-2000', 'start' => '2025-03-15', 'end' => '2026-06-30', 
            'pkg' => 'Performance retainer - $6,500/mo', 'addr' => '456 Commerce Plaza, Retail Hub'
        ],
        [
            'company_name' => 'MedX Healthcare', 'email' => 'partners@medx.com', 
            'phone' => '+1-555-3000', 'start' => '2025-06-01', 'end' => '2026-12-31', 
            'pkg' => 'Growth Plan retainer - $10,000/mo', 'addr' => '789 Wellness Parkway, Health City'
        ]
    ];

    $clientStmt = $db->prepare("INSERT INTO clients (company_name, email, phone, status, contract_start, contract_end, package_details, billing_address) 
                                VALUES (:name, :email, :phone, 'active', :start, :end, :pkg, :addr)");
    $clientIds = [];
    foreach ($clients as $c) {
        $clientStmt->execute([
            ':name' => $c['company_name'],
            ':email' => $c['email'],
            ':phone' => $c['phone'],
            ':start' => $c['start'],
            ':end' => $c['end'],
            ':pkg' => $c['pkg'],
            ':addr' => $c['addr']
        ]);
        $clientIds[] = $db->lastInsertId();
    }

    // Add campaigns for clients
    $campaignStmt = $db->prepare("INSERT INTO campaigns (client_id, name, channel, budget, spend, revenue_influenced, start_date, status) 
                                  VALUES (:cid, :name, :chan, :budget, :spend, :rev, :sdate, :status)");
    $campaignIds = [];
    
    // Axiora Tech Campaigns
    $campaignStmt->execute([':cid' => $clientIds[0], ':name' => 'Q3 LinkedIn Lead Gen', ':chan' => 'LinkedIn', ':budget' => 20000, ':spend' => 18500, ':rev' => 45000, ':sdate' => '2026-04-10', ':status' => 'active']);
    $campaignIds[] = $db->lastInsertId();
    $campaignStmt->execute([':cid' => $clientIds[0], ':name' => 'Google Search Ads 2026', ':chan' => 'Website', ':budget' => 30000, ':spend' => 28000, ':rev' => 84000, ':sdate' => '2026-03-01', ':status' => 'active']);
    $campaignIds[] = $db->lastInsertId();

    // Global Retail Campaigns
    $campaignStmt->execute([':cid' => $clientIds[1], ':name' => 'Summer Facebook Shopping', ':chan' => 'Facebook', ':budget' => 15000, ':spend' => 14000, ':rev' => 32000, ':sdate' => '2026-05-01', ':status' => 'active']);
    $campaignIds[] = $db->lastInsertId();
    $campaignStmt->execute([':cid' => $clientIds[1], ':name' => 'Instagram Store Promo', ':chan' => 'Instagram', ':budget' => 12000, ':spend' => 11000, ':rev' => 24000, ':sdate' => '2026-05-15', ':status' => 'active']);
    $campaignIds[] = $db->lastInsertId();

    // MedX Healthcare Campaigns
    $campaignStmt->execute([':cid' => $clientIds[2], ':name' => 'YouTube Healthcare Series', ':chan' => 'YouTube', ':budget' => 25000, ':spend' => 22000, ':rev' => 55000, ':sdate' => '2026-02-10', ':status' => 'active']);
    $campaignIds[] = $db->lastInsertId();
    $campaignStmt->execute([':cid' => $clientIds[2], ':name' => 'Twitter Healthcare Discussions', ':chan' => 'X', ':budget' => 8000, ':spend' => 7500, ':rev' => 12000, ':sdate' => '2026-03-01', ':status' => 'active']);
    $campaignIds[] = $db->lastInsertId();
    
    echo "[OK] Seeded 3 clients and 6 campaigns.\n";

    // 3. SEED SOCIAL MEDIA ACCOUNTS (~50 accounts)
    echo "[INFO] Seeding social media accounts (~50 accounts)...\n";
    $platforms = ['Facebook', 'Instagram', 'LinkedIn', 'X', 'YouTube', 'WhatsApp Business', 'Snapchat'];
    $socialStmt = $db->prepare("INSERT INTO social_accounts (client_id, assigned_user_id, platform, profile_name, username, followers, following, page_likes, status, created_at) 
                                VALUES (:cid, :uid, :platform, :name, :username, :followers, :following, :likes, 'active', :created)");
    
    $socialIds = [];
    $accountCount = 0;
    foreach ($executiveUserIds as $execUid) {
        // Each executive manages 5 accounts distributed among the 3 clients
        for ($i = 0; $i < 5; $i++) {
            $clientId = $clientIds[$accountCount % count($clientIds)];
            $platform = $platforms[$accountCount % count($platforms)];
            
            $clientNameClean = str_replace(' ', '', strtolower($clients[$clientId - 1]['company_name'] ?? 'client'));
            $platformClean = strtolower($platform);
            
            $profileName = $clients[$clientId - 1]['company_name'] . " " . $platform;
            $username = "@" . $clientNameClean . "_" . str_replace(' ', '', strtolower($platform));
            
            $followers = rand(5000, 150000);
            $following = rand(100, 2000);
            $likes = in_array($platform, ['Facebook', 'Instagram', 'Snapchat']) ? rand(3000, 90000) : 0;
            
            $createdDate = date('Y-m-d', strtotime('-' . rand(180, 500) . ' days'));

            $socialStmt->execute([
                ':cid' => $clientId,
                ':uid' => $execUid,
                ':platform' => $platformClean,
                ':name' => $profileName,
                ':username' => $username,
                ':followers' => $followers,
                ':following' => $following,
                ':likes' => $likes,
                ':created' => $createdDate
            ]);
            $socialIds[] = $db->lastInsertId();
            $accountCount++;
        }
    }
    echo "[OK] Seeded $accountCount connected social media accounts.\n";

    // 4. SEED MANAGER ASSIGNED TASKS (Daily, Weekly, Monthly)
    echo "[INFO] Seeding manager assigned tasks...\n";
    $taskStmt = $db->prepare("INSERT INTO tasks (assigned_to_user_id, created_by_user_id, title, description, start_date, deadline, completed_at, priority, status, progress_percent, estimated_hours, actual_hours, remarks) 
                              VALUES (:assigned, :creator, :title, :desc, :start, :deadline, :completed, :priority, :status, :progress, :est, :act, :remarks)");

    $dailyTaskTemplates = [
        ['title' => 'Create social media posts', 'desc' => 'Draft copy and select assets for daily posting pipeline.'],
        ['title' => 'Publish scheduled posts', 'desc' => 'Verify tool buffers and deploy scheduled daily posts.'],
        ['title' => 'Reply to comments', 'desc' => 'Engage with customer comments and feedback on recent posts.'],
        ['title' => 'Reply to direct messages', 'desc' => 'Provide assistance to brand inquiries in DMs.'],
        ['title' => 'Like company-related posts', 'desc' => 'Monitor and like mentions of company tags and hashtags.'],
        ['title' => 'Share company posts', 'desc' => 'Distribute core brand updates across business pages.'],
        ['title' => 'Comment on industry posts', 'desc' => 'Perform social listening and comment on industry trends.'],
        ['title' => 'Check notifications', 'desc' => 'Review page notification alerts for emergency responses.'],
        ['title' => 'Monitor engagement', 'desc' => 'Review reach rates and post velocity stats.'],
        ['title' => 'Update CRM', 'desc' => 'Log new social leads and transition logs in the CRM dashboard.']
    ];

    $weeklyTaskTemplates = [
        ['title' => 'Weekly content planning', 'desc' => 'Build outline of the content calendar for next week.'],
        ['title' => 'Competitor analysis', 'desc' => 'Analyze top 3 competitor social post strategies.'],
        ['title' => 'Performance report', 'desc' => 'Compile weekly reach, clicks, and CPL metrics.'],
        ['title' => 'Campaign optimization', 'desc' => 'Adjust keywords, target groups, and daily budgets.'],
        ['title' => 'Hashtag research', 'desc' => 'Track trending hashtags in the industry niche.'],
        ['title' => 'Audience analysis', 'desc' => 'Analyze demographic shifts and profile traffic patterns.'],
        ['title' => 'Meeting with Manager', 'desc' => 'Align with Digital Marketing Manager on campaign targets.']
    ];

    $monthlyTaskTemplates = [
        ['title' => 'Monthly performance report', 'desc' => 'Aggregate all channels monthly reach, spend, and ROI snapshots.'],
        ['title' => 'Campaign summary', 'desc' => 'Generate high-level presentation on completed campaign budgets vs actuals.'],
        ['title' => 'Growth analysis', 'desc' => 'Compile audience growth metrics across all channels.'],
        ['title' => 'Content calendar planning', 'desc' => 'Draft calendar themes and asset needs for the upcoming month.'],
        ['title' => 'ROI report', 'desc' => 'Calculate detailed CPL and ROI for invoicing calculations.'],
        ['title' => 'Client presentation', 'desc' => 'Host review session with the client on campaign results.'],
        ['title' => 'Strategy meeting', 'desc' => 'Host department brainstorming session for Q3/Q4 channels.']
    ];

    // Seed tasks over last 90 days
    $db->beginTransaction();
    $totalTasksSeeded = 0;
    
    // Generate tasks for each executive
    foreach ($executiveUserIds as $execUid) {
        // Seed 15 Daily tasks (random dates in last 90 days)
        for ($i = 0; $i < 15; $i++) {
            $template = $dailyTaskTemplates[array_rand($dailyTaskTemplates)];
            $dayOffset = rand(1, 90);
            
            $startDate = date('Y-m-d H:i:s', strtotime("-$dayOffset days"));
            $deadline = date('Y-m-d H:i:s', strtotime("-$dayOffset days + 8 hours"));
            
            // Randomize status
            $rand = rand(1, 100);
            if ($rand <= 75) {
                // Completed
                $status = 'completed';
                $completedAt = date('Y-m-d H:i:s', strtotime("-$dayOffset days + " . rand(2, 7) . " hours"));
                $progress = 100;
                $est = rand(1, 3);
                $act = $est + (rand(-1, 1) * 0.5);
                $remarks = 'Task completed successfully on schedule.';
            } elseif ($rand <= 90) {
                // In Progress
                $status = 'in_progress';
                $completedAt = null;
                $progress = rand(20, 80);
                $est = rand(1, 3);
                $act = rand(1, 2);
                $remarks = 'Task currently in progress.';
            } else {
                // Overdue or Pending
                $isOverdue = (strtotime($deadline) < time());
                $status = 'pending';
                $completedAt = null;
                $progress = 0;
                $est = rand(1, 3);
                $act = 0;
                $remarks = $isOverdue ? 'Overdue pending resolution.' : 'Assigned and scheduled.';
            }

            $taskStmt->execute([
                ':assigned' => $execUid,
                ':creator' => $managerUserId,
                ':title' => $template['title'],
                ':desc' => $template['desc'],
                ':start' => $startDate,
                ':deadline' => $deadline,
                ':completed' => $completedAt,
                ':priority' => ['low', 'medium', 'high'][rand(0, 2)],
                ':status' => $status,
                ':progress' => $progress,
                ':est' => $est,
                ':act' => $act,
                ':remarks' => $remarks
            ]);
            $totalTasksSeeded++;
        }

        // Seed 6 Weekly Tasks
        for ($i = 0; $i < 6; $i++) {
            $template = $weeklyTaskTemplates[array_rand($weeklyTaskTemplates)];
            $weekOffset = rand(1, 12);
            
            $startDate = date('Y-m-d H:i:s', strtotime("-$weekOffset weeks"));
            $deadline = date('Y-m-d H:i:s', strtotime("-$weekOffset weeks + 5 days"));
            
            $rand = rand(1, 100);
            if ($rand <= 80) {
                $status = 'completed';
                $completedAt = date('Y-m-d H:i:s', strtotime("-$weekOffset weeks + " . rand(2, 4) . " days"));
                $progress = 100;
                $est = rand(4, 12);
                $act = $est + rand(-2, 2);
                $remarks = 'Weekly report compiled and reviewed.';
            } elseif ($rand <= 95) {
                $status = 'in_progress';
                $completedAt = null;
                $progress = rand(30, 90);
                $est = rand(4, 12);
                $act = rand(2, 6);
                $remarks = 'Work in progress.';
            } else {
                $status = 'pending';
                $completedAt = null;
                $progress = 0;
                $est = rand(4, 12);
                $act = 0;
                $remarks = 'Pending scheduling.';
            }

            $taskStmt->execute([
                ':assigned' => $execUid,
                ':creator' => $managerUserId,
                ':title' => $template['title'],
                ':desc' => $template['desc'],
                ':start' => $startDate,
                ':deadline' => $deadline,
                ':completed' => $completedAt,
                ':priority' => ['medium', 'high'][rand(0, 1)],
                ':status' => $status,
                ':progress' => $progress,
                ':est' => $est,
                ':act' => $act,
                ':remarks' => $remarks
            ]);
            $totalTasksSeeded++;
        }

        // Seed 3 Monthly Tasks
        for ($i = 1; $i <= 3; $i++) {
            $template = $monthlyTaskTemplates[array_rand($monthlyTaskTemplates)];
            $startDate = date('Y-m-d H:i:s', strtotime("-$i months"));
            $deadline = date('Y-m-d H:i:s', strtotime("-$i months + 20 days"));
            
            $rand = rand(1, 100);
            if ($rand <= 90) {
                $status = 'completed';
                $completedAt = date('Y-m-d H:i:s', strtotime("-$i months + " . rand(10, 18) . " days"));
                $progress = 100;
                $est = rand(15, 30);
                $act = $est + rand(-4, 4);
                $remarks = 'Completed monthly alignment check.';
            } else {
                $status = 'in_progress';
                $completedAt = null;
                $progress = rand(40, 85);
                $est = rand(15, 30);
                $act = rand(5, 10);
                $remarks = 'Gathering monthly data sources.';
            }

            $taskStmt->execute([
                ':assigned' => $execUid,
                ':creator' => $managerUserId,
                ':title' => $template['title'],
                ':desc' => $template['desc'],
                ':start' => $startDate,
                ':deadline' => $deadline,
                ':completed' => $completedAt,
                ':priority' => 'high',
                ':status' => $status,
                ':progress' => $progress,
                ':est' => $est,
                ':act' => $act,
                ':remarks' => $remarks
            ]);
            $totalTasksSeeded++;
        }
    }
    
    $db->commit();
    echo "[OK] Seeded $totalTasksSeeded total tasks (Daily, Weekly, Monthly).\n";

    // 5. SEED DAILY ACTIVITY DATA & ENGAGEMENT ANALYTICS (90 days history)
    echo "[INFO] Seeding 90-day daily activity logs & platform-wise channel daily metrics...\n";
    
    $activityStmt = $db->prepare("INSERT INTO daily_activity_logs (user_id, account_id, recorded_date, posts_published, posts_scheduled, comments_made, replies_sent, likes_given, shares_completed, new_followers, lost_followers, messages_replied, videos_uploaded, stories_posted, reels_published) 
                                  VALUES (:uid, :aid, :date, :posts, :sched, :comments, :replies, :likes, :shares, :new_f, :lost_f, :msg, :videos, :stories, :reels)");
    
    $metricsStmt = $db->prepare("INSERT INTO channel_daily_metrics (client_id, platform, metric_date, reach, impressions, engagements, clicks, leads_generated, revenue_influenced, spend) 
                                 VALUES (:cid, :platform, :date, :reach, :impressions, :engagements, :clicks, :leads, :rev, :spend)
                                 ON DUPLICATE KEY UPDATE reach = reach + VALUES(reach), impressions = impressions + VALUES(impressions), engagements = engagements + VALUES(engagements), clicks = clicks + VALUES(clicks), leads_generated = leads_generated + VALUES(leads_generated), revenue_influenced = revenue_influenced + VALUES(revenue_influenced), spend = spend + VALUES(spend)");

    $db->beginTransaction();
    $activityCount = 0;
    
    // For the last 90 days
    for ($day = 90; $day >= 0; $day--) {
        $dateStr = date('Y-m-d', strtotime("-$day days"));
        
        // Loop through all social accounts
        // We know each social account has a client_id and is assigned to an executive user_id
        $accounts = $db->query("SELECT account_id, client_id, assigned_user_id, platform FROM social_accounts")->fetchAll(PDO::FETCH_OBJ);
        
        foreach ($accounts as $acc) {
            $isWeekend = (date('N', strtotime($dateStr)) >= 6);
            
            // Executives post less on weekends
            $postChance = $isWeekend ? 15 : 70;
            $postsPub = (rand(1, 100) <= $postChance) ? rand(1, 3) : 0;
            $postsSched = rand(0, 2);
            
            $comments = rand(5, 30);
            $replies = rand(5, 25);
            $likes = rand(20, 100);
            $shares = rand(1, 10);
            $newF = rand(20, 150);
            $lostF = rand(2, 20);
            $msg = rand(3, 15);
            
            $videos = (in_array($acc->platform, ['youtube', 'facebook']) && rand(1, 100) <= 20) ? 1 : 0;
            $stories = in_array($acc->platform, ['instagram', 'snapchat']) ? rand(1, 5) : 0;
            $reels = (in_array($acc->platform, ['instagram', 'youtube']) && rand(1, 100) <= 30) ? 1 : 0;
            
            $activityStmt->execute([
                ':uid' => $acc->assigned_user_id,
                ':aid' => $acc->account_id,
                ':date' => $dateStr,
                ':posts' => $postsPub,
                ':sched' => $postsSched,
                ':comments' => $comments,
                ':replies' => $replies,
                ':likes' => $likes,
                ':shares' => $shares,
                ':new_f' => $newF,
                ':lost_f' => $lostF,
                ':msg' => $msg,
                ':videos' => $videos,
                ':stories' => $stories,
                ':reels' => $reels
            ]);
            $activityCount++;

            // Calculate realistic channel performance metrics based on activity
            $reach = $postsPub * rand(2000, 10000) + $stories * rand(500, 2000) + $reels * rand(5000, 25000) + rand(1000, 3000);
            $impressions = $reach * rand(12, 18) / 10;
            $engagements = $postsPub * rand(100, 800) + $comments * 2 + $replies * 2 + rand(50, 200);
            $clicks = intval($engagements * (rand(15, 30) / 100));
            $leads = intval($clicks * (rand(2, 8) / 100));
            
            $spend = $postsPub * rand(5, 50) + rand(10, 40);
            $revenue = $leads * rand(150, 400);

            // Save platform-wise channel daily metrics
            $metricsStmt->execute([
                ':cid' => $acc->client_id,
                ':platform' => $acc->platform,
                ':date' => $dateStr,
                ':reach' => $reach,
                ':impressions' => $impressions,
                ':engagements' => $engagements,
                ':clicks' => $clicks,
                ':leads' => $leads,
                ':rev' => $revenue,
                ':spend' => $spend
            ]);
        }

        // Also seed Website daily metrics (since 'website' is a channel on the dashboard)
        foreach ($clientIds as $cid) {
            $reach = rand(5000, 20000);
            $impressions = $reach * rand(2, 3);
            $clicks = rand(500, 2500);
            $engagements = rand(100, 400);
            $leads = intval($clicks * (rand(3, 7) / 100));
            $spend = rand(50, 250);
            $revenue = $leads * rand(300, 600);

            $metricsStmt->execute([
                ':cid' => $cid,
                ':platform' => 'website',
                ':date' => $dateStr,
                ':reach' => $reach,
                ':impressions' => $impressions,
                ':engagements' => $engagements,
                ':clicks' => $clicks,
                ':leads' => $leads,
                ':rev' => $revenue,
                ':spend' => $spend
            ]);
        }
    }
    $db->commit();
    echo "[OK] Seeded $activityCount daily activity logs & platform metric rolling tables.\n";

    // 6. SEED POSTS CALENDAR AND INDIVIDUAL POST ANALYTICS
    echo "[INFO] Seeding posts calendar & analytics...\n";
    $postStmt = $db->prepare("INSERT INTO posts (account_id, campaign_id, content, media_url, status, scheduled_at, published_at, created_at) 
                              VALUES (:aid, :camp, :content, :media, :status, :sched, :pub, :created)");
    
    $postAnalyticStmt = $db->prepare("INSERT INTO post_analytics (post_id, reach, impressions, engagements, clicks, recorded_date) 
                                      VALUES (:pid, :reach, :impress, :eng, :clicks, :rdate)");

    $db->beginTransaction();
    $postCount = 0;
    
    // Pull some connected accounts
    $accounts = $db->query("SELECT account_id, platform, client_id FROM social_accounts LIMIT 15")->fetchAll(PDO::FETCH_OBJ);
    foreach ($accounts as $acc) {
        // Map campaigns of this client
        $camps = $db->query("SELECT campaign_id FROM campaigns WHERE client_id = {$acc->client_id}")->fetchAll(PDO::FETCH_COLUMN);
        $campaignId = !empty($camps) ? $camps[array_rand($camps)] : null;
        
        // Seed 10 published posts in the past
        for ($i = 0; $i < 10; $i++) {
            $dayOffset = rand(5, 80);
            $pubDate = date('Y-m-d H:i:s', strtotime("-$dayOffset days"));
            
            $content = "Check out our latest product updates! We're introducing standard features that streamline workflows. #marketing #business #" . strtolower($acc->platform);
            
            $postStmt->execute([
                ':aid' => $acc->account_id,
                ':camp' => $campaignId,
                ':content' => $content,
                ':media' => 'https://raptor.cdn.com/assets/img/posts/post_' . rand(1, 20) . '.jpg',
                ':status' => 'published',
                ':sched' => date('Y-m-d H:i:s', strtotime("-$dayOffset days - 2 hours")),
                ':pub' => $pubDate,
                ':created' => date('Y-m-d H:i:s', strtotime("-$dayOffset days - 5 hours"))
            ]);
            $pid = $db->lastInsertId();

            // Seed stats
            $reach = rand(1000, 15000);
            $impress = $reach * rand(11, 15) / 10;
            $eng = intval($reach * (rand(3, 10) / 100));
            $clicks = intval($eng * (rand(15, 30) / 100));

            $postAnalyticStmt->execute([
                ':pid' => $pid,
                ':reach' => $reach,
                ':impress' => $impress,
                ':eng' => $eng,
                ':clicks' => $clicks,
                ':rdate' => date('Y-m-d', strtotime($pubDate))
            ]);
            $postCount++;
        }

        // Seed 3 future scheduled posts
        for ($i = 1; $i <= 3; $i++) {
            $schedDate = date('Y-m-d H:i:s', strtotime("+$i days"));
            $content = "Coming soon! Get ready for our upcoming launch. Stay tuned for details. #" . strtolower($acc->platform);
            
            $postStmt->execute([
                ':aid' => $acc->account_id,
                ':camp' => $campaignId,
                ':content' => $content,
                ':media' => 'https://raptor.cdn.com/assets/img/posts/post_future_' . rand(1, 5) . '.jpg',
                ':status' => 'scheduled',
                ':sched' => $schedDate,
                ':pub' => null,
                ':created' => date('Y-m-d H:i:s')
            ]);
            $postCount++;
        }
    }
    $db->commit();
    echo "[OK] Seeded $postCount posts in content calendar.\n";

    // 7. SEED AUDIENCE DEMOGRAPHICS & SENTIMENT LOGS
    echo "[INFO] Seeding demographic breakdowns, sentiment trends, and competitor benchmarks...\n";
    
    // Demographics
    $demoStmt = $db->prepare("INSERT INTO audience_demographics_snapshots (client_id, dimension_type, dimension_label, percentage, recorded_date) 
                              VALUES (:cid, :type, :label, :percent, :rdate)");
    
    $sentimentStmt = $db->prepare("INSERT INTO brand_sentiment_logs (client_id, recorded_date, positive_score, neutral_score, negative_score, overall_sentiment_score) 
                                   VALUES (:cid, :rdate, :pos, :neu, :neg, :overall)");

    $compStmt = $db->prepare("INSERT INTO competitor_benchmarks (client_id, metric_name, our_metric_value, competitor_avg_value, vs_competitor_percentage, recorded_date) 
                              VALUES (:cid, :metric, :our, :comp, :vs, :rdate)");

    $db->beginTransaction();
    foreach ($clientIds as $cid) {
        $rdate = date('Y-m-d');
        
        // Age Splits
        $demoStmt->execute([':cid' => $cid, ':type' => 'age', ':label' => '18-24', ':percent' => 15.5, ':rdate' => $rdate]);
        $demoStmt->execute([':cid' => $cid, ':type' => 'age', ':label' => '25-34', ':percent' => 45.2, ':rdate' => $rdate]);
        $demoStmt->execute([':cid' => $cid, ':type' => 'age', ':label' => '35-44', ':percent' => 25.3, ':rdate' => $rdate]);
        $demoStmt->execute([':cid' => $cid, ':type' => 'age', ':label' => '45+', ':percent' => 14.0, ':rdate' => $rdate]);

        // Gender Splits
        $demoStmt->execute([':cid' => $cid, ':type' => 'gender', ':label' => 'Male', ':percent' => 48.0, ':rdate' => $rdate]);
        $demoStmt->execute([':cid' => $cid, ':type' => 'gender', ':label' => 'Female', ':percent' => 50.5, ':rdate' => $rdate]);
        $demoStmt->execute([':cid' => $cid, ':type' => 'gender', ':label' => 'Other', ':percent' => 1.5, ':rdate' => $rdate]);

        // Devices
        $demoStmt->execute([':cid' => $cid, ':type' => 'device', ':label' => 'Desktop', ':percent' => 35.0, ':rdate' => $rdate]);
        $demoStmt->execute([':cid' => $cid, ':type' => 'device', ':label' => 'Mobile', ':percent' => 60.0, ':rdate' => $rdate]);
        $demoStmt->execute([':cid' => $cid, ':type' => 'device', ':label' => 'Tablet', ':percent' => 5.0, ':rdate' => $rdate]);

        // Seed 90 days of sentiment
        for ($day = 90; $day >= 0; $day--) {
            $sdate = date('Y-m-d', strtotime("-$day days"));
            $pos = rand(60, 85);
            $neg = rand(5, 15);
            $neu = 100 - $pos - $neg;
            $overall = intval($pos + ($neu * 0.2));
            
            $sentimentStmt->execute([
                ':cid' => $cid,
                ':rdate' => $sdate,
                ':pos' => $pos,
                ':neu' => $neu,
                ':neg' => $neg,
                ':overall' => $overall
            ]);
        }

        // Competitor Benchmarks
        $metricsBench = ['Engagement Rate', 'Follower Growth', 'CTR', 'Conversion Rate'];
        foreach ($metricsBench as $metric) {
            $ourVal = rand(4, 15);
            $compVal = rand(3, 10);
            $vs = (($ourVal - $compVal) / $compVal) * 100;
            
            $compStmt->execute([
                ':cid' => $cid,
                ':metric' => $metric,
                ':our' => $ourVal,
                ':comp' => $compVal,
                ':vs' => $vs,
                ':rdate' => $rdate
            ]);
        }
    }
    $db->commit();
    echo "[OK] Seeded demography splits, rolling sentiment graphs, and competitor metrics.\n";

    // 8. SEED LEADS, WEB SESSIONS, ATTRIBUTION TOUCHPOINTS
    echo "[INFO] Seeding customer intelligence leads & attribution touchpoints...\n";
    
    $leadInsertStmt = $db->prepare("INSERT INTO leads (client_id, assigned_to_user_id, first_name, last_name, email, phone, status, lead_quality, conversion_probability, lead_value, lead_source, created_at) 
                                    VALUES (:cid, :uid, :fname, :lname, :email, :phone, :status, :quality, :prob, :val, :source, :created)");
    
    $sessionStmt = $db->prepare("INSERT INTO web_behavior_sessions (session_id, client_id, visitor_id, is_returning, landing_page, exit_page, pages_viewed, scroll_depth_percent, session_duration_seconds, device_type, country, city, ip_address, created_at) 
                                 VALUES (:sid, :cid, :vid, :returning, :landing, :exit, :pages, :scroll, :dur, :device, :country, :city, :ip, :created)");

    $touchpointStmt = $db->prepare("INSERT INTO attribution_touchpoints (lead_id, session_id, touchpoint_order, traffic_channel, recorded_at) 
                                    VALUES (:lid, :sid, :torder, :channel, :created)");

    $journeyLogStmt = $db->prepare("INSERT INTO customer_journey_log (lead_id, from_stage_id, to_stage_id, transitioned_at) 
                                    VALUES (:lid, :from, :to, :tdate)");

    $firstNames = ['John', 'Jane', 'Michael', 'Emily', 'David', 'Sarah', 'James', 'Jessica', 'Robert', 'Ashley', 'William', 'Amanda', 'Joseph', 'Melissa', 'Thomas', 'Stephanie'];
    $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas'];
    $sources = ['LinkedIn', 'Google Search', 'Organic Search', 'Instagram', 'Facebook', 'YouTube', 'Email', 'Referral'];

    $db->beginTransaction();
    $totalLeads = 0;
    
    foreach ($clientIds as $cid) {
        // Seed 25 leads per client
        for ($i = 0; $i < 25; $i++) {
            $fname = $firstNames[array_rand($firstNames)];
            $lname = $lastNames[array_rand($lastNames)];
            $email = strtolower($fname . "." . $lname . "@gmail.com");
            $phone = "+1-555-" . rand(1000, 9999);
            
            $dayOffset = rand(2, 88);
            $createdDate = date('Y-m-d H:i:s', strtotime("-$dayOffset days"));
            
            $status = ['new', 'contacted', 'qualified', 'lost'][rand(0, 3)];
            $quality = ['hot', 'warm', 'cold'][rand(0, 2)];
            $prob = ($quality === 'hot') ? rand(70, 95) : (($quality === 'warm') ? rand(30, 65) : rand(5, 25));
            $val = rand(1000, 15000);
            
            $source = $sources[array_rand($sources)];
            $execUid = $executiveUserIds[array_rand($executiveUserIds)];

            $leadInsertStmt->execute([
                ':cid' => $cid,
                ':uid' => $execUid,
                ':fname' => $fname,
                ':lname' => $lname,
                ':email' => $email,
                ':phone' => $phone,
                ':status' => $status,
                ':quality' => $quality,
                ':prob' => $prob,
                ':val' => $val,
                ':source' => $source,
                ':created' => $createdDate
            ]);
            $lid = $db->lastInsertId();
            $totalLeads++;

            // Seed journey logs
            if ($status === 'new') {
                $journeyLogStmt->execute([':lid' => $lid, ':from' => null, ':to' => 4, ':tdate' => $createdDate]); // Leads
            } elseif ($status === 'contacted') {
                $journeyLogStmt->execute([':lid' => $lid, ':from' => null, ':to' => 4, ':tdate' => date('Y-m-d H:i:s', strtotime($createdDate . " -2 days"))]);
                $journeyLogStmt->execute([':lid' => $lid, ':from' => 4, ':to' => 3, ':tdate' => $createdDate]); // Engaged
            } elseif ($status === 'qualified') {
                $journeyLogStmt->execute([':lid' => $lid, ':from' => null, ':to' => 4, ':tdate' => date('Y-m-d H:i:s', strtotime($createdDate . " -3 days"))]);
                $journeyLogStmt->execute([':lid' => $lid, ':from' => 4, ':to' => 5, ':tdate' => $createdDate]); // Qualified
            }

            // Seed sessions and touchpoints for attribution modeling (First touch, last touch)
            $visitorId = md5($email);
            for ($touch = 1; $touch <= 3; $touch++) {
                $sessionId = md5($email . $touch . rand(1, 1000));
                
                $sessionDate = date('Y-m-d H:i:s', strtotime($createdDate . " - " . (4 - $touch) . " hours"));
                
                $sessionStmt->execute([
                    ':sid' => $sessionId,
                    ':cid' => $cid,
                    ':vid' => $visitorId,
                    ':returning' => ($touch > 1) ? 1 : 0,
                    ':landing' => '/landing-page-' . $touch,
                    ':exit' => '/contact-success',
                    ':pages' => rand(2, 6),
                    ':scroll' => rand(40, 100),
                    ':dur' => rand(45, 300),
                    ':device' => ['Desktop', 'Mobile', 'Tablet'][rand(0, 2)],
                    ':country' => 'United States',
                    ':city' => ['New York', 'San Francisco', 'Chicago'][rand(0, 2)],
                    ':ip' => '192.168.1.' . rand(2, 254),
                    ':created' => $sessionDate
                ]);

                $touchpointStmt->execute([
                    ':lid' => $lid,
                    ':sid' => $sessionId,
                    ':torder' => $touch,
                    ':channel' => $source,
                    ':created' => $sessionDate
                ]);
            }
        }
    }
    $db->commit();
    echo "[OK] Seeded $totalLeads total customer leads and multi-touch web sessions.\n";

    // 9. SEED BILLING INVOICES & PAYMENTS
    echo "[INFO] Seeding billing ledger (invoices & payment links)...\n";
    $invoiceStmt = $db->prepare("INSERT INTO invoices (client_id, invoice_number, amount, status, due_date, created_at) 
                                 VALUES (:cid, :number, :amount, :status, :due, :created)");
    
    $paymentStmt = $db->prepare("INSERT INTO payments (invoice_id, amount, payment_method, transaction_reference, paid_at) 
                                 VALUES (:iid, :amount, :method, :ref, :paid)");

    $db->beginTransaction();
    $invCount = 1;
    foreach ($clientIds as $cid) {
        // Seed 3 invoices per client
        for ($month = 3; $month >= 1; $month--) {
            $invNum = sprintf("INV-2026-%03d", $invCount++);
            $amount = $clients[$cid - 1]['company_name'] === 'MedX Healthcare' ? 10000.00 : ($clients[$cid - 1]['company_name'] === 'Global Retail Corp' ? 6500.00 : 8000.00);
            
            $created = date('Y-m-d', strtotime("-$month months"));
            $due = date('Y-m-d', strtotime("-$month months + 15 days"));
            
            // Randomize status
            if ($month > 1) {
                $status = 'paid';
            } else {
                $status = ['paid', 'unpaid', 'overdue'][rand(0, 2)];
            }

            $invoiceStmt->execute([
                ':cid' => $cid,
                ':number' => $invNum,
                ':amount' => $amount,
                ':status' => $status,
                ':due' => $due,
                ':created' => $created
            ]);
            $iid = $db->lastInsertId();

            if ($status === 'paid') {
                $paymentStmt->execute([
                    ':iid' => $iid,
                    ':amount' => $amount,
                    ':method' => ['Stripe', 'Bank Transfer', 'PayPal'][rand(0, 2)],
                    ':ref' => 'TXN' . rand(100000, 999999),
                    ':paid' => date('Y-m-d H:i:s', strtotime($due . " - 3 days"))
                ]);
            }
        }
    }
    $db->commit();
    echo "[OK] Seeded invoice ledger logs successfully.\n";

    // 10. SEED NOTIFICATIONS & BEST POSTING TIME HEATMAP
    echo "[INFO] Seeding system notifications & hourly engagement heatmap parameters...\n";
    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                               VALUES (:uid, :title, :msg, :type, :read, :created)");
    
    $heatmapStmt = $db->prepare("INSERT INTO best_posting_time_metrics (client_id, platform, day_of_week, hour_of_day, avg_engagement_rate, total_posts_analyzed) 
                                 VALUES (:cid, :platform, :day, :hour, :rate, :total)");

    $db->beginTransaction();
    
    // Seed notifications for Sarah (Manager) and Executives
    $usersToNotif = array_merge([$managerUserId], $executiveUserIds);
    foreach ($usersToNotif as $uid) {
        $notifStmt->execute([
            ':uid' => $uid,
            ':title' => 'New Task Assigned',
            ':msg' => 'A new weekly campaign optimization task has been assigned to you by Sarah Jenkins.',
            ':type' => 'task',
            ':read' => 0,
            ':created' => date('Y-m-d H:i:s', strtotime('-1 hours'))
        ]);
        $notifStmt->execute([
            ':uid' => $uid,
            ':title' => 'Task Due Tomorrow',
            ':msg' => 'Your assigned task "Reply to Comments" is approaching its due date tomorrow.',
            ':type' => 'task',
            ':read' => 0,
            ':created' => date('Y-m-d H:i:s', strtotime('-3 hours'))
        ]);
        $notifStmt->execute([
            ':uid' => $uid,
            ':title' => 'Weekly Report Submitted',
            ':msg' => 'The weekly performance report outline has been compiled and is ready for manager approval.',
            ':type' => 'report',
            ':read' => 1,
            ':created' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]);
    }

    // Seed best posting time heatmap grid (7 days * 24 hours per client per platform)
    // We only need to seed a few top platforms (Facebook, Instagram, LinkedIn) to generate nice heatmaps
    $heatmapPlatforms = ['facebook', 'instagram', 'linkedin'];
    foreach ($clientIds as $cid) {
        foreach ($heatmapPlatforms as $platform) {
            for ($day = 0; $day < 7; $day++) {
                for ($hour = 0; $hour < 24; $hour++) {
                    // Randomize engagement rate, making hours between 9AM-11AM and 2PM-5PM have higher rates
                    $isPeak = ($hour >= 9 && $hour <= 11) || ($hour >= 14 && $hour <= 17);
                    $rate = $isPeak ? rand(400, 850) / 100 : rand(50, 300) / 100;
                    $total = rand(10, 45);

                    $heatmapStmt->execute([
                        ':cid' => $cid,
                        ':platform' => $platform,
                        ':day' => $day,
                        ':hour' => $hour,
                        ':rate' => $rate,
                        ':total' => $total
                    ]);
                }
            }
        }
    }
    $db->commit();
    echo "[OK] Seeded alerts and heatmaps tables.\n";

    // 11. SEED CALENDAR EVENTS
    echo "[INFO] Seeding calendar events schedule...\n";
    $eventStmt = $db->prepare("INSERT INTO calendar_events (client_id, user_id, title, description, event_type, start_date, end_date) 
                               VALUES (:cid, :uid, :title, :desc, :type, :start, :end)");

    $db->beginTransaction();
    foreach ($clientIds as $cid) {
        // Monthly Reviews
        for ($month = 3; $month >= 0; $month--) {
            $eventStmt->execute([
                ':cid' => $cid,
                ':uid' => $managerUserId,
                ':title' => 'Monthly Strategy Review - Client #' . $cid,
                ':desc' => 'Align on monthly targets, budgets, and actual outcomes.',
                ':type' => 'review',
                ':start' => date('Y-m-d 10:00:00', strtotime("-$month months + 5 days")),
                ':end' => date('Y-m-d 11:30:00', strtotime("-$month months + 5 days"))
            ]);
        }

        // Weekly Alignment
        for ($week = 12; $week >= 0; $week--) {
            $eventStmt->execute([
                ':cid' => $cid,
                ':uid' => $managerUserId,
                ':title' => 'Weekly Sync - Client #' . $cid,
                ':desc' => 'Review weekly CTR and lead counts.',
                ':type' => 'meeting',
                ':start' => date('Y-m-d 14:00:00', strtotime("-$week weeks Monday")),
                ':end' => date('Y-m-d 15:00:00', strtotime("-$week weeks Monday"))
            ]);
        }
    }

    // National holidays and launches
    $eventStmt->execute([':cid' => null, ':uid' => null, ':title' => 'New Year Holiday', ':desc' => 'National holiday - offices closed.', ':type' => 'holiday', ':start' => '2026-01-01 00:00:00', ':end' => '2026-01-01 23:59:59']);
    $eventStmt->execute([':cid' => null, ':uid' => null, ':title' => 'Independence Day Holiday', ':desc' => 'National holiday.', ':type' => 'holiday', ':start' => '2026-07-04 00:00:00', ':end' => '2026-07-04 23:59:59']);

    $db->commit();
    echo "[OK] Calendar events seeded successfully.\n";

    echo "=== 90-Day Seeding Complete ===\n";

} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    die("[FATAL ERROR] " . $e->getMessage() . "\n");
}
