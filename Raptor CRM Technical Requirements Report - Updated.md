# Raptor CRM - Technical Requirements Report (TRR)

**Version:** 2.0 (Updated)  
**Author:** AI Pair Programmer (Antigravity)  
**Date:** July 3, 2026

---

## 1. Objective
Develop a secure, scalable, responsive, and lightweight Digital Marketing CRM named Raptor CRM (incorporating the **Axiora Pulse** dashboard layout). The application must be fully compatible with standard PHP shared hosting environments such as ProFreeHost, InfinityFree, and cPanel-based hosting, with zero Node.js/Docker server-side dependencies.

---

## 2. Technology Stack
* **Backend:** PHP 8.x, PDO (PHP Data Objects), MVC architecture, Native REST API endpoints.
* **Frontend:** HTML5, CSS3, Bootstrap 5, JavaScript (ES6), jQuery, AJAX.
* **Database:** MySQL 8+ / MariaDB.
* **Web Server:** Apache (with `.htaccess` rewrite rules for routing).

---

## 3. Third-Party Libraries
The platform relies on lightweight client-side and server-side libraries to enable rich visual dashboards and reports without heavy builds:

### 3.1 UI & Charting Libraries
* **ApexCharts:** Used for high-fidelity interactive visual charts (e.g. *Engagement Time Heatmaps*, *Spend vs. Revenue Combo Charts*, *Geographic Performance bubble charts*).
* **Chart.js:** Used for fast-rendering donut charts (*Lead Quality*, *Attribution Analysis*, *Channel Revenue*) and sparkline trend indicators.
* **DataTables:** For server-side paginated tables (*Campaign Performance*, *Top Performing Campaigns*, *Content Performance*).
* **FullCalendar:** For scheduling and managing drafts on the Content Calendar.
* **Font Awesome:** For system icon layouts.
* **SweetAlert2 & Toastr:** For non-blocking UI alert triggers and smart alert prompts.
* **Select2 & Flatpickr:** For searchable select boxes and date filters.

### 3.2 PHP Libraries (Server-side)
* **PHPMailer:** SMTP-based mail client for password resets and notification triggers.
* **DomPDF:** Programmatic conversion of dashboard metrics and invoices to PDF files.
* **PhpSpreadsheet:** Exporter tool to generate campaign reports in Excel and CSV.
* **Carbon:** Semantic date/time parsing.

---

## 4. Role-Based Database Schema (MySQL 8+)
To support the 4 roles (Admin, Manager, Analyst, Employer) and the rich dashboard datasets, the database schema contains the following structure:

### 4.1 Roles & Users (RBAC)
```sql
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE, -- 'admin', 'manager', 'analyst', 'employer'
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- bcrypt hashes
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

CREATE TABLE permissions (
    permission_id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) NOT NULL UNIQUE, -- e.g., 'view_executive_dashboard', 'edit_budgets'
    description TEXT
);

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
);
```

### 4.2 Clients, Leads & Campaigns
```sql
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE leads (
    lead_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    assigned_to_user_id INT, -- links to users (Manager/Analyst)
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    status ENUM('new', 'contacted', 'qualified', 'lost') DEFAULT 'new',
    lead_value DECIMAL(10, 2) DEFAULT 0.00,
    lead_quality ENUM('hot', 'warm', 'cold') DEFAULT 'warm',
    lead_source VARCHAR(50), -- 'LinkedIn', 'Google', 'Facebook', etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE campaigns (
    campaign_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    name VARCHAR(150) NOT NULL,
    channel VARCHAR(50), -- 'LinkedIn', 'Instagram', 'YouTube', etc.
    budget DECIMAL(10, 2) DEFAULT 0.00,
    spend DECIMAL(10, 2) DEFAULT 0.00,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'paused', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
);
```

### 4.3 Social Media & Analytics
```sql
CREATE TABLE social_accounts (
    account_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    platform VARCHAR(50) NOT NULL, -- 'facebook', 'instagram', 'linkedin', 'youtube', 'x'
    profile_name VARCHAR(100) NOT NULL,
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at TIMESTAMP,
    status DEFAULT 'active',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
);

CREATE TABLE posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    content TEXT,
    media_url VARCHAR(255),
    status ENUM('draft', 'scheduled', 'published', 'failed') DEFAULT 'draft',
    scheduled_at TIMESTAMP NULL,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES social_accounts(account_id) ON DELETE CASCADE
);

CREATE TABLE post_statistics (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    reach INT DEFAULT 0,
    impressions INT DEFAULT 0,
    engagements INT DEFAULT 0,
    clicks INT DEFAULT 0,
    recorded_date DATE NOT NULL,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE
);
```

### 4.4 Operations & Tracking
```sql
CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department VARCHAR(100),
    job_title VARCHAR(100),
    hire_date DATE,
    status ENUM('active', 'suspended', 'terminated') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    assigned_to INT NOT NULL, -- user_id
    created_by INT NOT NULL,   -- user_id
    title VARCHAR(150) NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    deadline DATETIME,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('unpaid', 'paid', 'overdue') DEFAULT 'unpaid',
    due_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
);

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    transaction_ref VARCHAR(100),
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE
);

CREATE TABLE activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);
```

---

## 5. Third-Party API Integrations (Dashboard Feeders)
The dashboard metrics (e.g. conversions, clicks, impressions) are populated via asynchronous backend workers pulling data from API connections:

1. **Meta Graph API (Facebook & Instagram):**
   * *Endpoints used:* `/{page-id}/insights`, `/{instagram-business-account-id}/insights`.
   * *Extracted Metrics:* Reach, Impressions, Engagements, Profile Clicks, Demographics (Age/Gender/Location).
2. **LinkedIn API:**
   * *Endpoints used:* `/organizationalEntityShareStatistics`, `/organizationalEntityFollowerStatistics`.
   * *Extracted Metrics:* Impressions, Clicks, Share Metrics, Follower Demographics.
3. **YouTube Data API v3:**
   * *Endpoints used:* `youtubeAnalytics.reports.query`.
   * *Extracted Metrics:* Views, Likes, Shares, Average View Duration, Subscribers.
4. **Google Ads API:**
   * *Reports fetched:* `campaign`, `ad_group`, `ad_group_ad`.
   * *Extracted Metrics:* Impressions, Clicks, Spend (Cost), Conversions.
5. **Google Analytics (GA4) Admin API:**
   * *Extracted Metrics:* Website Visitors, Bounce Rate, Top Landing/Exit Pages, Scroll Depth.

---

## 6. Dashboard Data Flow Architecture
1. **API Pulls:** A cron script or background worker fetches platform statistics hourly and stores them in `post_statistics`, `leads`, and `campaigns`.
2. **Aggregation Queries:** Database views aggregate hourly tables into daily and monthly summary buckets.
3. **Rest endpoints:** Lightweight PHP controllers serve requests via JSON:
   * `GET /api/dashboard/executive`: Outputs consolidated metrics, spend vs. revenue, funnel stages, and smart alert anomalies.
   * `GET /api/dashboard/campaigns`: Returns active campaigns, budget status, and channel matrices.
   * `GET /api/dashboard/customer-intelligence`: Outputs journey map counts, CSAT, demographics, and multi-touch attribution weights.
4. **Asynchronous rendering:** JavaScript templates call ApexCharts/Chart.js to display the charts interactively.
