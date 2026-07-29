<?php
// WebsiteAnalytics Model - Manages Website Traffic & Behavior Snapshots

class WebsiteAnalytics extends Model {

    public function getLatestSnapshot($clientId = null) {
        $sql = 'SELECT s.*, c.company_name AS client_name 
                FROM website_analytics_snapshots s
                JOIN clients c ON s.client_id = c.client_id';

        if ($clientId) {
            $sql .= ' WHERE s.client_id = :client_id';
        }

        $sql .= ' ORDER BY s.snapshot_date DESC LIMIT 1';

        $this->query($sql);
        if ($clientId) {
            $this->bind(':client_id', (int)$clientId);
        }

        return $this->single();
    }

    public function getSnapshots($clientId = null, $limit = 30) {
        $sql = 'SELECT s.*, c.company_name AS client_name 
                FROM website_analytics_snapshots s
                JOIN clients c ON s.client_id = c.client_id';

        if ($clientId) {
            $sql .= ' WHERE s.client_id = :client_id';
        }

        $sql .= ' ORDER BY s.snapshot_date ASC LIMIT ' . (int)$limit;

        $this->query($sql);
        if ($clientId) {
            $this->bind(':client_id', (int)$clientId);
        }

        return $this->resultSet();
    }

    public function getTrafficSources($snapshotId) {
        $this->query('SELECT * FROM website_traffic_sources WHERE snapshot_id = :snapshot_id ORDER BY sessions DESC');
        $this->bind(':snapshot_id', (int)$snapshotId);
        return $this->resultSet();
    }

    public function getTopPages($snapshotId) {
        $this->query('SELECT * FROM website_top_pages WHERE snapshot_id = :snapshot_id ORDER BY pageviews DESC LIMIT 10');
        $this->bind(':snapshot_id', (int)$snapshotId);
        return $this->resultSet();
    }

    public function getGA4Credentials($clientId) {
        $this->query('SELECT * FROM website_credentials WHERE client_id = :client_id');
        $this->bind(':client_id', (int)$clientId);
        return $this->single();
    }

    public function saveGA4Credentials($clientId, $ga4PropertyId) {
        $this->query('INSERT INTO website_credentials (client_id, ga4_property_id, status) 
                      VALUES (:client_id, :ga4_property_id, "Active") 
                      ON DUPLICATE KEY UPDATE ga4_property_id = :ga4_property_id, status = "Active"');
        $this->bind(':client_id', (int)$clientId);
        $this->bind(':ga4_property_id', $ga4PropertyId);
        return $this->execute();
    }

    public function saveSnapshotData($clientId, $date, $metrics, $sources, $pages) {
        // Insert or update snapshot
        $this->query('INSERT INTO website_analytics_snapshots 
            (client_id, snapshot_date, sessions, users, new_users, pageviews, bounce_rate, avg_session_duration) 
            VALUES 
            (:client_id, :snapshot_date, :sessions, :users, :new_users, :pageviews, :bounce_rate, :avg_session_duration)
            ON DUPLICATE KEY UPDATE 
            sessions = VALUES(sessions), users = VALUES(users), new_users = VALUES(new_users), 
            pageviews = VALUES(pageviews), bounce_rate = VALUES(bounce_rate), avg_session_duration = VALUES(avg_session_duration)');

        $this->bind(':client_id', (int)$clientId);
        $this->bind(':snapshot_date', $date);
        $this->bind(':sessions', (int)($metrics['sessions'] ?? 0));
        $this->bind(':users', (int)($metrics['users'] ?? 0));
        $this->bind(':new_users', (int)($metrics['new_users'] ?? 0));
        $this->bind(':pageviews', (int)($metrics['pageviews'] ?? 0));
        $this->bind(':bounce_rate', (float)($metrics['bounce_rate'] ?? 0));
        $this->bind(':avg_session_duration', (int)($metrics['avg_session_duration'] ?? 0));

        if ($this->execute()) {
            $snapshot = $this->getLatestSnapshot($clientId);
            if ($snapshot && !empty($snapshot->snapshot_id)) {
                $snapshotId = $snapshot->snapshot_id;

                // Refresh sources
                $db = Database::getInstance()->getConnection();
                $db->exec("DELETE FROM website_traffic_sources WHERE snapshot_id = {$snapshotId}");
                foreach ($sources as $src) {
                    $this->query('INSERT INTO website_traffic_sources (snapshot_id, channel_group, sessions, conversions) VALUES (:snapshot_id, :channel_group, :sessions, :conversions)');
                    $this->bind(':snapshot_id', $snapshotId);
                    $this->bind(':channel_group', $src['channel_group']);
                    $this->bind(':sessions', (int)$src['sessions']);
                    $this->bind(':conversions', (int)$src['conversions']);
                    $this->execute();
                }

                // Refresh top pages
                $db->exec("DELETE FROM website_top_pages WHERE snapshot_id = {$snapshotId}");
                foreach ($pages as $pg) {
                    $this->query('INSERT INTO website_top_pages (snapshot_id, page_path, pageviews, avg_time_on_page, conversions) VALUES (:snapshot_id, :page_path, :pageviews, :avg_time_on_page, :conversions)');
                    $this->bind(':snapshot_id', $snapshotId);
                    $this->bind(':page_path', $pg['page_path']);
                    $this->bind(':pageviews', (int)$pg['pageviews']);
                    $this->bind(':avg_time_on_page', (int)$pg['avg_time_on_page']);
                    $this->bind(':conversions', (int)$pg['conversions']);
                    $this->execute();
                }
                return true;
            }
        }
        return false;
    }
}
