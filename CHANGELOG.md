# Changelog

All notable changes to the **Raptor Platform** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.1.0] - 2026-11-10

### Added
- **Global Command Palette (`Ctrl + K` / `Cmd + K`)**: Keyboard-driven fuzzy search modal across Employees, CRM Leads, Campaigns, and Portal navigation destinations.
- **Glassmorphic Toast Notification Engine**: Floating non-blocking toast notifications with progress timers, subtle Web Audio synthesizer chimes, and automatic pickup of server flash messages.
- **Raptor Unified CLI (`bin/raptor`)**: ANSI terminal console unifying test suites (`test:run`), migrations (`migrate:status`, `migrate:run`), queue diagnostics (`queue:status`), and system health telemetry (`health:check`).
- **Decoupled Event Dispatcher (`app/core/EventDispatcher.php`)**: PSR-14-inspired priority event bus for decoupled hooks, audit logging, and asynchronous notifications.
- **Dead-Letter Queue (DLQ) Inspector**: Admin UI at `index.php?route=settings/dlq` for inspecting and retrying quarantined background jobs and webhook payloads.
- **OpenAPI 3.1 & Interactive Scalar Explorer**: Complete API specification in `public/openapi.json` rendered via Scalar at `index.php?route=apidocs/index`.
- **Distributed Request Correlation**: Automatic `X-Request-ID` generation and header propagation across all HTTP responses.
- **DataTables Export Suite**: HTML5 buttons (CSV, Excel, Print) and Column Visibility selectors.
- **Progressive Web App Manifest**: `public/manifest.json` for desktop and mobile installation.

---

## [2.0.0] - 2026-11-09

### Added
- **Unified Email Marketing Platform**: Mass broadcast engine with `FOR UPDATE SKIP LOCKED` database queue row locking.
- **Deliverability Intelligence Rule Engine**: Rules `D-001` through `D-020` validating SPF, DKIM 2048-bit, DMARC, spam trigger words, image-to-text ratios, HTML validity, and unsubscribe mechanisms.
- **Marketing Alert Center**: Real-time threshold alerts and automated campaign pauses for bounce and complaint spikes.
- **Preflight Deliverability Runs**: Cryptographic audit snapshots and pre-dispatch compliance verification.
- **Immutable Consent Ledger**: RFC 8058 One-Click Unsubscribe and audit trail in `marketing_consent_events`.
- **Automated Verification Suite**: 32/32 UAT test pack and consolidated 15-suite test runner.

---

## [1.5.0] - 2026-11-01

### Added
- **Statutory HR & Payroll Overhaul**: Complete Indian statutory compliance (PF, ESIC, Gratuity, Professional Tax, LOPs).
- **Pay Band Auto-Synchronization**: Bidirectional synchronization between monthly CTC, salary structures, and Employee Directory bands (Band 1 through Band 8).
- **Executive Payroll Reconciliation**: Month-over-month variance analytics and pre-payroll readiness checklists.
- **Times New Roman Payslip PDF**: Pixel-perfect typography and layout matching Axiora corporate standard.

---

## [1.0.0] - 2026-10-15

### Added
- Core lightweight PHP MVC framework (`App`, `Controller`, `Model`, `Database`, `Security`, `Policy`).
- Employee Directory, Leave Balances, and Daily Attendance check-in.
- Customer 360, Inbound Leads, Accounts, and Opportunities.
- Meta WhatsApp Cloud API integration and Omnichannel Shared Inbox.
- Telephony call logging, click-to-call, and TRAI DND registry.
- Social media publishing for LinkedIn, Facebook, and Instagram.
