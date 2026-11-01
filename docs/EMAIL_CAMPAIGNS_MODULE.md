# Email Campaigns & Mass Mailing Module - Enterprise Deliverability Guide

## 1. Overview & Deliverability Philosophy

The **Email Campaigns & Mass Mailing** module is an enterprise-grade deliverability engine built natively into Raptor CRM. It provides high-throughput bulk mailing with strict adherence to email authentication standards (**RFC 8058, SPF, 2048-bit DKIM, DMARC**), real-time bounce and complaint feedback loops, queue concurrency protection, and automated reputation safeguards.

### Core Philosophy: Maximize Inbox Placement, Zero False Promises
- **No 100% Delivery Claims**: Mailbox providers (Google, Microsoft, Yahoo) decide inbox placement using proprietary AI algorithms and sender reputation. Deliverability cannot be guaranteed by software alone.
- **Strict Reputation Isolation**: Bulk marketing emails must **never** be dispatched from your corporate apex root domain (e.g., `company.com`). All broadcasts are sent from dedicated sending subdomains (e.g., `mail.company.com`) to isolate marketing risk from mission-critical business communications.
- **Zero Local IP Sending**: Bulk mail is never dispatched from the web application server's own hosting IP. Dispatches route exclusively through tier-1 transactional/bulk providers (**Amazon SES, Postmark, SendGrid, Mailgun, or dedicated SMTP relays**).
- **Privacy & Module Isolation**: The campaign engine provides read-only synchronization of internal employees from the payroll module (capturing name and work email only). **Zero payroll financial data (basic salary, CTC, allowances, banking details) is ever queried or exposed.**
- **Suppression Firewall Invariant**: An address that has unsubscribed, hard-bounced, or filed a spam complaint is added to the global suppression list and is **provably never emailed again**.

---

## 2. Architecture & Components

```
+-----------------------------------------------------------------------------------+
|                                 RAPTOR CRM UI                                     |
|  Campaigns | Templates | Audience & Segments | Domains & DNS | Analytics & Health |
+----------------------------------------+------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                       PRE-SEND DELIVERABILITY QUALITY GATE                        |
|  - RFC 8058 Unsubscribe Link Check           - Physical Postal Address Check      |
|  - Multipart Plain-Text Alternative Check    - SPF / DKIM / DMARC Status Check    |
|  - Heuristic Spam Trigger Scoring (< 5.0)    - Self-Test Dispatch Verification    |
+----------------------------------------+------------------------------------------+
                                         | (Pass)
                                         v
+-----------------------------------------------------------------------------------+
|                        QUEUE SEEDING & CONCURRENT ENGINE                          |
|  Table: campaign_email_queue (DB-backed FIFO with FOR UPDATE SKIP LOCKED)         |
+----------------------------------------+------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                       CLI QUEUE WORKER (Cron / Supervisor)                        |
|  1. Auto-Pause Tripwires Check (Bounce > 2% or Complaint > 0.1% -> HALT)          |
|  2. Global Suppression Firewall Check (Checked immediately before send)           |
|  3. Warm-Up Schedule Rate Limit & Daily Cap (Stage 1: 50/day ... Stage 7: 10k/day)|
|  4. Merge Tag Engine & Safe Escaping ({{first_name|fallback}})                    |
|  5. Open Pixel & HMAC Signed Click Injection (Anti-Open Redirect Protection)      |
|  6. RFC 8058 One-Click Headers Injection (List-Unsubscribe, Precedence: bulk)     |
+----------------------------------------+------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                        SWAPPABLE PROVIDER ADAPTERS                                |
|  Amazon SES API / SMTP | Postmark API | SendGrid API | Mailgun API | Custom SMTP  |
+----------------------------------------+------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                     REAL-TIME FEEDBACK LOOPS & TRACKING                           |
|  - Webhook Ingestion (SES SNS, Postmark, SendGrid, Mailgun HMAC verified)         |
|  - Hard Bounce & Complaint -> Real-Time Suppression & Cascade                     |
|  - 3x Soft Bounce Threshold -> Automatic Repeat Suppression                       |
|  - Branded HTTPS Click & Open Tracking with Apple MPP Detection                   |
+-----------------------------------------------------------------------------------+
```

---

## 3. Database Schema Overview (Migration 0049)

The module introduces 15 relational tables with indexing on emails, statuses, campaign IDs, and scheduling queues:

| Table Name | Purpose | Key Columns & Indexes |
|---|---|---|
| `campaign_contacts` | Master audience directory | `email` (UNIQUE), `status`, `consent_type`, `last_engagement_at` |
| `campaign_lists` | Audience lists (static & internal) | `list_id`, `name`, `is_internal`, `optin_mode` |
| `campaign_list_contacts` | Many-to-many list memberships | `(list_id, contact_id)` (PK), `status` |
| `campaign_tags` | Dynamic tagging labels | `tag_id`, `name` (UNIQUE), `color` |
| `campaign_contact_tags` | Tag assignments | `(contact_id, tag_id)` (PK) |
| `campaign_segments` | Rule-based dynamic filters | `segment_id`, `match_type`, `rules_json` |
| `campaign_templates` | Responsive HTML & Plain-text templates | `template_id`, `html_content`, `plain_text_content` |
| `campaigns_broadcast` | Broadcast campaigns & A/B tests | `campaign_code` (UQ), `status`, `subject_a`, `subject_b` |
| `campaign_recipients` | Recipient tracking & HMAC tokens | `(campaign_id, contact_id)` (UQ), `unsubscribe_token` |
| `campaign_email_queue` | DB-backed dispatch queue | `status`, `scheduled_at`, `locked_at`, `locked_by` |
| `campaign_email_events` | Granular audit trail & tracking logs | `event_type`, `ip_address`, `user_agent`, `payload_json` |
| `campaign_suppressions` | Global suppression firewall | `email` (UNIQUE), `reason`, `source_campaign_id` |
| `campaign_domains` | Sending domains & DNS verification | `domain_name` (UQ), `dkim_public_key`, `spf_status` |
| `campaign_warmup_schedules` | Automated domain ramp-up caps | `(domain_id, date_stamp)` (UQ), `current_stage`, `daily_cap` |
| `campaign_audit_logs` | Compliance governance trail | `action`, `entity_type`, `entity_id`, `user_id` |

---

## 4. Environment Configuration (`.env`)

Never commit production email credentials to source control or store them in plain-text database fields. Configure all provider credentials in your root `.env` file:

```env
# ------------------------------------------------------------------------------
# RAPTOR CRM: EMAIL CAMPAIGN & SENDER CONFIGURATION
# ------------------------------------------------------------------------------

# Active Mailer Provider: 'ses' | 'postmark' | 'sendgrid' | 'mailgun' | 'smtp' | 'mock'
MAIL_PROVIDER=postmark

# Default Sender Identity (Must use a verified subdomain, e.g. mail.yourcompany.com)
MAIL_FROM_ADDRESS=news@mail.yourcompany.com
MAIL_FROM_NAME="Axiora Updates"
MAIL_REPLY_TO=support@yourcompany.com

# Administrator Alert Email for Tripwires & Daily Health Reports
ADMIN_ALERT_EMAIL=devops@yourcompany.com

# Application Secret Key (Used for HMAC tokens and AES-256 DKIM key encryption)
APP_KEY=raptor-production-secret-encryption-key-replace-with-64-random-chars

# ------------------------------------------------------------------------------
# PROVIDER 1: AMAZON SES
# ------------------------------------------------------------------------------
AWS_SES_REGION=us-east-1
AWS_SES_ACCESS_KEY=AKIAIOSFODNN7EXAMPLE
AWS_SES_SECRET_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
# Optional SES Configuration Set for Open/Click & Reputation Tracking
AWS_SES_CONFIGURATION_SET=raptor-production-set

# ------------------------------------------------------------------------------
# PROVIDER 2: POSTMARK (Recommended for premier transactional deliverability)
# ------------------------------------------------------------------------------
POSTMARK_SERVER_TOKEN=your-postmark-server-api-token-here
POSTMARK_MESSAGE_STREAM=broadcast

# ------------------------------------------------------------------------------
# PROVIDER 3: SENDGRID
# ------------------------------------------------------------------------------
SENDGRID_API_KEY=SG.your-sendgrid-api-key-here

# ------------------------------------------------------------------------------
# PROVIDER 4: MAILGUN
# ------------------------------------------------------------------------------
MAILGUN_API_KEY=your-mailgun-api-key-here
MAILGUN_DOMAIN=mail.yourcompany.com
MAILGUN_ENDPOINT=https://api.mailgun.net/v3
MAILGUN_WEBHOOK_KEY=your-mailgun-webhook-signing-key

# ------------------------------------------------------------------------------
# PROVIDER 5: CUSTOM SMTP RELAY
# ------------------------------------------------------------------------------
SMTP_HOST=smtp.mailtrap.io
SMTP_PORT=587
SMTP_USER=your-smtp-username
SMTP_PASS=your-smtp-password
SMTP_ENCRYPTION=tls
```

---

## 5. Domain Setup & DNS Authentication Guide

Every sending domain configured in **Email Marketing $\rightarrow$ Domains & DNS** requires 5 DNS records at your DNS host (Cloudflare, AWS Route 53, Google Cloud DNS, GoDaddy):

### 1. SPF Record (Sender Policy Framework)
- **Record Type**: `TXT`
- **Host / Name**: Subdomain (e.g., `mail` or `@` if creating zone for `mail.company.com`)
- **TTL**: Auto / 300 seconds
- **Value**:
  - For Amazon SES: `v=spf1 include:amazonses.com ~all`
  - For Postmark: `v=spf1 include:spf.mtasv.net ~all`
  - For SendGrid: `v=spf1 include:sendgrid.net ~all`
  - For Mailgun: `v=spf1 include:mailgun.org ~all`

### 2. DKIM Record (DomainKeys Identified Mail - 2048-bit RSA)
- **Record Type**: `TXT`
- **Host / Name**: `raptor._domainkey.mail` (or `<selector>._domainkey.<subdomain>`)
- **TTL**: Auto / 300 seconds
- **Value**:
  ```text
  v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...[copy from Raptor UI]...IDAQAB
  ```

### 3. DMARC Policy Record
- **Record Type**: `TXT`
- **Host / Name**: `_dmarc.mail`
- **TTL**: Auto / 300 seconds
- **Value (Initial Monitoring Mode)**:
  ```text
  v=DMARC1; p=none; sp=none; rua=mailto:dmarc-reports@mail.yourcompany.com; aspf=r; adkim=r
  ```
  *(After monitoring aggregate reports for 2-4 weeks with 0 alignment failures, advance `p=none` to `p=quarantine` or `p=reject`).*

### 4. MX Record (Inbound & Bounce Routing)
- **Record Type**: `MX`
- **Host / Name**: Subdomain (e.g. `mail`)
- **Priority**: `10`
- **Target / Value**: `inbound.mail.yourcompany.com` (or provider's inbound MX)

### 5. Branded Custom Tracking CNAME
- **Record Type**: `CNAME`
- **Host / Name**: `track.mail`
- **Target / Value**: `crm.yourcompany.com` (Your Raptor CRM domain)
- **Note**: Ensure HTTPS certificate covers this subdomain so tracking redirects remain encrypted.

---

## 6. Background Sending Engine & Worker Deployment

Bulk emails are **never processed inside an HTTP web request**. Campaigns seed records into `campaign_email_queue`, which are processed in batches by the CLI worker.

### Option A: Supervisor Daemon (Recommended for Production)

Supervisor keeps the worker running continuously as a managed background daemon, restarting it automatically if it crashes or reaches its batch quota.

1. Install Supervisor:
   ```bash
   sudo apt-get install supervisor
   ```

2. Create configuration file `/etc/supervisor/conf.d/raptor_email_worker.conf`:
   ```ini
   [program:raptor_email_worker]
   command=/usr/bin/php /var/www/raptor/bin/email_queue_worker.php --daemon --batch=50 --rate=10 --sleep=2
   directory=/var/www/raptor
   autostart=true
   autorestart=true
   user=www-data
   numprocs=2
   process_name=%(program_name)s_%(process_num)02d
   redirect_stderr=true
   stdout_logfile=/var/log/supervisor/raptor_email_worker.log
   stdout_logfile_maxbytes=20MB
   stdout_logfile_backups=5
   ```

3. Update and start Supervisor:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start raptor_email_worker:*
   ```

### Option B: Linux Crontab (Standard Interval Execution)

Edit crontab for `www-data`:
```bash
sudo crontab -u www-data -e
```

Add the following entries:
```cron
# 1. Process Campaign Email Queue every minute (locks batch of 100 items safely)
* * * * * /usr/bin/php /var/www/raptor/bin/email_queue_worker.php --once --batch=100 >> /var/log/raptor_queue.log 2>&1

# 2. Daily Deliverability Health Check & Report Email (Daily at 06:00 UTC)
0 6 * * * /usr/bin/php /var/www/raptor/bin/daily_health_report.php >> /var/log/raptor_health.log 2>&1
```

### Option C: Systemd Service (Alternative Linux Daemon)

Create `/etc/systemd/system/raptor-email-worker.service`:
```ini
[Unit]
Description=Raptor CRM High-Throughput Email Queue Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/raptor
ExecStart=/usr/bin/php /var/www/raptor/bin/email_queue_worker.php --daemon --batch=50 --rate=10 --sleep=2
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```
Enable and start:
```bash
sudo systemctl daemon-reload
sudo systemctl enable raptor-email-worker
sudo systemctl start raptor-email-worker
```

### Option D: Windows Task Scheduler (Windows Server Deployment)
1. Open **Task Scheduler** $\rightarrow$ Create Task.
2. Trigger: **At startup** (or Repeat task every **1 minute**).
3. Action: Start a Program:
   - Program: `C:\php\php.exe`
   - Arguments: `E:\raptor\bin\email_queue_worker.php --once --batch=50`
   - Start in: `E:\raptor`

---

## 7. Webhook Configuration (Bounces & Complaints)

Configure webhook notifications in your chosen provider's dashboard to ensure real-time global suppression:

| Provider | Webhook URL | Event Types to Subscribe | Authentication / Signature |
|---|---|---|---|
| **Amazon SES** | `https://crm.yourcompany.com/index.php?route=campaign_webhooks/ses` | Bounce, Complaint, Delivery (via SNS Topic) | Automatic Amazon SNS Subscription Confirmation |
| **Postmark** | `https://crm.yourcompany.com/index.php?route=campaign_webhooks/postmark` | Bounce, Spam Complaint, Delivery | Built-in payload parser |
| **SendGrid** | `https://crm.yourcompany.com/index.php?route=campaign_webhooks/sendgrid` | Bounced, Spam Report, Delivered | Event Webhook JSON |
| **Mailgun** | `https://crm.yourcompany.com/index.php?route=campaign_webhooks/mailgun` | Permanent Failure, Temporary Failure, Complained, Delivered | HMAC-SHA256 signature verification (`MAILGUN_WEBHOOK_KEY`) |

---

## 8. Reputation Warm-Up Schedule & Safety Tripwires

### Automated IP/Domain Warm-Up Schedule
Sending volume automatically ramps up across 7 stages to avoid trigger-blocking by Gmail and Microsoft spam filters:

| Stage | Daily Sending Cap | Recommended Duration | Focus |
|---|---|---|---|
| **Stage 1** | **50 emails / day** | Days 1–4 | Most engaged users only (opened in last 30 days) |
| **Stage 2** | **150 emails / day** | Days 5–8 | High-engagement contacts |
| **Stage 3** | **350 emails / day** | Days 9–14 | Active subscribers |
| **Stage 4** | **800 emails / day** | Days 15–21 | Regular subscriber base |
| **Stage 5** | **2,000 emails / day** | Days 22–28 | Broad audience |
| **Stage 6** | **5,000 emails / day** | Days 29–35 | Full audience |
| **Stage 7** | **10,000+ emails / day** | Ongoing | Unthrottled production sending |

*The engine automatically throttles dispatches once the daily cap for the sender domain is reached, rescheduling remaining items for 09:00 UTC the following day.*

### Automated Deliverability Tripwires (Auto-Pause)
The queue worker inspects campaign bounce and complaint metrics before dispatching each item:
- **Bounce Rate > 2.0%**: Campaign is **immediately automatically paused**, status is set to `paused`, and an emergency alert is triggered.
- **Complaint Rate > 0.10%**: Campaign is **immediately automatically paused** (breaching Google/Yahoo bulk sender thresholds).

### Re-Engagement Cleanup (Sunset Policy)
Subscribers who have recorded zero opens and zero clicks over the last **90–180 days** are flagged as `inactive` by `CampaignAnalyticsService::flagInactiveContacts(90)` and excluded from future broadcast lists.

---

## 9. Deliverability Tooling: Google Postmaster & Microsoft SNDS

### Google Postmaster Tools
1. Navigate to [postmaster.google.com](https://postmaster.google.com/).
2. Log in with your corporate Google Workspace admin account.
3. Click **Add Domain (+)** and enter your sending subdomain: `mail.yourcompany.com`.
4. Copy the unique verification TXT string (e.g. `google-site-verification=...`) and add it to your DNS host.
5. Review weekly dashboards:
   - **User Reported Spam Rate**: Must remain strictly below **0.10%** (never exceed 0.30%).
   - **Domain & IP Reputation**: Aim for **High** reputation rating.

### Microsoft SNDS & JERP
1. Register for [Microsoft Smart Network Data Services (SNDS)](https://sendersupport.olc.protection.outlook.com/snds/).
2. Request access for your dedicated sending IP address.
3. Enroll in the **Junk Email Reporting Program (JERP)** to receive real-time spam complaint loops from Outlook/Hotmail users.

---

## 10. Pre-Flight Go-Live Checklist

Complete every check before launching your first marketing campaign to external audiences:

- [ ] **1. Dedicated Subdomain Configured**: Verified sending subdomain (e.g. `mail.company.com`) is created in Raptor CRM and apex domain (`company.com`) is protected.
- [ ] **2. 2048-bit DKIM Key Installed**: The public key is published at `<selector>._domainkey.<subdomain>` and verified green in **Domains & DNS**.
- [ ] **3. SPF Record Configured**: SPF TXT record includes your active provider (`include:amazonses.com`, `include:spf.mtasv.net`, etc.) and verified green.
- [ ] **4. DMARC Published**: Record exists at `_dmarc.<subdomain>` with at least `p=none` and an active `rua=` mailbox.
- [ ] **5. Inbound MX Configured**: Valid MX records exist for the subdomain to prove bounce and reply routing legitimacy.
- [ ] **6. Production Credentials in `.env`**: `.env` contains valid production API keys or SMTP secrets (no default or placeholder keys).
- [ ] **7. Webhook Endpoints Configured**: Bounce, complaint, and delivery webhooks are configured and verified in the provider dashboard.
- [ ] **8. Background Worker Active**: Supervisor daemon or Linux cron is confirmed running `email_queue_worker.php`.
- [ ] **9. Pre-Send Checklist Passed**: Campaign draft contains valid `{{unsubscribe_url}}`, company postal address, and auto-generated plain text.
- [ ] **10. Seed-List Test Completed**: Test email dispatched to yourself and seed addresses on Gmail, Outlook, and Yahoo, verifying delivery to the **Primary Inbox**.
- [ ] **11. All Automated Tests Passing**: Executed `php bin/run_all_campaign_tests.php` and verified **444 of 444 assertions passed**.
