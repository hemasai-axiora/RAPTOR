<?php
// Raptor CRM Post Model

class Post extends Model {
    // Get all posts
    public function getPosts() {
        $this->query('SELECT p.*, s.profile_name, pl.name as platform_name, pl.icon as platform_icon 
                      FROM posts p
                      JOIN social_accounts s ON p.account_id = s.account_id
                      LEFT JOIN platforms pl ON p.platform_id = pl.platform_id
                      ORDER BY p.created_at DESC');
        return $this->resultSet();
    }

    // Get post by ID
    public function getPostById($id) {
        $this->query('SELECT p.*, s.profile_name, pl.name as platform_name, pl.icon as platform_icon 
                      FROM posts p
                      JOIN social_accounts s ON p.account_id = s.account_id
                      LEFT JOIN platforms pl ON p.platform_id = pl.platform_id
                      WHERE p.post_id = :id');
        $this->bind(':id', $id);
        return $this->single();
    }

    // Get posts by Account ID
    public function getPostsByAccount($accountId) {
        $this->query('SELECT p.*, pl.name as platform_name, pl.icon as platform_icon
                      FROM posts p
                      LEFT JOIN platforms pl ON p.platform_id = pl.platform_id
                      WHERE p.account_id = :account_id
                      ORDER BY p.created_at DESC');
        $this->bind(':account_id', $accountId);
        return $this->resultSet();
    }

    // Add post
    public function addPost($data) {
        $this->query('INSERT INTO posts (account_id, platform_id, campaign_id, content, media_url, status, scheduled_at, published_at) 
                      VALUES (:account_id, :platform_id, :campaign_id, :content, :media_url, :status, :scheduled_at, :published_at)');
        $this->bind(':account_id', $data['account_id']);
        $this->bind(':platform_id', $data['platform_id']);
        $this->bind(':campaign_id', $data['campaign_id'] ?? null);
        $this->bind(':content', $data['content']);
        $this->bind(':media_url', $data['media_url'] ?? null);
        $this->bind(':status', $data['status'] ?? 'draft');
        $this->bind(':scheduled_at', $data['scheduled_at'] ?? null);
        $this->bind(':published_at', $data['published_at'] ?? null);
        return $this->execute();
    }

    // Update post
    public function updatePost($data) {
        $this->query('UPDATE posts 
                      SET account_id = :account_id, platform_id = :platform_id, campaign_id = :campaign_id, content = :content, media_url = :media_url, status = :status, scheduled_at = :scheduled_at, published_at = :published_at 
                      WHERE post_id = :id');
        $this->bind(':account_id', $data['account_id']);
        $this->bind(':platform_id', $data['platform_id']);
        $this->bind(':campaign_id', $data['campaign_id'] ?? null);
        $this->bind(':content', $data['content']);
        $this->bind(':media_url', $data['media_url'] ?? null);
        $this->bind(':status', $data['status']);
        $this->bind(':scheduled_at', $data['scheduled_at'] ?? null);
        $this->bind(':published_at', $data['published_at'] ?? null);
        $this->bind(':id', $data['post_id']);
        return $this->execute();
    }

    // Remove post
    public function removePost($id) {
        $this->query('DELETE FROM posts WHERE post_id = :id');
        $this->bind(':id', $id);
        return $this->execute();
    }
}
