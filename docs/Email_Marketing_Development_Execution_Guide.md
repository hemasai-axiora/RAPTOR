# PHP Email Marketing & Deliverability Intelligence
## Developer Execution Playbook, Architecture, Build Checklist and Release Runbook

> **Document type:** Implementation guide / technical execution plan  
> **Version:** 1.0 | **Prepared:** 9 November 2026  
> **Source of truth:** `Email_Marketing_Module_BRD_SRS_PHP_2026.docx` (BRD/SRS, 50 functional requirements).  
> **Target:** Existing PHP application for HR, employee/payroll administration and social media management.  
> **State:** Development-ready *reference architecture*. Framework, PHP version, database, hosting, enterprise identity, ESP, volume and legal jurisdictions must be confirmed in Sprint 0.  
> **Owner placeholders:** `[Product Owner]`, `[Tech Lead]`, `[Security]`, `[Marketing Operations]`, `[QA Lead]`, `[DevOps]`.

---

## How to use this guide

This playbook turns requirements into a **sequenced implementation plan**. It contains decision gates, tasks, database structures, API contracts, server-side rules, example PHP/SQL, CI/CD practices, testing evidence, operational runbooks, and clear completion criteria.

**Important:** Code is representative and designed to be adapted to your application's framework, database migrations, provider SDK and hosting. It is **not** a drop-in production application. In particular, sending, consent, signature validation, sanitization and PII processing need actual integration tests and security review.

**Required decisions before coding:**
1. Confirm the existing PHP framework and versions; don't migrate the HR application merely to add email marketing.
2. Choose **A: Mautic sidecar**, **B: phpList sidecar**, or **C: custom PHP marketing module** using the evaluation process below. The complete reference database/API implementation mainly describes option C; for A/B, adapt it into a facade and mapping layer instead of building duplicate campaign engines.
3. Select one email service provider (ESP) with authenticated sending, feedback events, suppression support, sending quotas, and appropriate data-processing terms.
4. Establish separate marketing-vs-HR data access, consent policy, approved sender domains and the first usable business campaign.
5. Sign off the rule that **no sender can force Gmail Primary, Promotions, Updates or Spam placement**. The user may select *communication intent*, but classification remains recipient-provider controlled.

**Legend:** `P0` release blocker; `P1` next phase; `P2` optional advanced scope. `MUST` means not negotiable for the stated release; `SHOULD` is expected unless a documented design decision differs.

---

# 1. Executive implementation decision

## 1.1 Recommended product boundary

Build an **Email Marketing** workspace inside the existing UI, with a logically isolated marketing domain. Reuse company login, employee *identity* and existing roles only as authenticated actors, **not** as marketing contact data. Keep payroll and employment data inaccessible to marketing operators.

```text
                         EXISTING PHP APPLICATION
┌─────────────────────────────────────────────────────────────────┐
│ Login / staff identity / organization / common navigation      │
│   ├── HR & Employees                     (existing, isolated)  │
│   ├── Payroll                            (existing, isolated)  │
│   ├── Social Media                       (existing)            │
│   └── EMAIL MARKETING WORKSPACE (new)                          │
│       ├── Overview / Alerts / Campaign Calendar                │
│       ├── Audiences / Contacts / Lists / Segments / Consent    │
│       ├── Template Studio / Preflight / Approvals              │
│       ├── Campaigns / Scheduling / Queue Monitor               │
│       ├── Journeys / Webhook Integrations (P1)                 │
│       └── Reports / Sender Health / Settings                   │
└───────────────┬─────────────────────────────────────────────────┘
                │ authenticated API + server-side authorization
        ┌───────▼───────────────────────────────────────┐
        │ Marketing Application Services / Domain      │
        │ Contacts • Consent • Segments • Templates     │
        │ Campaigns • Preflight • Delivery • Analytics  │
        └───────┬─────────────┬──────────────┬──────────┘
                │             │              │
          Marketing DB   Queue/workers   Provider adapter
                │             │              │
          Audit / outbox   Scheduler       ESP SMTP/API
                                              │
                             Signed webhooks ─┘
```

**Deployment rule:** never run 100k message loops inside an HTTP request or inside a payroll cron job. Marketing queue workers must be independently scalable, independently pausable, and lower-priority than essential HR/payroll operations.

## 1.2 Decide reuse vs custom before writing the campaign engine

| Option | Build/reuse pattern | Advantages | Constraints | Select when |
|---|---|---|---|---|
| **A. Mautic sidecar** | Maintain Mautic separately; PHP application owns branded UX, permissions, preflight and integration facade; Mautic manages native contacts/segments/campaigns where suitable | Strong journey automation, mature marketing feature set | Separate operations, API mapping, sync complexity, license/upgrade review, potential duplicate sources of truth | Advanced automated journeys are a business priority |
| **B. phpList sidecar** | Separate newsletter engine; existing app owns UX, contact consent policy and configuration integration | Newsletter and bulk-send functionality already developed | UI/API customization and approval/preflight layer still required | Primarily newsletters and broadcast campaigns |
| **C. Custom module** | Build defined marketing services, editor integration and provider adapter inside PHP stack | Maximum UI/data/approval control; fits existing system | Most engineering effort and ongoing responsibility | Smaller focused marketing workflow; deep custom HR/social integration |

**Working recommendation:** prototype **A (Mautic)** and **C (thin custom module + ESP)** in Sprint 0. Use B only if newsletters are overwhelmingly the main requirement. Avoid copying an entire open-source platform into the existing HR codebase; use a sidecar service with documented interfaces. A custom Deliverability Intelligence/Preflight layer is likely needed whichever option wins.

### Decision scorecard (0–5 each; weighted, evidence-based)

| Dimension | Weight | Required evidence |
|---|---:|---|
| Fit to must-have campaign workflows | 20% | Working webinar invite → segment → schedule → feedback demo |
| Integration with current login/RBAC | 15% | Staff role denial and session/SSO proof |
| Consent, suppression and approval customization | 20% | Unsubscribe stop-before-send test; revision invalidation |
| Advanced journeys and future capabilities | 10% | Working trigger/delay/exit prototype |
| Dev + maintenance effort | 15% | Named engineers and estimated story points |
| Operational security / upgrade viability | 10% | Supported PHP/DB compatibility, patches, isolation |
| Licensing and compliance | 10% | Legal review of modifications/distribution and DPA |

**Go/no-go gate:** select the option only after both prototypes execute one real end-to-end *sandbox* campaign and the Tech Lead signs an Architecture Decision Record (ADR-001). **Do not mix campaign ownership between your own database and Mautic without a formal system-of-record decision.**

## 1.3 Current useful repositories and docs

| Project / documentation | Link | Intended use |
|---|---|---|
| Mautic | https://github.com/mautic/mautic | PHP marketing automation sidecar |
| Mautic supported releases | https://mautic.org/releases/ | Check supported version and exact PHP/DB compatibility before installation |
| Mautic API documentation | https://devdocs.mautic.org/en/7.2/rest_api/campaigns.html | Evaluate documented campaign endpoint surface against required workflows |
| Mautic cron operations | https://docs.mautic.org/en/7.2/configuration/cron_jobs.html | Understand scheduler/segment/campaign operations |
| phpList | https://github.com/phpList/phplist3 | Newsletter/campaign sidecar |
| GrapesJS | https://github.com/GrapesJS/grapesjs | Drag-and-drop visual editor in the PHP application's frontend |
| GrapesJS newsletter preset | https://github.com/GrapesJS/preset-newsletter | HTML-email starter blocks and inline-CSS export |
| GrapesJS MJML | https://github.com/GrapesJS/mjml | Alternative MJML authoring workflow |
| Symfony Mailer | https://github.com/symfony/mailer | PHP mail transport when custom delivery is appropriate |
| Symfony Messenger | https://symfony.com/doc/current/messenger.html | Queue patterns if compatible with the existing application |

**Version/licensing guardrails:** pin tagged stable releases and vetted dependencies; check release advisories, licenses, provider terms and current supported runtimes. Mautic's source declares GPL-3.0; confirm obligations with counsel if modifying/redistributing. Avoid pinning a specific version in this plan because support status changes. PHP-list project release candidates are not production releases. Editor is JavaScript used **inside** a PHP application, not a PHP replacement.

---

# 2. Boundaries, invariants and MVP

## 2.1 Product scope and privacy boundaries

**Marketing contacts**: leads, opt-in subscribers, customers, partners, webinar registrants and permitted contacts from social media lead forms.  
**Staff identities**: existing employees who can sign in and operate marketing tools, with explicit marketing permissions.  
**Payroll/HR records**: separate business-purpose records. The marketing module MUST NOT implicitly read HR databases for campaign recipients.

Mandatory invariants:

- One normalized address can be in multiple groups, but only one *initial* recipient record for one campaign (per brand/recipient identity).
- An email must never send as marketing unless applicable permission/legal basis is documented and current, including purpose, channel and brand.
- **Unsubscribe, complaint and hard bounce restrictions take effect even after campaign approval or queueing**.
- An approved template revision is immutable; changes to subject/HTML/links/variables/sender/audience invalidate approval.
- Every outgoing message has a traceable campaign, template revision, provider identity and delivery attempt.
- Provider acceptance means **accepted for processing**, not delivered to inbox.
- Opens are privacy-sensitive and unreliable; open rates must never be the sole deliverability metric or automatic winner criterion.
- Intent labels (`personal_style`, `promotional`, `operational`) are suggestions **not inbox-routing instructions**; content type is validated against actual send purpose.
- No unverified From domain, missing suppression gate, misconfigured webhook signature or revoked legal permission may be overridden by a marketing user.
- Provider temporary failure must not cause uncontrolled, repeated sends or recursive retries.

## 2.2 Release scope

### MVP / P0 (first safe production campaign)

- [ ] Login integration, module access control, brand/domain setup and audit log.
- [ ] Contacts, imports, lists, tags, basic segment filters, consent evidence, preferences, suppressions.
- [ ] 8–10 starter email templates, editor and responsive test previews.
- [ ] Versioned template storage, merge variables, safe HTML and plaintext exports.
- [ ] Communication intent selector, rule-based preflight, actionable alert center.
- [ ] Campaign wizard, recipients preview, de-duplication, approval, send now/schedule.
- [ ] Workers, throttling, provider adapter, webhook ingestion, pause/resume/cancel.
- [ ] One-click unsubscribe for applicable marketing mail, body opt-out and compliance policy.
- [ ] Campaign health dashboard, metrics definitions, audit and export.
- [ ] Testing, monitoring, backup/restore and operational response runbooks.

### P1 (after MVP is stable)

- [ ] Expanded dynamic segments/segment builder UX, localization-ready brand kits.
- [ ] Event-based journeys, delays, branch decisions, workflow exits.
- [ ] Frequency caps, quiet hours and advanced timezone targeting.
- [ ] A/B tests, social-source lead sync, conversion attribution.
- [ ] Reputation trends, finer provider segmentation and advanced operational reports.
- [ ] Retention/export/deletion automation and advanced self-service data controls.

### P2

- [ ] AI copy suggestions (human approved), multilingual template variants, deeper insights.
- [ ] Statistical experiment analysis beyond simple A/B.
- [ ] Cross-account agency-style multi-tenancy if formally required.

### Out of scope until separately approved

- Guaranteed inbox placement or spoofing mailbox metadata to change Gmail tabs.
- Mailing scraped/purchased contact lists or bypassing unsubscribe/complaints.
- Staff mailbox ingestion or individual mailbox impersonation.
- Reusing the marketing workflow for payslips, credentials or legally required HR notices.
- Running a self-managed outbound SMTP/IP reputation infrastructure as MVP.

---

# 3. Sprint 0: Audit the existing PHP system (implementation day zero)

**Owner:** Tech Lead + DevOps + Security + Product Owner.  
**Deliverable:** `docs/architecture/ADR-001.md` and an accepted one-page integration map.  
**Acceptance:** no code implementation begins until the following checklist is addressed.

## 3.1 Inspect repository and environment

```bash
# Run only in the development environment; do not paste secrets into tickets.
php -v
composer --version
composer show --direct          # if Composer project
php -m                         # extensions installed
find . -maxdepth 3 -name 'composer.json' -o -name 'package.json'
git status --short
# Then identify: route definitions, auth middleware, migrations, queue,
# cron, config, secrets management, file storage, mailer and logging.
```

| Review | Record evidence |
|---|---|
| PHP application | Framework/version (Laravel, Symfony, CodeIgniter, CakePHP, custom), service providers, deployment model |
| Database | Engine, version, schema conventions, migration tooling, read replicas, indexes, backups |
| Authentication | Sessions/SSO, role model, MFA availability, service accounts and session expiration |
| Hosting | VPS, shared hosting, container/Kubernetes, load balancer, outgoing network, background workers |
| Frontend | Blade/Twig/PHP templates, Vue/React/vanilla JS, bundling and CSP |
| Existing social module | Lead objects, post/campaign IDs, attribution, API permissions, incoming webhooks |
| HR/Payroll | Existing access boundaries, sensitive columns and database ownership |
| Existing mail use | Password reset, HR notices and SMTP transport; ensure no cross-purpose mixing |
| Volume | Total contacts, weekly/monthly sends, maximum batch, number of brands/domains |
| Jurisdiction | Recipient geographies, applicable privacy rules, contractual restrictions |

## 3.2 Delivery and privacy preconditions

- [ ] Marketing operator permission model agreed; no general `SELECT * FROM employees` exposure.
- [ ] Contact acquisition and proof of permission verified for each source.
- [ ] Marketing and essential transactional sending identities, streams and suppression policies defined separately.
- [ ] Subdomain and DNS ownership confirmed by administrator.
- [ ] Provider sandbox account and event feedback mechanism available.
- [ ] Legal owner reviews unsubscribe, lawful basis, privacy notice, retention and processor contracts.
- [ ] Choose a pilot audience of **real permissioned** contacts; do not use employee payroll addresses as a default test list.

## 3.3 Non-functional baselines (proposed; tune after load test)

| Metric | Starting target | Evidence |
|---|---:|---|
| Common filtered API p95 | <500 ms at agreed synthetic workload | APM/load test |
| Import target dataset | 100k contacts | Repeatable import benchmark |
| Campaign planning dataset | 100k recipients | Queue benchmark; provider quotas separately checked |
| Time to revoke consent | Before next external send attempt | Concurrency test |
| Event deduplication | No duplicate business effects | Replayed webhook test |
| Approval invalidation | Immediate on protected edits | Integration test |
| Rollback | Tested deployment and DB migration recovery | Staging rehearsal |
| Data loss tolerance | Define RPO/RTO in operations sign-off | Restore drill |

**Caution:** A “100k message test” must use a stub/fake provider and synthetic contacts, **not** emit 100k real messages.

---

# 4. Architecture and repository organization

## 4.1 Reference module structure (Option C)

Adapt names to the current framework (do not install another full MVC framework inside an existing MVC application):

```text
app/
  Marketing/
    Http/
      Controllers/            # Thin REST endpoints, requests validated
      Middleware/             # RBAC, brand boundary, request correlation
      Requests/               # Input schemas
    Domain/
      Audiences/              # Contact, List, Segment, Consent, Suppression
      Templates/              # Revisions, merge tokens, render, brand kit
      Campaigns/              # Draft, snapshot, approval, recipient planner
      Delivery/               # Message outbox, attempts, adapters, rate-limit
      Deliverability/         # Domain checks, rule engine, alert lifecycle
      Reporting/              # Event aggregation, analytics definitions
      Automations/            # P1: journeys and trigger processor
      Integrations/           # P1: social-source adapters
    Jobs/                     # Import, plan, dispatch, event process, health
    Events/                   # Marketing domain events
    Policies/                 # Marketing view/edit/approve/send/export
    Providers/                # SES / Postmark / Mailgun / etc adapters
    Services/                 # App service orchestration
    DTO/                      # Provider-independent contracts
    Exceptions/
  Shared/                     # Existing identity/correlation components
config/marketing.php
database/migrations/marketing/
resources/marketing/templates/  # Source MJML or editor starter JSON
public/assets/email-editor/    # Built versioned frontend assets
routes/marketing.php
scripts/marketing/              # Synthetic load tests/operations
storage/marketing/              # Restricted artifacts; preferably object store
storage/logs/                   # PII-redacted application logs
tests/Marketing/
  Unit/ Integration/ API/ Contract/ Security/ E2E/
docs/marketing/
  ADRs/ api/ runbooks/ UAT/ policy-rules/
```

**Security:** namespace and routes alone are not sufficient isolation. Enforce permission and organization/brand scoping in database queries and domain services, even when the UI hides an action.

## 4.2 Service responsibilities

| Service | Single responsibility | Must not do |
|---|---|---|
| AudienceService | Contact lifecycle, memberships, dedup | Read payroll records |
| ConsentService | Append consent events; compute permitted channel/purpose | Invent permission from list membership |
| SegmentService | Validate allowlisted predicates and query contacts | Execute user-supplied SQL |
| TemplateService | Manage versions, compile, sanitize, render, validate tokens | Mutate sent revisions |
| PreflightService | Collect deterministic checks, alerts and evidence | Promise Primary/Inbox outcomes |
| CampaignService | Wizard, state, approval, snapshot | Trigger blocking HTTP mass-send |
| DeliveryPlanner | Compute eligibility and recipient rows | Bypass current suppression |
| DispatchService | Controlled send attempts and reconciliation | Retry ambiguous sends without review |
| ProviderAdapter | Translate provider request/events into canonical models | Change core campaign rules |
| ReportingService | Aggregate status/event logs consistently | Confuse accepted with delivered |
| IntegrationService | Import permissioned leads from social module | Automatically opt in imported leads |

## 4.3 Architecture Decisions to record

- `ADR-001` sidecar vs custom marketing engine.
- `ADR-002` marketing data ownership and HR separation.
- `ADR-003` ESP/provider choice, regional processing and fallback policy.
- `ADR-004` contact identity and email normalization.
- `ADR-005` consent and suppression scope/precedence.
- `ADR-006` delivery state/idempotency/ambiguous-send strategy.
- `ADR-007` template editor and HTML compilation pipeline.
- `ADR-008` analytics/tracking privacy defaults.
- `ADR-009` brand/organization multi-tenancy.
- `ADR-010` keys/retention/backup/incident policy.

Each ADR: **context → alternatives → decision → reasons → risks → owner → review date → rollback**.

---

# 5. System setup, environments and dependencies

## 5.1 Environments

| Environment | Audience | Sending behavior | Access |
|---|---|---|---|
| Local | Synthetic addresses | Fake provider / Mailpit-style capture | Developers |
| CI | Deterministic fixtures | Fake provider only; no network sending | CI automation |
| Staging | Synthetic and allowlisted team inboxes | Provider sandbox/test mode; explicit allowlist | QA and approved users |
| Production | Real, permissioned contacts | Live ESP + rate limits + kill switch | Least privilege |

**Never clone a production contact database into development without an approved, secure masking/anonymization process.** Staging must not accidentally send to contacts from production.

## 5.2 Environment configuration sample

```dotenv
# EXAMPLE ONLY. Do not commit actual secrets or use these values in production.
APP_ENV=staging
MARKETING_ENABLED=false
MARKETING_DEFAULT_TIMEZONE=Asia/Kolkata
MARKETING_SEND_ENABLED=false
MARKETING_DEFAULT_BATCH_SIZE=250
MARKETING_PROVIDER=ses
MARKETING_MAX_SENDS_PER_SECOND=2
MARKETING_MAX_CAMPAIGN_RECIPIENTS=25000
MARKETING_SIGNED_UNSUB_BASE_URL=https://unsubscribe.example.com
MARKETING_TRACKING_ENABLED=false
MARKETING_WEBHOOK_MAX_BODY_BYTES=262144
MARKETING_SAFETY_ALLOWED_TEST_DOMAINS=example.com,example.org
# Retrieve secret and provider credentials from a secret manager, not Git.
```

**Configuration policy:** all provider keys live in a secret store; separate staging and production identities; rotation procedure documented; audit role changes. Never log secret values, one-click tokens, full webhook payloads or complete addresses unless operationally necessary and access-protected.

## 5.3 Dependencies (select only if compatible)

- Runtime: a currently supported PHP branch, with versions based on the existing application and chosen sidecar.
- Database: MySQL 8+/MariaDB or PostgreSQL according to the current application; examples below target **MySQL 8/InnoDB**.
- Jobs: existing queue system, Symfony Messenger, Laravel Queue, RabbitMQ, Redis, SQS or a proven alternative. Add a dead-letter queue.
- Email authoring: GrapesJS + newsletter preset **or** GrapesJS + MJML; choose one canonical editor schema. Preview/render with deterministic compilation.
- Sending: vendor SDK/API with provider-side event feedback; use existing mailer for transactional mail separately if needed.
- Observability: JSON structured logs, traces/correlation IDs, metrics and alerting.
- Testing: PHPUnit/Pest depending on repository, API contract tests, Playwright/Cypress if available, and static analysis (PHPStan/Psalm).
- Security: existing application auth stack, CSP, HTML sanitizer, URL validator, webhook verifier, audit and secrets management.

Avoid needless new infrastructure in an MVP. Use an existing queue, IAM and monitoring stack when they meet requirements.

---

# 6. The canonical data model

## 6.1 Entity catalog / ownership

| Table / entity | Critical fields (minimum) | Key constraints and behavior |
|---|---|---|
| `marketing_brands` | id, organization_id, name, timezone, sender_policy, created_at | Org/brand authorization boundary |
| `marketing_domains` | id, brand_id, domain, verified_at, spf/dkim/dmarc evidence, checked_at | No unverified From |
| `marketing_sender_identities` | id, brand_id, from_name, from_email, reply_to, provider_identity_id | Domain must match verified identity |
| `marketing_contacts` | id, organization_id, email_original, email_canonical, status, source, locale, timezone | Unique org + canonical email; preserve original |
| `marketing_contact_sources` | id, contact_id, source_type, source_ref, collected_at, notice_version | Evidence of acquisition |
| `marketing_lists` | id, brand_id, name, owner_id, status | Static group |
| `marketing_list_members` | list_id, contact_id, added_at, source | Unique pair; no duplicate contact enrollment |
| `marketing_tags` / `marketing_contact_tags` | brand_id, tag_name, contact_id | Classification only, not consent |
| `marketing_segments` | brand_id, name, rules_json, rules_version, owner_id | Allowlisted filters, previewable |
| `marketing_consent_events` | contact_id, brand_id, channel, purpose, action, evidence, occurred_at | Append-only grant/revoke history |
| `marketing_suppressions` | contact_id, brand_id/list scope, reason, effective_at | Applicable exclusions at send time |
| `marketing_templates` | brand_id, name, category, language, current_revision_id | Logical reusable template |
| `marketing_template_revisions` | template_id, revision_no, editor_json, html, text, hash, status, created_by | Immutable once approved/sent |
| `marketing_campaigns` | brand_id, purpose, intent, state, sender_id, revision_id, schedule | Campaign lifecycle |
| `marketing_campaign_audiences` | campaign_id, inclusion/exclusion kind, list/segment reference | Audience specification |
| `marketing_campaign_snapshots` | campaign_id, approved_hash, version info, rules_version, approved_at | Immutable approval artifact |
| `marketing_campaign_recipients` | campaign_id, contact_id, normalized email hash, state | Unique campaign + contact + variant |
| `marketing_send_attempts` | recipient_id, attempt_no, idempotency_key, provider_msg_id, state | Unique logical attempt key |
| `marketing_provider_events` | provider_event_id, provider_msg_id, type, occurred_at, payload_hash | Dedup incoming callbacks |
| `marketing_alerts` | object scope, rule_version, severity, state, evidence, assignee | Alert identity and resolution |
| `marketing_preflight_runs` | artifact hash, policy_version, evaluated_at, results_json | Stale results invalidate approval |
| `marketing_audit_events` | actor, action, object, before_hash, after_hash, correlation_id | Tamper-evident audit trail |
| `marketing_outbox` | aggregate, event_type, payload, state, created_at | Transactional domain-event publishing |
| `marketing_import_jobs` / `marketing_import_rows` | source, mapping, state, row_errors, counts | Staged import and rollback window |
| `marketing_webhook_receipts` | provider, unique event key, signature state, processed_at | Replay protection |
| `marketing_tracking_events` | contact_id, campaign_id, event, consent, created_at | PII minimal; optional |

**Source-of-truth rule:** if Mautic/phpList manages a given entity, keep a provider mapping (`external_system`, `external_id`) and avoid creating two editable masters. Your facade should enforce the same policy before delegating.

## 6.2 Contact identity and deduplication

1. Trim surrounding whitespace and validate email syntax; preserve original and normalized versions.
2. Normalize the domain to a canonical IDN/ASCII form using a tested library and lower-case the domain.
3. Define local-part case handling explicitly; many providers treat it case-insensitively, but **do not silently strip plus tags or Gmail dots**, because that can merge accounts incorrectly.
4. Apply an agreed case-folding policy and uniqueness constraints; record actual normalized values.
5. Merge only after a conflict review: preserve consent history, provenance, tags, and suppression scope. Revocation must win if a merged record conflicts.
6. Never treat merely appearing in an uploaded CSV as proof of marketing permission.

## 6.3 Proposed core MySQL migration (illustrative)

The following is a **minimum viable schema illustration**, not the whole database. Adapt column sizes, migrations, timestamp precision, FK strategy and partitioning to your existing conventions. Create the remaining catalog entities before enabling their features. IDs below are unsigned `BIGINT`; use UUIDs/ULIDs consistently if your app does.

```sql
CREATE TABLE marketing_brands (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  INDEX idx_brand_org (organization_id)
) ENGINE=InnoDB;

CREATE TABLE marketing_contacts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  email_original VARCHAR(320) NOT NULL,
  email_canonical VARCHAR(320) NOT NULL,
  first_name VARCHAR(160) NULL,
  last_name VARCHAR(160) NULL,
  source_type VARCHAR(60) NULL,
  timezone VARCHAR(64) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
    ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_org_email (organization_id, email_canonical),
  INDEX idx_org_status (organization_id, status)
) ENGINE=InnoDB;

CREATE TABLE marketing_lists (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brand_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  INDEX idx_list_brand (brand_id),
  CONSTRAINT fk_list_brand FOREIGN KEY (brand_id) REFERENCES marketing_brands(id)
) ENGINE=InnoDB;

CREATE TABLE marketing_list_members (
  list_id BIGINT UNSIGNED NOT NULL,
  contact_id BIGINT UNSIGNED NOT NULL,
  added_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (list_id, contact_id),
  INDEX idx_members_contact (contact_id),
  FOREIGN KEY (list_id) REFERENCES marketing_lists(id),
  FOREIGN KEY (contact_id) REFERENCES marketing_contacts(id)
) ENGINE=InnoDB;

CREATE TABLE marketing_consent_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contact_id BIGINT UNSIGNED NOT NULL,
  brand_id BIGINT UNSIGNED NOT NULL,
  channel VARCHAR(24) NOT NULL,                 -- email
  purpose VARCHAR(60) NOT NULL,                 -- marketing_newsletter, webinar, etc.
  action VARCHAR(20) NOT NULL,                  -- grant / revoke
  legal_basis VARCHAR(60) NULL,
  evidence_ref VARCHAR(255) NULL,
  notice_version VARCHAR(64) NULL,
  occurred_at DATETIME(6) NOT NULL,
  captured_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  FOREIGN KEY (contact_id) REFERENCES marketing_contacts(id),
  FOREIGN KEY (brand_id) REFERENCES marketing_brands(id),
  INDEX idx_consent_lookup (contact_id, brand_id, channel, purpose, occurred_at, id)
) ENGINE=InnoDB;

CREATE TABLE marketing_suppressions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contact_id BIGINT UNSIGNED NOT NULL,
  brand_id BIGINT UNSIGNED NULL,                 -- NULL = all brands in organization
  list_id BIGINT UNSIGNED NULL,
  reason VARCHAR(40) NOT NULL,                  -- unsubscribe, complaint, hard_bounce
  effective_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expires_at DATETIME(6) NULL,
  created_by_event VARCHAR(120) NULL,
  FOREIGN KEY (contact_id) REFERENCES marketing_contacts(id),
  INDEX idx_suppression_current (contact_id, effective_at, expires_at)
) ENGINE=InnoDB;

CREATE TABLE marketing_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brand_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  category VARCHAR(50) NOT NULL,
  current_revision_id BIGINT UNSIGNED NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  FOREIGN KEY (brand_id) REFERENCES marketing_brands(id)
) ENGINE=InnoDB;

CREATE TABLE marketing_template_revisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id BIGINT UNSIGNED NOT NULL,
  revision_no INT UNSIGNED NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  subject VARCHAR(500) NOT NULL,
  preheader VARCHAR(500) NULL,
  editor_json JSON NOT NULL,
  html MEDIUMTEXT NOT NULL,
  plain_text MEDIUMTEXT NOT NULL,
  content_sha256 CHAR(64) NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME(6) NULL,
  UNIQUE KEY uq_template_revision (template_id, revision_no),
  FOREIGN KEY (template_id) REFERENCES marketing_templates(id)
) ENGINE=InnoDB;

CREATE TABLE marketing_campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brand_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  purpose VARCHAR(80) NOT NULL,
  communication_intent VARCHAR(32) NOT NULL,
  sender_identity_id BIGINT UNSIGNED NOT NULL,
  template_revision_id BIGINT UNSIGNED NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'draft',
  schedule_at_utc DATETIME(6) NULL,
  schedule_timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
  approved_snapshot_hash CHAR(64) NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  INDEX idx_campaign_due (state, schedule_at_utc),
  FOREIGN KEY (brand_id) REFERENCES marketing_brands(id),
  FOREIGN KEY (template_revision_id) REFERENCES marketing_template_revisions(id)
) ENGINE=InnoDB;

CREATE TABLE marketing_campaign_recipients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  contact_id BIGINT UNSIGNED NOT NULL,
  variant_key VARCHAR(20) NOT NULL DEFAULT 'default',
  state VARCHAR(32) NOT NULL DEFAULT 'planned',
  eligible_checked_at DATETIME(6) NULL,
  last_attempt_at DATETIME(6) NULL,
  provider_message_id VARCHAR(255) NULL,
  UNIQUE KEY uq_campaign_contact (campaign_id, contact_id),
  INDEX idx_recipient_state (campaign_id, state, id),
  INDEX idx_provider_message_id (provider_message_id),
  FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id),
  FOREIGN KEY (contact_id) REFERENCES marketing_contacts(id)
) ENGINE=InnoDB;

CREATE TABLE marketing_send_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient_id BIGINT UNSIGNED NOT NULL,
  attempt_no SMALLINT UNSIGNED NOT NULL,
  idempotency_key VARCHAR(160) NOT NULL,
  state VARCHAR(32) NOT NULL,
  provider_message_id VARCHAR(255) NULL,
  requested_at DATETIME(6) NULL,
  completed_at DATETIME(6) NULL,
  error_code VARCHAR(80) NULL,
  UNIQUE KEY uq_attempt (recipient_id, attempt_no),
  UNIQUE KEY uq_idempotency (idempotency_key),
  FOREIGN KEY (recipient_id) REFERENCES marketing_campaign_recipients(id)
) ENGINE=InnoDB;

CREATE TABLE marketing_provider_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(50) NOT NULL,
  provider_event_key VARCHAR(255) NOT NULL,
  provider_message_id VARCHAR(255) NULL,
  recipient_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(50) NOT NULL,
  occurred_at DATETIME(6) NULL,
  received_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  payload_sha256 CHAR(64) NOT NULL,
  UNIQUE KEY uq_provider_event (provider, provider_event_key),
  INDEX idx_event_message (provider, provider_message_id)
) ENGINE=InnoDB;
```

### Required migration extensions before implementation

- Add unique scoping constraints enforcing contact/org vs list/brand/org compatibility. A simple foreign key on `contact_id` and `list_id` does **not** enforce their relationship; do it in transactions or with stronger schema design.
- Create organizations/brands/senders/domains and link identities with composite scoping or scoped service queries.
- Add campaign audience expressions and exclusions, snapshot rows and approval history.
- Create a durable **outbox** and dispatch lease/lock columns (see §11).
- Add unique webhook event key strategy for providers lacking an event ID (stable hash of documented immutable fields; test collision cases).
- Store suppression scope as a validated enum/schema, not ambiguous combinations of nullable columns.
- Build retention, archive and partition strategies for provider events and analytics at scale.
- If requirements include multi-brand consent boundaries, define exactly how revocation across brands should behave; never assume a local list unsubscribe cancels unrelated essential communications.

## 6.4 Critical indexes to test

- Contacts: `(organization_id, email_canonical) UNIQUE` and `(organization_id, status)`.
- Lists: `(list_id, contact_id) UNIQUE`; inverse `(contact_id, list_id)` for contact detail.
- Consent: `(contact_id, brand_id, channel, purpose, occurred_at, id)`; use a materialized current-state table if verified appropriate at scale, but preserve append-only history.
- Campaigns: `(state, schedule_at_utc)` and `(brand_id, created_at)`.
- Recipients: unique `(campaign_id, contact_id)` for one initial send; if genuine repeat cycles exist, introduce explicit `run_id` in the uniqueness key.
- Provider events: stable provider event key; indexes for message ID lookup and reporting window.
- Alerts: `(brand_id, state, severity, last_seen_at)` and deduplication key.
- Outbox: `(state, next_attempt_at, id)` for safe batching.

**Performance validation:** use `EXPLAIN ANALYZE` on realistic synthetic data; index by access path, not just by columns listed here.

---
# 7. Authentication, authorization and multi-brand safety (FR-001–006)

## 7.1 Roles and permissions

| Role | Typical operations | Explicitly forbidden |
|---|---|---|
| System Administrator | Define providers, keys (via secret manager), domains, limits, access | Default read access to HR/Payroll via marketing |
| Marketing Manager | Campaign planning, send approval (according to policy), reporting | Editing authentication policy or mandatory blocks |
| Campaign Creator | Draft contacts/segments/templates/campaigns and request approval | Approving own send where maker-checker enabled |
| Template Designer | Create/edit template revisions, test rendering | Production sending or exporting all contacts |
| Analyst | Aggregated campaign reporting | List imports, sending, full PII export |
| Compliance/Deliverability Officer | Review consent evidence, alerts, suppressions, approved exceptions | Bypassing statutory or provider requirements |
| Viewer/Auditor | Read allowed audit records | Modify or send |

**Permission slugs:**

```text
marketing.view
marketing.contact.view
marketing.contact.import
marketing.contact.export
marketing.contact.modify
marketing.consent.manage
marketing.template.create
marketing.template.edit
marketing.template.approve
marketing.campaign.create
marketing.campaign.approve
marketing.campaign.send
marketing.campaign.pause
marketing.alert.ack
marketing.alert.manage
marketing.report.view
marketing.provider.admin
marketing.domain.admin
marketing.audit.view
```

**Enforcement pattern:**

1. Authenticate staff with existing mechanism; map to a user ID.
2. Require module permission at middleware level.
3. Recheck fine-grained action at controller and domain-service levels.
4. Scope every query to the staff user's permitted `organization_id` and `brand_id`; do not rely on a request-supplied `brand_id` alone.
5. For request IDs, verify record belongs to an authorized brand **before** reading private fields.
6. For high-risk operations, log actor, permission, action, object, hash, IP/client fingerprint as appropriate and correlation ID.
7. Require reauthentication/MFA if existing infrastructure supports it for provider key rotation and sensitive exports.

**Example (framework-agnostic pseudo-PHP):**

```php
public function showCampaign(AuthUser $user, int $campaignId): CampaignView
{
    $user->requirePermission('marketing.view');
    $campaign = $this->campaignRepo->findInBrands(
        $campaignId,
        $user->authorizedMarketingBrandIds()
    );
    if ($campaign === null) {
        throw new NotFoundOrForbiddenException(); // avoid cross-brand data disclosure
    }
    return $this->presenter->forUser($campaign, $user);
}
```

## 7.2 Security acceptance checks

- [ ] HR-only employee gets 403 on **all** marketing API routes and cannot infer contact counts.
- [ ] Template Designer cannot call `POST /campaigns/{id}/send` directly.
- [ ] Foreign-brand object IDs cannot be viewed, modified, exported or approved.
- [ ] Background workers run as limited service principals, not full database superusers.
- [ ] All data exports are audited, scoped, access-controlled and retention-limited.
- [ ] CSRF, session expiry, rate limits and authorization are enforced server-side.
- [ ] Shared password reset / account notices remain in their existing transactional system.

---

# 8. Audiences: contacts, groups, segments, consent (FR-015–024)

## 8.1 Contact creation, import and merge workflow

```text
CSV / API / lead form
   ↓  Source identified + permission context provided
Upload → file-type / size validation → temporary encrypted staging
   ↓  Parse with streaming reader (no entire 100k file in memory)
Column mapping → sample preview → email validation
   ↓
Deduplicate within CSV → match existing canonical emails
   ↓
Classify rows: new / update / duplicate / invalid / missing permission
   ↓
Operator approval + provenance capture
   ↓
Transactionally upsert contacts and valid memberships
   ↓
Append consent events only when valid evidence is supplied
   ↓
Report totals + rejected rows + import audit + rollback eligibility
```

**CSV safety:** strip/escape spreadsheet formula injection on export (`=`, `+`, `-`, `@` at cell start), validate UTF-8 and size limits, do not execute spreadsheet formulas, quarantine malformed rows, handle BOM/quoted delimiters, and redact rejected-row personal data from logs.

**Import rules:**

- Consent fields are not automatically set true because a list exists or an operator checked “Import”.
- `source_ref`, collection date, notice version, lawful basis/permission and channel purpose must be explicit.
- If the imported source lacks sufficient permission, the contact may be stored in a **non-marketable** state until validation is complete; do not send promotional mail.
- On duplicate: update permitted profile attributes; preserve all historical events and suppressed status; never use a fresh CSV to overwrite a prior unsubscribe.
- Large imports run asynchronously in batches and generate per-row errors with an expiry/retention policy.
- Import rollback may undo newly created list memberships/contacts if untouched, but must not delete independent audit or consent evidence nor undo later business actions without review.

## 8.2 Groups/lists vs tags vs segments

| Construct | Definition | Example | Mutable membership |
|---|---|---|---|
| **List / Group** | Static set of contacts associated with a brand | Webinar registrants - November | Manual and through authorized import/API |
| **Tag** | Classification of a contact | `interest:startup` | Manual/automated, not marketing consent |
| **Segment** | Saved query built from validated conditions | Opted-in AND registered AND country=IN | Calculated at plan time, optionally cached |
| **Suppression** | Restriction that must override eligibility | Hard bounce or withdrawn marketing consent | Immediate policy-controlled update |

**Dynamic segment JSON example:**

```json
{
  "operator": "AND",
  "conditions": [
    {"field": "brand_marketing_permission", "op": "eq", "value": true},
    {"field": "source_type", "op": "eq", "value": "webinar_form"},
    {"operator": "OR", "conditions": [
      {"field": "country", "op": "eq", "value": "IN"},
      {"field": "timezone", "op": "eq", "value": "Asia/Kolkata"}
    ]}
  ]
}
```

**Compiler:** allowlist field names, operations and data types; convert to parameterized queries; enforce query complexity/depth and result limit; never interpret arbitrary JSON as SQL. Every count and preview uses identical eligibility rules and brand scoping; do not show only group membership count as “eligible recipients.”

## 8.3 Consent state model

Track events instead of one boolean `is_subscribed`:

- `grant`: who/what/when/how/notice version, purpose, brand, channel, source and evidence ref.
- `revoke`: same scope plus source (`footer_link`, `one_click`, `manual`, `support_request`, `provider_complaint`).
- `complaint`: global marketing suppression unless applicable policy requires broader block.
- `hard_bounce`: email-address suppression and validation requirement before any authorized reinstatement.
- `restore`: never silently restore by import; require evidence of renewed authorization and guard against overriding complaints.
- `consent_state_projection`: optional materialized latest valid state for efficient lookups; rebuildable from events.

**Decision pseudocode (fail closed):**

```text
canSendMarketing(contact, brand, purpose, campaign, now):
    reject unless campaign's actual purpose is marketing AND
                  contact and brand belong to same authorized organization
    reject if contact email invalid/unverified per policy
    reject if a current global/brand/purpose/list suppression applies
    reject unless consent or another approved lawful basis permits this
                  specific brand + email channel + marketing purpose
    reject if campaign cancel/pause flag active
    reject if quiet hours/frequency caps deny this send (when enabled)
    accept, with recorded decision version and timestamp
```

**Cross-purpose caution:** a webinar registration confirmation can be a necessary operational email, while subsequent product offers are marketing. Do not make the marketing opt-out govern genuine HR/payroll essentials; equally, do not pretend a promotional blast is “transactional” to bypass controls.

## 8.4 Preferences and one-click unsubscribe

Mandatory user-facing experience:

- Footer contains an easily visible, working `Unsubscribe` link for marketing.
- Preference center permits selected newsletter/purpose subscriptions per brand where applicable; plain unsubscribe does not require authentication.
- Use an opaque, unguessable, scope-bound token with revocation behavior; avoid putting raw email, contact ID or reusable API secrets in links.
- Implement **RFC 8058** one-click for qualifying mailing-list/marketing emails: receiver submits `POST`, no confirmation click, no login, no captcha and no extra fields required.
- The one-click `GET` endpoint should be an informational/unsubscribe UI page, not a state-changing action: link scanners can make GET requests.
- Recheck suppression in the dispatch worker, not only at initial audience preview.
- Include `List-Unsubscribe` and `List-Unsubscribe-Post` headers as appropriate; sign both headers via DKIM as prescribed by RFC 8058 and make sure the chosen ESP supports/configures this correctly.

**Header example (message-specific opaque token):**

```text
List-Unsubscribe: <https://unsubscribe.example.com/one-click/OPAQUE_TOKEN>
List-Unsubscribe-Post: List-Unsubscribe=One-Click
```

**Expected one-click POST body:** `List-Unsubscribe=One-Click`. It is generated by the receiving email client, not your own users. Tokens must be bound to a contact and subscription scope, and kept valid long enough to honor requests. One-click behavior cannot depend on browser cookies, CSRF session tokens, interstitial pages or authentication.

**Example response semantics:**

- `POST /public/unsubscribe/one-click/{token}`: verify token + method/body, apply scope and suppression idempotently, return `200` or `204` for repeated requests; do **not** trigger a marketing confirmation email.
- `GET /public/preferences/{token}`: render accessible preference UI, no unsubscribe side effect from a GET.
- `POST /public/preferences/{token}`: save allowed preference changes, apply verification controls suitable for increases in permission.

**SLO:** Immediately update internal state; never let the next worker send after recorded opt-out. Gmail and Yahoo also publish unsubscribe processing requirements; implement an internal target **well below** their outer limits.

## 8.5 Consent/suppression test matrix

| Scenario | Expected |
|---|---|
| Contact in 3 lists | Exactly one recipient row for a campaign |
| Imported lead with no marketing permission | Stored non-marketable / excluded |
| Existing unsubscribed contact appears in new CSV | Remains suppressed |
| Recipient clicks one-click unsubscribe after scheduling | Blocked before provider send |
| Complaint webhook arrives during batch | No further attempted marketing sends to contact |
| Purpose-specific opt-out | Only permitted scopes change; policy recorded |
| Payroll employee record has work email | Does not magically appear as marketing contact |
| Cross-brand contact record | Permission checked separately for each relevant brand |

---

# 9. Template Studio and rendering pipeline (FR-007–014)

## 9.1 Editor decisions and deliverables

**Preferred:** embed GrapesJS as a versioned frontend bundle. Choose newsletter preset for basic table-based HTML, or GrapesJS + MJML for advanced responsive layouts. Save the *canonical editor source* (structured JSON/MJML) as well as compiled HTML and plain text; **never** regenerate a sent revision from a newer editor library version.

Minimum editor toolbar:

- Template gallery: filter category, brand, language, owner, last-tested time and state.
- Compose: subject, preheader, communication intent, body, footer, sender identity.
- Blocks: brand header, image/alt text, heading, paragraph, bullet list, spacer, CTA, social links, signature, unsubscribe/footer.
- Personalization: variable insertion, token schema preview, missing-token/error panel.
- Display modes: desktop, narrow mobile, images blocked, text-only.
- Actions: Save draft, Duplicate, Compare revisions, Test send, Run preflight, Request approval, Archive.
- HTML source editing: advanced permission only; sanitize/compile on server; no bypass.
- Protect footer unsubscribe and required legal content from deletion in marketing templates.

## 9.2 Starter template inventory

| Key | Category | Default communication intent | Must-have content |
|---|---|---|---|
| `welcome_subscription` | Welcome | Promotional or personal-style guidance | Context of subscription, preference link |
| `webinar_invitation` | Webinar | Promotional | Date/time/timezone, agenda, CTA |
| `webinar_confirm` | Event confirmation | Operational | Confirmed event details and access path |
| `webinar_reminder` | Reminder | Operational | Date/time/joining instructions; no offer injection |
| `newsletter_monthly` | Newsletter | Promotional | Sections, brand identity, visible unsubscribe |
| `product_course_launch` | Launch | Promotional | Product value proposition, truthful CTA |
| `offer_announcement` | Promotion | Promotional | Accurate pricing/terms, unsubscribe |
| `lead_nurture` | Education | Promotional | Helpful content + explicit relationship |
| `survey_feedback` | Feedback | Context-dependent | Purpose, link, permission and privacy |
| `account_system_update` | Necessary operational notice | Operational | Exact account/event context; separate send workflow |

**Important:** A “personal-style” appearance must not be used to impersonate a one-to-one conversation. Any broadcast promotional communication remains marketing for consent and unsubscribe purposes.

## 9.3 Template source and render flow

```text
Editor JSON / MJML + approved brand kit + subject / preheader
             ↓
Schema validation (allowed blocks, tags, length, URL types)
             ↓
Canonicalize → revision number + content hash
             ↓
Server-side compile to email HTML (safe inline CSS)
             ↓
Sanitize active content / unsafe attributes / prohibited URLs
             ↓
Validate required footer, unsubscribe, alt text and merge tokens
             ↓
Plain-text equivalent + accessible link labels
             ↓
Preview with non-sensitive dummy values
             ↓
Run rule engine + optional renderer/seed tests
             ↓
Freeze revision + approval + campaign snapshot
             ↓
Render per recipient at dispatch; escape variable values
```

**No scripts, forms, event handlers or untrusted HTML inside sent messages.** Inline trusted CSS according to email-client constraints. If remote asset domains are user supplied, review URLs for phishing/suspicious redirects, asset availability and tracking/privacy policy.

## 9.4 Personalization token contract

Supported starter tokens:

| Token | Type | Default | Behavior |
|---|---|---|---|
| `{{contact.first_name}}` | string | `there` if approved | HTML-escape in text node |
| `{{contact.company}}` | string | Optional | HTML-escape; omit empty block if desired |
| `{{campaign.title}}` | string | Required | Block if missing |
| `{{event.starts_at_local}}` | formatted date/time | Required for webinar | Render with event and user timezone policy |
| `{{event.join_url}}` | URL | Required for reminder | Validate scheme/host and HTML-attribute escape |
| `{{subscription.manage_url}}` | trusted URL | Required for marketing | Generated server side; never supplied by arbitrary contact field |
| `{{brand.name}}` | string | Required | Server-controlled brand data |

**Compiler rules:**

- Never apply regex-only find/replace to raw email HTML and call it safe; use a schema-aware token renderer with context-specific escaping (text, HTML attribute, URL).
- Do not support arbitrary PHP expressions or database field paths in templates.
- Merge tag resolution must be deterministic and prevent contacts from reading one another's values.
- Required tokens missing → **BLOCK**. Optional missing tokens → explicit default or configured conditional content.
- Test subject line, plaintext body, all href attributes, alt attributes and UTF-8.
- Avoid leaking unsubscribe tokens in test-mode logs.

## 9.5 Template revision and approval

**States:** `draft → in_review → approved → archived`; rejected revisions return to `draft` as **new** revisions or become `rejected`. A revision already approved or sent is read-only.

**Approval input hash** must cover at least:

```text
SHA-256(canonical-json({
  subject, preheader, compiled_html, plain_text, variable_schema,
  from_identity, communication_intent, footer_version,
  brand_id, editor_schema_version, policy_version
}))
```

Campaign approval additionally binds: campaign objective/purpose, sender identity, template revision/hash, audiences and exclusions, schedule (when policy demands), tracking settings, frequency limits and current rule-set version. Store a hash of this frozen snapshot. Re-run blocking validations when a sensitive field changes.

**Revision behavior:**

- Editing approved revision creates a draft revision; previous approved/sent revision remains intact.
- Approval action requires distinct approver where maker-checker policy is active.
- Before queue publication, compare current hash to approved hash; if mismatch, revert to `needs_review`.
- Store what content was actually used for each send (revision ref + final-render hash) to support incident audit.
- Template gallery may indicate **last evaluated** status, but a fresh campaign preflight must still validate sender, audience and current policy.

## 9.6 Preview and email-client QA

- Test desktop/mobile clients (at minimum Gmail web/mobile, Outlook variants, Apple Mail where available) using authorized seed mailboxes or a commercial rendering provider.
- Include images-disabled, dark-mode, text-only, screen-reader and broken-image scenarios.
- Verify 600–700px-ish email layout where appropriate, mobile readable font sizing, tap target spacing, alt text and semantic hierarchy.
- Check subject/preheader clipping heuristically, but do not treat any fixed character count as a universal provider requirement.
- Ensure user-supplied URL and CTA display text are clear and accurate. Do not disguise destinations.
- Seed test sends must be separated from production analytics and must display an explicit **TEST** label in logs.

**Acceptance:** 10 starter templates can be loaded, saved, versioned, personalized, rendered and regression-tested without breaking the mandatory footer or HTML safety constraints.

---

# 10. Deliverability Intelligence and alert engine (FR-037–042)

## 10.1 Never model provider tabs as a guaranteed output

Present a **Communication intent (guidance only)** field:

- `personal_style`: relevant, conversational presentation where truthful, never fake one-to-one correspondence.
- `promotional`: campaigns, newsletters, offers, product/course launches and marketing invites.
- `operational`: necessary confirmations, service notices and event updates tied to a user action.

These are **content-policy presets** that select checklists and UI guidance. They **do not** set a Gmail classification flag, bypass unsubscribe obligations or override actual message content. Controlled seed inbox tests may show observed placements for each tested account only. The product must state **“Observed results are sample-specific; inbox tab and spam-folder placement are not guaranteed.”**

## 10.2 Rule engine design

Each rule definition must include:

```json
{
  "rule_id": "D-005",
  "policy_version": "2026-11-09.1",
  "category": "unsubscribe",
  "severity": "BLOCK",
  "scope": ["template", "campaign", "sending_identity"],
  "applies_to": ["promotional", "subscribed_marketing"],
  "validator_key": "mandatory_unsubscribe_headers",
  "remediation_key": "configure_one_click_unsubscribe",
  "reference_url": "https://www.rfc-editor.org/rfc/rfc8058",
  "enabled": true
}
```

The engine receives a **typed, immutable preflight context** containing brand, domain verification evidence, sender, intent, classified actual purpose, current template revision, audience eligibility summary, policy version, provider capabilities and test results. It returns deterministic findings with evidence and remediations. Keep *objective rules* separate from *heuristic advice*.

### Severity/action mapping

| Severity | Meaning | Behavior |
|---|---|---|
| `BLOCK` | Legal/permission, essential sender configuration, required unsubscribe or security precondition fails | Disable scheduling/sending; requires actual correction |
| `HIGH` | Important risk with uncertain delivery impact | Require explicit review/approval according to policy |
| `WARNING` | Quality/compatibility or reputational heuristic | Show guidance and record acknowledgment |
| `INFO` | Optimization suggestion or unverified observation | Non-blocking, never imply inbox guarantee |

**No aggregate “92% inbox probability.”** Optional health grades should be labels derived from transparent checks (`Blocked`, `Review required`, `Ready for controlled send`) and their scope/limitations.

## 10.3 Initial policy rule catalog

| Rule | Condition | Level | Engineering action and remediation |
|---|---|---|---|
| D-001 | Sender/From domain unverified | BLOCK | Verify ownership and provider sender identity |
| D-002 | Required SPF/DKIM/DMARC or DMARC alignment fails for relevant traffic | BLOCK | Fix DNS and aligned authentication; revalidate |
| D-003 | No lawful permission/consent for a marketing audience | BLOCK | Exclude/review contact source; don't auto-opt-in |
| D-004 | Audience includes suppressed or revoked recipients | BLOCK until planner excludes | Re-evaluate; never send affected rows |
| D-005 | Applicable marketing one-click headers / mechanism missing | BLOCK | Configure RFC 8058 with proper DKIM signing |
| D-006 | Required visible unsubscribe/preferences link absent | BLOCK | Insert immutable footer component |
| D-007 | Template has required but missing variables | BLOCK | Fix schema or approved defaults |
| D-008 | Sender/content/intent conflict, e.g. promotional offer labeled operational | HIGH / BLOCK by policy | Change intent/classification or remove promotional content |
| D-009 | HTML contains scripts, forms, event handlers, prohibited URLs | BLOCK | Sanitize/reject editor content |
| D-010 | High recent complaint/spam-rate signal from provider/Postmaster | HIGH/incident | Pause appropriate campaigns, investigate cohort and source |
| D-011 | Broken or unsafe CTA destination | HIGH/BLOCK | Verify and correct URL; do not fetch internal endpoints |
| D-012 | Too many redirects, misleading link labels or URL shorteners | WARNING/HIGH | Use direct transparent destination |
| D-013 | No plain-text alternative, invalid MIME or header syntax | BLOCK | Rebuild compliant message |
| D-014 | Images missing alt text, poor mobile accessibility | WARNING | Fix alt/copy/layout and retest |
| D-015 | Excessive images-to-context ratio or image-only message | WARNING | Add meaningful readable text; not a deterministic spam predictor |
| D-016 | Unusual sender/From change or reputation data stale | HIGH | Review sender, verify DNS and start with controlled cohort |
| D-017 | Campaign approved hash no longer matches | BLOCK | Re-run preflight and approval |
| D-018 | Provider send quota reached or webhook ingestion unhealthy | BLOCK/operational | Pause and fix event feedback/capacity |
| D-019 | Active approved send exceeds allowed frequency/quiet-hours policy | BLOCK affected rows | Reschedule/exclude |
| D-020 | Unauthorized asset or tracking domain | HIGH/BLOCK | Replace or register domain |

**Rules D-001–D-014 map to the BRD/SRS's original 14 core checks; D-015–D-020 extend the implementation catalog.** Review thresholds and classifications at policy sign-off; heuristics are not receiver algorithms.

## 10.4 A real-time alert lifecycle

```text
DETECTED → OPEN → ACKNOWLEDGED → IN_PROGRESS → RESOLVED
                    ↘ WAIVED (only permitted heuristic, actor/reason/expiry)
            RESOLVED → REOPENED when failing again
```

Schema fields: `id`, `alert_dedup_key`, `object_type`, `object_id`, `brand_id`, `rule_id`, `policy_version`, `severity`, `state`, `evidence_json`, `affected_count`, `first_seen_at`, `last_seen_at`, `assigned_to`, `acknowledged_by`, `acknowledged_at`, `resolved_by`, `resolved_at`, `resolution_note`, `waiver_reason`, `waiver_expiry`, `correlation_id`.

**Alert UX requirements:**

- Show affected template/campaign/domain and specific evidence, not simply “may go to spam.”
- Provide a **Fix now** deep link to correct screen; include what changed and why the warning appeared.
- Aggregate duplicate notifications by a stable dedup key, while incrementing occurrence count.
- Acknowledging does not clear a block; only passing the applicable rule does.
- Alert routing: creator for template issues, marketer/manager for campaign issues, DevOps for webhook/provider incidents, Security/Compliance for consent/security incidents.
- Alert itself should never send a promotional blast. Operational system alerts are distinct and rate limited.

## 10.5 Preflight API contract

**Request:**

```http
POST /api/v1/marketing/campaigns/814/preflight
Content-Type: application/json
X-Request-ID: 2a8f-illustrative-request-id
```

```json
{"expected_revision_id": 147, "include_link_checks": true}
```

**Response:**

```json
{
  "run_id": "pfl_20261109_000184",
  "policy_version": "2026-11-09.1",
  "artifact_sha256": "<64-char-real-hash-from-server>",
  "evaluated_at": "2026-11-09T12:00:00Z",
  "status": "blocked",
  "summary": {"block": 1, "high": 1, "warning": 1, "info": 0},
  "findings": [
    {"rule_id": "D-005", "severity": "BLOCK", "scope": "campaign",
     "message": "Required one-click unsubscribe setup is not verified",
     "field": "sender_identity_id", "remediation": "Configure list-unsubscribe headers and verify DKIM signing"},
    {"rule_id": "D-008", "severity": "HIGH", "scope": "template",
     "message": "Promotional offer conflicts with selected operational intent",
     "field": "communication_intent", "remediation": "Reclassify or revise the email"},
    {"rule_id": "D-014", "severity": "WARNING", "scope": "template",
     "message": "A content image is missing alternative text",
     "field": "editor_json.blocks[3]", "remediation": "Describe the image meaning"}
  ]
}
```

Response fields are illustrative; actual APIs should be specified in OpenAPI and generated into documentation/contract tests. `artifact_sha256` is computed server-side, not supplied or trusted from the browser.

## 10.6 Real-time vs scheduled preflight

| Event | Re-evaluate |
|---|---|
| Save template draft | Template/static HTML checks |
| Change sender/brand | DNS/authentication, policy and header checks |
| Edit subject/body/links/footer | Full content preflight; invalidate approval |
| Change audience/segment | Consent/preference eligibility summary |
| Request approval | Full preflight; store revision/hash/evidence |
| Before creating send jobs | Verify current policy, approval artifact, provider health |
| Before each external provider call | Live suppression, paused state and tenant restrictions |
| Provider/monitoring incident | Current queued campaigns and sending controls |

**DNS details:** perform read-only SPF/DKIM/DMARC validation with provider identity configuration; verifying DNS records alone does not prove a particular message was signed/aligned correctly. Perform a real send to controlled seed accounts and inspect authentication results where appropriate. SPF/DKIM/DMARC rules must reflect sender volume/purpose/current provider requirements; favor stronger compliant settings for all marketing streams.

## 10.7 Illustrative PHP preflight design

```php
interface MarketingRule
{
    /** @return Finding[] */
    public function evaluate(PreflightContext $context): array;
}

final readonly class Finding
{
    public function __construct(
        public string $ruleId,
        public string $severity,
        public string $message,
        public string $remediation,
        public array $evidence = [],
    ) {}
}

final class PreflightEngine
{
    /** @param iterable<MarketingRule> $rules */
    public function __construct(private iterable $rules) {}

    /** @return array{status: string, findings: array} */
    public function evaluate(PreflightContext $context): array
    {
        $findings = [];
        foreach ($this->rules as $rule) {
            array_push($findings, ...$rule->evaluate($context));
        }
        $severities = array_column(array_map(
            static fn (Finding $f): array => ['severity' => $f->severity],
            $findings
        ), 'severity');
        $status = in_array('BLOCK', $severities, true) ? 'blocked'
            : (in_array('HIGH', $severities, true) ? 'review_required' : 'ready');
        return ['status' => $status, 'findings' => $findings];
    }
}
```

In implementation: type the DTO `PreflightContext`, validate all rule inputs, catch individual detector failures and fail closed for safety-critical rules; persist results in `marketing_preflight_runs`, include rule-set version, run age and source timestamps. Avoid remote HTTP checks on every keystroke: debounce in UI, cache safe domain checks and enforce a time budget.

## 10.8 Reputation signals and metrics

- Google Postmaster metrics (if domain is eligible/has enough data) are **domain-level, delayed, incomplete and not equivalent to your ESP's own complaint rate**.
- Monitor recipient-domain rejection patterns, provider hard/soft bounces, complaints, unsubscribes, authentication failures and throttles.
- Suggested internal review thresholds: Gmail reported spam rate ≥0.10% investigate; ≥0.20% urgent; ≥0.30% stop/incident response, consistent with staying below Google's published 0.3% limit. **These are proposed operational triggers**, not receiver inbox-placement predictions.
- Yahoo advises bulk senders to keep complaints below 0.3%; use a lower internal trigger. Microsoft consumer high-volume authentication requirements also apply when relevant.
- Time windows and denominators differ across dashboards; report source, calculation and retrieval timestamp beside every rate.
- No public green check “spam-free” or “guaranteed inbox” badge.

---

# 11. Campaign creation, approvals and send engine (FR-025–036)

## 11.1 Campaign wizard (screen behavior)

1. **Campaign details:** name, brand, objective, actual purpose/classification, communication intent.
2. **Sender:** verified From identity, reply-to, provider configuration and display name.
3. **Audience:** include lists/segments, exclude lists/tags/contacts, simulate eligible count, show permission coverage.
4. **Template:** choose immutable revision, personalize fields, subject and preheader; preview render.
5. **Sending settings:** local timezone, schedule, tracking preferences, provider throttles, frequency/quiet-hours (if enabled).
6. **Preflight:** actionable findings; fix blockers; controlled test send; verify headers.
7. **Approval:** frozen snapshot, approver identity, policy version, audience criteria and sender fingerprint.
8. **Launch:** enqueue job or schedule; monitor progress and control pause/cancel; reconcile provider events.

**Never enqueue at step 6 just because an operator clicked “Test.”** Test-mode contacts and metrics are isolated.

## 11.2 Campaign state machine

```text
DRAFT
  ↓ submit for review
IN_REVIEW ─── rejected/change ───> DRAFT
  ↓ approved snapshot + passing preflight
APPROVED
  ├── send now ─────────────> QUEUED
  └── schedule ─────────────> SCHEDULED ──due──> QUEUED
QUEUED ──workers──> SENDING ──all reconciled──> COMPLETED
  │                   │
  ├── PAUSED <────────┤
  └── CANCELLED <─────┘
SENDING ──unrecoverable issue──> FAILED / NEEDS_REVIEW
```

**Transition permissions:** creator may draft; approver approves; sender/authorized manager schedules; authorized operator may pause/cancel; only an approved, unchanged snapshot with passing mandatory checks may enter `QUEUED`.

**External reality:** `CANCELLED` means **no more send requests should be initiated**; already accepted messages cannot be recalled. Display in UI.

## 11.3 Campaign recipient states

```text
planned → eligible → queued → leased → submitting
                                ├─> accepted → delivered (event, if reported)
                                │             ├─> bounced
                                │             └─> complaint
                                ├─> deferred/retryable (known no acceptance)
                                ├─> failed_permanent
                                └─> unknown_outcome (ambiguous provider response)

planned/queued/leased → suppressed | skipped | cancelled
```

**Never reduce all states to `sent=true`.** Provider accepted, deferred, delivered, bounced, complaint, suppression and unknown have different semantics. A delayed webhook must not rewrite state backward improperly; store all source events and compute a canonical status according to a documented precedence/state-reducer.

## 11.4 Planning/snapshot algorithm

1. Lock campaign row; verify approved current hash and allowed transition.
2. Expand inclusion lists and evaluated segments; apply exclusions.
3. Deduplicate by organization/campaign + canonical contact identity.
4. Compute preliminary eligibility using current consent/suppression.
5. Insert recipient rows with unique constraints via batched upsert; retain excluded counts/reasons.
6. Store a **plan revision** (criteria, evaluated_at, rule version, total eligible, excluded breakdown). Decide whether future segment members are included: **default no**, snapshot recipients when approved/launched.
7. Commit within bounded transactions; enqueue only outbound IDs through a transactional outbox.
8. Recheck eligibility at *actual* dispatch; if revoked, move to suppressed and never call ESP.

**Race condition:** do not hold a database transaction open through an external provider request. If consent changes between final query and network handoff, prevention depends on well-defined synchronization boundaries; prioritize dispatch-time recheck and process revocations promptly. Document residual in-flight race and ensure queue pause is immediate.

## 11.5 Durable outbox + worker ownership

Use a database outbox to atomically create domain events when campaign state changes. Separate worker(s) poll/publish. For individual messages:

- Reserve a recipient/send attempt with an atomic conditional update / row lock and a lease expiry.
- Persist a stable `idempotency_key = hash(campaign_run_id | contact_id | variant)` (not random per retry).
- Revalidate suppression/consent and campaign state **immediately before provider call**.
- Apply token-bucket limits per provider identity/brand and optionally recipient domain.
- Construct message with revision and recipient data, MIME headers and unsubscribe URL.
- Send through provider adapter and persist accepted provider message identifier.
- If provider reports clear, retryable non-acceptance, schedule exponential backoff with jitter.
- If timeout/connection loss happens **after the request may have reached the provider**, set `unknown_outcome`. Query provider by stable external ID or reconcile events **before** retrying; if provider cannot dedupe or reconcile, escalate for manual action rather than blindly resending.
- Dead-letter repeated failures, preserving attempt evidence; monitor backlog and SLA.

**Crucial accuracy note:** exactly-once *external delivery* is generally not provable without the ESP's idempotency semantics. Internal unique keys prevent duplicate jobs/rows but do **not** by themselves guarantee that a provider never accepts a duplicated request. Build an explicit unknown-outcome path.

## 11.6 Worker pseudo-implementation (not copy/paste production code)

```php
final class MarketingDispatchWorker
{
    public function handle(int $recipientId): void
    {
        // 1. Atomically acquire lease; no-op if already handled or leased elsewhere.
        $lease = $this->leaseRepo->tryAcquire($recipientId, ttlSeconds: 120);
        if ($lease === null) { return; }

        try {
            $ctx = $this->recipientRepo->loadDispatchContext($recipientId);
            if (!$ctx->campaign->isApprovedAndSending() ||
                !$ctx->approvalHashIsCurrent()) {
                $this->recipientRepo->markSkipped($recipientId, 'campaign_not_approved');
                return;
            }

            // Must be checked at dispatch, not merely at preview.
            $decision = $this->eligibility->evaluateNow($ctx);
            if (!$decision->allowed) {
                $this->recipientRepo->markSuppressed($recipientId, $decision->reason);
                return;
            }

            $this->rateLimiter->acquire($ctx->provider, $ctx->brandId);
            $message = $this->renderer->renderApprovedRevisionFor($ctx);
            $attempt = $this->attemptRepo->prepareStableAttempt($ctx);

            // Mark external handoff in progress *before* network call.
            $this->attemptRepo->markSubmitting($attempt->id);
            $response = $this->provider->send($message, $attempt->idempotencyKey);

            $this->attemptRepo->recordProviderAcceptance(
                $attempt->id,
                $response->providerMessageId
            );
        } catch (DefinitelyNotAcceptedException $e) {
            $this->retryPolicy->scheduleKnownSafeRetry($recipientId, $e);
        } catch (AmbiguousProviderOutcomeException $e) {
            $this->attemptRepo->markUnknownAndReconcile($recipientId, $e);
        } finally {
            $this->leaseRepo->releaseIfOwned($lease);
        }
    }
}
```

**Implementation duties omitted here:** database commits and row locking, shutdown signals, exception classification, provider-supported idempotency, kill switch, credential handling, DSN, network timeouts, per-recipient back-pressure, completed state reducer, worker concurrency and metrics. All must be written and tested.

## 11.7 Scheduling and timing

- Store `schedule_at_utc` plus source timezone (`Asia/Kolkata` or another IANA zone) and intended local time.
- Resolve ambiguous/nonexistent DST times for foreign regions using an explicit documented policy; show resolution before approval.
- Scheduler uses UTC due-time and a lease so only one scheduler owns a batch.
- Timezone-aware delivery to recipients (P1) uses recipient timezone only if known/verified; fallback to brand timezone or skip under policy.
- Pausing stops *new provider requests*; jobs waiting in a queue may remain stored. Resume must revalidate consent/suppression, campaign hash, health and policy.
- Frequency caps count accepted/relevant prior sends according to an agreed business definition, not just queued tasks.

## 11.8 Provider adapter interface

```php
interface EmailProviderAdapter
{
    public function verifySenderIdentity(string $domain): VerificationResult;
    public function send(OutboundMessage $message, string $stableKey): ProviderSendResult;
    public function verifyWebhook(string $rawBody, array $headers): VerificationResult;
    /** @return CanonicalProviderEvent[] */
    public function parseEvents(string $rawBody, array $headers): array;
    public function getSendingQuota(): ProviderQuota;
    public function supportsIdempotentSend(): bool;
}
```

Provider-specific adapter capabilities and limitations MUST be documented before launch. Some providers will not support guaranteed idempotent sends or “delivered” events consistently; make these optional in the contract and adapt state/analytics accordingly.

---
# 12. Webhook ingestion, event processing and analytics (FR-031–032, 041, 043–044)

## 12.1 Provider event pipeline

```text
ESP → HTTPS webhook → request size cap → provider signature verification
      → stable event key → durable receipt + dedup → quick 2xx ACK
      → processing queue → canonical event translator
      → state reducer → suppression/alert updates → reporting projections
      → replay/DLQ metrics + reconciliation
```

**Do not perform a large campaign aggregation inside a webhook HTTP request.** Verify and persist promptly; process asynchronously. Be prepared for duplicate events, delayed events, out-of-order events, temporary outages and missing events.

## 12.2 Signature verification & replay prevention

- Use the **provider's documented authentication scheme**. Some use HMAC over raw bytes; others use signed webhook envelopes, provider-issued JWTs, or an intermediary such as SNS with certificate verification and subscription confirmation.
- Read **exact raw request bytes** before parsing. Never compute a signature over reserialized JSON unless the provider specifically documents that format.
- Validate timestamp or replay window where supported, secret rotation, HTTPS and maximum payload size; keep signed-event IDs in a unique table.
- Restrict webhook endpoint permissions but do not require an end-user session.
- A successful repeated event should be idempotently acknowledged without creating duplicate suppressions or counts.
- If verification or valid event normalization fails: reject with appropriate error, log redacted metadata, create an incident if needed.
- If durable storage/queue is unavailable: return a retryable error according to provider semantics; monitor replay.
- For SNS or similar intermediaries, confirm allowed topic/source and signature; never auto-confirm arbitrary subscription URLs.

**Provider-independent pseudocode:**

```php
public function webhook(ServerRequestInterface $request): ResponseInterface
{
    $raw = (string) $request->getBody();
    if (strlen($raw) > $this->maxBodyBytes) {
        return $this->response(413);
    }
    $provider = $this->providerRegistry->forVerifiedRoute($request);
    $verified = $provider->verifyWebhook($raw, $request->getHeaders());
    if (!$verified->ok) {
        $this->securityLog->recordInvalidWebhook($provider->name());
        return $this->response(401);
    }

    // In one transaction: insert receipt with UNIQUE event key and durable outbox row.
    // Duplicate insertion is a no-op; no duplicate metrics or consent changes.
    $this->receiptService->persistVerifiedRawOrMinimalEvent($provider, $raw);
    return $this->response(204);
}
```

**Not shown:** provider-specific replay checks, transaction handling, safe parsing, PII encryption/redaction, callback request throttling, trusted clock handling and data retention. Implement those explicitly.

## 12.3 Canonical event vocabulary

| Event | Meaning | Effects |
|---|---|---|
| `provider_accepted` | Provider queued/accepted request | Record message ID and request outcome |
| `delivery_confirmed` | Provider reports destination server delivery | Update delivery metric, **not** inbox placement |
| `delivery_deferred` | Temporary delivery issue | Record reason, schedule only provider-supported reconciliation |
| `hard_bounce` | Permanent address failure | Suppress address/brand per policy before next send |
| `soft_bounce` | Temporary address failure | Increment history; bounded retry/suppression thresholds |
| `complaint` | User indicated spam/unwanted mail to provider | Immediate marketing suppression and reputation alert |
| `unsubscribe` | Contact withdrew permission | Consent/suppression update, stop journeys |
| `click` | Tracking link clicked when enabled/permitted | Optional engagement record and privacy controls |
| `open` | Tracking pixel triggered when enabled/permitted | Unreliable measurement; label as approximate |
| `rejection` | Provider rejected message request or attempted delivery | Reason category + operational alert |

**Event ordering:** an “accepted” event that arrives after “hard_bounce” does not restore deliverable status. Retain raw immutable history plus a derived state reducer. Handle events unmatched to local message IDs via reconciliation queue, not silent discard.

## 12.4 Analytics definitions (show denominator, window and source)

| Measure | Formula / definition | Important caveat |
|---|---|---|
| Eligible audience | Distinct contacts passing inclusion, exclusion, current consent and suppression rules | Snapshot and send-time values differ |
| Queued | Recipient jobs admitted for processing | Not sent |
| Submitted | Actual attempted provider API calls | Not all accepted |
| Accepted | Message accepted for processing by provider | Not delivered |
| Delivery reported | Provider reports recipient server delivery | Not inbox placement |
| Hard bounce rate | Unique hard-bounced recipients ÷ agreed denominator | State the denominator explicitly |
| Complaint rate | Provider reported complaints ÷ accepted/delivered denominator as defined | Different from Gmail Postmaster rate |
| Unsubscribe rate | Unique opt-outs attributed to campaign ÷ agreed sent/accepted denominator | Can include delayed requests |
| Unique click rate | Distinct tracked recipients clicking ÷ selected denominator | Only if tracking allowed/enabled |
| Open rate | Tracked pixel events ÷ selected denominator | Distorted by proxy prefetch/privacy features |
| Conversion | Unique business event ID associated with campaign under attribution rules | Requires explicit event ingestion |

**Reconciliation:** aggregate from canonical event records with idempotent processors; make projections rebuildable. Surface “data delayed/unavailable” when provider/Postmaster telemetry is absent. Keep test sends out of production KPIs. Track “last refresh” and the exact observation window.

## 12.5 Analytics dashboard UX

- Campaign overview: target count, excluded count by reason, queued/accepted/delivered reported, bounce, complaint, unsubscribe, click and conversion metrics.
- Breakdown: sender identity, audience segment, ESP, recipient domain and timeframe, respecting minimum cohort size.
- Performance charts: day/hour, cohorts, A/B variants (P1), campaign links and UTM values.
- Delivery health panel: provider latency, failure rate, webhook backlog, quota utilization and DNS checks.
- Data quality: consent coverage, invalid addresses, import errors, suppression reasons, duplicates prevented.
- Export: aggregated by default, filtered PII only by permitted role, export audit event recorded.

---

# 13. API contracts and integration endpoints

## 13.1 API conventions

Prefix ` /api/v1/marketing` (drop the leading space in implementation). JSON UTF-8, TLS-only, existing session authentication or appropriately scoped service bearer tokens. Use one error shape; all data is authorized and brand-scoped server-side.

**Response shape:**

```json
{
  "data": {"id": 814, "state": "draft"},
  "meta": {"request_id": "req_abc123", "version": "v1"}
}
```

**Failure shape:**

```json
{
  "error": {
    "code": "CAMPAIGN_NOT_APPROVED",
    "message": "The current campaign revision must be approved before sending.",
    "fields": ["approved_snapshot_hash"]
  },
  "meta": {"request_id": "req_abc123"}
}
```

**HTTP conventions:** `201` create, `200` read/update, `202` asynchronous accepted, `204` no content, `400` schema, `401` unauthenticated, `403` permission, `404` not found/unauthorized scoped object as policy demands, `409` stale revision/invalid transition, `422` business rule, `429` rate limit, `503` provider/queue degraded. All user-facing 4xx responses should be actionable without exposing private contact data.

## 13.2 Endpoint catalog (proposed, adjust for chosen sidecar)

| Method | Endpoint | Purpose | Permission / invariant |
|---|---|---|---|
| `GET` | `/brands` | Authorized brands | `marketing.view` |
| `GET/POST` | `/sending-identities` | Manage verified identities | `marketing.domain.admin` |
| `POST` | `/sending-identities/{id}/verify` | Refresh DNS/provider check | Admin; no fake verified flag |
| `GET/POST` | `/contacts` | Search/create marketing contacts | Scoped view/create |
| `GET/PATCH` | `/contacts/{id}` | Profile; permitted edits | Scoped access and provenance |
| `GET` | `/contacts/{id}/timeline` | Consent/send/event history | PII access control |
| `POST` | `/contacts/imports` | Upload/stage CSV | Import permission, safe storage |
| `GET` | `/contacts/imports/{id}` | Status and row errors | Import permission, scoped |
| `POST` | `/contacts/imports/{id}/commit` | Commit validated rows | Consent evidence required |
| `GET/POST` | `/lists` | Static groups | Scoped list access |
| `POST/DELETE` | `/lists/{id}/members` | Add/remove membership | Never overwrite opt-out |
| `GET/POST` | `/segments` | Safe segment definitions | Allowlisted segment compiler |
| `POST` | `/segments/{id}/preview` | Count and sample | Consent filtered, PII min |
| `GET/POST` | `/templates` | Gallery/create logical template | Brand permission |
| `POST` | `/templates/{id}/revisions` | New draft revision | Do not mutate approved revision |
| `GET` | `/templates/{id}/revisions/{rev}` | Version/preview | Brand permission |
| `POST` | `/templates/{id}/preflight` | Template-only checks | Policy version and content hash |
| `POST` | `/templates/{id}/test-send` | Seed send | Allowlist, rate limits |
| `POST` | `/templates/{id}/submit-review` | Approval workflow | Maker-checker |
| `POST` | `/templates/{id}/approve` | Approve immutable revision | Approver distinct where configured |
| `GET/POST` | `/campaigns` | Search/create | Brand permission |
| `GET/PATCH` | `/campaigns/{id}` | Draft details/audience | ETag/If-Match revision guard |
| `POST` | `/campaigns/{id}/audience-preview` | Eligible/dedupe counts | Current suppression view |
| `POST` | `/campaigns/{id}/preflight` | Full preflight | Persisted run/evidence |
| `POST` | `/campaigns/{id}/submit-review` | Review snapshot | Preflight fresh |
| `POST` | `/campaigns/{id}/approve` | Approval | Maker-checker and content hash |
| `POST` | `/campaigns/{id}/schedule` | Schedule approved send | RBAC + valid snapshot |
| `POST` | `/campaigns/{id}/send-now` | Start approved send | Rate and quota controls |
| `POST` | `/campaigns/{id}/pause` | Stop new provider calls | Stop/lease behavior |
| `POST` | `/campaigns/{id}/resume` | Resume after revalidation | Consent/policy recheck |
| `POST` | `/campaigns/{id}/cancel` | Halt future attempts | Already accepted cannot be undone |
| `GET` | `/campaigns/{id}/deliveries` | Recipient send states | Scoped data access |
| `GET` | `/campaigns/{id}/reports` | Analytics and quality | Metric definitions |
| `GET` | `/alerts` | Filtered alert inbox | Scoped alerts |
| `POST` | `/alerts/{id}/acknowledge` | Record acknowledgement | Cannot clear block |
| `GET` | `/health/senders` | Domain and provider health | Admin/report role |
| `POST` | `/public/unsubscribe/one-click/{token}` | RFC 8058 unsubscribe | No logged-in session required |
| `GET` | `/public/preferences/{token}` | Permission UI | Token and rate limit |
| `POST` | `/public/preferences/{token}` | Save preferences | Verify changes per policy |
| `POST` | `/webhooks/{provider}` | Authenticated ESP events | Provider signature only |
| `POST` | `/integrations/social-leads` | Social lead import (P1) | Service auth; consent mapping |

API `POST` actions that cause a campaign state change accept an `Idempotency-Key` header and a required expected revision ID or HTTP `If-Match` where applicable. Retain idempotency outcomes for a documented replay window. For webhooks, provider event IDs govern deduplication independently.

## 13.3 Example campaign creation

```http
POST /api/v1/marketing/campaigns
Content-Type: application/json
Idempotency-Key: create-webinar-invite-20261109
```

```json
{
  "brand_id": 2,
  "name": "November Startup Webinar - Invitation",
  "purpose": "webinar_marketing",
  "communication_intent": "promotional",
  "sender_identity_id": 9,
  "template_revision_id": 147,
  "audience": {
    "include_lists": [12, 13],
    "include_segments": [5],
    "exclude_lists": [99],
    "exclude_contact_ids": []
  },
  "tracking": {"utm_source": "email", "utm_medium": "campaign", "clicks_enabled": false}
}
```

**Expected:** draft campaign is persisted, does not send, and returns server revision + permissioned URL. `brand_id` is checked against user authorization rather than trusted.

## 13.4 OpenAPI and contract tests

- Maintain `docs/marketing/api/openapi.yaml`, examples and error code dictionary in Git.
- Add schemas for allowable `communication_intent`, `campaign.state`, template revision, recipient state and webhook normalized event.
- Generate/validate route contracts in CI and fail breaking changes without `/v2` or migration plan.
- Test requested and server-computed `organization_id`/`brand_id` mismatch.
- For sidecar Mautic/phpList, map facade routes to upstream APIs behind an explicit compatibility layer; never let browser clients call sidecar admin API credentials directly.

---

# 14. Social-media integration and journeys (P1)

## 14.1 Existing social module → marketing events

Proposed event schema:

```json
{
  "event_id": "social-lead-tenant2-9c2e",
  "event_type": "social.lead.received",
  "occurred_at": "2026-11-09T12:30:00Z",
  "organization_id": 1,
  "brand_id": 2,
  "social_campaign_id": "SOC-2026-441",
  "source": "approved_lead_form",
  "lead": {"email": "sample@example.org", "first_name": "Sample"},
  "permission": {
    "status": "pending_verification",
    "purpose": "webinar_marketing",
    "notice_version": "webinar-v2",
    "evidence_ref": "secure://evidence/opaque-id"
  }
}
```

**Business rules:**

1. Accept only authenticated service-to-service events with stable IDs and source validation.
2. Validate original lead-form notice and consent scope; otherwise keep `pending`/non-marketable.
3. Deduplicate contact without overriding opt-out or complaints.
4. Map source campaign/post/ad IDs to UTM metadata and contact history.
5. Emit `marketing.contact.permission_eligible` only if current permission requirements are truly met.
6. Preserve original source evidence, but avoid copying raw lead forms or unnecessary personal data into analytics.
7. Sync revocations from contact preferences back to integrated systems when technically and legally appropriate.

## 14.2 Journey engine minimum design

**Nodes:** trigger → filter/decision → delay → send approved template → wait for permitted event → stop. Each journey run owns `run_id`, contact, brand, entered_at, current_node, status, next_due_at, event correlation and reason.

**Guardrails:**

- Do not enroll a contact without valid purpose/permission at entry *and* at each outbound step.
- Unsubscribe or complaint exits all relevant marketing journeys immediately.
- Trigger event IDs are unique; repeated webhook does not duplicate enrollment.
- Delay worker honors pause, send caps and expired event dates.
- Journey templates are approved revisions, not live editable drafts.
- Prevent loops, endless retries, circular graphs and unbounded automation frequency.
- Track attribution separately from consent; a click does not automatically opt a recipient into other purposes.

**Example webinar flow:**

```text
Permissioned registration
     ↓
Operational confirmation (separate permitted route)
     ↓ [only when marketing permission exists]
Marketing webinar education email
     ↓ wait 2 days
If unsubscribed/complaint → EXIT
Else if event still future → reminder (classify accurately)
     ↓
Event completed → survey / follow-up only if permitted
```

---

# 15. Deliverability setup: domains, headers and provider operations

## 15.1 Configure authenticated marketing sender

Use provider-managed sending identity and **a separately configured, recognizable marketing subdomain** where appropriate, e.g. `mail.brand.example` (illustrative). Subdomains are not a workaround to evade spam classification; sender reputation can be considered across related domains. Maintain a clear legitimate From name, reply-to and public privacy/identity details.

Admin checklist:

- [ ] Verify sending domain through ESP.
- [ ] Publish SPF per provider guidance; avoid multiple SPF TXT records at same hostname.
- [ ] Configure DKIM selectors/keys through provider; verify signed test message.
- [ ] Configure DMARC (`p=none` is the documented minimum in several bulk-sender rules; plan stricter policy as domain security matures) and observe aggregate reports if possible.
- [ ] Verify organizational From alignment with at least SPF or DKIM as required; strongly prefer robust DKIM alignment.
- [ ] Confirm forward/reverse DNS, RFC-valid headers and TLS as applicable and provider managed.
- [ ] Configure working `List-Unsubscribe` and `List-Unsubscribe-Post`, signed as required.
- [ ] Add visible opt-out/preference link to marketing footer.
- [ ] Configure HTTPS click/asset domains if tracking is enabled; avoid misleading redirects.
- [ ] Register webhook endpoints with full verification and appropriate rotation.
- [ ] Validate first test send headers in controlled Gmail/Outlook/Yahoo inboxes.
- [ ] Record evidence `verified_at`, provider account ID, DNS lookup result and policy version; schedule periodic rechecks.

**Important provider expectations (verify live before launch):** Google sender rules distinguish all senders from higher-volume senders (around 5,000/day to personal Gmail); bulk senders need SPF+DKIM, DMARC and one-click unsubscribe on marketing/subscribed mail, among other requirements. Google started stronger enforcement in November 2025. Yahoo also requires bulk sender authentication, low complaint rates and easy unsubscribe; Microsoft applies SPF/DKIM/DMARC requirements to high-volume Outlook.com consumer sending. See source URLs in §23.

## 15.2 RFC 8058 implementation checklist

- [ ] Both documented unsubscribe headers are generated on every applicable outbound message.
- [ ] ESP preserves and DKIM signs the required headers; inspect actual received message headers.
- [ ] Opaque URL uniquely resolves recipient and list/purpose scope.
- [ ] `POST` endpoint handles `List-Unsubscribe=One-Click` without cookies/auth/interstitial.
- [ ] `GET` does not silently unsubscribe from link scanner visits.
- [ ] Repeated POST is idempotent.
- [ ] Revocation propagates to queues and journeys before future sends.
- [ ] Header remains available even if recipient unsubscribes weeks later, according to token policy.
- [ ] Host, path, redirects and token confidentiality tested.

## 15.3 Email service selection checklist

| Capability | Minimum evidence from shortlisted ESP |
|---|---|
| Authenticated sender identity | SPF/DKIM/DMARC-compatible documentation and verification |
| Suppression/complaint data | Hard bounce and complaint callbacks, clear event definitions |
| One-click unsubscribe support | Ability to specify/retain headers and DKIM-sign appropriately |
| Throttling/limits | Quotas/API response semantics and per-second limits |
| Retry/idempotency | Documented semantics for API timeout and request IDs |
| Data protection | Processing region, DPA, retention and contractual terms |
| API/security | Scoped keys, TLS, signed webhooks, rotation, sandbox |
| Pricing | Per-email, feature, dedicated IP, data transfer and event retention |
| Reporting | Delivered vs accepted vs deferred vs complaint granularity |
| Support | Incident procedures and monitoring/quotas |

**Candidate evaluation:** Amazon SES (custom build and flexible event publishing), Postmark/Mailgun/SendGrid/Brevo or a provider already contracted to your organization. Do not assume all providers expose the same features or event semantics. For SES, use configuration sets and event destinations per AWS documentation; protect and verify the chosen SNS/event ingestion path.

## 15.4 Quality practices that help, but cannot guarantee a tab

- Send only to contacts who expect the message; honor opt-outs promptly.
- Use truthful subjects, recognizable sender identity, relevant content and accurate links.
- Keep audience acquisition evidence and remove invalid addresses.
- Schedule consistent, controlled sends based on provider quotas and real engagement, without arbitrary “warm-up” manipulation.
- Do not use deceptive `Re:`/`Fwd:` subjects, fake personal conversation, link cloaking, misleading recipients or hidden text.
- Make email mobile-friendly, accessible and understandable with images disabled.
- Run controlled seed tests, inspect actual authentication results and use domain reputation metrics when available.
- Record placement tests as **observations from sample accounts**, never universal assertions.

---

# 16. Security, privacy and compliance execution

## 16.1 Threat model to complete in Sprint 0/1

| Threat | Typical path | Controls to implement |
|---|---|---|
| Cross-domain/brand data exposure | Change IDs in URL | Scoped queries + authorization at all layers |
| Exposure of employee payroll data | Reusing HR database query in marketing | Separate data access, minimal explicit join allowlist |
| XSS / active HTML | Template editor, previews, custom HTML | Strict HTML schema, sanitized compile, isolated preview CSP/sandbox |
| SSRF | Server checks attacker-controlled image/CTA links | Block private/loopback/link-local/cloud-metadata networks, verify redirects and DNS changes, timeouts, allowlist schemes |
| SQL injection | Custom segment rules | Typed allowlisted fields/operators, bound SQL parameters |
| Webhook forgery | Unsigned fake bounce/complaint | Provider-specific signature and source validation |
| Webhook replay | Same valid event sent repeatedly | Unique event IDs and idempotent reducer |
| Duplicate email | Worker crash/ambiguous provider response | Stable attempt ID, reconciliation, manual unknown-outcome queue |
| Consent bypass | User imports a suppressed address | Immutable history; fail-closed eligibility at dispatch |
| Credential leak | ESP key in repo/log | Secret store, least privilege, rotation and redaction |
| CSV injection | Exported name begins with `=` | Safe CSV export escaping |
| Unapproved send | UI-only permission guard | Server-side RBAC + immutable approval hash |
| Tracking overcollection | Open pixels, unnecessary link profile | Privacy defaults, minimal data, lawful basis and opt-out where appropriate |
| Vendor outage | ESP API unavailable, webhook backlog | Circuit breaker, queue visibility, retry/reconciliation, kill switch |
| Unauthorized mass sends | Stolen staff session | MFA where possible, unusual send limits, two-person approval |

**SSRF detail:** A template link-health checker is especially risky if it follows links. Prefer a secure separate network worker with public-address resolution policy, no internal DNS/IP ranges, strict redirect count, timeouts, download limits and independent egress controls. Never allow the checker to access private intranet/metadata services.

## 16.2 Data handling and retention

- Define specific fields to collect (name, email, consent evidence, source, locale/timezone); minimize optional profiling.
- Encrypt backups and sensitive at-rest fields where suitable; require TLS in transit.
- Restrict raw provider events, CSV imports, unsubscribe tokens and contact exports to roles/service accounts.
- Choose retention for raw webhook payloads, staged CSV, detailed click/open events, complaint evidence, audit logs and aggregate reporting independently. Record actual retention values and deletion jobs in a policy file, not in ad-hoc code.
- A deletion request must coordinate data you control and upstream sidecar/ESP data, subject to legitimate legal/audit retention requirements.
- If compliance evidence must be retained, store the least necessary proof in restricted records and anonymize analytics where possible.
- Every data subject export/deletion must have identity verification, scope, audit, status and error handling.
- Do not copy production recipients to test environments.

## 16.3 Indian DPDP and other jurisdiction checks

The **Digital Personal Data Protection Act, 2023** and **DPDP Rules, 2025** create duties involving notice, purpose, consent and data subject rights, with phased commencement. Your counsel must verify which provisions are operational at your launch date, whether your organization is covered, and any applicable exemptions/retention duties; **this guide is not legal advice**. Also review rules relevant to international recipients (e.g., GDPR, US CAN-SPAM, local e-marketing law) and contractual processor duties.

Deliverable `docs/marketing/privacy/legal-mapping.md`:

```text
Jurisdiction → audience source → lawful basis/permission evidence →
notice version → allowed purposes → unsubscribe behavior →
retention → export/erasure procedure → owner → effective date.
```

**Critical separation:** HR employee information and payroll records were collected for employment purposes; those records must not become a marketing mailing list without a separate, lawful basis and approved workflow.

## 16.4 Application security controls

- Composer dependency audit and lock file in source control; monitor CVEs.
- Static analysis, secure coding review, request validation and output escaping.
- Secret scanning; no provider credentials in frontend HTML, logs or example `.env` files.
- HTTPS + HSTS, strong session cookies, CSRF for authenticated forms, CSP for app and preview.
- Object-level authorization tests for all endpoints.
- Signed download links/authorization for exports, with short expiries.
- PII-sensitive fields redacted or hashed in observability; keep raw evidence under appropriate access controls.
- Rate limits on public preferences/unsubscribe tokens; do not impede legitimate one-click unsubscribes.
- Backup encryption, access review, tested restoration and incident procedure.

---

# 17. Test plan: layers, fixtures and acceptance scenarios

## 17.1 Test pyramid

| Test class | What to test | Owner |
|---|---|---|
| Unit | Email normalization, consent projection, segment compiler, rules, state transitions, token formatting | Backend |
| Integration | Migrations, queue, outbox, provider adapter stubs, webhooks, approvals, permissions | Backend/QA |
| Contract | REST OpenAPI schemas, provider events and external Mautic/phpList facade | Backend |
| Security | IDOR, XSS, SSRF, import misuse, webhook signature/replay, bypassed approval | Security/QA |
| E2E | Wizard → audience → template → preflight → approval → test → live controlled send | QA |
| Rendering | Gmail/Outlook/mobile and images-off for starter templates | Marketing/QA |
| Load | 100k synthetic contacts / 100k synthetic message plans, queue/backlog and reporting | DevOps/QA |
| Recovery | Worker kill, provider outage, DB restore, webhook replay, rollback | DevOps |

## 17.2 Deterministic fixture dataset

Create **synthetic** records:

- 1,000 contacts, of which 100 belong to two overlapping groups.
- 100 contacts lacking permission; 25 revoked; 10 complaints; 10 hard bounces.
- 10 contacts with special characters/Unicode names/HTML injection strings.
- 10 contacts with two brand contexts and differing permission.
- 5 templates: valid, missing unsubscribe, required merge tag missing, unsafe HTML, misleading intent.
- 3 providers scenarios: immediate acceptance, clear temporary rejection, ambiguous timeout.
- 5 webhooks: duplicate, out of order, invalid signature, unknown message, valid complaint.
- 1 scheduler DST edge case and 1 timezone `Asia/Kolkata` case.

## 17.3 Essential UAT suite (maps to BRD/SRS 22 cases; extend with detailed checks)

| UAT ID | Scenario | Acceptance result |
|---|---|---|
| UAT-01 | HR-only staff calls marketing endpoints | 403; no contact/aggregate disclosure |
| UAT-02 | Same address in two/three lists | Exactly one initial send row for campaign |
| UAT-03 | CSV includes invalid/unpermissioned contact | Quarantined/non-marketable with row error |
| UAT-04 | Imported contact previously unsubscribed | No marketing send; history retained |
| UAT-05 | Revoke after schedule, before dispatch | Worker blocks provider request |
| UAT-06 | Invalid or unverified From domain | Preflight BLOCK |
| UAT-07 | Required SPF/DKIM/DMARC condition fails | BLOCK and remediation shown |
| UAT-08 | Missing visible unsubscribe/footer | BLOCK |
| UAT-09 | Invalid one-click header configuration | BLOCK and header audit evidence |
| UAT-10 | Template contains script/event handler | Rejected/sanitized; unsafe content never dispatched |
| UAT-11 | Required variable missing | BLOCK; exact token and location shown |
| UAT-12 | Personal-style intent selected for overt promo | Classification warning/high review; no promise of Primary |
| UAT-13 | Approved HTML/subject changes | Approval invalid; must re-run review |
| UAT-14 | Unauthorized approval/send | Blocked server side and audited |
| UAT-15 | Test send to allowlisted seed | Test-only event, no production KPI impact |
| UAT-16 | Provider returns 429 | Bounded retry/backoff, rate alert, no uncontrolled repeats |
| UAT-17 | Worker crashes at handoff boundary | Reconcile unknown outcome; no blind retry |
| UAT-18 | Duplicate webhook event arrives | One canonical business effect/count |
| UAT-19 | Complaint event arrives while campaign running | Future sends to contact suppressed |
| UAT-20 | Pause while 10k recipients queued | New provider calls cease after in-flight boundary |
| UAT-21 | Analytics provider accepted vs delivered | Correct distinct states/denominators |
| UAT-22 | Foreign-brand ID access attempted | Scoped 404/403, no PII leak |
| UAT-23 | One-click unsubscribe POST repeated | Works idempotently without login |
| UAT-24 | GET by mail-security scanner | No unsubscribe side effect |
| UAT-25 | Replay of signed but duplicate event | No duplicate status/counter |
| UAT-26 | Header DKIM verification in controlled inbox | Valid expected authentication evidence |
| UAT-27 | Link checker targets `127.0.0.1`/metadata IP | Blocked/no network access |
| UAT-28 | Source CSV has formula-injection cell | Safe in export; no executable formula |
| UAT-29 | Bulk template library stress test | Common UI/API response within agreed p95 |
| UAT-30 | Staging data copied into production job accidentally | Production send allowlist/feature gate prevents accidental blast |
| UAT-31 | DNS evidence expires/change | Recheck or policy-defined send block |
| UAT-32 | Job in DLQ is retried | Stable event IDs and safe reconciliation maintained |

**Release evidence:** attach test-run link/screenshots/log IDs to each UAT; no `P0` may be open at production launch. Where inbox placement is observed, record mailbox/domain/date; no pass criterion may promise Primary tab.

## 17.4 Example PHPUnit-style tests (adapt fixtures)

```php
public function test_unsubscribe_after_enqueue_blocks_send(): void
{
    $contact = $this->fixtures->permissionedContact();
    $campaign = $this->fixtures->approvedCampaignFor($contact);
    $recipient = $this->planner->enqueue($campaign, $contact);

    $this->consentService->revokeEmailMarketing($contact->id, $campaign->brandId);
    $this->dispatchWorker->handle($recipient->id);

    self::assertSame('suppressed', $this->recipientRepo->get($recipient->id)->state);
    self::assertSame(0, $this->fakeProvider->sendCount());
}

public function test_approval_becomes_stale_after_template_change(): void
{
    $campaign = $this->fixtures->approvedCampaign();
    $this->templates->createNewDraftRevision($campaign->templateId, '<p>Updated</p>');
    // If only a *separate* draft is created, current approved campaign can remain
    // locked to the original approved revision. Changing the bound campaign revision
    // or protected sender/body fields MUST invalidate its campaign approval.
    $this->campaigns->bindTemplateRevision($campaign->id, $this->templates->latestDraftId());
    self::assertFalse($this->campaigns->isSendAuthorized($campaign->id));
}
```

**Test subtlety:** creating a separate unbound draft does not silently alter an already approved frozen campaign. The invalidation rule applies when the campaign's **bound content/sender/audience/schedule** or required policy evidence changes.

## 17.5 Performance and fault injection

- Concurrent sender jobs with same recipient ID and worker restarts; test leasing and unique constraints.
- Campaign audience preview under 100k synthetic contacts; record query plan.
- Events arriving for unrecognized provider IDs; later reconciliation.
- Provider returns varying 400/401/403/429/500/503 and network timeouts; map permanent vs retryable vs ambiguous accurately.
- Webhook queue grows while ESP remains healthy; alert, recover and replay without duplicate counts.
- Mid-import failure halfway through CSV; clean resume and summary.
- Sidecar API unavailable during sync; retries cannot override consent revocation.
- Campaign paused exactly as dispatch lease expires; no new send requests start after confirmed pause barrier.
- Database writes fail after provider accepted: explicit unknown-outcome/reconciliation processing.

---

# 18. Delivery plan: workstreams, sprints, evidence and exit criteria

> Estimates below are **indicative planning ranges**, not promised delivery dates. Actual effort depends heavily on existing PHP stack, team, provider choice, reuse selection and quality of contact data. Sprint order is more important than nominal duration. Parallelize UI and backend only after stable contracts exist.

## Sprint 0 — Discovery + proof of concept (1–2 weeks)

| Task | Owner | Deliverable | Done when |
|---|---|---|---|
| Review existing application | Tech Lead | Repository/deployment map | Auth, DB, framework, jobs identified |
| Choose platform A/B/C | Tech Lead + PO | ADR-001 / scorecard | Sandbox campaign demo in each shortlisted approach |
| Choose ESP | DevOps + PO | Provider capability matrix | Event, quota, security, DPA verified |
| Legal/consent workshop | Security/Legal | Permission scope and lawful-basis matrix | Consent rules signed off |
| UI navigation prototype | UX/Product | Wireframes for 8 core screens | Users validate workflow |
| Prepare staging | DevOps | Isolated environment, fake provider | No production sends possible |

**Exit gate:** architecture, domain ownership, provider and privacy decisions documented.

## Sprint 1 — Marketing domain, identities and security (2–3 weeks)

- [ ] Add feature flag, route prefix, navigation, permission matrix.
- [ ] Create migration package for brands/sender identities/domains/audit/contacts.
- [ ] Configure test domain, provider sandbox, TLS/secret storage.
- [ ] Enforce brand organization scoping and `403`/`404` isolation.
- [ ] Implement immutable audit event writer and correlation IDs.
- [ ] Build unit/integration CI pipeline and test fixtures.

**Deliverable:** restricted module skeleton, secure domain identity and foundation schema.  
**Exit:** HR-only user cannot access marketing; no sending capability exposed yet.

## Sprint 2 — Contacts, lists and consent (2–3 weeks)

- [ ] Contact CRUD and canonicalization policy.
- [ ] Static lists, tags and unique membership enforcement.
- [ ] Staged CSV importer, source evidence, preview/commit/error exports.
- [ ] Append-only consent ledger, current-state lookup, opt-out/suppression model.
- [ ] Contact detail timeline and segmented search.
- [ ] Preference center and tokenized unsubscribe endpoints.
- [ ] Unit/UAT for dedup, revoked consent and cross-brand access.

**Deliverable:** a compliant audience repository ready to be targeted.  
**Exit:** 100k synthetic contacts import; suppressed records never marked eligible.

## Sprint 3 — Template Studio (2–3 weeks)

- [ ] Integrate chosen editor/preset and approved starter templates.
- [ ] Build safe compiler, token resolver, plain-text variant and preview engine.
- [ ] Implement template gallery, versioning, drafts, compare and protected footer.
- [ ] Add subject/preheader/intent selection and controlled seed test sends.
- [ ] Test mobile/images-off/email client rendering and accessibility.

**Deliverable:** 10 tested templates with versioned content.  
**Exit:** unsafe HTML rejected; required merge tags and unsubscribe footer enforced.

## Sprint 4 — Preflight & approvals (2–3 weeks)

- [ ] Domain/authentication evidence checks and refresh schedule.
- [ ] Implement initial rule registry D-001–D-014 and alert severity/actions.
- [ ] Build actionable alert center and re-evaluation triggers.
- [ ] Freeze template and campaign content hashes; approval routing.
- [ ] Test alert acknowledgment vs resolution and approval invalidation.

**Deliverable:** reviewable campaigns and transparent preflight status.  
**Exit:** mandatory configuration/permission failures block launch.

## Sprint 5 — Campaigns & delivery (3–4 weeks)

- [ ] Campaign wizard and saved include/exclude audience specification.
- [ ] Eligibility planner, batch recipient rows and unique constraints.
- [ ] Durable outbox, scheduler, send leases, provider adapter.
- [ ] Throttles, retries, unknown-outcome handling, pause/resume/cancel.
- [ ] Signed webhook receiver and event normalization.
- [ ] End-to-end sandbox campaign with consent revocation during send.

**Deliverable:** production-capable controlled send path behind feature flag.  
**Exit:** crash/replay tests and ambiguous-timeout reconciliation pass.

## Sprint 6 — Reporting, reliability & pilot (2–3 weeks)

- [ ] Reporting projections and explicit metric labels.
- [ ] Dashboard charts, exports, per-campaign health.
- [ ] Operational alarms, DLQ replay, backup/restore rehearsal, runbooks.
- [ ] UAT-01–32 with security and load testing.
- [ ] Pilot on a small permissioned audience and verify received headers, opt-outs and provider feedback.
- [ ] Go/no-go checklist and rollback sign-off.

**Deliverable:** approved production pilot and operational handover.  
**Exit:** no critical defects, all P0 controls validated, operations owner named.

## P1 program — Automations and advanced marketing (after stable MVP)

1. Social lead integration and attribution.
2. Event-based journeys with consent/exit checks.
3. Dynamic segmentation UI improvements and frequency/quiet hours.
4. A/B testing and conversion reporting.
5. Advanced reputation trends and cohort anomaly analysis.

**Resource suggestion (subject to sizing):** product/BA 0.5–1 FTE, PHP backend 2, frontend 1, QA 1, DevOps/security shared, UX shared, marketing operations owner, compliance/legal reviewer. Sidecar option may change effort allocation toward integration/operations.

---

# 19. Developer ticket backlog (copy into Jira / GitHub Issues)

Use task IDs and write acceptance criteria before implementation. Every ticket should include `owner`, `estimate`, `dependencies`, `unit/API tests`, `security considerations` and a link to the matching FR/UAT.

| Epic | Ticket IDs / deliverables | Depends on |
|---|---|---|
| E-01 Foundation | EM-001 feature flag; EM-002 brand RBAC; EM-003 marketing schema; EM-004 audit; EM-005 CI/test fixture | ADR-001 |
| E-02 Sender setup | EM-006 identity management; EM-007 DNS verifier; EM-008 ESP sandbox; EM-009 keys and rotation | E-01 |
| E-03 Audience | EM-010 contact model; EM-011 import staging; EM-012 dedup; EM-013 lists; EM-014 tags; EM-015 segment rules | E-01 |
| E-04 Privacy | EM-016 consent events; EM-017 suppression; EM-018 preferences; EM-019 one-click; EM-020 erasure/export workflow | E-03 |
| E-05 Templates | EM-021 editor embed; EM-022 starter gallery; EM-023 compiler; EM-024 tokens; EM-025 versioning; EM-026 preview | E-01 |
| E-06 Deliverability | EM-027 rule engine; EM-028 DNS rules; EM-029 content rules; EM-030 alerts; EM-031 seed tests; EM-032 policy versioning | E-02/E-04/E-05 |
| E-07 Campaign lifecycle | EM-033 wizard; EM-034 audience preview; EM-035 approval hash; EM-036 schedule; EM-037 control endpoints | E-04/E-06 |
| E-08 Delivery reliability | EM-038 recipient planner; EM-039 outbox; EM-040 worker leases; EM-041 ESP adapter; EM-042 retries; EM-043 unknown outcomes | E-07 |
| E-09 Feedback | EM-044 webhook verifier; EM-045 event dedup; EM-046 suppression updates; EM-047 event reconciler | E-08 |
| E-10 Reporting | EM-048 metrics; EM-049 dashboard; EM-050 exports; EM-051 monitoring/alerts | E-09 |
| E-11 Launch | EM-052 security test; EM-053 fault test; EM-054 load test; EM-055 pilot; EM-056 runbook/rollback | All P0 |
| E-12 P1 automation | EM-057 social sync; EM-058 journeys; EM-059 A/B; EM-060 conversion events | MVP stable |

### Sample implementation ticket

**EM-019 — RFC 8058 one-click unsubscribe**

- **User story:** As a recipient of a subscribed marketing email, I can unsubscribe with one click so no more marketing email is sent for the selected subscription scope.
- **Preconditions:** verified sender, consent ledger, suppression writer, public host and provider supporting correct DKIM-signable headers.
- **Implementation:** generate opaque per-message scope token; emit and DKIM sign required headers; create POST handler; append revoke/suppression event; invalidate pending dispatch; write audit; rate-limit abuse without blocking legitimate requests.
- **Acceptance:** GET no side effect; POST body processed without login; repeated POST idempotent; next worker send blocked; sender inbox sample shows header and valid DKIM signature; no token in logs.
- **Tests:** UAT-05, 09, 23, 24, 26 + webhook concurrency.
- **Dependencies:** EM-016/017, EM-008, EM-023, EM-041.

### Definition of Ready (DoR)

- [ ] Requirement and affected roles identified.
- [ ] API/DB contracts agreed; dependencies resolved.
- [ ] Acceptance cases include abuse/error paths, not only happy path.
- [ ] Privacy/data scope and monitoring determined.
- [ ] Feature flag / rollout plan known.

### Definition of Done (DoD)

- [ ] Code reviewed, tested and merged with migrations.
- [ ] Static/security checks pass; secrets absent.
- [ ] API and user docs updated.
- [ ] Audit and metrics present for sensitive operations.
- [ ] Performance baseline or justification recorded.
- [ ] UAT evidence linked, owner signed off.

---
# 20. CI/CD, deployment and release management

## 20.1 Suggested CI pipeline

```text
PR opened
  ↓ lint/format + static analysis + dependency/license scan + secret scan
  ↓ unit tests + API schema contract tests + migration tests
  ↓ integration tests with MySQL/Redis/fake ESP + webhook replay tests
  ↓ frontend editor build + controlled template render regression tests
  ↓ security checks (including authorization and unsafe URL fixtures)
  ↓ reviewer approval → merge
  ↓ staging deploy → schema migration → smoke tests
  ↓ controlled pilot release with feature flag → monitoring → scale gate
```

**Avoid CI access to production ESP credentials.** CI sends exclusively through fake/mocked providers and synthetic test addresses.

### Suggested CI checks

```bash
# Adapt all commands to actual application and installed dependencies.
composer validate --strict
composer audit
vendor/bin/phpunit tests/Marketing
vendor/bin/phpstan analyse app/Marketing  # if configured
# Frontend build/tests only when your repository has package.json:
npm ci
npm run build
npm run test --if-present
```

Additional release checks:

- [ ] Versioned DB migrations reviewed for locking/load effects.
- [ ] Rollback/backward-compatible deployment strategy documented.
- [ ] Provider sandbox/test keys clearly separated.
- [ ] Workers will not send before consent/outbox schema is ready.
- [ ] Feature flag defaults **off** in fresh deployment.
- [ ] Background worker container/process has proper memory, concurrency and graceful shutdown.
- [ ] Public unsubscribe routes are reachable over HTTPS independently of a staff login.
- [ ] All scheduled jobs are enabled only once for their intended role.

## 20.2 Example production rollout phases

1. **Infrastructure**: provision marketing DB tables/schema, queue, storage bucket, backup and service identity.
2. **Dark deploy**: deploy API/UI with `MARKETING_SEND_ENABLED=false`; apply migrations; workers can ingest test events but cannot send real marketing mail.
3. **Domain setup**: verify configured sender identities, SPF/DKIM/DMARC, list-unsubscribe behavior and seed inbox authentication.
4. **Audience dry-run**: import a restricted pilot list with verified permissions, calculate actual eligibility and exclusion reasons.
5. **Sandbox end-to-end**: approve, schedule, dispatch through fake/sandbox ESP and replay webhooks.
6. **Small controlled live pilot**: enable live send for **one brand, one authenticated identity and an explicitly approved small cohort**; keep hard rate/size caps.
7. **Observation**: inspect delivery rejection/complaint/unsubscribe feedback, received authentication headers and all alerts.
8. **Controlled expansion**: raise permitted volume only with named approver and documented provider quotas; monitor after each increase.
9. **General release**: document training, support, runbooks, access reviews and ongoing policy ownership.

**Do not treat a passed preflight as a replacement for a pilot.** Content and authentication can be valid while recipients still classify email as unwanted.

## 20.3 Go-live readiness checklist

### Product and approval

- [ ] All P0 requirements mapped to tests and demonstrated.
- [ ] Roles confirmed for creator, approver, sender and compliance owners.
- [ ] 10 starter templates tested and approved.
- [ ] Critical UX explains that communication intent does not determine inbox tab.
- [ ] Campaign approval expires/requires re-review when protected data changes.

### Security and legal

- [ ] Privacy, source-permission and consent model approved.
- [ ] Marketing and HR/payroll data access isolation verified.
- [ ] All marketing footer/one-click unsubscribe tests passed.
- [ ] Domain auth and actual signed headers confirmed.
- [ ] Provider DPA/processing region and event retention understood.
- [ ] No unresolved critical security vulnerability.

### Delivery and operations

- [ ] Worker concurrency, provider quota, retry, leasing and DLQ configured.
- [ ] Ambiguous external-send outcome procedure tested.
- [ ] Kill switch `MARKETING_SEND_ENABLED` works without a code deployment.
- [ ] Provider webhooks verified; replay dedupe and complaint suppression tested.
- [ ] Monitoring and escalation channels confirmed, 24/7 responsibility where needed.
- [ ] DB restore and deploy rollback rehearsed.
- [ ] Staging and production keys/addresses are isolated.

### Reporting and pilot

- [ ] Provider accepted vs delivered reported distinction clear.
- [ ] Complaint, unsubscribe and bounce rates show denominators/source.
- [ ] Pilot evidence reviewed; no pending BLOCK alerts.
- [ ] UAT passed and business sign-off recorded.

## 20.4 Rollback / feature disable strategy

**Emergency stop:**

1. Disable marketing sends globally or for affected brand/provider in server-side configuration; apply to scheduler and dispatch workers immediately.
2. Stop claiming/initiating external sends; allow in-flight attempts to reconcile, and disclose that accepted messages cannot be withdrawn.
3. Preserve all webhook receivers and unsubscribe endpoints **even while sending is disabled** so opt-outs/complaints still process.
4. Retain campaign/attempt/event audit; do not wipe records merely to clear dashboards.
5. Reconcile `unknown_outcome` before any resume.
6. Roll back application code to previous compatible build if needed. Do not blindly reverse destructive migrations with live data present; follow a tested expand/contract strategy.
7. Re-run domain, consent, policy and end-to-end tests before restoring sending.

**Rollback success:** no new outbound sends, public unsubscribe still works, webhooks still accepted, audit preserved, recovery steps and incident decision recorded.

---

# 21. Production runbooks and support operations

## 21.1 Alert triage SLA (proposed operations target)

| Incident | Suggested first response | Typical owner | Immediate containment |
|---|---|---|---|
| Invalid consent / unauthorized audience | Immediate | Compliance + Marketing | Stop affected campaign, inspect source |
| Sender authentication failure | Immediate | DevOps | Stop send on affected identity |
| Complaint spike/rejection incident | Immediate | Deliverability owner | Pause affected cohort/domain/sender |
| Webhook ingestion offline | Urgent | DevOps | Pause new large campaigns; recover feedback pipeline |
| Queue backlog increasing | Within defined operational SLA | DevOps | Check worker health, quota, locks, provider responses |
| Broken CTA/unsafe URL | Before send | Campaign owner | Block and fix template, reapprove |
| Minor accessibility warning | Prior to approved template release | Designer | Improve content/contrast/alt |
| Missing optional aggregate metrics | Next report cycle | Analyst | Label delayed/unavailable, backfill |

These are **internal proposed response classes**, not guaranteed vendor SLAs. Convert to explicit minutes/hours and on-call arrangements before production.

## 21.2 Runbook: spam or complaint spike

1. Confirm source metric (Google Postmaster vs provider), observation window and denominator.
2. Identify affected brands/domains/campaigns/recipient sources and recent changes.
3. Pause relevant promotional traffic and record incident ID/time.
4. Verify sender authentication, list-source evidence, opt-outs and complaint suppression.
5. Inspect rejected/complained cohorts without exporting unnecessary PII.
6. Correct root cause: permission/source issues, subject/copy mismatch, broken unsubscribe, reputation/volume changes, compromised account, etc.
7. Review with deliverability and compliance owners; resume in small approved cohort only after remediation and measurement plan.
8. Document timeline, counts, remediation, follow-up checks and prevention rule changes.

## 21.3 Runbook: provider returns 429 / throttle

1. Confirm configured quota, actual rate and `Retry-After` behavior if provider provides one.
2. Reduce token-bucket rate and pause affected campaigns as required.
3. Schedule retries only for definite non-accepted requests, with jitter and bounded attempts.
4. Reconcile ambiguous outcomes; do not re-enqueue entire campaign blindly.
5. If sustained, request quota review or change campaign schedule; never rotate provider identities to evade enforcement.
6. Update provider status page and owner-facing operational alert.

## 21.4 Runbook: worker crash / stuck leased recipient

1. Check crash logs, current lease TTL, database connectivity and affected IDs.
2. Identify which attempts reached `submitting`; classify as **possibly accepted** until reconciled.
3. Expire or reclaim leases only after appropriate timeout and provider reconciliation.
4. Retry only known-safe failures; otherwise mark `unknown_outcome` and escalate.
5. Verify duplicates prevented in SQL constraint + provider logs/events.
6. Fix root cause, load-test on staging, resume within configured rate limit.

## 21.5 Runbook: duplicate or suspicious webhook

1. Verify raw request signature/source according to provider's scheme.
2. Confirm stable event key and dedup row; repeated valid events get idempotent acknowledgment.
3. Invalid signatures should not update recipient, consent or suppression.
4. Check replay-window handling and clock skew; rotate compromised secret if necessary.
5. Reprocess from durable receipt or DLQ using idempotent reducer, not manual SQL counter edits.
6. Alert Security if attempted forgery or broader compromise suspected.

## 21.6 Runbook: revocation not reflected in a sidecar

1. Treat the master permission/consent system as authoritative for outbound marketing eligibility.
2. Disable affected sidecar campaign/segment or API bridge as required.
3. Repair sync jobs and verify the consumer's last processed consent event cursor.
4. Reconcile suppressed records against sidecar and provider suppression lists.
5. Replay missing revocations and validate no queued send can bypass the final eligibility gate.
6. Document incident, affected contacts, corrective actions and legal notifications if applicable.

## 21.7 Operational dashboard minimum

- Queue depth, oldest queued age, in-flight attempts, unknown outcomes, DLQ volume.
- Provider request latency, 2xx/4xx/429/5xx by identity and brand.
- Webhook receipt rate, invalid signature rate, last good event time, backlog age.
- Campaigns blocked by consent, missing header, stale hash or quota.
- Hourly accepted/complaint/bounce/unsubscribe metrics with source.
- Domain DNS/auth verification age, latest error and status.
- Scheduled campaigns due soon, past-due and paused.
- Permissioned exports and unusually high sending/approval activity.

---

# 22. Traceability matrix: business requirements → implementation → tests

This matrix intentionally tracks every BRD/SRS functional requirement; it is the master acceptance index. Enter ticket/test execution links as project work starts.

| FR ID | Feature | Implementation workstream | Primary test(s) | Phase |
|---|---|---|---|---|
| FR-001 | Shared login | E-01 auth integration | UAT-01 | P0 |
| FR-002 | Role permissions | E-01 RBAC | UAT-01, 14 | P0 |
| FR-003 | Brands/senders | E-02 identities | UAT-06, 22 | P0 |
| FR-004 | Audit | E-01 event log | Security audit tests | P0 |
| FR-005 | Approval | E-06/E-07 snapshots | UAT-13, 14 | P0 |
| FR-006 | Organization scoping | E-01 schema/authorization | UAT-22 | P1 readiness (enforce initial boundary) |
| FR-007 | Gallery | E-05 | Template E2E | P0 |
| FR-008 | Visual/HTML editor | E-05 | UAT-10 | P0 |
| FR-009 | Preview | E-05 | UAT-15 + render QA | P0 |
| FR-010 | Merge tags | E-05 | UAT-11 | P0 |
| FR-011 | Versioning | E-05 | UAT-13 | P0 |
| FR-012 | Brand kit | E-05 | Brand acceptance | P1 |
| FR-013 | Accessibility | E-05/E-06 | UAT template accessibility | P1 |
| FR-014 | Localization | E-05 | Locale fallback suite | P2 |
| FR-015 | Contacts | E-03 | Import fixtures | P0 |
| FR-016 | Lists/groups | E-03 | UAT-02 | P0 |
| FR-017 | Segments | E-03 | Segment query matrix | P0 |
| FR-018 | Consent | E-04 | UAT-03–05 | P0 |
| FR-019 | Preferences | E-04 | UAT-23, 24 | P0 |
| FR-020 | Suppression | E-04/E-08 | UAT-04, 05, 19 | P0 |
| FR-021 | CSV import | E-03 | UAT-03, 28 | P0 |
| FR-022 | Contact timeline | E-03/E-09 | Privacy/access API suite | P0 |
| FR-023 | Retention/privacy requests | E-04 | Erasure/export integration | P1 |
| FR-024 | Social/CRM sync | E-12 | Sync revocation test | P1 |
| FR-025 | Campaign wizard | E-07 | E2E wizard | P0 |
| FR-026 | Snapshot | E-07 | UAT-13 | P0 |
| FR-027 | Dedup | E-08 | UAT-02, 17 | P0 |
| FR-028 | Schedule | E-07/E-08 | DST/timezone scheduler suite | P0 |
| FR-029 | Queue/retry | E-08 | UAT-16, 17, 32 | P0 |
| FR-030 | Pause/resume/cancel | E-07/E-08 | UAT-20 | P0 |
| FR-031 | Bounce/complaint | E-09 | UAT-19 | P0 |
| FR-032 | Webhooks | E-09 | UAT-18, 25 | P0 |
| FR-033 | Separate transactional | E-01/E-08 | HR separation/security suite | P0 |
| FR-034 | A/B test | E-12 | Exclusive cohort test | P1 |
| FR-035 | Recurring | E-12 | Repeat consent recheck | P1 |
| FR-036 | Frequency/quiet hours | E-12 | Caps and timezone suite | P1 |
| FR-037 | Domain authentication | E-02/E-06 | UAT-06, 07, 26 | P0 |
| FR-038 | Preflight rules | E-06 | UAT-08–12 | P0 |
| FR-039 | Intent advisor | E-05/E-06 | UAT-12 | P0 |
| FR-040 | Controlled test send | E-05/E-06 | UAT-15 | P0 |
| FR-041 | Sender health | E-06/E-10 | DNS/provider metrics suite | P0 |
| FR-042 | Alerts | E-06 | Alert lifecycle tests | P0 |
| FR-043 | Reporting | E-10 | UAT-21 | P0 |
| FR-044 | Link/UTM checks | E-05/E-06 | UAT-27 + UTM roundtrip | P0 |
| FR-045 | Journeys | E-12 | Journey re-entry/exit suite | P1 |
| FR-046 | Social attribution | E-12 | Source mapping suite | P1 |
| FR-047 | Conversions | E-12 | Unique external event IDs | P1 |
| FR-048 | Reputation insights | E-10/E-12 | Anomaly alert tests | P1 |
| FR-049 | Experiments | E-12 | Insufficient sample tests | P2 |
| FR-050 | AI assistance | E-12 optional | Human approval/no fabricated placement | P2 |

**Scope clarification:** the source BRD/SRS marks FR-006 organization partitioning as P1; initial implementation still MUST enforce current organization/brand authorization and no HR/marketing leakage. Full multi-company tenancy features can remain P1.

---

# 23. Required project documentation, owner sign-off and source references

## 23.1 Minimum documents to maintain in the repo

```text
docs/marketing/
  README.md                         # This guide / onboarding
  architecture/
    ADR-001-build-vs-reuse.md
    ADR-002-data-boundaries.md
    ADR-003-email-provider.md
    ADR-004-delivery-idempotency.md
    system-context.md
  api/
    openapi.yaml
    error-codes.md
  database/
    schema.md
    migrations-and-backups.md
    data-retention.md
  deliverability/
    rules.yaml                     # Version-controlled policy definitions
    rules-changelog.md
    sender-domain-setup.md
    unsubscribe-rfc8058.md
    templates-and-intents.md
  privacy/
    consent-policy.md
    legal-mapping.md
    deletion-export-workflow.md
  operations/
    deploy.md
    rollback.md
    provider-incidents.md
    webhook-replay.md
    unknown-outcomes.md
    backup-restore.md
  QA/
    acceptance-matrix.md
    synthetic-fixtures.md
    client-render-results.md
    pilot-report.md
```

## 23.2 Simple ADR / requirement change template

```markdown
# ADR-00X: [Decision]
- Date: YYYY-MM-DD
- Owner: [Person/role]
- Status: proposed | accepted | superseded
- Related FR: FR-...
- Context/problem:
- Business impact:
- Alternatives considered:
- Decision:
- Technical consequences:
- Privacy/security consequences:
- Estimated effort / operating cost:
- Verification and acceptance:
- Rollback / revisiting trigger:
- Approvals:
```

## 23.3 Definition of project completion

The marketing feature is considered production-ready only when:

1. A permissioned operator can create an audience, design an email, see **specific fixable warnings**, obtain approval, schedule, send and monitor without touching HR/payroll records.
2. Permission, unsubscribe, complaint, hard-bounce and brand scope restrictions are enforced at **dispatch time** and in the chosen sidecar/provider integration.
3. Templates are safely rendered and revision-locked; sender authentication and RFC 8058 controls are verified on actual controlled test messages.
4. Worker crashes, ambiguous external responses and duplicate/out-of-order webhooks do not silently cause duplicate business effects or uncontrolled re-sends.
5. Reports show accurate sender/protocol states and limitations rather than claiming inbox/tab guarantees.
6. All P0 scenarios pass, monitoring/rollback ownership is signed off and there is a successful small live pilot.
7. The team has documented how to maintain updates, rules, retention, source consent records and provider integrations after release.

## 23.4 Verified external references (checked 9 November 2026)

Use vendor documents as the operative source; provider rules evolve and must be rechecked during launch readiness review. Links are included for practical developer navigation.

**Sender guidelines and protocols**

1. Google, *Email sender guidelines*: https://support.google.com/mail/answer/81126
2. Google, *Email sender guidelines FAQ*: https://support.google.com/mail/answer/14229414
3. Yahoo Sender Hub, *Sender Best Practices*: https://senders.yahooinc.com/best-practices/
4. Microsoft, *High-volume sender authentication requirements*: https://support.microsoft.com/en-us/outlook/fix-ndr-error-550-5-7-515-in-outlook-com
5. IETF, *RFC 8058 (One-Click List-Unsubscribe)*: https://www.rfc-editor.org/rfc/rfc8058
6. IETF, *RFC 5322 (Internet Message Format)*: https://www.rfc-editor.org/rfc/rfc5322

**Open source and provider operations**

7. Mautic source: https://github.com/mautic/mautic
8. Mautic release/support: https://mautic.org/releases/
9. Mautic campaign API docs: https://devdocs.mautic.org/en/7.2/rest_api/campaigns.html
10. Mautic scheduled jobs: https://docs.mautic.org/en/7.2/configuration/cron_jobs.html
11. phpList source: https://github.com/phpList/phplist3
12. phpList releases: https://github.com/phpList/phplist3/releases
13. GrapesJS docs: https://grapesjs.com/docs/
14. GrapesJS newsletter preset: https://github.com/GrapesJS/preset-newsletter
15. GrapesJS MJML plugin: https://github.com/GrapesJS/mjml
16. Symfony Messenger queues: https://symfony.com/doc/current/messenger.html
17. Amazon SES event publishing: https://docs.aws.amazon.com/ses/latest/dg/monitor-sending-using-event-publishing-setup.html

**Privacy**

18. Government of India, PIB: notification of DPDP Rules 2025 and phased compliance: https://www.pib.gov.in/PressReleasePage.aspx?PRID=2190014

---

# Appendix A. Initial navigation and screen acceptance checklist

| Screen | Essential UI elements | Ready criterion |
|---|---|---|
| Marketing Home | health banners, current campaigns, scheduled sends, pending reviews | Only permitted brand data shown |
| All Contacts | filters, profile, permission, history and groups | No hidden HR data; redactions by role |
| Lists & Segments | list CRUD, safe filter builder, eligible counts | Dedup and consent respected |
| Imports | CSV upload, mapping, preview, errors, commit | No opt-in by default |
| Template Gallery | template categories, preview, status, version | Archived/sent revisions immutable |
| Template Editor | editor blocks, subject/preheader, intent, tokens, variants | Safe output and protected footer |
| Preflight & Alerts | severity, evidence, fix link, rerun, history | Blocks enforce send denial |
| Campaign Wizard | audience, template, sender, schedule, review | No send without approval |
| Campaign Monitor | accepted/delivered/events, pause and cancel | States truthful and traceable |
| Reports | filters, definitions, exports and refresh time | Metrics reproduce from events |
| Sender Settings | domain verification, DKIM/DMARC status, limits | No unverified identity sends |
| Preferences | public tokenized opt-outs; simple UX | No login required for applicable one-click |

# Appendix B. Recommended first pilot use case

**Use case:** send a single webinar invitation to a modest, permissioned subscriber group. This is a useful first test because it exercises lists, email templates, marketing consent, CTA validation, preflight, approval, scheduling, tracking, unsubscribe and provider events.

Pilot steps:

1. Create a marketing brand and verify a legitimate From domain.
2. Import 100–500 **real permissioned** webinar-interested contacts (or fewer depending on what you already lawfully have); keep import evidence.
3. Create `November Startup Webinar - Invitation` from the starter template, with true event date/time and clear CTA.
4. Select `promotional` intent and confirm marketing unsubscribe controls.
5. Run full preflight, fix all blockers and review warnings.
6. Test-send to authorized seed inboxes; inspect headers and client layout.
7. Freeze content/audience and obtain independent approval.
8. Send a **small cohort** within quota; verify complaints, unsubscribes and delivery statuses.
9. Resume only after pilot health review; document learning and new validation rules.

**Do not** select a bulk audience solely to prove throughput. Throughput must first be demonstrated with fake providers and synthetic recipients.

# Appendix C. Open questions to resolve in ADRs (not blockers to reading this guide)

| Question | Why it changes execution |
|---|---|
| Which PHP framework and version currently run? | Determines DI, HTTP, queues, migrations and compatibility |
| Is the DB MySQL/MariaDB/PostgreSQL? | Changes SQL/migrations, lock strategy and indexes |
| How many contacts/messages per month and peak day? | Determines quotas, rate limits, infrastructure and estimates |
| Which brands/domains/From identities are used? | Determines tenant/sender scoping and authentication |
| Which ESP/region is approved? | Determines event schemas, DKIM, retention and cost |
| Is advanced journey automation needed immediately? | Decides Mautic sidecar vs thin custom build |
| What is the current social lead schema? | Changes P1 connector mapping and attribution |
| What permission evidence exists for current mailing lists? | Determines which contacts may be marketed to |
| Do different countries/jurisdictions appear in audiences? | Changes legal obligations, notices and preferences |
| What notification/on-call system is available? | Changes alert routing and incident procedure |

---

**Document close — implementation baseline.** Maintain this file under version control. Record changes through pull requests and ADRs, and do not weaken mandatory consent, unsubscribe, authentication or authorization checks to meet a campaign date.
