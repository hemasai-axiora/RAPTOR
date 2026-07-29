<?php
/**
 * Migration 0028: Content Posts extension (post_code, client_id, title, content_type, platform, summary metrics) & time-series content_engagement table.
 */

echo "Starting Migration 0028: Content Posts & Engagement Engine...\n";

// Helper function to check if column exists
$columnExists = function (PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE :col");
        $stmt->execute([':col' => $column]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
};

// 0. Allow account_id to be NULL
try {
    $db->exec("ALTER TABLE posts MODIFY COLUMN account_id INT NULL");
    echo "    + Modified account_id to be NULLable\n";
} catch (Exception $e) {}

// 1. Add post_code column if not exists
if (!$columnExists($db, 'posts', 'post_code')) {
    $db->exec("ALTER TABLE posts ADD COLUMN post_code VARCHAR(20) NULL UNIQUE AFTER post_id");
    echo "    + Added post_code column to posts table\n";
} else {
    echo "    = post_code column already exists\n";
}

// 2. Add client_id column if not exists
if (!$columnExists($db, 'posts', 'client_id')) {
    $db->exec("ALTER TABLE posts ADD COLUMN client_id INT NULL AFTER account_id");
    try {
        $db->exec("ALTER TABLE posts ADD CONSTRAINT fk_posts_client FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE");
        echo "    + Added client_id column with FK constraint to clients(client_id)\n";
    } catch (Exception $e) {
        echo "    + Added client_id column (FK note: " . $e->getMessage() . ")\n";
    }
} else {
    echo "    = client_id column already exists\n";
}

// 3. Add title column if not exists
if (!$columnExists($db, 'posts', 'title')) {
    $db->exec("ALTER TABLE posts ADD COLUMN title VARCHAR(255) NULL AFTER campaign_id");
    echo "    + Added title column to posts table\n";
}

// 4. Add content_type column if not exists
if (!$columnExists($db, 'posts', 'content_type')) {
    $db->exec("ALTER TABLE posts ADD COLUMN content_type VARCHAR(50) NOT NULL DEFAULT 'Image Post' AFTER media_url");
    echo "    + Added content_type column to posts table\n";
}

// 5. Add platform column if not exists
if (!$columnExists($db, 'posts', 'platform')) {
    $db->exec("ALTER TABLE posts ADD COLUMN platform VARCHAR(50) NOT NULL DEFAULT 'LinkedIn' AFTER content_type");
    echo "    + Added platform column to posts table\n";
}

// 6. Add latest summary metrics columns to posts table
$summaryCols = [
    'current_likes' => "INT DEFAULT 0",
    'current_comments' => "INT DEFAULT 0",
    'current_shares' => "INT DEFAULT 0",
    'current_saves' => "INT DEFAULT 0",
    'current_reach' => "INT DEFAULT 0",
    'current_impressions' => "INT DEFAULT 0",
    'current_clicks' => "INT DEFAULT 0",
    'current_video_views' => "INT DEFAULT 0",
    'current_engagement_rate' => "DECIMAL(5,2) DEFAULT 0.00",
    'last_engagement_updated_at' => "TIMESTAMP NULL"
];

foreach ($summaryCols as $col => $definition) {
    if (!$columnExists($db, 'posts', $col)) {
        $db->exec("ALTER TABLE posts ADD COLUMN {$col} {$definition}");
        echo "    + Added {$col} column to posts table\n";
    }
}

// 7. Create content_engagement time-series table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS content_engagement (
    engagement_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    likes INT DEFAULT 0,
    comments INT DEFAULT 0,
    shares INT DEFAULT 0,
    saves INT DEFAULT 0,
    reach INT DEFAULT 0,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    video_views INT DEFAULT 0,
    engagement_rate DECIMAL(5,2) DEFAULT 0.00,
    captured_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT NULL,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "    + Verified/created content_engagement time-series table\n";

// 8. Backfill missing post_code values for existing posts
$year = date('Y');
$stmt = $db->query("SELECT post_id FROM posts WHERE post_code IS NULL OR post_code = '' ORDER BY post_id ASC");
$postsToUpdate = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($postsToUpdate)) {
    $updateStmt = $db->prepare("UPDATE posts SET post_code = :code WHERE post_id = :id");
    foreach ($postsToUpdate as $index => $id) {
        $code = sprintf("PST-%s-%05d", $year, (int) $id);
        $updateStmt->execute([':code' => $code, ':id' => (int) $id]);
    }
    echo "    + Backfilled " . count($postsToUpdate) . " existing posts with unique post_code values.\n";
}

echo "Migration 0028 complete.\n";
