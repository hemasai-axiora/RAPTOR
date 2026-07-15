# Raptor CRM - Product Requirements Document (PRD)

**Version:** 2.0 (Updated)  
**Author:** AI Pair Programmer (Antigravity)  
**Date:** July 3, 2026

---

## 1. Product Vision
Raptor CRM (powered by **Axiora Pulse** design system) is a modern, high-fidelity Digital Marketing CRM designed to help agencies manage clients, marketing campaigns, social media performance, leads, employees, invoices, reports, and business operations through a centralized platform. It empowers marketing teams, managers, analysts, and business clients with data-driven decision-making dashboards.

---

## 2. Product Goals
* **Centralize business operations** for marketing agencies and their clients.
* **Improve lead management** and lifecycle visibility.
* **Track digital marketing campaigns** with granular ROI and spend metrics.
* **Monitor social media analytics** natively across multiple major channels.
* **Simplify employee task management** and operations.
* **Generate interactive reports** and AI-driven business insights.
* **Enable robust Role-Based Access Control (RBAC)** to serve diverse stakeholders.

---

## 3. User Roles & Permission Scopes
The platform features **four distinct user roles**, each designed for specific operational scopes and data access limits:

1. **Admin (System Administrator)**
   * *Description:* Unrestricted administrative, structural, integration, and security control.
   * *Primary Goal:* Maintain system uptime, manage user profiles, configure API keys, database backups, and global integrations.
   * *Access:* Access to all modules, finance data, database settings, and billing configurations.

2. **Manager (Agency / Campaign Manager)**
   * *Description:* Operational lead overseeing marketing campaigns, client contracts, and employee workloads.
   * *Primary Goal:* Execute marketing strategies, assign leads, schedule content calendar drafts, manage client portfolios, and assign tasks.
   * *Access:* All modules except system settings (SMTP, API keys, databases) and developer-level configurations.

3. **Analyst (Marketing Analyst)**
   * *Description:* Data specialist focused on tracking performance, analyzing metrics, and generating reports.
   * *Primary Goal:* Track campaign ROI, evaluate channel performance, write AI insights and recommendations, and prepare client-facing performance reports.
   * *Access:* Full read access to dashboards, analytics, client list, campaigns, and reports. No direct modification of campaign budgets, client settings, or lead assignments.

4. **Employer (Client / Business Owner)**
   * *Description:* The external client or business owner paying for the marketing services.
   * *Primary Goal:* Monitor the "30-second marketing health check," track spend vs. revenue influenced, review lead quality, and evaluate campaign ROI.
   * *Access:* Limited read-only access to the *Executive Overview Dashboard* and designated client reports. Restricted from internal tasks, employee attendance, and technical configurations.

---

## 4. Role-Based Access Control (RBAC) Matrix

| Module / Screen | Action | Employer (Client) | Analyst | Manager | Admin |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **Authentication** | Login & Pass Reset | Read/Write | Read/Write | Read/Write | Read/Write |
| **User Profile** | Edit Self Profile | Read/Write | Read/Write | Read/Write | Read/Write |
| **User Management** | Create / Edit / Delete Users | No Access | No Access | No Access | Read/Write |
| **Executive Dashboard** | View KPIs, Funnels, Smart Alerts | Read-Only | Read-Only | Read-Only | Read-Only |
| **Campaigns & Channels** | View Performance & Trends | No Access | Read-Only | Read-Only | Read-Only |
| **Campaigns Management** | Create / Edit / Delete / Budgets | No Access | No Access | Read/Write | Read/Write |
| **Customer Intelligence** | View Journey, CSAT, Sentiment | No Access | Read-Only | Read-Only | Read-Only |
| **Client Management** | View / Add / Edit Client Records | No Access | Read-Only | Read/Write | Read/Write |
| **Lead Management** | Assign Leads & Track Stages | No Access | No Access | Read/Write | Read/Write |
| **Content Calendar** | View / Schedule / Edit Drafts | No Access | Read-Only | Read/Write | Read/Write |
| **Employee Management** | Profiles, Attendance, Tasks | No Access | No Access | Read/Write | Read/Write |
| **Finance Module** | Generate Invoices & Payments | No Access | No Access | Read/Write | Read/Write |
| **Reports** | View, Export, & Print | Read-Only | Read/Write | Read/Write | Read/Write |
| **System Settings** | SMTP, Backup, Integrations | No Access | No Access | No Access | Read/Write |

---

## 5. Dashboard Specifications (Axiora Pulse Format)
The system shall implement three distinct, high-fidelity dashboard views based on the **Axiora Pulse** visual layout:

### A. Executive Marketing Overview Dashboard
Designed to give the **Employer (Client)**, **Manager**, and **Admin** a "30-second marketing health check" focused on business outcomes.
* **Top Metric Cards (KPIs):**
  * *Marketing Health Score:* Score out of 100 with trend (e.g. `88/100, Healthy`, `+8 pts vs last period`) and sparkline.
  * *Marketing ROI:* Ratio metric (e.g. `4.21x, Healthy`, `+0.71x vs last period`) and sparkline.
  * *Revenue Influenced:* Total revenue generated (e.g. `$2.48M`, `+18.6%`) and sparkline.
  * *Qualified Leads:* Count metric (e.g. `1,245`, `+16.3%`) and sparkline.
  * *Lead Quality Score:* Score out of 100 (e.g. `81/100, Healthy`, `+0.3%`) and sparkline.
  * *Customer Acquisition Cost (CAC):* Cost per customer (e.g. `$42.6, Watch`, `+6.3%`) and sparkline.
  * *Conversion Rate:* Conversion percentage (e.g. `3.62%`, `+0.48%`) and sparkline.
  * *Customer Lifetime Value (LTV):* Value (e.g. `$1,842`, `+12.2%`) and sparkline.
* **Visual Charts & Sections:**
  * *Marketing Spend vs. Revenue:* Combo chart showing monthly marketing spend (bar), revenue influenced (bar), and ROI (line).
  * *Marketing Funnel (Overall):* Funnel visualization tracking conversion rates and previous-period trends across:
    `Reach -> Impressions -> Website Visitors -> Engaged Users -> Leads -> Qualified Leads -> Customers -> Revenue`
  * *Performance by Channel:* Stacked horizontal bar chart tracking Reach, Engagement Rate, Leads, and Revenue by channel (Instagram, Facebook, LinkedIn, YouTube, X, Website) with ROI values listed adjacent.
  * *Revenue & Lead Contribution by Channel:* Two donut charts showing percentage contribution by platform.
  * *Top Performing Campaigns:* Table detailing campaign name, spend, leads generated, revenue influenced, ROI, and status (Healthy / Watch / Action).
  * *Growth Trend:* Comparative sparklines for core KPIs vs. the previous period.
  * *AI Insights (What Matters Most):* Narrative bullet list highlights generated by AI engines summarizing trends and proposing immediate action items.
  * *Smart Alerts Banner:* Real-time notifications detailing channel anomalies (e.g., "ROI dropped on X (Twitter) by 18%").

### B. Channel & Campaign Performance Dashboard
Designed for **Managers** and **Analysts** to manage, analyze, and optimize active marketing channels and campaign budgets.
* **Top Metric Cards:**
  * Total Reach (e.g., `12.84M`), Total Impressions (`28.67M`), Total Engagements (`1.92M`), Website Clicks (`86.3K`), Total Leads (`1,245`), Engagement Rate (`6.72%`), CTR (`2.91%`), and Cost per Lead (`$34.2`).
* **Visual Charts & Sections:**
  * *Platform Performance Comparison:* Table detailing Platform, Reach, Engagement Rate, Leads, Revenue, and ROI.
  * *Campaign Performance (Top 7):* Detailed grid showing active campaigns, channels, spend, reach, leads, revenue, ROI, and status.
  * *Content Performance (Top 5):* Table showing top content pieces, publishing platform, engagement rate, and reach.
  * *Engagement Trend (All Platforms):* Combo bar and line chart illustrating total engagements (bars) vs. engagement rate (line) over time.
  * *Traffic Sources & Leads by Channel:* Donut chart for traffic channels (Organic Social, Direct, Paid Social, Referral, Email, etc.) and a horizontal bar chart representing leads generated.
  * *Best Posting Time:* Hourly heatmap grid showing average engagement rate by day of week and hour.
  * *Platform Opportunities & Budget Optimization:* Card list detailing actionable changes (e.g., "Reallocate $12.5K from X to LinkedIn to get 42% more leads") with an "Apply Recommendation" quick action button.

### C. Customer Intelligence & AI Analytics Dashboard
Designed for **Analysts**, **Managers**, and **Admins** to audit user behavior, conversion pathways, and customer retention metrics.
* **Top Metric Cards:**
  * Customer Satisfaction (CSAT) Score (`4.5 / 5`), Brand Sentiment Score (`78/100`), Returning Visitors (`28.6K`), Lead Quality Score (`81/100`), Avg Conversion Probability (`72%`), Customer Retention Rate (`61.4%`), Churn Risk Score (`24/100`), and Avg Session Duration (`04:32`).
* **Visual Charts & Sections:**
  * *Customer Journey Map:* Process flowchart illustrating user drop-off and conversion rates at each stage.
  * *Lead Quality Analysis:* Donut chart detailing proportions of Hot, Warm, and Cold leads.
  * *Audience Demographics & Interests:* Interactive charts tracking age ranges, locations (geo map), gender, device distributions, and top interests (e.g., AI & Tech, Entrepreneurship).
  * *Website Behavior Overview:* Metrics for Bounce Rate, Pages per Session, and Average Scroll Depth combined with tables for Top Landing Pages and Top Exit Pages.
  * *Conversion Path Analysis:* Text-based flows mapping top 5 multi-touch conversion paths (e.g. `LinkedIn -> Website -> Demo -> Contact -> Customer (32%)`).
  * *Attribution Analysis & Competitor Benchmarking:* Multi-model attribution donut chart (First Touch, Last Touch, Data Driven, Time Decay) paired with benchmarking tables.
  * *Brand Sentiment Trend:* Line chart showing sentiment lines (Positive, Neutral, Negative) over a rolling 30-day timeline.
  * *Lead Source Performance:* Bar chart outlining leads and conversion rates per source.

---

## 6. Product Roadmap

### Phase 1: Core System & RBAC (Authentication, Core Dashboards & Roles)
* Complete user authentication (register, login, password reset) with role-based session initialization.
* Implement the Executive Marketing Overview Dashboard (supporting view filters and role scopes).
* Configure database structure for core models: clients, campaigns, leads, and social accounts.

### Phase 2: Operations & Deep Analytics (Campaigns, Content, CRM Operations)
* Deploy Channel & Campaign Performance Dashboard and Content Calendar.
* Launch Client Management, Lead Management, and Task Management features.
* Release the Finance Module (invoice generation, payment logging, profit tracking).
* Roll out the Customer Intelligence & AI Analytics Dashboard.

### Phase 3: AI Insights & Third-Party Integrations
* Integrate Google Ads, Meta Graph, LinkedIn, and YouTube APIs.
* Embed AI Assistant for automated "AI Insights" generation and "Smart Alerts" warnings.
* Build out REST API and PWA (Progressive Web App) mobile views.

---

## 7. Success Metrics
* **Lead Conversion Rate:** 20% average increase within 90 days.
* **Campaign ROI Tracking Accuracy:** 100% data ingestion accuracy from integrated APIs.
* **Operational Efficiency:** Reduction of manual reporting time for Analysts by 85%.
* **Client Retention:** Improvement in client NPS due to transparent Executive dashboard access.
