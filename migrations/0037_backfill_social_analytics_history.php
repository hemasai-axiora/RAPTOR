<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();

    // Get default platform_id, account_id, user_id from database
    $defaultPlatformId = (int)$db->query("SELECT platform_id FROM platforms LIMIT 1")->fetchColumn() ?: 209;
    $defaultAccountId = (int)$db->query("SELECT account_id FROM social_accounts LIMIT 1")->fetchColumn() ?: 352;
    $defaultUserId = (int)$db->query("SELECT user_id FROM users WHERE status = 'active' LIMIT 1")->fetchColumn() ?: 482;

    $validUsers = $db->query("SELECT user_id FROM users")->fetchAll(PDO::FETCH_COLUMN);

    // Build map of platform name -> platform_id
    $platformMap = [];
    $pRows = $db->query("SELECT platform_id, name FROM platforms")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pRows as $p) {
        $platformMap[strtolower($p['name'])] = (int)$p['platform_id'];
    }

    // Fetch all existing content_engagement rows with post details
    $stmt = $db->query("SELECT ce.*, p.platform, p.platform_id AS post_platform_id, p.account_id AS post_account_id, p.title, p.content
                        FROM content_engagement ce
                        JOIN posts p ON ce.post_id = p.post_id
                        ORDER BY ce.engagement_id ASC");
    $engagements = $stmt->fetchAll(PDO::FETCH_OBJ);

    $count = 0;
    foreach ($engagements as $eng) {
        $platName = strtolower($eng->platform ?? 'linkedin');
        $platformId = $eng->post_platform_id ?: ($platformMap[$platName] ?? $defaultPlatformId);
        $accountId = $eng->post_account_id ?: $defaultAccountId;
        $userId = in_array((int)$eng->created_by_user_id, $validUsers, true) ? (int)$eng->created_by_user_id : $defaultUserId;

        $likes = (int) $eng->likes;
        $comments = (int) $eng->comments;
        $shares = (int) $eng->shares;
        $reach = (int) $eng->reach;
        $impressions = (int) $eng->impressions;
        $clicks = (int) $eng->clicks;
        $views = max(1, $reach > 0 ? $reach : ($likes + $comments + $shares));
        $engRate = (float) $eng->engagement_rate;
        if ($engRate <= 0 && $views > 0) {
            $engRate = round(min(100.0, (($likes + $comments + $shares) / $views) * 100), 2);
        }

        // Check if analytics_entries row exists for this post
        $checkStmt = $db->prepare("SELECT entry_id FROM analytics_entries WHERE post_id = :post_id LIMIT 1");
        $checkStmt->execute([':post_id' => $eng->post_id]);
        $existingEntry = $checkStmt->fetch(PDO::FETCH_OBJ);

        if ($existingEntry) {
            $entryId = $existingEntry->entry_id;
            $upStmt = $db->prepare("UPDATE analytics_entries SET likes = :likes, comments = :comments, shares = :shares, views = :views, reach = :reach, impressions = :impressions, clicks = :clicks, engagement_rate = :eng_rate, updated_by = :uid, updated_at = :captured WHERE entry_id = :entry_id");
            $upStmt->execute([
                ':likes' => $likes,
                ':comments' => $comments,
                ':shares' => $shares,
                ':views' => $views,
                ':reach' => $reach,
                ':impressions' => $impressions,
                ':clicks' => $clicks,
                ':eng_rate' => $engRate,
                ':uid' => $userId,
                ':captured' => $eng->captured_at ?: date('Y-m-d H:i:s'),
                ':entry_id' => $entryId
            ]);
        } else {
            $insEntry = $db->prepare("INSERT INTO analytics_entries (platform_id, account_id, post_id, likes, comments, shares, views, reach, impressions, clicks, engagement_rate, updated_by, created_at, updated_at) VALUES (:platform_id, :account_id, :post_id, :likes, :comments, :shares, :views, :reach, :impressions, :clicks, :eng_rate, :uid, :captured, :captured)");
            $insEntry->execute([
                ':platform_id' => $platformId,
                ':account_id' => $accountId,
                ':post_id' => $eng->post_id,
                ':likes' => $likes,
                ':comments' => $comments,
                ':shares' => $shares,
                ':views' => $views,
                ':reach' => $reach,
                ':impressions' => $impressions,
                ':clicks' => $clicks,
                ':eng_rate' => $engRate,
                ':uid' => $userId,
                ':captured' => $eng->captured_at ?: date('Y-m-d H:i:s')
            ]);
            $entryId = $db->lastInsertId();
        }

        // Insert history record
        $insHist = $db->prepare("INSERT INTO analytics_history (entry_id, platform_id, account_id, post_id, likes, comments, shares, views, reach, impressions, clicks, engagement_rate, custom_notes, updated_by, created_at) VALUES (:entry_id, :platform_id, :account_id, :post_id, :likes, :comments, :shares, :views, :reach, :impressions, :clicks, :eng_rate, :notes, :uid, :captured)");
        $insHist->execute([
            ':entry_id' => $entryId,
            ':platform_id' => $platformId,
            ':account_id' => $accountId,
            ':post_id' => $eng->post_id,
            ':likes' => $likes,
            ':comments' => $comments,
            ':shares' => $shares,
            ':views' => $views,
            ':reach' => $reach,
            ':impressions' => $impressions,
            ':clicks' => $clicks,
            ':eng_rate' => $engRate,
            ':notes' => 'Engagement update from Content Management & Analytics',
            ':uid' => $userId,
            ':captured' => $eng->captured_at ?: date('Y-m-d H:i:s')
        ]);

        $count++;
    }

    echo "Successfully backfilled $count social analytics history records.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
