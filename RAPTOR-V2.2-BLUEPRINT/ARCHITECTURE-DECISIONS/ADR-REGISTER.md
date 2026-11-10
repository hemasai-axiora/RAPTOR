# Architecture Decision Records (ADR) Register

```
Document:             ADR-REGISTER.md
Directory:            RAPTOR-V2.2-BLUEPRINT/ARCHITECTURE-DECISIONS/
Classification:       Enterprise Architectural Decision Records
Target Release:       Raptor v2.2
Status:               Proposed / Ready for Technical Approval
```

---

## Index of Architecture Decision Records

- **ADR-001**: Retaining Native PHP 8.2+ Custom MVC Architecture (Rejecting Full-Framework Rewrite)
- **ADR-002**: Retaining Bootstrap 5.3 + Semantic CSS Tokens (Rejecting Tailwind CSS & Heavy JS Frameworks)
- **ADR-003**: Enforcing OWASP ASVS Level 2 as the Application Security Baseline
- **ADR-004**: Database Queue with `FOR UPDATE SKIP LOCKED` (Rejecting Redis / RabbitMQ Daemon Dependencies)
- **ADR-005**: Universal Multi-LLM Provider Abstraction (`AiService.php`)
- **ADR-006**: Centralizing Global Quick Search with Strict Team-Subtree Permission Filtering
- **ADR-007**: Shift-Left Quality Gates & PHPStan Level 5 Static Analysis
- **ADR-008**: Dedicated Service Layer Extraction from Legacy Controllers
- **ADR-009**: AI Coding Agent Operational Governance Policy (`AGENTS.md`)
- **ADR-010**: Automated End-to-End Browser Testing via Chrome DevTools MCP & Playwright

---

### ADR-001: Retaining Native PHP 8.2+ Custom MVC Architecture
- **Status**: Accepted
- **Context**: As Raptor grows, some engineers may advocate rewriting the application in Laravel, Symfony, or converting the frontend to Next.js/React.
- **Decision**: Raptor will strictly retain its lightweight, custom PHP 8.2/8.3 MVC architecture.
- **Consequences**:
  - *Positive*: Sub-8ms boot times, minimal memory consumption, zero framework upgrade churn, high maintainability, full control over security and routing.
  - *Negative*: Developers must write custom abstractions rather than relying on Laravel ecosystem packages.
- **Alternatives Considered**: Laravel 11 (Rejected: High overhead, unnecessary rewrite of working database and permission logic).

---

### ADR-002: Retaining Bootstrap 5.3 + Semantic CSS Tokens
- **Status**: Accepted
- **Context**: The existing UI needs visual modernization and dark/light theme consistency without breaking dozens of existing blade/view templates.
- **Decision**: Retain Bootstrap 5.3 as the grid and utility foundation while layering centralized semantic CSS variables (`COLOR-TOKENS.css`) and calibrated glassmorphism surfaces.
- **Consequences**:
  - *Positive*: 100% backward compatibility with existing view layouts; instant theme toggling via standard CSS custom properties.
  - *Negative*: Developers must adhere to semantic token classes instead of writing raw hex colors inline.
- **Alternatives Considered**: Tailwind CSS (Rejected: Requires Node.js build watch pipelines, alters existing markup classes across hundreds of views).

---

### ADR-003: Enforcing OWASP ASVS Level 2 Security Baseline
- **Status**: Accepted
- **Context**: Raptor processes confidential corporate payroll data, customer contact lists, and marketing credentials.
- **Decision**: Adopt OWASP Application Security Verification Standard (ASVS) v4.0.3 Level 2 as the formal development and verification standard.
- **Consequences**:
  - *Positive*: Verifiable enterprise security posture, defense against injection, strict multi-tenant boundary checks.
  - *Negative*: Requires automated security test suites to run on every PR before merge.

---

### ADR-004: Database Queue with `FOR UPDATE SKIP LOCKED`
- **Status**: Accepted
- **Context**: High-volume email broadcasting, WhatsApp deliveries, and journey automation require asynchronous background job queues.
- **Decision**: Use MySQL 8.0+ transactional row locking (`FOR UPDATE SKIP LOCKED`) in `marketing_queue` with a CLI daemon worker (`bin/raptor queue:worker`).
- **Consequences**:
  - *Positive*: Zero extra server infrastructure (no Redis or RabbitMQ daemons to manage and monitor); atomic database transaction consistency.
  - *Negative*: Maximum queue throughput bounded by database disk I/O (sufficient for 100k+ emails/day).

---

### ADR-005: Universal Multi-LLM Provider Abstraction
- **Status**: Accepted
- **Context**: Raptor requires generative AI capabilities across subject line suggestions, email templates, and customer inbox smart replies without being locked into a single AI provider.
- **Decision**: Maintain `AiService.php` as a vendor-neutral client supporting Google Gemini, OpenAI GPT, and Anthropic Claude with user-configured API keys.
- **Consequences**:
  - *Positive*: Enterprise flexibility; customers choose their preferred AI vendor or use self-hosted keys.
  - *Negative*: Feature scope limited to capabilities supported across all three vendors (completions/chat).

---

### ADR-006: Centralizing Global Quick Search with Strict Subtree Permissions
- **Status**: Accepted
- **Context**: Audit finding `AUD-003` identified potential horizontal information disclosure in Command Palette (`Ctrl + K`).
- **Decision**: Refactor `QuickSearchController` to pass all lead, customer, and employee queries through `visibleUserIds()` and `Policy::canViewRecord()`.
- **Consequences**:
  - *Positive*: Eliminates horizontal privilege escalation; guarantees non-admin users only discover authorized records.
  - *Negative*: Search query execution incorporates subtree join conditions (sub-5ms difference).

---

### ADR-007: Shift-Left Quality Gates & PHPStan Level 5
- **Status**: Accepted
- **Context**: AI coding agents and human developers can introduce subtle type mismatches or dead code paths that pass manual testing.
- **Decision**: Introduce a 7-stage quality gate enforcing PHPStan Level 5 static analysis and 100% pass rates across all 772 existing test assertions.
- **Consequences**:
  - *Positive*: Catch regressions before code review; prevent runtime fatal errors.
  - *Negative*: Developers must write explicit type-hints and PHPDoc annotations.

---

### ADR-008: Dedicated Service Layer Extraction from Legacy Controllers
- **Status**: Accepted
- **Context**: Legacy controllers (`AttendanceController`, `LeavesController`) contain raw SQL and date arithmetic.
- **Decision**: Incrementally extract business calculations into dedicated services (`AttendanceService`, `LeaveService`) while keeping controller method signatures intact.
- **Consequences**:
  - *Positive*: Highly testable service units; single source of truth for business logic.
  - *Negative*: Requires refactoring legacy controllers during Sprint 5.

---

### ADR-009: AI Coding Agent Operational Governance Policy
- **Status**: Accepted
- **Context**: Autonomous AI coding agents (Antigravity, Cursor, Claude Code) may hallucinate architecture changes, bypass security gates, or delete working tests.
- **Decision**: Check in mandatory AI governance context files (`AGENTS.md`, `CLAUDE.md`, `copilot-instructions.md`) establishing strict operational rules and prohibited actions.
- **Consequences**:
  - *Positive*: Disciplined, deterministic, regression-protected AI assistance.
  - *Negative*: AI agents must follow a 12-step verification protocol for every modification.

---

### ADR-010: Automated Browser Testing via Chrome DevTools MCP & Playwright
- **Status**: Accepted
- **Context**: Backend tests cover 772 assertions, but frontend UI interactions (modals, dropdowns, keyboard events, responsive layout collapses) lack automated verification.
- **Decision**: Implement automated E2E browser verification via Chrome DevTools MCP and Playwright (`npm test`) covering 10 critical user journeys.
- **Consequences**:
  - *Positive*: Visual and DOM regressions caught automatically before deployment.
  - *Negative*: Requires Node.js and headless browser runtime in test environments.
