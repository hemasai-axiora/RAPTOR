# Raptor Platform — AI Coding Agent Governance Policy

```
Document:             AGENTS.md
Directory:            RAPTOR-V2.2-BLUEPRINT/DEVELOPER-WORKFLOW/
Classification:       AI Agent Engineering Policy & Operational Boundaries
Target Release:       Raptor v2.2
Applicability:        All autonomous, semi-autonomous, and conversational AI coding agents
                      (Google Antigravity, Claude Code, Cursor, GitHub Copilot, Codex, etc.)
```

---

## 1. Core Mandate

You are an expert pair-programming software engineer working on the **Raptor Platform**. You must treat this codebase with high technical rigor, architectural discipline, and zero-regression preservation.

**Raptor is a production enterprise operating system.** It manages statutory employee payrolls, tax compliance, high-volume email broadcasts, WhatsApp communications, and sales leads. A careless change can trigger statutory payroll penalties, domain blacklisting, or horizontal data exposure.

---

## 2. Mandatory Rules of Engagement (12-Step Protocol)

Before and during any code modifications, every AI coding agent must:

1. **Inspect Before Acting**: Thoroughly read the target controller, model, service, and layout before drafting changes. Never assume an architecture or method signature.
2. **Identify Dependent Modules**: Understand whether changing a model or method affects Payroll, Attendance, Leads, Campaigns, or Quick Search.
3. **Respect Security & Authorization**: Every controller action must preserve `$this->requireAuth()`, `$this->requirePermission()`, CSRF checks, and team-subtree data scoping (`Policy::getTeamSubtreeUserIds()`).
4. **Preserve Database Isolation**: Always use PDO prepared statements with parameter binding. Never concatenate variables into SQL strings.
5. **Smallest Viable Diff**: Implement the smallest, cleanest change that solves the user's objective. Avoid sweeping refactorings of unrelated files.
6. **No Phantom Dependencies**: Never add new Composer or npm dependencies without explicit user instruction and architectural approval.
7. **Verify Syntax**: Run `php -l <file>` on every modified PHP file immediately after editing.
8. **Run Regression Suites**: Execute `php bin/run_all_campaign_tests.php` to prove that all 772 baseline assertions pass without failure.
9. **Never Mock Away Failures**: If a test fails, fix the underlying code bug. Never modify or delete existing test assertions to force a green build.
10. **Preserve Documentation**: Maintain existing PHPDoc blocks, comments, and architectural headers.
11. **Semantic Commit Messages**: Format git commits using Conventional Commits (`feat(...)`, `fix(...)`, `refactor(...)`, `test(...)`).
12. **Report Risks Transparently**: If a requested feature has security, database, or performance implications, explicitly state them to the user.

---

## 3. Strictly Prohibited AI Actions

Under no circumstances may an AI agent:
- **Bypass or remove authentication or permission gates** (`requireAuth`, `requirePermission`, `Policy::can*`).
- **Disable or bypass CSRF token validation** on POST/PUT/DELETE requests.
- **Expose secrets, passwords, tokens, or API keys** in source files, git commits, or console outputs.
- **Execute destructive SQL commands** (`DROP TABLE`, `TRUNCATE`, `DELETE FROM ...` without where clauses) on live or staging databases.
- **Modify existing migration files** (files `0000_...` to `0058_...`). New database changes must be added as new sequential migration files (e.g. `0059_...`).
- **Break backward compatibility** of public API endpoints or OpenAPI specifications.
- **Replace the custom PHP MVC architecture** with Laravel, Symfony, React, Vue, or Next.js.
- **Perform unauthorized deployments** to production.

---

## 4. AI Change Report Template

When completing a task, the AI agent must provide a structured change report formatted as follows:

```markdown
### AI Change Verification Summary
- **Target Component**: [Module / File name]
- **Modification Purpose**: [Concise 1-sentence description]
- **Security & Authorization Review**: [Confirmed CSRF, RBAC, Subtree scoping intact]
- **Test Execution Scorecard**: [772 / 772 passed, 0 failures]
- **Syntax Check**: [Zero PHP syntax errors detected]
- **Rollback Instructions**: [Git command to revert if required]
```

