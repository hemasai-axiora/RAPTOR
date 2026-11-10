# Regression Prevention & Baseline Preservation Plan

```
Document:             REGRESSION-PLAN.md
Directory:            RAPTOR-V2.2-BLUEPRINT/TESTING/
Classification:       Regression Test Specification & Coverage Inventory
Target Release:       Raptor v2.2
Verified Baseline:    772 Assertions across 16 Suites (v2.1.4)
```

---

## 1. Baseline Preservation Invariant

Raptor v2.2 modernization **must not** degrade, break, or disable any existing verified capability. The 16 automated test suites executing **772 continuous unit and integration assertions** represent the non-negotiable architectural floor of the application.

---

## 2. Inventory of the 16 Verified Test Suites

| # | Suite Name | Test Harness File | Assertions | Core Functionality Validated |
|---|---|---|---|---|
| **1** | **Customer 360 & Foundations** | `bin/test_customer_360_sprint1.php` | 43 | Unified identity stitching, customer 360 profile consolidation, activity timeline aggregation. |
| **2** | **Lead Ingestion & Tracking** | `bin/test_lead_capture_sprint2.php` | 35 | Form submission ingestion, tracking beacon cookies, visitor IP resolution, UTM capture. |
| **3** | **Meta WhatsApp Cloud API** | `bin/test_whatsapp_sprint3.php` | 34 | WhatsApp Business Cloud API v19.0, template dispatch, parameter binding, webhook receipts. |
| **4** | **Shared Omnichannel Inbox** | `bin/test_shared_inbox_sprint4.php` | 40 | Unified conversation threads (WhatsApp + Email + SMS), canned replies, agent assignment. |
| **5** | **Automation & Lead Scoring** | `bin/test_automation_sprint5.php` | 40 | Dynamic lead scoring rules, threshold promotions, journey execution transitions. |
| **6** | **Telephony & Call Mgmt** | `bin/test_telephony_sprint6.php` | 38 | Click-to-call bridging, call logging, TRAI DND registry scrubbing, duration calculation. |
| **7** | **Social Publishing & Ads** | `bin/test_social_sprint8.php` | 36 | Multi-platform publishing (LinkedIn, Meta), scheduled posts, inbound Lead Ad ingestion. |
| **8** | **Contacts & Suppression** | `bin/test_email_campaigns_phase2.php` | 38 | RFC 8058 one-click unsubscribes, contact lists, CSV bulk import, suppression blacklists. |
| **9** | **Templates & Pre-Send Gate** | `bin/test_email_campaigns_phase3.php` | 35 | Merge tag replacement, pre-send spam checklist, responsive HTML wrapper validation. |
| **10** | **Queue & Provider Adapters** | `bin/test_email_campaigns_phase4.php` | 28 | `marketing_queue` row locking (`SKIP LOCKED`), attempt counters, exponential backoff, DLQ. |
| **11** | **Webhooks & Tracking Stats** | `bin/test_email_campaigns_phase5.php` | 53 | Open pixel tracking, click redirect hashing, webhook bounce and complaint processing. |
| **12** | **Domains, DNS & Health** | `bin/test_email_campaigns_phase6.php` | 63 | SPF record validation, DKIM 2048-bit key validation, DMARC alignment, deliverability reports. |
| **13** | **End-to-End Lifecycle** | `bin/test_email_campaigns_e2e.php` | 43 | Full broadcast lifecycle: Create ➔ Preflight ➔ Queue ➔ Dispatch ➔ Track ➔ Analytics. |
| **14** | **Deliverability Rules** | `bin/test_deliverability_intelligence.php`| 32 | Rules `D-001` through `D-020` (Spam trigger words, image-to-text ratios, HTML validity). |
| **15** | **Modernization Extensions** | `bin/test_modernization_extensions.php` | 30 | Command Palette search, DLQ inspector, `X-Request-ID` tracing, OpenAPI Scalar specs. |
| **16** | **Statutory Payroll Overhaul**| `bin/test_payroll_pipeline.php` | 184 | Pay Bands 1–8, EPF & ESIC ceiling caps, Professional Tax slabs, Gratuity, LOP deductions. |
| **TOTAL** | **16 Consolidated Suites** | `bin/run_all_campaign_tests.php` | **772** | **100% Pass Rate Required on Every PR and Commit.** |

---

## 3. Regression Safeguard Protocol for AI Agents

Whenever an AI coding agent or developer touches code in `app/`:
1. **Pre-Change Verification**: Run `php bin/run_all_campaign_tests.php` to confirm a 772-pass clean slate.
2. **Post-Change Verification**: Run `php bin/run_all_campaign_tests.php` immediately after editing.
3. **Zero Compromise Rule**: If the score drops to 771/772, the change is considered broken. The agent must revert or fix the code. Under no circumstances may an agent delete or edit an assertion in `bin/test_*` to hide a regression.

