# skill-smartcaptcha

> An AI **skill** for integrating and administering **Yandex SmartCaptcha**: the website widget
> (auto, advanced, invisible), server-side token validation via `/validate`, React and Vue 3,
> mobile apps via WebView, captcha management (console / CLI / Terraform / API),
> troubleshooting and pricing.

[Русский](README.md) · **English** · License: MIT · Yandex SmartCaptcha

The agent entry point is `SKILL.md`; all depth lives in `docs/` and `references/`, ready-made code in
`templates/` and `examples/`, step-by-step playbooks in `workflows/`.

> **Note:** the skill content is written in Russian (matching the Russian-language official
> Yandex Cloud documentation it is verified against). AI agents consume it just fine either way.

## What it is

skill-smartcaptcha is a knowledge-and-code layer for protecting forms from bots with
Yandex SmartCaptcha. The skill helps when you:

- add a captcha to a website (HTML, SPA, React, Vue 3) or a mobile app (Android / iOS / Flutter);
- implement server-side `smart-token` validation (`/validate`) and want the right semantics out of
  the box: decide only by the `status` field, fail-open on service outages, one-time tokens, timeouts;
- create and configure a captcha in Yandex Cloud: console, `yc smartcaptcha`, Terraform
  (`yandex_smartcaptcha_captcha`), REST/gRPC API, IAM roles, task variants and display rules;
- debug why the captcha is not rendering or lets bots through (domains, restricted mode, error
  events), and estimate costs and quotas.

Every technical fact is verified against the official Yandex Cloud documentation (a snapshot of
[yandex-cloud/docs](https://github.com/yandex-cloud/docs), the `ru/smartcaptcha` section); the
mapping lives in `references/source-map.md`.

## Installation (for AI agents)

This repository **is** the skill: `SKILL.md` sits at the repo root, next to the supporting `docs/`,
`references/`, `templates/`, `examples/`, `workflows/`, `scripts/`, `evals/`. Install it wherever
your agent reads skills from.

### Claude Code

Personal (available in all projects):

```bash
git clone https://github.com/rekryt/skill-smartcaptcha.git ~/.claude/skills/skill-smartcaptcha
```

Project-only (run from the project root):

```bash
git clone https://github.com/rekryt/skill-smartcaptcha.git .claude/skills/skill-smartcaptcha
```

The skill ends up at `…/skills/skill-smartcaptcha/SKILL.md` and is **discovered automatically** — it
loads by itself whenever you work with SmartCaptcha or bot protection for forms, or can be invoked
explicitly via `/skill-smartcaptcha`. Update with `git -C ~/.claude/skills/skill-smartcaptcha pull`.

### Claude.ai (web) & Claude Desktop

1. Package the skill as a ZIP with the **folder at the archive root**:

   ```bash
   git clone https://github.com/rekryt/skill-smartcaptcha.git
   zip -r skill-smartcaptcha.zip skill-smartcaptcha
   ```

2. In Claude, open **Settings → Capabilities → Skills** (code execution must be enabled), choose
   **Create skill → Upload a skill** and upload `skill-smartcaptcha.zip`.
3. The skill loads automatically on relevant requests or via `/skill-smartcaptcha`. Uploaded skills
   are private to your account; on Team/Enterprise plans an admin can share them.

### Claude Agent SDK / Messages API

The skill is uploaded to your workspace and then attached to the request's execution container
(Skills and code-execution beta flags required). Python example:

```python
from anthropic import Anthropic
from anthropic.lib import files_from_dir

client = Anthropic()  # reads ANTHROPIC_API_KEY

skill = client.beta.skills.create(
    display_title="Yandex SmartCaptcha",
    files=files_from_dir("./skill-smartcaptcha"),   # the folder containing SKILL.md
)

resp = client.beta.messages.create(
    model="claude-opus-4-8",
    max_tokens=2048,
    betas=["skills-2025-10-02", "code-execution-2025-08-25", "files-api-2025-04-14"],
    container={"skills": [{"type": "custom", "skill_id": skill.id, "version": "latest"}]},
    tools=[{"type": "code_execution_20250825", "name": "code_execution"}],
    messages=[{"role": "user", "content": "Add Yandex SmartCaptcha to a registration form (Express) with server-side token validation."}],
)
```

Skills used via the API/SDK are **workspace-scoped** (separate from Claude Code and claude.ai); up
to 8 skills per request. See the Agent Skills docs for exact details — beta identifiers may change.

### Any other agent / framework

A skill is just `SKILL.md` plus nested references with **progressive disclosure**. Copy this folder
into your agent's skills path (or point the agent at `SKILL.md`): it reads `SKILL.md` first and pulls
files from `docs/`, `references/`, `templates/`, etc. on demand. The `description` field in the
`SKILL.md` frontmatter is what makes an agent reach for the skill.

## What it covers

- **Every widget installation method:** automatic (`div.smart-captcha`), advanced
  (`window.smartCaptcha.render()` — SPA, multiple widgets, lazy loading), invisible captcha
  (`invisible` + `execute()`), complete parameter and JS API event references.
- **Server-side `/validate` as a discipline:** decide only by `status`, fail-open on outages,
  one-time token (5 minutes), IP behind proxies, conventions (no token → 403 without calling the
  API; a missing secret is a loud configuration error). Ready-made modules: Python, Node.js,
  PHP (cURL), PHP inside an event loop (AMPHP v3), a `/validate` mock for tests, a token-check CLI.
- **Frameworks and platforms:** React (the official `@yandex/smart-captcha`), Vue 3 / Nuxt
  (a composable built on the advanced method + TypeScript types for `window.smartCaptcha`),
  Android / iOS / Flutter via WebView with a JavaScript bridge.
- **Captcha management:** creation and configuration via the console, the `yc smartcaptcha` CLI,
  Terraform `yandex_smartcaptcha_captcha`, REST/gRPC API; keys and their security; IAM roles; task
  variants and display rules (including `metadata`); metrics and audit logs.
- **Operations:** "symptom → causes → fix" troubleshooting (domains, CSP, restricted mode,
  `Invalid or expired Token`), pricing, quotas, the service's public IPs.
- **Runnable examples:** full-stack Express (Node 18+) and Flask apps with a form and token validation.

## What it does NOT include

- **Bypassing or auto-solving captchas** — the skill helps you protect your own forms, not defeat
  someone else's checks.
- **Other anti-bot products** (Google reCAPTCHA, hCaptcha, Cloudflare Turnstile) or general anti-bot
  architecture (WAF, rate limiting) — Yandex SmartCaptcha only.
- **Official SDKs where none exist:** Yandex ships no official Vue or mobile packages — the skill
  provides vetted patterns on top of the official widget JS API (third-party wrappers are listed and
  marked as unofficial).
- **Guaranteed-current prices** — the numbers in `docs/pricing-limits.md` are pinned to the
  documentation snapshot date; check the Yandex Cloud price list before budgeting.

## Repository structure

| Path | Purpose |
| --- | --- |
| `SKILL.md` | entry point: key facts, scenario picker and routing to every file |
| `README.md` / `README.en.md` | human-readable description (RU / EN) |
| `docs/` | 16 knowledge files: from `overview.md` and `server-validation.md` to `vue.md` and `troubleshooting.md` |
| `references/` | dense reference tables: widget params, JS API, `/validate`, management API, source map |
| `templates/` | 13 ready-made templates: HTML pages, `validate.{py,js,php}`, `validate-amphp.php`, `mock-validate.php`, React `.tsx`, Vue `useSmartCaptcha.ts` + `smartcaptcha.d.ts` |
| `examples/` | runnable full-stack examples: Express and Flask |
| `workflows/` | step-by-step playbooks: captcha setup, website / React / mobile integration |
| `scripts/` | `check_token.py` — CLI token check against `/validate` (stdlib only) |
| `evals/` | 15 test cases with verifiable assertions for evaluating the skill |

## Quick start

The widget on a page (automatic method) and token validation on the server:

```html
<script src="https://smartcaptcha.yandexcloud.net/captcha.js" defer></script>
<form method="POST" action="/submit">
  <!-- form fields -->
  <div class="smart-captcha" data-sitekey="<client_key>" style="height: 100px"></div>
  <button type="submit">Submit</button>
</form>
```

```bash
curl -X POST https://smartcaptcha.yandexcloud.net/validate \
  -d "secret=<server_key>" -d "token=<smart-token_from_form>" -d "ip=<user_ip>"
# → {"status": "ok", ...} — allow; {"status": "failed", ...} — deny (HTTP != 200 → fail-open)
```

The token is one-time and lives 5 minutes; decide only by the `status` field. Then open `SKILL.md` —
the scenario table will route you to the right section for your task.

## License

[MIT](LICENSE) © 2026 rekryt.

Repository: <https://github.com/rekryt/skill-smartcaptcha>
