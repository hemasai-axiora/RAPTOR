# Claude Code & CLI Guidelines for Raptor Platform

```
Document:             CLAUDE.md
Directory:            RAPTOR-V2.2-BLUEPRINT/DEVELOPER-WORKFLOW/
Classification:       Claude CLI & Agent Guidelines
Target Release:       Raptor v2.2
```

---

## 1. Environment & Architecture Overview

- **Stack**: PHP 8.2 / 8.3 native MVC, MySQL 8.0+ / MariaDB, SQLite 3 for offline mock unit tests, Bootstrap 5.3, Vanilla JS/jQuery.
- **Root Directory**: `e:\raptor`
- **Front Controller**: `public/index.php`
- **Routing Engine**: `app/core/App.php` (dispatches to `app/controllers/{Name}Controller.php`)
- **Autoloader**: PSR-4 via `vendor/autoload.php` + core autoloader for `app/core`, `app/models`, `app/services`.
- **Test Runner**: `bin/run_all_campaign_tests.php`
- **Unified CLI**: `bin/raptor`

---

## 2. Essential Commands

### Running Tests
```bash
# Execute master test suite (16 suites, 772 assertions)
php bin/run_all_campaign_tests.php

# Execute via unified Raptor CLI
php bin/raptor test:run

# Verify system health diagnostics
php bin/raptor health:check

# Test Sentry live connectivity & dispatch
php bin/raptor sentry:test
```

### Database & Migrations
```bash
# Inspect pending vs applied migrations
php bin/raptor migrate:status

# Run outstanding migrations
php bin/raptor migrate:run
```

### Syntax Checking
```bash
# Check syntax of modified files
php -l app/controllers/SomeController.php
php -l app/services/SomeService.php
```

---

## 3. Engineering Invariants for Claude

1. **Never alter existing working tests**: All 772 assertions must pass. If an edit causes a failure, investigate and resolve the regression in the application code.
2. **Always test parameter binding**: No raw string interpolation into SQL queries.
3. **Respect CSRF**: Any new HTML form must include `<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">`.
4. **Follow Design Tokens**: When updating or creating views, use CSS classes from `COLOR-TOKENS.css` and avoid hardcoded inline styles.
5. **Traceability**: Preserve `X-Request-ID` propagation in any custom HTTP client requests or webhook callbacks.
