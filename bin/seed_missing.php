<?php
// Seed missing marketing and calendar data for RAPTOR CRM
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "[INFO] Seeding missing tables and default users...\n";

    // Establish connection and fetch roles
    $roleIds = [];
    $rolesQuery = $db->query("SELECT role_id, role_name FROM roles")->fetchAll(PDO::FETCH_OBJ);
    foreach ($rolesQuery as $r) {
        $roleIds[$r->role_name] = $r->role_id;
    }

    // 1. Ensure admin@raptor.com and manager@raptor.com exist
    $checkAdmin = $db->query("SELECT COUNT(*) FROM users WHERE email = 'admin@raptor.com'")->fetchColumn();
    if (!$checkAdmin && isset($roleIds['admin'])) {
        $stmt = $db->prepare("INSERT INTO users (role_id, name, email, password, status) VALUES (:rid, 'Admin User', 'admin@raptor.com', :pass, 'active')");
        $stmt->execute([':rid' => $roleIds['admin'], ':pass' => password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10])]);
        echo "[OK] Seeded admin@raptor.com user.\n";
    }

    $checkManager = $db->query("SELECT COUNT(*) FROM users WHERE email = 'manager@raptor.com'")->fetchColumn();
    if (!$checkManager && isset($roleIds['manager'])) {
        $stmt = $db->prepare("INSERT INTO users (role_id, name, email, password, status) VALUES (:rid, 'Manager User', 'manager@raptor.com', :pass, 'active')");
        $stmt->execute([':rid' => $roleIds['manager'], ':pass' => password_hash('manager123', PASSWORD_BCRYPT, ['cost' => 10])]);
        echo "[OK] Seeded manager@raptor.com user.\n";
    }

    // Fetch some client IDs and user IDs
    $clients = $db->query("SELECT client_id FROM clients")->fetchAll(PDO::FETCH_COLUMN);
    $users = $db->query("SELECT user_id FROM users")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($clients)) {
        echo "[WARNING] No clients found. Skipping CRM metrics seeding.\n";
        exit(0);
    }
    if (empty($users)) {
        echo "[WARNING] No users found. Skipping CRM metrics seeding.\n";
        exit(0);
    }

    $db->beginTransaction();

    // 2. Seed social_accounts (at least 50 rows)
    $platforms = ['facebook', 'instagram', 'linkedin', 'youtube', 'x'];
    $accountCount = 0;
    $accountIds = [];
    foreach ($clients as $cid) {
        foreach ($platforms as $platform) {
            $stmt = $db->prepare("INSERT IGNORE INTO social_accounts (client_id, platform, profile_name, profile_url, status) 
                                   VALUES (:cid, :platform, :name, :url, 'active')");
            $stmt->execute([
                ':cid' => $cid,
                ':platform' => $platform,
                ':name' => ucfirst($platform) . ' Client ' . $cid,
                ':url' => 'https://' . $platform . '.com/client' . $cid
            ]);
            $aid = $db->lastInsertId();
            if ($aid) {
                $accountIds[] = $aid;
                $accountCount++;
            }
        }
    }
    echo "[OK] Seeded {$accountCount} social accounts.\n";

    if (empty($accountIds)) {
        $accountIds = $db->query("SELECT account_id FROM social_accounts")->fetchAll(PDO::FETCH_COLUMN);
    }

    // 3. Seed posts (at least 100 rows)
    if (!empty($accountIds)) {
        $campaigns = $db->query("SELECT campaign_id FROM campaigns")->fetchAll(PDO::FETCH_COLUMN);
        $postCount = 0;
        for ($i = 1; $i <= 120; $i++) {
            $aid = $accountIds[array_rand($accountIds)];
            $campId = !empty($campaigns) ? $campaigns[array_rand($campaigns)] : null;
            $status = ['draft', 'scheduled', 'published'][rand(0, 2)];
            $stmt = $db->prepare("INSERT INTO posts (account_id, campaign_id, content, status, scheduled_at, published_at) 
                                   VALUES (:aid, :camp, :content, :status, :sched, :pub)");
            $stmt->execute([
                ':aid' => $aid,
                ':camp' => $campId,
                ':content' => 'Seeded marketing post #' . $i,
                ':status' => $status,
                ':sched' => $status !== 'draft' ? date('Y-m-d H:i:s', strtotime('+' . rand(1, 10) . ' days')) : null,
                ':pub' => $status === 'published' ? date('Y-m-d H:i:s', strtotime('-' . rand(1, 10) . ' days')) : null
            ]);
            $postCount++;
        }
        echo "[OK] Seeded {$postCount} posts.\n";
    }

    // 4. Seed channel_daily_metrics (at least 100 rows)
    $metricsCount = 0;
    foreach ($clients as $cid) {
        foreach ($platforms as $platform) {
            for ($day = 20; $day >= 0; $day--) {
                $date = date('Y-m-d', strtotime("-$day days"));
                $stmt = $db->prepare("INSERT IGNORE INTO channel_daily_metrics (client_id, platform, metric_date, reach, impressions, engagements, clicks, leads_generated, spend, revenue_influenced) 
                                       VALUES (:cid, :platform, :date, :reach, :imp, :eng, :clicks, :leads, :spend, :rev)");
                $stmt->execute([
                    ':cid' => $cid,
                    ':platform' => $platform,
                    ':date' => $date,
                    ':reach' => rand(1000, 5000),
                    ':imp' => rand(5000, 10000),
                    ':eng' => rand(100, 500),
                    ':clicks' => rand(50, 200),
                    ':leads' => rand(1, 10),
                    ':spend' => (float)rand(10, 100),
                    ':rev' => (float)rand(100, 1000)
                ]);
                $metricsCount++;
            }
        }
    }
    echo "[OK] Seeded {$metricsCount} channel daily metrics rows.\n";

    // 5. Seed calendar_events
    $eventCount = 0;
    foreach ($clients as $cid) {
        $stmt = $db->prepare("INSERT INTO calendar_events (client_id, user_id, title, description, event_type, start_date, end_date) 
                               VALUES (:cid, :uid, :title, :desc, 'meeting', :start, :end)");
        $stmt->execute([
            ':cid' => $cid,
            ':uid' => $users[array_rand($users)],
            ':title' => 'Client Strategy Review #' . $cid,
            ':desc' => 'Scheduled review meeting for Client #' . $cid,
            ':start' => date('Y-m-d 10:00:00', strtotime('+1 days')),
            ':end' => date('Y-m-d 11:30:00', strtotime('+1 days'))
        ]);
        $eventCount++;
    }
    echo "[OK] Seeded {$eventCount} calendar events.\n";

    // 6. Seed daily_activity_logs (at least 100 rows)
    if (!empty($accountIds)) {
        $activityCount = 0;
        foreach ($users as $uid) {
            $aid = $accountIds[array_rand($accountIds)];
            for ($day = 5; $day >= 0; $day--) {
                $date = date('Y-m-d', strtotime("-$day days"));
                $stmt = $db->prepare("INSERT IGNORE INTO daily_activity_logs (user_id, account_id, recorded_date, posts_published, posts_scheduled) 
                                       VALUES (:uid, :aid, :date, :pub, :sched)");
                $stmt->execute([
                    ':uid' => $uid,
                    ':aid' => $aid,
                    ':date' => $date,
                    ':pub' => rand(1, 3),
                    ':sched' => rand(1, 5)
                ]);
                $activityCount++;
            }
        }
        echo "[OK] Seeded {$activityCount} daily activity logs.\n";
    }

    // 7. Seed brand_sentiment_logs (at least 50 rows)
    $sentimentCount = 0;
    foreach ($clients as $cid) {
        for ($day = 10; $day >= 0; $day--) {
            $date = date('Y-m-d', strtotime("-$day days"));
            $pos = rand(40, 70);
            $neu = rand(15, 25);
            $neg = 100 - $pos - $neu;
            $stmt = $db->prepare("INSERT IGNORE INTO brand_sentiment_logs (client_id, recorded_date, positive_score, neutral_score, negative_score, overall_sentiment_score) 
                                   VALUES (:cid, :date, :pos, :neu, :neg, :overall)");
            $stmt->execute([
                ':cid' => $cid,
                ':date' => $date,
                ':pos' => $pos,
                ':neu' => $neu,
                ':neg' => $neg,
                ':overall' => $pos + round($neu/2)
            ]);
            $sentimentCount++;
        }
    }
    echo "[OK] Seeded {$sentimentCount} brand sentiment logs.\n";

    // 8. Seed competitor_benchmarks (at least 10 rows)
    $benchCount = 0;
    $metrics = ['Engagement Rate', 'Follower Growth', 'CTR', 'Conversion Rate', 'Share of Voice'];
    foreach ($clients as $cid) {
        foreach ($metrics as $metric) {
            $our = (float) (rand(20, 80) / 10);
            $comp = (float) (rand(20, 80) / 10);
            $stmt = $db->prepare("INSERT IGNORE INTO competitor_benchmarks (client_id, metric_name, our_metric_value, competitor_avg_value, vs_competitor_percentage, recorded_date) 
                                   VALUES (:cid, :metric, :our, :comp, :diff, :date)");
            $stmt->execute([
                ':cid' => $cid,
                ':metric' => $metric,
                ':our' => $our,
                ':comp' => $comp,
                ':diff' => $our - $comp,
                ':date' => date('Y-m-d')
            ]);
            $benchCount++;
        }
    }
    echo "[OK] Seeded {$benchCount} competitor benchmarks.\n";

    // 9. Seed best_posting_time_metrics (at least 10 rows)
    $postingCount = 0;
    foreach ($clients as $cid) {
        foreach ($platforms as $platform) {
            for ($day = 0; $day < 7; $day++) {
                for ($hour = 9; $hour <= 18; $hour += 3) {
                    $stmt = $db->prepare("INSERT IGNORE INTO best_posting_time_metrics (client_id, platform, day_of_week, hour_of_day, avg_engagement_rate, total_posts_analyzed) 
                                           VALUES (:cid, :platform, :day, :hour, :rate, :total)");
                    $stmt->execute([
                        ':cid' => $cid,
                        ':platform' => $platform,
                        ':day' => $day,
                        ':hour' => $hour,
                        ':rate' => (float)(rand(10, 50) / 10),
                        ':total' => rand(5, 20)
                    ]);
                    $postingCount++;
                }
            }
        }
    }
    echo "[OK] Seeded {$postingCount} best posting time metrics.\n";

    // 10. Seed audience_demographics_snapshots (at least 5 rows)
    $demoCount = 0;
    $dimensions = [
        ['type' => 'age', 'label' => '18-24', 'pct' => 15.5],
        ['type' => 'age', 'label' => '25-34', 'pct' => 45.2],
        ['type' => 'age', 'label' => '35-44', 'pct' => 25.3],
        ['type' => 'gender', 'label' => 'Male', 'pct' => 52.4],
        ['type' => 'gender', 'label' => 'Female', 'pct' => 47.6]
    ];
    foreach ($clients as $cid) {
        foreach ($dimensions as $dim) {
            $stmt = $db->prepare("INSERT IGNORE INTO audience_demographics_snapshots (client_id, dimension_type, dimension_label, percentage, recorded_date) 
                                   VALUES (:cid, :type, :label, :pct, :date)");
            $stmt->execute([
                ':cid' => $cid,
                ':type' => $dim['type'],
                ':label' => $dim['label'],
                ':pct' => $dim['pct'],
                ':date' => date('Y-m-d')
            ]);
            $demoCount++;
        }
    }
    echo "[OK] Seeded {$demoCount} audience demographics snapshots.\n";

    $db->commit();
    echo "[DONE] Missing tables seeding complete.\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "[ERROR] " . $e->getMessage() . "\n";
}
