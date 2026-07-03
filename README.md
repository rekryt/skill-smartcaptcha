# skill-smartcaptcha

> AI-**скилл** для интеграции и администрирования **Yandex SmartCaptcha**: виджет на сайте
> (обычный, расширенный, невидимый), серверная проверка токена через `/validate`, React и Vue 3,
> мобильные приложения через WebView, управление капчей (консоль / CLI / Terraform / API),
> диагностика и тарификация.

**Русский** · [English](README.en.md) · Лицензия: MIT · Yandex SmartCaptcha

Точка входа для агента — `SKILL.md`; вся глубина живёт в `docs/` и `references/`, готовый код — в
`templates/` и `examples/`, пошаговые сценарии — в `workflows/`.

## Что это

skill-smartcaptcha — это слой знаний и готового кода для защиты форм от ботов с помощью
Yandex SmartCaptcha. Скилл помогает, когда вы:

- добавляете капчу на сайт (HTML, SPA, React, Vue 3) или в мобильное приложение (Android / iOS / Flutter);
- пишете серверную проверку `smart-token` (`/validate`) и хотите правильную семантику из коробки:
  решение только по полю `status`, fail-open при сбоях сервиса, одноразовость токена, таймауты;
- создаёте и настраиваете капчу в Yandex Cloud: консоль, `yc smartcaptcha`, Terraform
  (`yandex_smartcaptcha_captcha`), REST/gRPC API, роли IAM, варианты заданий и правила показа;
- разбираетесь, почему капча не отображается или пропускает ботов (домены, ограниченный режим,
  события ошибок), и оцениваете стоимость и квоты.

Все технические факты выверены по официальной документации Yandex Cloud (снимок
[yandex-cloud/docs](https://github.com/yandex-cloud/docs), раздел `ru/smartcaptcha`); карта
соответствия — в `references/source-map.md`.

## Установка (для ИИ-агентов)

Этот репозиторий **и есть** скилл: `SKILL.md` лежит в корне, рядом со вспомогательными `docs/`,
`references/`, `templates/`, `examples/`, `workflows/`, `scripts/`, `evals/`. Установите его туда,
откуда ваш агент читает скиллы.

### Claude Code

Персонально (доступно во всех проектах):

```bash
git clone https://github.com/rekryt/skill-smartcaptcha.git ~/.claude/skills/skill-smartcaptcha
```

Только для проекта (запускать из корня проекта):

```bash
git clone https://github.com/rekryt/skill-smartcaptcha.git .claude/skills/skill-smartcaptcha
```

Скилл оказывается по пути `…/skills/skill-smartcaptcha/SKILL.md` и **обнаруживается автоматически** —
он подгружается сам, когда вы работаете со SmartCaptcha или защитой форм от ботов, либо вызывается
явно через `/skill-smartcaptcha`. Обновление: `git -C ~/.claude/skills/skill-smartcaptcha pull`.

### Claude.ai (веб) и Claude Desktop

1. Упакуйте скилл в ZIP так, чтобы **папка была в корне архива**:

   ```bash
   git clone https://github.com/rekryt/skill-smartcaptcha.git
   zip -r skill-smartcaptcha.zip skill-smartcaptcha
   ```

2. В Claude откройте **Settings → Capabilities → Skills** (должно быть включено выполнение кода),
   выберите **Create skill → Upload a skill** и загрузите `skill-smartcaptcha.zip`.
3. Скилл подгружается автоматически на релевантных запросах или вызывается через `/skill-smartcaptcha`.
   Загруженные скиллы приватны для вашего аккаунта; на планах Team/Enterprise админ может ими делиться.

### Claude Agent SDK / Messages API

Скилл загружается в ваш workspace, а затем подключается к контейнеру выполнения запроса (нужны
бета-флаги Skills и code-execution). Пример на Python:

```python
from anthropic import Anthropic
from anthropic.lib import files_from_dir

client = Anthropic()  # читает ANTHROPIC_API_KEY

skill = client.beta.skills.create(
    display_title="Yandex SmartCaptcha",
    files=files_from_dir("./skill-smartcaptcha"),   # папка, содержащая SKILL.md
)

resp = client.beta.messages.create(
    model="claude-opus-4-8",
    max_tokens=2048,
    betas=["skills-2025-10-02", "code-execution-2025-08-25", "files-api-2025-04-14"],
    container={"skills": [{"type": "custom", "skill_id": skill.id, "version": "latest"}]},
    tools=[{"type": "code_execution_20250825", "name": "code_execution"}],
    messages=[{"role": "user", "content": "Добавь Yandex SmartCaptcha на форму регистрации (Express) с серверной проверкой токена."}],
)
```

Скиллы через API/SDK действуют **в пределах workspace** (отдельно от Claude Code и claude.ai); до 8
скиллов на запрос. Точные детали смотрите в документации Agent Skills — бета-идентификаторы могут
меняться.

### Любой другой агент / фреймворк

Скилл — это просто `SKILL.md` плюс вложенные референсы с **прогрессивным раскрытием**. Скопируйте эту
папку в путь скиллов вашего агента (или укажите агенту на `SKILL.md`): он сначала читает `SKILL.md`,
а файлы из `docs/`, `references/`, `templates/` и т. д. подтягивает по мере необходимости. Именно поле
`description` во frontmatter `SKILL.md` заставляет агента обратиться к скиллу.

## Что покрывает

- **Все способы подключения виджета:** автоматический (`div.smart-captcha`), расширенный
  (`window.smartCaptcha.render()` — SPA, несколько виджетов, отложенная загрузка), невидимая капча
  (`invisible` + `execute()`), полные справочники параметров и событий JS API.
- **Серверная проверка `/validate` как дисциплина:** решение только по `status`, fail-open при
  недоступности сервиса, одноразовый токен (5 минут), IP за прокси, конвенции (нет токена → 403 без
  запроса; отсутствие секрета — громкая ошибка конфигурации). Готовые модули: Python, Node.js,
  PHP (cURL), PHP в event loop (AMPHP v3), мок `/validate` для тестов, CLI-утилита проверки токена.
- **Фреймворки и платформы:** React (официальный `@yandex/smart-captcha`), Vue 3 / Nuxt (composable
  на расширенном методе + TypeScript-типы `window.smartCaptcha`), Android / iOS / Flutter через
  WebView с JavaScript-мостом.
- **Управление капчей:** создание и настройка через консоль, CLI `yc smartcaptcha`, Terraform
  `yandex_smartcaptcha_captcha`, REST/gRPC API; ключи и их безопасность; роли IAM; варианты заданий и
  правила показа (включая `metadata`); метрики и аудитные логи.
- **Эксплуатация:** диагностика «симптом → причины → решение» (домены, CSP, ограниченный режим,
  `Invalid or expired Token`), тарификация, квоты, публичные IP сервиса.
- **Запускаемые примеры:** фуллстек Express (Node 18+) и Flask с формой и проверкой токена.

## Что НЕ входит

- **Обход и автоматическое решение капч** — скилл помогает защищать свои формы, а не преодолевать
  чужие проверки.
- **Другие антибот-решения** (Google reCAPTCHA, hCaptcha, Cloudflare Turnstile) и общая антибот-архитектура
  (WAF, rate limiting) — только Yandex SmartCaptcha.
- **Официальные SDK там, где их нет:** для Vue и мобильных платформ у Яндекса нет официальных пакетов —
  скилл даёт проверенные паттерны на официальном JS API виджета (сторонние обёртки перечислены с
  пометкой «неофициальные»).
- **Гарантированно актуальные цены** — тарифы в `docs/pricing-limits.md` зафиксированы на дату снимка
  документации; перед расчётом бюджета сверяйтесь с прайс-листом Yandex Cloud.

## Структура репозитория

| Путь | Назначение |
| --- | --- |
| `SKILL.md` | точка входа: ключевые факты, выбор сценария и маршрутизация по всем файлам |
| `README.md` / `README.en.md` | человекочитаемое описание (RU / EN) |
| `docs/` | 16 файлов знаний: от `overview.md` и `server-validation.md` до `vue.md` и `troubleshooting.md` |
| `references/` | плотные справочники: параметры виджета, JS API, `/validate`, management API, карта источников |
| `templates/` | 13 готовых шаблонов: HTML-страницы, `validate.{py,js,php}`, `validate-amphp.php`, `mock-validate.php`, React `.tsx`, Vue `useSmartCaptcha.ts` + `smartcaptcha.d.ts` |
| `examples/` | запускаемые фуллстек-примеры: Express и Flask |
| `workflows/` | пошаговые сценарии: создание капчи, интеграция сайта / React / мобильного приложения |
| `scripts/` | `check_token.py` — CLI-проверка токена через `/validate` (только stdlib) |
| `evals/` | 15 тест-кейсов с проверяемыми критериями для оценки качества скилла |

## Быстрый старт

Виджет на страницу (автоматический метод) и проверка токена на сервере:

```html
<script src="https://smartcaptcha.yandexcloud.net/captcha.js" defer></script>
<form method="POST" action="/submit">
  <!-- поля формы -->
  <div class="smart-captcha" data-sitekey="<ключ_клиента>" style="height: 100px"></div>
  <button type="submit">Отправить</button>
</form>
```

```bash
curl -X POST https://smartcaptcha.yandexcloud.net/validate \
  -d "secret=<ключ_сервера>" -d "token=<smart-token_из_формы>" -d "ip=<IP_пользователя>"
# → {"status": "ok", ...} — пропустить; {"status": "failed", ...} — отказать (HTTP != 200 → fail-open)
```

Токен одноразовый и живёт 5 минут; решение принимайте только по полю `status`. Дальше откройте
`SKILL.md` — таблица «Выбор сценария» направит в нужный раздел под вашу задачу.

## Лицензия

[MIT](LICENSE) © 2026 rekryt.

Репозиторий: <https://github.com/rekryt/skill-smartcaptcha>
