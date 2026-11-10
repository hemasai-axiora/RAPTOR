# GitHub Copilot & Cursor Instructions for Raptor

```
Document:             copilot-instructions.md
Directory:            RAPTOR-V2.2-BLUEPRINT/DEVELOPER-WORKFLOW/
Classification:       Inline AI Assistant Prompt Instructions
Target Release:       Raptor v2.2
```

---

## 1. Context & Invariants

You are assisting an engineer on **Raptor**, an enterprise PHP 8.2+ MVC business operations platform.

- **Strict Typing**: All new PHP service classes must start with `declare(strict_types=1);`.
- **Controller Responsibilities**: Controllers only coordinate requests. Put complex business calculations into `app/services/`.
- **Database Access**: Always use PDO prepared statements with named parameters (`:user_id`, `:status`). Never write raw string interpolation into SQL queries.
- **Security Gates**: Ensure any new controller action calls `$this->requireAuth()` and `$this->requirePermission('module', 'action')`.
- **Styling**: Use semantic CSS tokens (`var(--raptor-primary)`, `var(--raptor-surface)`) instead of hardcoded hex colors or arbitrary Bootstrap classes.
- **Zero Regressions**: Any generated code must pass the 16 existing test suites (772 assertions).

