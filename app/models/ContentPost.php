<?php
// Raptor CRM Content Post Model

class ContentPost extends Model {

    public function generatePostCode(): string {
        $year = date('Y');
        $this->query('SELECT MAX(post_id) AS max_id FROM posts');
        $row = $this->single();
        $nextId = ($row && $row->max_id) ? ((int) $row->max_id + 1) : 1;
        return sprintf('PST-%s-%05d', $year, $nextId);
    }

    public function getPosts(array $filters = []) {
        $sql = 'SELECT p.*, 
                       cl.company_name AS client_name, 
                       cmp.name AS campaign_name,
                       cmp.campaign_code
                FROM posts p
                LEFT JOIN clients cl ON p.client_id = cl.client_id
                LEFT JOIN campaigns cmp ON p.campaign_id = cmp.campaign_id
                WHERE 1=1';

        $params = [];

        if (!empty($filters['client_id'])) {
            $sql .= ' AND p.client_id = :client_id';
            $params[':client_id'] = (int)$filters['client_id'];
        }

        if (!empty($filters['platform'])) {
            $sql .= ' AND p.platform = :platform';
            $params[':platform'] = $filters['platform'];
        }

        if (!empty($filters['content_type'])) {
            $sql .= ' AND p.content_type = :content_type';
            $params[':content_type'] = $filters['content_type'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (p.post_code LIKE :search OR p.title LIKE :search OR p.content LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sort = $filters['sort'] ?? 'published_at';
        switch ($sort) {
            case 'engagement_rate':
                $sql .= ' ORDER BY p.current_engagement_rate DESC, p.created_at DESC';
                break;
            case 'reach':
                $sql .= ' ORDER BY p.current_reach DESC, p.created_at DESC';
                break;
            case 'published_at':
            default:
                $sql .= ' ORDER BY p.published_at DESC, p.created_at DESC';
                break;
        }

        $this->query($sql);
        foreach ($params as $param => $value) {
            $this->bind($param, $value);
        }
        return $this->resultSet();
    }

    public function getPostById($id) {
        $this->query('SELECT p.*, 
                             cl.company_name AS client_name, 
                             cmp.name AS campaign_name,
                             cmp.campaign_code
                      FROM posts p
                      LEFT JOIN clients cl ON p.client_id = cl.client_id
                      LEFT JOIN campaigns cmp ON p.campaign_id = cmp.campaign_id
                      WHERE p.post_id = :id');
        $this->bind(':id', $id);
        return $this->single();
    }

    public function addPost($data) {
        if (empty($data['post_code']) || strpos($data['post_code'], 'Auto-generated') !== false) {
            $data['post_code'] = $this->generatePostCode();
        }

        $this->query('INSERT INTO posts 
            (post_code, client_id, campaign_id, account_id, title, content, media_url, content_type, platform, status, scheduled_at, published_at) 
            VALUES 
            (:post_code, :client_id, :campaign_id, :account_id, :title, :content, :media_url, :content_type, :platform, :status, :scheduled_at, :published_at)');

        $this->bind(':post_code', $data['post_code']);
        $this->bind(':client_id', !empty($data['client_id']) ? (int)$data['client_id'] : null);
        $this->bind(':campaign_id', !empty($data['campaign_id']) ? (int)$data['campaign_id'] : null);
        $this->bind(':account_id', !empty($data['account_id']) ? (int)$data['account_id'] : null);
        $this->bind(':title', !empty($data['title']) ? $data['title'] : null);
        $this->bind(':content', $data['content'] ?? '');
        $this->bind(':media_url', !empty($data['media_url']) ? $data['media_url'] : null);
        $this->bind(':content_type', $data['content_type'] ?? 'Image Post');
        $this->bind(':platform', $data['platform'] ?? 'LinkedIn');
        $this->bind(':status', $data['status'] ?? 'published');
        $this->bind(':scheduled_at', !empty($data['scheduled_at']) ? $data['scheduled_at'] : null);
        $this->bind(':published_at', !empty($data['published_at']) ? $data['published_at'] : date('Y-m-d H:i:s'));

        return $this->execute();
    }

    public function updatePost($data) {
        $this->query('UPDATE posts 
                      SET client_id = :client_id, campaign_id = :campaign_id, title = :title, 
                          content = :content, media_url = :media_url, content_type = :content_type, 
                          platform = :platform, status = :status, scheduled_at = :scheduled_at, 
                          published_at = :published_at 
                      WHERE post_id = :id');

        $this->bind(':id', $data['post_id']);
        $this->bind(':client_id', !empty($data['client_id']) ? (int)$data['client_id'] : null);
        $this->bind(':campaign_id', !empty($data['campaign_id']) ? (int)$data['campaign_id'] : null);
        $this->bind(':title', !empty($data['title']) ? $data['title'] : null);
        $this->bind(':content', $data['content'] ?? '');
        $this->bind(':media_url', !empty($data['media_url']) ? $data['media_url'] : null);
        $this->bind(':content_type', $data['content_type'] ?? 'Image Post');
        $this->bind(':platform', $data['platform'] ?? 'LinkedIn');
        $this->bind(':status', $data['status'] ?? 'published');
        $this->bind(':scheduled_at', !empty($data['scheduled_at']) ? $data['scheduled_at'] : null);
        $this->bind(':published_at', !empty($data['published_at']) ? $data['published_at'] : null);

        return $this->execute();
    }

    public function recordEngagement($data) {
        $postId = (int)$data['post_id'];
        $likes = (int)($data['likes'] ?? 0);
        $comments = (int)($data['comments'] ?? 0);
        $shares = (int)($data['shares'] ?? 0);
        $saves = (int)($data['saves'] ?? 0);
        $reach = (int)($data['reach'] ?? 0);
        $impressions = (int)($data['impressions'] ?? 0);
        $clicks = (int)($data['clicks'] ?? 0);
        $videoViews = (int)($data['video_views'] ?? 0);
        $userId = $_SESSION['user_id'] ?? null;

        // Auto-calculate engagement rate
        $totalEngagements = $likes + $comments + $shares + $saves;
        $engagementRate = 0.00;
        if ($reach > 0) {
            $engagementRate = round(($totalEngagements / $reach) * 100, 2);
        } elseif ($impressions > 0) {
            $engagementRate = round(($totalEngagements / $impressions) * 100, 2);
        }

        // 1. Insert time-series record into content_engagement
        $this->query('INSERT INTO content_engagement 
            (post_id, likes, comments, shares, saves, reach, impressions, clicks, video_views, engagement_rate, created_by_user_id) 
            VALUES 
            (:post_id, :likes, :comments, :shares, :saves, :reach, :impressions, :clicks, :video_views, :engagement_rate, :created_by_user_id)');

        $this->bind(':post_id', $postId);
        $this->bind(':likes', $likes);
        $this->bind(':comments', $comments);
        $this->bind(':shares', $shares);
        $this->bind(':saves', $saves);
        $this->bind(':reach', $reach);
        $this->bind(':impressions', $impressions);
        $this->bind(':clicks', $clicks);
        $this->bind(':video_views', $videoViews);
        $this->bind(':engagement_rate', $engagementRate);
        $this->bind(':created_by_user_id', $userId);
        $this->execute();

        // 2. Update summary metrics on posts table
        $this->query('UPDATE posts 
                      SET current_likes = :likes,
                          current_comments = :comments,
                          current_shares = :shares,
                          current_saves = :saves,
                          current_reach = :reach,
                          current_impressions = :impressions,
                          current_clicks = :clicks,
                          current_video_views = :video_views,
                          current_engagement_rate = :engagement_rate,
                          last_engagement_updated_at = NOW() 
                      WHERE post_id = :id');

        $this->bind(':id', $postId);
        $this->bind(':likes', $likes);
        $this->bind(':comments', $comments);
        $this->bind(':shares', $shares);
        $this->bind(':saves', $saves);
        $this->bind(':reach', $reach);
        $this->bind(':impressions', $impressions);
        $this->bind(':clicks', $clicks);
        $this->bind(':video_views', $videoViews);
        $this->bind(':engagement_rate', $engagementRate);

        return $this->execute();
    }

    public function getEngagementHistory($postId) {
        $this->query('SELECT ce.*, u.name AS recorded_by_name
                      FROM content_engagement ce
                      LEFT JOIN users u ON ce.created_by_user_id = u.user_id
                      WHERE ce.post_id = :id
                      ORDER BY ce.captured_at ASC');
        $this->bind(':id', $postId);
        return $this->resultSet();
    }

    public function recordDemographics($data) {
        $postId = (int)$data['post_id'];
        $followersPct = (float)($data['followers_pct'] ?? 0);
        $nonFollowersPct = (float)($data['non_followers_pct'] ?? 0);
        $userId = $_SESSION['user_id'] ?? null;

        // 1. Insert parent demographic snapshot
        $this->query('INSERT INTO post_audience_demographics 
            (post_id, followers_pct, non_followers_pct, created_by_user_id) 
            VALUES 
            (:post_id, :followers_pct, :non_followers_pct, :created_by_user_id)');
        $this->bind(':post_id', $postId);
        $this->bind(':followers_pct', $followersPct);
        $this->bind(':non_followers_pct', $nonFollowersPct);
        $this->bind(':created_by_user_id', $userId);
        $this->execute();

        $db = Database::getInstance()->getConnection();
        $demographicsId = (int)$db->lastInsertId();

        if ($demographicsId > 0) {
            // 2. Insert Age Breakdown
            if (!empty($data['age']) && is_array($data['age'])) {
                $stmtAge = $db->prepare('INSERT INTO post_audience_age (demographics_id, age_bracket, percentage) VALUES (:did, :bracket, :pct)');
                foreach ($data['age'] as $bracket => $pct) {
                    if ((float)$pct > 0) {
                        $stmtAge->execute([':did' => $demographicsId, ':bracket' => $bracket, ':pct' => (float)$pct]);
                    }
                }
            }

            // 3. Insert Gender Breakdown
            if (!empty($data['gender']) && is_array($data['gender'])) {
                $stmtGender = $db->prepare('INSERT INTO post_audience_gender (demographics_id, gender, percentage) VALUES (:did, :gender, :pct)');
                foreach ($data['gender'] as $gender => $pct) {
                    if ((float)$pct > 0) {
                        $stmtGender->execute([':did' => $demographicsId, ':gender' => $gender, ':pct' => (float)$pct]);
                    }
                }
            }

            // 4. Insert Country Breakdown
            if (!empty($data['countries']) && is_array($data['countries'])) {
                $stmtCountry = $db->prepare('INSERT INTO post_audience_country (demographics_id, country, percentage) VALUES (:did, :country, :pct)');
                foreach ($data['countries'] as $c) {
                    $countryName = trim($c['country'] ?? '');
                    $pct = (float)($c['percentage'] ?? 0);
                    if ($countryName !== '' && $pct > 0) {
                        $stmtCountry->execute([':did' => $demographicsId, ':country' => $countryName, ':pct' => $pct]);
                    }
                }
            }
        }

        // 5. Update summary fields on posts table
        $this->query('UPDATE posts SET current_followers_pct = :f, current_non_followers_pct = :nf WHERE post_id = :id');
        $this->bind(':f', $followersPct);
        $this->bind(':nf', $nonFollowersPct);
        $this->bind(':id', $postId);
        return $this->execute();
    }

    public function getLatestDemographics($postId) {
        $this->query('SELECT * FROM post_audience_demographics WHERE post_id = :id ORDER BY captured_at DESC LIMIT 1');
        $this->bind(':id', $postId);
        $demo = $this->single();

        if (!$demo) {
            return null;
        }

        $db = Database::getInstance()->getConnection();

        // Fetch Age
        $stmtAge = $db->prepare('SELECT age_bracket, percentage FROM post_audience_age WHERE demographics_id = :did ORDER BY age_id ASC');
        $stmtAge->execute([':did' => $demo->demographics_id]);
        $demo->age = $stmtAge->fetchAll(PDO::FETCH_OBJ);

        // Fetch Gender
        $stmtGender = $db->prepare('SELECT gender, percentage FROM post_audience_gender WHERE demographics_id = :did ORDER BY gender_id ASC');
        $stmtGender->execute([':did' => $demo->demographics_id]);
        $demo->gender = $stmtGender->fetchAll(PDO::FETCH_OBJ);

        // Fetch Countries
        $stmtCountry = $db->prepare('SELECT country, percentage FROM post_audience_country WHERE demographics_id = :did ORDER BY percentage DESC');
        $stmtCountry->execute([':did' => $demo->demographics_id]);
        $demo->countries = $stmtCountry->fetchAll(PDO::FETCH_OBJ);

        return $demo;
    }

    public function getDemographicsHistory($postId) {
        $this->query('SELECT pad.*, u.name AS recorded_by_name 
                      FROM post_audience_demographics pad 
                      LEFT JOIN users u ON pad.created_by_user_id = u.user_id 
                      WHERE pad.post_id = :id 
                      ORDER BY pad.captured_at ASC');
        $this->bind(':id', $postId);
        return $this->resultSet();
    }

    public function getAggregatedDemographics($clientId = null, $campaignId = null) {
        $db = Database::getInstance()->getConnection();

        $where = 'WHERE 1=1';
        $params = [];
        if ($clientId) {
            $where .= ' AND p.client_id = :cid';
            $params[':cid'] = (int)$clientId;
        }
        if ($campaignId) {
            $where .= ' AND p.campaign_id = :cmpid';
            $params[':cmpid'] = (int)$campaignId;
        }

        // Weighted followers vs non-followers
        $sqlSplit = "SELECT 
                        SUM(p.current_followers_pct * GREATEST(p.current_reach, 1)) / NULLIF(SUM(GREATEST(p.current_reach, 1)), 0) AS weighted_followers_pct,
                        SUM(p.current_non_followers_pct * GREATEST(p.current_reach, 1)) / NULLIF(SUM(GREATEST(p.current_reach, 1)), 0) AS weighted_non_followers_pct,
                        SUM(p.current_reach) AS total_reach
                     FROM posts p {$where}";
        $stmt = $db->prepare($sqlSplit);
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_OBJ);

        // Weighted Age
        $sqlAge = "SELECT pa.age_bracket, 
                          ROUND(SUM(pa.percentage * GREATEST(p.current_reach, 1)) / NULLIF(SUM(GREATEST(p.current_reach, 1)), 0), 2) AS weighted_pct
                   FROM post_audience_age pa
                   JOIN post_audience_demographics pad ON pa.demographics_id = pad.demographics_id
                   JOIN posts p ON pad.post_id = p.post_id
                   {$where}
                   GROUP BY pa.age_bracket
                   ORDER BY weighted_pct DESC";
        $stmtAge = $db->prepare($sqlAge);
        $stmtAge->execute($params);
        $age = $stmtAge->fetchAll(PDO::FETCH_OBJ);

        // Weighted Gender
        $sqlGender = "SELECT pg.gender, 
                             ROUND(SUM(pg.percentage * GREATEST(p.current_reach, 1)) / NULLIF(SUM(GREATEST(p.current_reach, 1)), 0), 2) AS weighted_pct
                      FROM post_audience_gender pg
                      JOIN post_audience_demographics pad ON pg.demographics_id = pad.demographics_id
                      JOIN posts p ON pad.post_id = p.post_id
                      {$where}
                      GROUP BY pg.gender
                      ORDER BY weighted_pct DESC";
        $stmtGender = $db->prepare($sqlGender);
        $stmtGender->execute($params);
        $gender = $stmtGender->fetchAll(PDO::FETCH_OBJ);

        // Weighted Countries
        $sqlCountry = "SELECT pc.country, 
                              ROUND(SUM(pc.percentage * GREATEST(p.current_reach, 1)) / NULLIF(SUM(GREATEST(p.current_reach, 1)), 0), 2) AS weighted_pct
                       FROM post_audience_country pc
                       JOIN post_audience_demographics pad ON pc.demographics_id = pad.demographics_id
                       JOIN posts p ON pad.post_id = p.post_id
                       {$where}
                       GROUP BY pc.country
                       ORDER BY weighted_pct DESC
                       LIMIT 5";
        $stmtCountry = $db->prepare($sqlCountry);
        $stmtCountry->execute($params);
        $countries = $stmtCountry->fetchAll(PDO::FETCH_OBJ);

        return [
            'summary' => $summary,
            'age' => $age,
            'gender' => $gender,
            'countries' => $countries
        ];
    }
}
