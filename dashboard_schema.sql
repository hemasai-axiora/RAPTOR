-- ============================================================================
-- Raptor CRM Dashboard Database Schema (MySQL 8+ / MariaDB)
-- Based on the Axiora Pulse Digital Marketing Research Document Specs
-- Date: July 3, 2026
-- ============================================================================

CREATE DATABASE IF NOT EXISTS raptor_crm_db;
USE raptor_crm_db;

-- Avoid Foreign Key checks error during rebuilds
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. AUTHENTICATION & ROLE-BASED ACCESS CONTROL (RBAC)
-- ----------------------------------------------------------------------------

-- Roles table mapping the 4 key roles: admin, manager, analyst, employer
DROP TABLE IF EXISTS roles;
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE COMMENT 'admin, manager, analyst, employer',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions list defining explicit access policies
DROP TABLE IF EXISTS permissions;
CREATE TABLE permissions (
    permission_id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) NOT NULL UNIQUE COMMENT 'e.g. view_executive_dashboard, edit_budgets',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intersection table for role-based permissions assignment
DROP TABLE IF EXISTS role_permissions;
CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User accounts table linked to roles
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL COMMENT 'Bcrypt hash password',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 2. CLIENT MANAGEMENT & USER LIFECYCLE (JOURNEY MAP)
-- ----------------------------------------------------------------------------

-- Client companies (the "Employers" who pay for marketing services)
DROP TABLE IF EXISTS clients;
CREATE TABLE clients (
    client_id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    contract_start DATE,
    contract_end DATE,
    package_details TEXT,
    billing_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leads tracking with quality and conversion probability (Customer Intelligence Dashboard)
DROP TABLE IF EXISTS leads;
CREATE TABLE leads (
    lead_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NULL,
    assigned_to_user_id INT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50),
    email VARCHAR(100),
    phone VARCHAR(20),
    status ENUM('new', 'contacted', 'qualified', 'lost') DEFAULT 'new',
    lead_quality ENUM('hot', 'warm', 'cold') DEFAULT 'warm' COMMENT 'Hot (32%), Warm (45%), Cold (23%)',
    conversion_probability DECIMAL(5,2) DEFAULT 0.00 COMMENT 'AI calculated conversion probability %',
    lead_value DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Expected revenue value',
    lead_source VARCHAR(50) NOT NULL COMMENT 'LinkedIn, Organic Search, Instagram, Facebook, YouTube, Email, Direct',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Journey Map cohorts tracking for drop-off funnel metrics
-- Stages: Reach, Website Visitors, Engaged Users, Leads, Qualified Leads, Customers, Revenue
DROP TABLE IF EXISTS customer_journey_stages;
CREATE TABLE customer_journey_stages (
    stage_id INT AUTO_INCREMENT PRIMARY KEY,
    stage_name VARCHAR(50) NOT NULL UNIQUE COMMENT 'Reach, Visitors, Engaged, Leads, Qualified, Customers',
    sort_order INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Journey logs tracking contact drop-offs
DROP TABLE IF EXISTS customer_journey_log;
CREATE TABLE customer_journey_log (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    from_stage_id INT NULL,
    to_stage_id INT NOT NULL,
    transitioned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(lead_id) ON DELETE CASCADE,
    FOREIGN KEY (from_stage_id) REFERENCES customer_journey_stages(stage_id) ON DELETE SET NULL,
    FOREIGN KEY (to_stage_id) REFERENCES customer_journey_stages(stage_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 3. CAMPAIGNS & BUDGET OPTIMIZATION
-- ----------------------------------------------------------------------------

-- Marketing campaigns (Top Performing Campaigns grid)
DROP TABLE IF EXISTS campaigns;
CREATE TABLE campaigns (
    campaign_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    channel VARCHAR(50) NOT NULL COMMENT 'LinkedIn, Instagram, Facebook, YouTube, X, Website',
    budget DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    spend DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Accumulated spend to date',
    revenue_influenced DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Attributed revenue',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    status ENUM('active', 'paused', 'completed') DEFAULT 'active',
    roi DECIMAL(5,2) GENERATED ALWAYS AS (CASE WHEN spend > 0 THEN (revenue_influenced / spend) ELSE 0.00 END) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recommendations generated by AI for budget reallocations
DROP TABLE IF EXISTS campaign_budget_recommendations;
CREATE TABLE campaign_budget_recommendations (
    recommendation_id INT AUTO_INCREMENT PRIMARY KEY,
    from_campaign_id INT NOT NULL,
    to_campaign_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'applied', 'dismissed') DEFAULT 'pending',
    applied_by_user_id INT NULL,
    applied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (to_campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (applied_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 4. SOCIAL MEDIA PLATFORMS & CONTENT CALENDAR
-- ----------------------------------------------------------------------------

-- Connected Social Media Accounts
DROP TABLE IF EXISTS social_accounts;
CREATE TABLE social_accounts (
    account_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    platform VARCHAR(50) NOT NULL COMMENT 'facebook, instagram, linkedin, youtube, x',
    profile_name VARCHAR(100) NOT NULL,
    profile_url VARCHAR(255),
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at TIMESTAMP NULL,
    status ENUM('active', 'expired', 'disconnected') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Content Calendar Posts
DROP TABLE IF EXISTS posts;
CREATE TABLE posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    campaign_id INT NULL,
    content TEXT,
    media_url VARCHAR(255) COMMENT 'Link to image/video assets in storage',
    status ENUM('draft', 'scheduled', 'published', 'failed') DEFAULT 'draft',
    scheduled_at TIMESTAMP NULL,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES social_accounts(account_id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily statistics for published posts (Content Performance Top 5)
DROP TABLE IF EXISTS post_analytics;
CREATE TABLE post_analytics (
    analytics_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    reach INT DEFAULT 0,
    impressions INT DEFAULT 0,
    engagements INT DEFAULT 0,
    clicks INT DEFAULT 0,
    recorded_date DATE NOT NULL,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 5. AGGREGATED METRICS SNAPSHOTS (FOR RAPID DASHBOARD LOADING)
-- ----------------------------------------------------------------------------

-- Aggregated daily channel performance metrics (Slide 3 platform comparison)
DROP TABLE IF EXISTS channel_daily_metrics;
CREATE TABLE channel_daily_metrics (
    metric_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    platform VARCHAR(50) NOT NULL COMMENT 'facebook, instagram, linkedin, youtube, x, website',
    metric_date DATE NOT NULL,
    reach BIGINT DEFAULT 0,
    impressions BIGINT DEFAULT 0,
    engagements BIGINT DEFAULT 0,
    clicks INT DEFAULT 0,
    leads_generated INT DEFAULT 0,
    revenue_influenced DECIMAL(12,2) DEFAULT 0.00,
    spend DECIMAL(12,2) DEFAULT 0.00,
    ctr DECIMAL(5,2) GENERATED ALWAYS AS (CASE WHEN impressions > 0 THEN (clicks / impressions)*100 ELSE 0.00 END) STORED,
    engagement_rate DECIMAL(5,2) GENERATED ALWAYS AS (CASE WHEN reach > 0 THEN (engagements / reach)*100 ELSE 0.00 END) STORED,
    cost_per_lead DECIMAL(10,2) GENERATED ALWAYS AS (CASE WHEN leads_generated > 0 THEN (spend / leads_generated) ELSE spend END) STORED,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE,
    UNIQUE KEY uq_client_platform_date (client_id, platform, metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Best Posting Time Heatmap aggregation cache
DROP TABLE IF EXISTS best_posting_time_metrics;
CREATE TABLE best_posting_time_metrics (
    metric_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    platform VARCHAR(50) NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0 (Sunday) to 6 (Saturday)',
    hour_of_day TINYINT NOT NULL COMMENT '0 to 23',
    avg_engagement_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    total_posts_analyzed INT NOT NULL DEFAULT 0,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE,
    UNIQUE KEY uq_client_platform_schedule (client_id, platform, day_of_week, hour_of_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 6. AUDIENCE DEMOGRAPHICS & BRAND SENTIMENT
-- ----------------------------------------------------------------------------

-- Audience Demographics snapshots
DROP TABLE IF EXISTS audience_demographics_snapshots;
CREATE TABLE audience_demographics_snapshots (
    snapshot_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    dimension_type ENUM('age', 'gender', 'location', 'device') NOT NULL,
    dimension_label VARCHAR(100) NOT NULL COMMENT 'e.g. 25-34, Female, India, Mobile',
    percentage DECIMAL(5,2) NOT NULL,
    recorded_date DATE NOT NULL,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE,
    UNIQUE KEY uq_client_demographics (client_id, dimension_type, dimension_label, recorded_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audience top interest distributions
DROP TABLE IF EXISTS audience_interests_snapshots;
CREATE TABLE audience_interests_snapshots (
    interest_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    interest_name VARCHAR(100) NOT NULL COMMENT 'e.g. Entrepreneurship, AI & Technology',
    percentage DECIMAL(5,2) NOT NULL,
    recorded_date DATE NOT NULL,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE,
    UNIQUE KEY uq_client_interest (client_id, interest_name, recorded_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily brand sentiment logs (Customer Intelligence)
DROP TABLE IF EXISTS brand_sentiment_logs;
CREATE TABLE brand_sentiment_logs (
    sentiment_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    recorded_date DATE NOT NULL,
    positive_score DECIMAL(5,2) NOT NULL COMMENT 'Score percentage out of 100',
    neutral_score DECIMAL(5,2) NOT NULL,
    negative_score DECIMAL(5,2) NOT NULL,
    overall_sentiment_score INT NOT NULL COMMENT 'Index 0-100 (e.g. 78/100)',
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE,
    UNIQUE KEY uq_client_sentiment_date (client_id, recorded_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Competitor Benchmarking statistics
DROP TABLE IF EXISTS competitor_benchmarks;
CREATE TABLE competitor_benchmarks (
    benchmark_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    metric_name VARCHAR(100) NOT NULL COMMENT 'Engagement Rate, Follower Growth, CTR, Conversion Rate, Share of Voice',
    our_metric_value DECIMAL(5,2) NOT NULL,
    competitor_avg_value DECIMAL(5,2) NOT NULL,
    vs_competitor_percentage DECIMAL(5,2) NOT NULL COMMENT 'e.g. +59.00',
    recorded_date DATE NOT NULL,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE,
    UNIQUE KEY uq_client_benchmark_metric (client_id, metric_name, recorded_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 7. WEBSITE BEHAVIOR & MULTI-TOUCH ATTRIBUTION LOGGING
-- ----------------------------------------------------------------------------

-- Web sessions and traffic summaries
DROP TABLE IF EXISTS web_behavior_sessions;
CREATE TABLE web_behavior_sessions (
    session_id VARCHAR(100) PRIMARY KEY,
    client_id INT NOT NULL,
    visitor_id VARCHAR(100) NOT NULL,
    is_returning BOOLEAN DEFAULT FALSE,
    landing_page VARCHAR(255) NOT NULL,
    exit_page VARCHAR(255) NULL,
    pages_viewed INT DEFAULT 1,
    scroll_depth_percent INT DEFAULT 0,
    session_duration_seconds INT DEFAULT 0,
    device_type VARCHAR(50) NOT NULL COMMENT 'Desktop, Tablet, Mobile',
    country VARCHAR(100),
    city VARCHAR(100),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Multi-touch attribution points tracking for calculations
-- Tracks user steps before final lead generation/conversion
DROP TABLE IF EXISTS attribution_touchpoints;
CREATE TABLE attribution_touchpoints (
    touchpoint_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    session_id VARCHAR(100) NOT NULL,
    touchpoint_order INT NOT NULL COMMENT '1 = First Touch, etc.',
    traffic_channel VARCHAR(50) NOT NULL COMMENT 'LinkedIn, Instagram, Google, Facebook, Direct',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(lead_id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES web_behavior_sessions(session_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 8. BUSINESS OPERATIONS & OPERATIONS LOGGING
-- ----------------------------------------------------------------------------

-- Employees profiles (for Manager & Admin views)
DROP TABLE IF EXISTS employees;
CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department VARCHAR(100) NOT NULL,
    job_title VARCHAR(100) NOT NULL,
    hire_date DATE NOT NULL,
    status ENUM('active', 'suspended', 'terminated') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task tracking (for Managers & Employees)
DROP TABLE IF EXISTS tasks;
CREATE TABLE tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    assigned_to_user_id INT NOT NULL,
    created_by_user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    deadline DATETIME NOT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Financial Invoices
DROP TABLE IF EXISTS invoices;
CREATE TABLE invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('unpaid', 'paid', 'overdue') DEFAULT 'unpaid',
    due_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Financial Payments tracking
DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50) COMMENT 'Stripe, PayPal, Razorpay, Bank Transfer',
    transaction_reference VARCHAR(150),
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 9. NOTIFICATIONS, SMART ALERTS & SYSTEM AUDITS
-- ----------------------------------------------------------------------------

-- Smart Alerts displayed at the bottom of the screens (Executive Overview / Settings)
DROP TABLE IF EXISTS smart_alerts;
CREATE TABLE smart_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
    message VARCHAR(255) NOT NULL COMMENT 'e.g. ROI dropped on X (Twitter) by 18%',
    metric_linked VARCHAR(100) NULL COMMENT 'e.g. roi_x_twitter, lead_quality_facebook',
    status ENUM('active', 'resolved', 'dismissed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Audit log
DROP TABLE IF EXISTS activity_logs;
CREATE TABLE activity_logs (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 9B. GLOBAL SYSTEM SETTINGS
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 10. DATABASE QUERY OPTIMIZATION INDEXES
-- ----------------------------------------------------------------------------

-- Optimization for Authentication & Users
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role_id);

-- Optimization for Leads tracking
CREATE INDEX idx_leads_client ON leads(client_id);
CREATE INDEX idx_leads_status ON leads(status);
CREATE INDEX idx_leads_quality ON leads(lead_quality);
CREATE INDEX idx_leads_source ON leads(lead_source);

-- Optimization for Campaigns & Channel metrics
CREATE INDEX idx_campaigns_client ON campaigns(client_id);
CREATE INDEX idx_campaigns_channel ON campaigns(channel);
CREATE INDEX idx_campaigns_status ON campaigns(status);
CREATE INDEX idx_channel_metrics_date ON channel_daily_metrics(metric_date);
CREATE INDEX idx_channel_metrics_lookup ON channel_daily_metrics(client_id, platform, metric_date);

-- Optimization for Web Behavior & Attribution
CREATE INDEX idx_sessions_client_date ON web_behavior_sessions(client_id, created_at);
CREATE INDEX idx_touchpoints_lead ON attribution_touchpoints(lead_id);
CREATE INDEX idx_touchpoints_channel ON attribution_touchpoints(traffic_channel);

-- Optimization for Brand Sentiment & Competitor benchmarks
CREATE INDEX idx_sentiment_client_date ON brand_sentiment_logs(client_id, recorded_date);
CREATE INDEX idx_benchmarks_client ON competitor_benchmarks(client_id, metric_name);
CREATE INDEX idx_smart_alerts_status ON smart_alerts(client_id, status);

SET FOREIGN_KEY_CHECKS = 1;
