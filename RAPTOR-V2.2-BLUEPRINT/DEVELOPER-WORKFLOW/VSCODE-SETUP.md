# VS Code & Antigravity IDE Setup Guide

```
Document:             VSCODE-SETUP.md
Directory:            RAPTOR-V2.2-BLUEPRINT/DEVELOPER-WORKFLOW/
Classification:       Developer Experience & Workspace Configuration
Target Release:       Raptor v2.2
```

---

## 1. Workspace Strategy

To maintain engineering velocity and code consistency across both human developers and autonomous AI coding agents, Raptor v2.2 provides a checked-in `.vscode/` configuration. 

### Core Objectives
1. **Zero Configuration Setup**: New engineers can clone the repository and have full PHP intellisense, formatting, static analysis warnings, and test runners active immediately.
2. **AI Guardrails**: AI agents (Antigravity, Cursor, GitHub Copilot) read workspace settings and project rules to prevent out-of-spec modifications.
3. **Verified Safe Extensions**: Only verified, active, secure extensions are recommended; noisy or unmaintained extensions are omitted.

---

## 2. Curated Extension Catalog

| Category | Extension Name | Identifier | Purpose & Benefit to Raptor | License |
|---|---|---|---|---|
| **PHP Core** | **PHP Intelephense** | `bmewburn.vscode-intelephense-client` | High-performance PHP code completion, signature help, type inferencing for PHP 8.2+. | Free (Premium optional) |
| **PHP Debug** | **PHP Debug (Xdebug)** | `xdebug.php-debug` | Interactive stepping and breakpoint debugging for controller/service execution. | Free (Open Source) |
| **Formatting** | **Prettier** | `esbenp.prettier-vscode` | Consistent formatting for JavaScript, CSS tokens, JSON configs, and Markdown. | Free (MIT) |
| **Code Quality** | **Error Lens** | `usernamehw.errorlens` | Highlights syntax, lint, and type errors inline on the active code line. | Free (MIT) |
| **Version Control** | **GitLens** | `eamodio.gitlens` | Git blame annotations, commit history, and branch navigation directly in the editor. | Free (Freemium) |
| **Security Scanning** | **Semgrep** | `Semgrep.semgrep` | Real-time static application security testing (SAST) detecting injection and auth flaws. | Free (Open Source) |
| **Secret Scanning** | **GitGuardian** | `GitGuardian.gitguardian` | Prevents accidental commits of API keys, Sentry DSNs, or database passwords. | Free (Community) |
| **HTML/CSS** | **CSS Peek** | `pranaygp.csspeek` | Jump from HTML class names directly to CSS variable definitions in `raptor-tokens.css`. | Free (MIT) |
| **HTML/CSS** | **Color Highlight** | `naumovs.color-highlight` | Displays visual swatches behind hex codes and CSS custom color variables. | Free (MIT) |
| **Productivity** | **EditorConfig** | `EditorConfig.EditorConfig` | Enforces consistent line endings (LF), indent style (spaces), and trimming across OSes. | Free (MIT) |
| **Productivity** | **REST Client** | `humao.rest-client` | Execute `.http` test requests directly against local Raptor endpoints without Postman. | Free (MIT) |
| **Task Tracking** | **Todo Tree** | `Gruntfuggly.todo-tree` | Tracks `TODO`, `FIXME`, and `SECURITY` annotations across all 50 modules. | Free (MIT) |

---

## 3. Workspace Installation & Activation

1. Open Raptor workspace in VS Code or Antigravity IDE:
   ```powershell
   code e:\raptor
   ```
2. When prompted: *"This workspace has recommended extensions"*, click **Install All**.
3. Workspace settings automatically configure:
   - Tab size: `4` spaces for PHP, `2` spaces for JS/CSS/JSON.
   - Line endings: `LF` across all files.
   - Format on save: enabled for JS, CSS, JSON, and Markdown.
   - Intelephense stubs: `['Core', 'standard', 'date', 'pcre', 'PDO', 'pdo_mysql', 'pdo_sqlite', 'json', 'curl', 'mbstring', 'openssl']`.

