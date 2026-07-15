<?php
// Raptor CRM Analytics Entry & History Model

class AnalyticsEntry extends Model {
    
    // Log a new analytics entry (updates the latest entries table and inserts to history)
    public function logEntry($data) {
        $db = $this->db;
        $db->beginTransaction();

        try {
            // Calculate engagement rate if not provided: (Likes + Comments + Shares) / Views * 100
            $likes = (int)($data['likes'] ?? 0);
            $comments = (int)($data['comments'] ?? 0);
            $shares = (int)($data['shares'] ?? 0);
            $views = (int)($data['views'] ?? 1);
            if ($views <= 0) $views = 1;
            
            $engagement_rate = $data['engagement_rate'] ?? (($likes + $comments + $shares) / $views * 100);
            $engagement_rate = round(min(100.0, max(0.0, $engagement_rate)), 2);

            // Check if latest entry already exists for this post (or account if post is null)
            if (!empty($data['post_id'])) {
                $this->query('SELECT entry_id FROM analytics_entries WHERE post_id = :post_id LIMIT 1');
                $this->bind(':post_id', $data['post_id']);
            } else {
                $this->query('SELECT entry_id FROM analytics_entries WHERE account_id = :account_id AND post_id IS NULL LIMIT 1');
                $this->bind(':account_id', $data['account_id']);
            }
            
            $existing = $this->single();

            if ($existing) {
                // Update existing latest entry
                $entryId = $existing->entry_id;
                $this->query('UPDATE analytics_entries 
                              SET likes = :likes, comments = :comments, shares = :shares, views = :views, 
                                  reach = :reach, impressions = :impressions, clicks = :clicks, 
                                  followers_gained = :followers_gained, engagement_rate = :engagement_rate, 
                                  custom_notes = :custom_notes, updated_by = :updated_by, updated_at = NOW()
                              WHERE entry_id = :entry_id');
                $this->bind(':likes', $likes);
                $this->bind(':comments', $comments);
                $this->bind(':shares', $shares);
                $this->bind(':views', $views);
                $this->bind(':reach', (int)($data['reach'] ?? 0));
                $this->bind(':impressions', (int)($data['impressions'] ?? 0));
                $this->bind(':clicks', (int)($data['clicks'] ?? 0));
                $this->bind(':followers_gained', (int)($data['followers_gained'] ?? 0));
                $this->bind(':engagement_rate', $engagement_rate);
                $this->bind(':custom_notes', $data['custom_notes'] ?? null);
                $this->bind(':updated_by', $data['updated_by']);
                $this->bind(':entry_id', $entryId);
                $this->execute();
            } else {
                // Insert new latest entry
                $this->query('INSERT INTO analytics_entries 
                              (platform_id, account_id, post_id, likes, comments, shares, views, reach, impressions, clicks, followers_gained, engagement_rate, custom_notes, updated_by) 
                              VALUES (:platform_id, :account_id, :post_id, :likes, :comments, :shares, :views, :reach, :impressions, :clicks, :followers_gained, :engagement_rate, :custom_notes, :updated_by)');
                $this->bind(':platform_id', $data['platform_id']);
                $this->bind(':account_id', $data['account_id']);
                $this->bind(':post_id', $data['post_id'] ?? null);
                $this->bind(':likes', $likes);
                $this->bind(':comments', $comments);
                $this->bind(':shares', $shares);
                $this->bind(':views', $views);
                $this->bind(':reach', (int)($data['reach'] ?? 0));
                $this->bind(':impressions', (int)($data['impressions'] ?? 0));
                $this->bind(':clicks', (int)($data['clicks'] ?? 0));
                $this->bind(':followers_gained', (int)($data['followers_gained'] ?? 0));
                $this->bind(':engagement_rate', $engagement_rate);
                $this->bind(':custom_notes', $data['custom_notes'] ?? null);
                $this->bind(':updated_by', $data['updated_by']);
                $this->execute();
                $entryId = $db->lastInsertId();
            }

            // Insert into history (stores every single log event)
            $this->query('INSERT INTO analytics_history 
                          (entry_id, platform_id, account_id, post_id, likes, comments, shares, views, reach, impressions, clicks, followers_gained, engagement_rate, custom_notes, updated_by, created_at) 
                          VALUES (:entry_id, :platform_id, :account_id, :post_id, :likes, :comments, :shares, :views, :reach, :impressions, :clicks, :followers_gained, :engagement_rate, :custom_notes, :updated_by, NOW())');
            $this->bind(':entry_id', $entryId);
            $this->bind(':platform_id', $data['platform_id']);
            $this->bind(':account_id', $data['account_id']);
            $this->bind(':post_id', $data['post_id'] ?? null);
            $this->bind(':likes', $likes);
            $this->bind(':comments', $comments);
            $this->bind(':shares', $shares);
            $this->bind(':views', $views);
            $this->bind(':reach', (int)($data['reach'] ?? 0));
            $this->bind(':impressions', (int)($data['impressions'] ?? 0));
            $this->bind(':clicks', (int)($data['clicks'] ?? 0));
            $this->bind(':followers_gained', (int)($data['followers_gained'] ?? 0));
            $this->bind(':engagement_rate', $engagement_rate);
            $this->bind(':custom_notes', $data['custom_notes'] ?? null);
            $this->bind(':updated_by', $data['updated_by']);
            $this->execute();

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // Get update history timeline for a post (or account if post_id is null)
    public function getHistory($accountId, $postId = null) {
        if ($postId) {
            $this->query('SELECT h.*, u.name as updated_by_name 
                          FROM analytics_history h
                          JOIN users u ON h.updated_by = u.user_id
                          WHERE h.post_id = :post_id
                          ORDER BY h.created_at DESC');
            $this->bind(':post_id', $postId);
        } else {
            $this->query('SELECT h.*, u.name as updated_by_name 
                          FROM analytics_history h
                          JOIN users u ON h.updated_by = u.user_id
                          WHERE h.account_id = :account_id AND h.post_id IS NULL
                          ORDER BY h.created_at DESC');
            $this->bind(':account_id', $accountId);
        }
        return $this->resultSet();
    }

    // Get complete chronological updates history timeline for visible scoping
    public function getCompleteHistory($userIds = null) {
        $sql = 'SELECT h.*, u.name as updated_by_name, p.name as platform_name, s.profile_name, pt.content as post_content
                FROM analytics_history h
                JOIN users u ON h.updated_by = u.user_id
                JOIN platforms p ON h.platform_id = p.platform_id
                JOIN social_accounts s ON h.account_id = s.account_id
                LEFT JOIN posts pt ON h.post_id = pt.post_id';
        
        if ($userIds !== null) {
            $sql .= ' WHERE h.updated_by IN (' . implode(',', array_map('intval', $userIds)) . ')';
        }
        
        $sql .= ' ORDER BY h.created_at DESC LIMIT 100';
        $this->query($sql);
        return $this->resultSet();
    }

    // Calculate core dashboard aggregates
    public function getDashboardMetrics($userIds = null) {
        $metrics = [];
        
        // Scope filters if specified
        $scopeFilter = '';
        if ($userIds !== null) {
            $scopeFilter = ' AND h.updated_by IN (' . implode(',', array_map('intval', $userIds)) . ')';
        }

        // Today's total updates
        $this->query("SELECT COUNT(*) FROM analytics_history h WHERE DATE(h.created_at) = CURRENT_DATE()" . $scopeFilter);
        $metrics['today_updates'] = (int)$this->stmt->fetchColumn();

        // Pending updates (assigned accounts that have no update today)
        $assignScope = '';
        if ($userIds !== null) {
            $assignScope = ' WHERE user_id IN (' . implode(',', array_map('intval', $userIds)) . ')';
        }
        $this->query("SELECT COUNT(DISTINCT account_id) FROM assignments" . $assignScope);
        $totalAssigned = (int)$this->stmt->fetchColumn();

        $historyScope = '';
        if ($userIds !== null) {
            $historyScope = ' AND updated_by IN (' . implode(',', array_map('intval', $userIds)) . ')';
        }
        $this->query("SELECT COUNT(DISTINCT account_id) FROM analytics_history WHERE DATE(created_at) = CURRENT_DATE()" . $historyScope);
        $updatedToday = (int)$this->stmt->fetchColumn();
        
        $metrics['pending_updates'] = max(0, $totalAssigned - $updatedToday);

        // Most active employee today
        $this->query("SELECT u.name, COUNT(*) as update_count
                      FROM analytics_history h
                      JOIN users u ON h.updated_by = u.user_id
                      WHERE DATE(h.created_at) = CURRENT_DATE()
                      GROUP BY h.updated_by
                      ORDER BY update_count DESC LIMIT 1");
        $activeEmp = $this->single();
        $metrics['most_active_employee'] = $activeEmp ? $activeEmp->name . ' (' . $activeEmp->update_count . ')' : 'N/A';

        // Top performing post (highest engagement rate in latest entries)
        $this->query("SELECT p.content, ae.engagement_rate, pl.name as platform_name, sa.profile_name
                      FROM analytics_entries ae
                      JOIN posts p ON ae.post_id = p.post_id
                      JOIN platforms pl ON ae.platform_id = pl.platform_id
                      JOIN social_accounts sa ON ae.account_id = sa.account_id
                      ORDER BY ae.engagement_rate DESC, ae.likes DESC LIMIT 1");
        $topPost = $this->single();
        $metrics['top_post'] = $topPost ? substr($topPost->content, 0, 20) . '... (' . $topPost->engagement_rate . '%)' : 'N/A';

        // Top performing platform (highest avg engagement rate)
        $this->query("SELECT pl.name, AVG(ae.engagement_rate) as avg_er
                      FROM analytics_entries ae
                      JOIN platforms pl ON ae.platform_id = pl.platform_id
                      GROUP BY ae.platform_id
                      ORDER BY avg_er DESC LIMIT 1");
        $topPlat = $this->single();
        $metrics['top_platform'] = $topPlat ? $topPlat->name . ' (' . round($topPlat->avg_er, 2) . '%)' : 'N/A';

        // Most engaged account (highest total engagements: likes + comments + shares)
        $this->query("SELECT sa.profile_name, SUM(ae.likes + ae.comments + ae.shares) as total_eng
                      FROM analytics_entries ae
                      JOIN social_accounts sa ON ae.account_id = sa.account_id
                      GROUP BY ae.account_id
                      ORDER BY total_eng DESC LIMIT 1");
        $mostEng = $this->single();
        $metrics['most_engaged_account'] = $mostEng ? $mostEng->profile_name . ' (' . number_format($mostEng->total_eng) . ')' : 'N/A';

        // Engagements today
        $this->query("SELECT SUM(likes + comments + shares) FROM analytics_history h WHERE DATE(created_at) = CURRENT_DATE()" . $scopeFilter);
        $metrics['today_engagement'] = (int)$this->stmt->fetchColumn();

        // Weekly engagement (last 7 days)
        $this->query("SELECT SUM(likes + comments + shares) FROM analytics_history h WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" . $scopeFilter);
        $metrics['weekly_engagement'] = (int)$this->stmt->fetchColumn();

        // Monthly engagement (last 30 days)
        $this->query("SELECT SUM(likes + comments + shares) FROM analytics_history h WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" . $scopeFilter);
        $metrics['monthly_engagement'] = (int)$this->stmt->fetchColumn();

        // Average engagement rate
        $this->query("SELECT AVG(engagement_rate) FROM analytics_entries");
        $metrics['average_engagement_rate'] = round((float)$this->stmt->fetchColumn(), 2);

        return $metrics;
    }

    // Platform-wise analytics
    public function getPlatformAnalytics() {
        $this->query("SELECT p.name, p.icon,
                             SUM(ae.likes) as likes, SUM(ae.comments) as comments, 
                             SUM(ae.shares) as shares, SUM(ae.views) as views,
                             AVG(ae.engagement_rate) as engagement_rate
                      FROM platforms p
                      LEFT JOIN analytics_entries ae ON p.platform_id = ae.platform_id
                      GROUP BY p.platform_id
                      ORDER BY engagement_rate DESC");
        return $this->resultSet();
    }

    // Account-wise analytics
    public function getAccountAnalytics() {
        $this->query("SELECT sa.profile_name, p.name as platform_name, p.icon as platform_icon,
                             SUM(ae.likes) as likes, SUM(ae.comments) as comments, 
                             SUM(ae.shares) as shares, SUM(ae.views) as views,
                             AVG(ae.engagement_rate) as engagement_rate
                      FROM social_accounts sa
                      JOIN platforms p ON sa.platform_id = p.platform_id
                      LEFT JOIN analytics_entries ae ON sa.account_id = ae.account_id
                      GROUP BY sa.account_id
                      ORDER BY engagement_rate DESC");
        return $this->resultSet();
    }

    // Employee performance score mapping for Manager Dashboard
    public function getEmployeeProductivity() {
        $this->query("SELECT u.user_id, u.name as employee_name,
                             (SELECT COUNT(*) FROM assignments WHERE user_id = u.user_id) as assigned_accounts,
                             (SELECT COUNT(DISTINCT account_id) FROM analytics_history WHERE updated_by = u.user_id AND DATE(created_at) = CURRENT_DATE()) as updates_today,
                             (SELECT MAX(created_at) FROM analytics_history WHERE updated_by = u.user_id) as last_update_time
                      FROM users u
                      JOIN roles r ON u.role_id = r.role_id
                      WHERE r.role_name = 'employee' AND u.status = 'active'
                      ORDER BY updates_today DESC");
        $rows = $this->resultSet();
        
        foreach ($rows as $row) {
            $assigned = $row->assigned_accounts;
            $today = $row->updates_today;
            
            // Performance score = (updates completed today / accounts assigned) * 100
            if ($assigned > 0) {
                $score = ($today / $assigned) * 100;
                $row->performance_score = round(min(100.0, $score), 2);
            } else {
                $row->performance_score = 0.00;
            }
        }
        return $rows;
    }
}
