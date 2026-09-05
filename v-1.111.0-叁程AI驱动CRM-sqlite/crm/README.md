<!-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved. -->

# 叁程 CRM (Triphase CRM) — PHP MVC CRM Application

**叁程 / Triphase** —— 线索、商机、客户，三段行程，一段不落。

A lightweight, dependency-free CRM built on a small custom PHP MVC framework.
No Composer packages required — just PHP and SQLite. Designed to be easy to
read, extend, and maintain: every layer has one job and the folders map
directly to the MVC pattern.

## Features

- **Auth** — register / login / logout, bcrypt password hashing, CSRF-protected forms, session-based auth guard
- **Customers** — full CRUD, search, activity/notes timeline, related leads & deals
- **Leads** — full CRUD, status filter (new / contacted / qualified / lost), one-click "convert to deal";
  the 流失原因 (lost-reason) column is shown only on the **lost** tab, keeping the other tabs lean
- **Deals** — kanban-style pipeline board grouped by stage, won deals auto-create an order and stay on the board
- **Orders** — created automatically when a deal is won (or manually), with line-item management and statuses
- **Attachments** — upload files (images / PDF / Excel / zip) on deals & orders; copied when a deal wins
- **Archiving** — lost (丢单) deals are auto-archived off the board; archived deals can be restored to 进行中
- **Settings** (`/settings`) — admins edit application info (system name, tagline, company, copyright notice, currency symbol)
  stored in `app_settings` and read through `appSetting()`/`money()`, so changes apply site-wide at once;
  everyone edits their own profile (name, email, 职位, phone, WhatsApp, notes) and password
- **AI assistant** (`/ai`) — 24 whitelisted tool calls in three tiers. **read** (`search_records`, `get_record`): runs at once, never writes, and `app_settings`/`users` are not even on the searchable list, so an API key can never leak through it. **write** (create lead/customer/deal, add follow-up, `update_lead/customer/deal/order`): only the fields you named change, and ownership is re-checked at confirm time. **delete** (`delete_lead/deal/order/customer/ai_request`): demands `confirm:true` plus a one-line reason, shows its computed *blast radius* (how many child rows and attachment files go with it), is never auto-executed even in 自动执行 mode, a plan with ≥2 deletes must be backed by a query the system actually ran in that request (a live model really did guess nationalities from names), and the preview shows each record's country/status/stage so a human checks facts, not just ids, leaves a snapshot of the removed row in `ai_actions`, and is gated by the admin switch `ai_allow_delete` (or `AI_ALLOW_DELETE=0`). `Ai::complete()` is a bounded loop (max 3 rounds): if the model only knows a *condition* — “every customer in India”, “any customer named armtek and their leads/deals/orders” — it asks for a query, we run it for real (read-only), hand the found codes back as `<tool_results>`, and the model then writes the plan against rows that exist. The server also resolves names in your instruction into real IDs (`<found>`), so it never has to guess one. It also remembers: whatever you asked within the configurable **context window** (`ai_context_minutes`, default 60 min, 0 = off) is handed back as `<history>` — the instruction, what the model answered, the status and the real codes it touched — so “mark that lead as lost” or “what happened to that Indian customer from before” works. The history is read straight from the `ai_actions` audit table (no second copy, no second truth), scoped to your own user id, capped at 10 rows / ~1500 chars with the omissions declared, and older turns stay reachable by querying `tables:ai_request` with `days` / `from` / `to`. A bare “确认 / ok” also survives: carryForwardIntent() replays the previous turn intent and its real codes
 as <continuation> (never when a plan is already pending, never with the window off), and fuzzyMatches()
 turns a typo like ashmad into “probably CUS-000020 Ahmad — approximate, not confirmed”, so the assistant
 asks about the right record instead of claiming it does not exist. The **field list itself is generated from the DB schema** (`Ai::fieldsFor()` reading `Schema::columns()`), shared by the prompt, the validator and the writer — 22 lead columns, 19 customer, 11 order, 7 deal, 6 follow-up — so adding a column makes it writable instead of silently refusing (“this lead has no source_country field”, which is exactly what a hand-written list did). Writable means “any column you named”: omitted columns stay untouched, an empty string really clears a nullable column, NOT NULL ones refuse instead of corrupting, and `status=lost` / `stage` / `archived` also write their timestamps like the UI does. System-owned columns (`public_code`, `order_number`, timestamps, `owner_id` on insert) stay out via `Ai::PROTECTED_COLUMNS`. A product catalog (`products`, code `PROD-000007`) is now the only source for deal and order line items: both the form picker and `set_order_items` resolve the reference through the same `OrderItem::normalizeRows()` / `Product::resolve()` (code, SKU or exact name), so there is no second rule for the AI to slip through — and line items still store name/price snapshots, so changing a price today never rewrites yesterday’s order. A referenced product cannot be deleted (it gets disabled instead). New tools: `create_product`, `update_product`, `delete_product`. New tools: `update_follow_up`, `set_order_items` (whole-order line replacement; subtotal and order amount are recomputed by the server, never trusted from the model), and `get_settings` / `update_setting` (admin-only, one key per call, validated by the same `Setting::sanitize()` the settings page uses — API keys are excluded entirely and never echoed back). Every customer / lead / deal carries a **stable code** — `CUS-000007` / `LEAD-000007` / `DEAL-000007` (`public_code`, derived from its id and generated on create, shown in the lists and on the customer page) — and every `*_id` tool parameter accepts that code *or* the numeric id; orders keep using `order_number`. A code that doesn't resolve is refused instead of silently hitting a neighbouring row, and the code can never be supplied or rewritten by a caller. Everything is audited in `ai_actions`; providers: 内置演示模型 (offline), 本地 Ollama, OpenAI, DeepSeek, Kimi, 通义千问, 智谱, SiliconFlow or any OpenAI-compatible endpoint — configured in 设置 → AI 助手
- **People are referenced by id only** — customers/leads/deals/orders store `owner_id`, follow-ups and
  activities `user_id`, attachments `uploaded_by`; the name is JOINed back on read, so a profile edit in
  Settings is instantly reflected everywhere that person appears as 负责人 (no denormalised copies)
- **Dashboard** — key stats (customers, open leads, pipeline value) and recent activity
- Clean Bootstrap 5 UI, responsive sidebar layout

## Tech / Architecture

```
crm/
├── app/
│   ├── config/config.php     # DB path & app settings (env-var driven)
│   ├── core/                 # The "framework": Router, Controller, Model, Database, helpers
│   ├── controllers/          # One controller per resource (Auth, Dashboard, Customer, Lead, Deal, Order)
│   ├── models/                # One model per DB table, extends core/Model.php
│   ├── views/                 # Plain PHP templates, grouped by resource + layouts/
│   ├── routes.php             # Single source of truth for every URL
│   └── bootstrap.php          # Wires config → session → router → dispatch
├── public/                    # Web root — the ONLY folder that should be exposed by your web server
│   ├── index.php              # Front controller (single entry point)
│   ├── .htaccess              # Rewrites all requests to index.php
│   └── assets/                # CSS/JS
├── database/schema.sql        # CANONICAL schema + seed data (idempotent, self-healing)
├── database/migrate.php       # One command: create / upgrade / repair the database
├── database/migrations/       # One-off ALTER-style changes (applied exactly once)
└── .htaccess                  # Points a domain root at /public if you can't set docroot directly
```

**Why this structure is easy to maintain:**
- **No magic** — routes are explicit in `routes.php`, not guessed from URLs. Search the file, see every endpoint.
- **Thin, consistent layers** — `core/Model.php` gives every model `all()`, `find()`, `create()`, `update()`, `delete()`, `count()` for free; each model only adds the queries specific to it (joins, aggregates).
- **One front controller** — `public/index.php` is the only PHP entry point, so there's one place requests are ever intercepted (logging, middleware, etc.).
- **Views are dumb templates** — no business logic in views beyond loops/conditionals; controllers prepare all data first.
- **PDO + prepared statements everywhere** — no raw string-built SQL, so adding fields is just adding to an array.

## Deal lifecycle rules (商机流转规则)

The pipeline works like this:

| Event | What the system does |
|---|---|
| Stage → **成交** (won) | Auto-creates an **order** from the deal + item lines; the deal is **NOT archived** and stays in the 成交 column for reference |
| Stage → **丢单** (lost) | The deal is **auto-archived** and removed from the kanban board |
| Archive page → **恢复** | The deal is unarchived **and reset to 进行中** (open) so it can be followed up again |

Notes:
- The board has no 丢单 column — lost deals only live in the archived list.
- A won deal with an existing order will not create a duplicate order.

## Requirements

- PHP 8.0+ with the `pdo_sqlite` extension enabled
- `extension=openssl` as well if you use the AI assistant against an **https** provider (PHP streams need it; local Ollama / the offline demo model don't)
- Apache with `mod_rewrite` (or adapt the two `.htaccess` files' rules for Nginx)

## Setup

1. **Create / upgrade the database** (single command, safe to re-run):
   ```bash
   php database/migrate.php
   ```
   - **Fresh install** → creates `database/crm.sqlite`, all tables, indexes, triggers and seeds:
     - Demo login: `admin@example.com` / `password`
     - 3 sample customers, 2 leads, 2 deals
   - **Existing / outdated database** → `schema.sql` is fully idempotent
     (`CREATE … IF NOT EXISTS` / `INSERT OR IGNORE`), so missing tables/columns/seeds
     are re-created automatically. If a table is missing (e.g. `no such table` errors),
     simply run this command to repair it.

   The database file is created at `database/crm.sqlite` (git-ignored).

2. **Configure the connection** — the app reads `DB_PATH` from the `.env` file or environment. The default is `database/crm.sqlite` (relative to the project root). To change it:
   ```bash
   # In .env
   DB_PATH=/absolute/path/to/crm.sqlite
   ```

3. **Point your web server's document root at `public/`.**
   - Apache (recommended): set `DocumentRoot` to the `public/` folder, or if you can't, the included root `.htaccess` will rewrite everything into `public/` for you (works when the app lives in a subfolder like `/crm`).
   - Nginx: point `root` at `public/` and add a `try_files $uri $uri/ /index.php?$query_string;` rule.

4. **Quick local test (no Apache needed):**
   ```bash
   cd public
   php -S localhost:8000
   ```
   Then visit `http://localhost:8000` and log in with the demo credentials above.

## Testing

The project ships with a zero-dependency test suite (no Composer/PHPUnit needed —
just the PHP CLI, same requirement as the app itself). Each case runs in its own
process against a throwaway SQLite database built by `database/migrate.php`.

```bash
php tests/run.php                  # run every case
php tests/run.php Order            # run one case by name filter
php tests/cases/OrderTest.php      # run a single case directly
```

The suite covers the model layer (CRUD, status transitions, archive lifecycle,
item sync), the autoloader regression (views calling model static helpers with no
controller pre-loading), attachment copy-on-convert, the migration tooling (fresh
build, idempotent re-run, legacy database upgrade) and an HTTP smoke test that
logs in through the real Router → Controller → View stack.

## Optional: enable the AI assistant

The AI module ships **disabled**, and with the built-in 演示模型 you can exercise the whole flow without an account
or network. Three settings decide how long you wait — 快速模式 (send the provider's
"stop thinking, just answer" switch; measured 7.8 s + empty plan → 1.3 s + a valid `create_lead` on
DeepSeek, and it falls back automatically when an endpoint rejects the parameter), 最大回复长度
(`max_tokens`, the usual reason an answer drags) and 响应超时 (`ai_timeout`, default 45 s; PHP's own
`max_execution_time` is raised around it so a slow model returns a readable hint instead of a Fatal error).
To point it at a real model, open 设置 → AI 助手 (admins), pick a provider, set the model and key and hit
“测试连接”. Environment values always win over the stored settings, which keeps secrets out of the database:

```bash
# .env  (see .env.example)
AI_ENABLED=1
AI_PROVIDER=openai            # mock | ollama | openai | deepseek | moonshot | dashscope | zhipu | siliconflow | custom
AI_MODEL=deepseek-v4-flash   # or deepseek-v4-pro / qwen3.8-flash / qwen3.8-max / gpt-4o-mini
AI_BASE_URL=https://api.openai.com/v1
AI_API_KEY=***            # never echoed back to the browser, never written to logs
AI_MODE=preview               # preview = 人确认后写库 (default) | auto
```

`openssl` must be enabled in `php.ini` (`extension=openssl`) for any **https** endpoint — this project talks to
providers over PHP streams, so a trimmed PHP build cannot reach cloud models. 设置 → AI 助手 tells you up front
which transports the current PHP has; 本地 Ollama (plain http on localhost) and the offline demo model need nothing.

Before choosing a cloud provider: the instruction *plus a snapshot of the signed-in user's own customers / leads /
deals ids* is sent to that API. Pick 本地 Ollama (or 内置演示模型) when data must stay in-house.

The HTTP-level cases speak over plain PHP streams (`TestHttp` in
`tests/bootstrap.php`), so the `curl` extension is **not** required — the only
requirement is `allow_url_fopen`, which is on by default.

## Documentation that writes itself

`app/core/AppMap.php` builds a map of the app from the running code and database — registered routes,
tables / columns / FKs / CHECK enums, settings keys, the AI tool whitelist and provider presets, and the
test inventory. The 使用说明 page renders it, and **`GET /help/context`** serves the same map as plain
text for handing to an LLM (it is also what the AI assistant gets in its system prompt). Change the code,
and the docs change with it — `AppMapTest` fails if they ever disagree.

## Architecture notes

- **Autoloading** — `app/core/autoloader.php` registers an `spl_autoload_register`
  that maps class names to `core/`, `models/` and `controllers/`. Views may call
  model static helpers (`Order::statusLabel()`, `Attachment::fileIcon()`…) safely;
  controllers never need to "pre-load" classes before rendering.
- **Views are dumb** — they never instantiate models or query the DB; controllers
  pass every array they need (including attachments).
- **One row per person** — `users` is the only place a person's name/contact details live.
  Anything that needs "who" stores `users.id` and resolves the rest at read time (JOINs in the
  models, plus `ownerLabel()` / `ownerBlock()` for detail pages). `SettingTest` asserts no business
  table grows an `owner_name`-style column, which is what would silently break Settings → 负责人 sync.

## Extending the database

- **New table / index / trigger** → add it to `database/schema.sql`, then run `php database/migrate.php`.
  schema.sql is re-applied on every run, so new tables appear on both fresh and existing DBs.
- **Structural change to an existing table** (e.g. `ALTER TABLE … ADD COLUMN`) → create a new
  numbered file `database/migrations/NNN_name.sql`; the runner applies it exactly once and records
  it in the `_migrations` table. See `database/migrations/README.md`.
  Keep `schema.sql` in sync as well (fresh DBs get the column from the baseline); when a migration
  contains *only* `ADD COLUMN` statements and every column already exists, the runner prints
  `skipped: NNN_name.sql` and just records it — no `duplicate column name` failure on a fresh
  database, while legacy databases (column missing) are still upgraded for real.

## Extending the app

Adding a new resource (say, "Tasks") follows the same 4 steps every time:

1. Add a table to `database/schema.sql`, run `php database/migrate.php`
2. Create `app/models/Task.php extends Model` with `protected string $table = 'tasks';`
3. Create `app/controllers/TaskController.php extends Controller` with `index/create/store/edit/update/destroy`
4. Register the routes in `app/routes.php` and add views under `app/views/tasks/`

No other file needs to change — routing, DB access, and layout wrapping all come for free from the core classes.

## Security notes

- Passwords are hashed with `password_hash()` (bcrypt).
- All forms include CSRF tokens, verified on every POST/PUT/DELETE.
- All SQL goes through PDO prepared statements.
- Change `APP_ENV` to `production` in `config.php` before deploying (disables verbose error output).
- The AI assistant cannot execute arbitrary SQL or tools: it answers with a JSON plan that is checked against a
  hard-coded whitelist (`Ai::tools()`), the caller's data permissions, and value ranges, and every run is audited
  in `ai_actions`. Written data always goes through the existing models, never through model-generated SQL.
- Rotate the demo admin password (or delete the seed user) before using this beyond local development.

## 版权 / Copyright

Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

- Every source file (PHP / SQL / CSS / Markdown / `.htaccess` / `.env.example`) carries this notice in its
  header, so the attribution survives being copied out of the repository.
- The line shown in the UI — sidebar footer, login page footer, `<meta name="copyright">` — comes from the
  `版权信息` entry under 设置 → 应用信息, so a deployment can show its own legal entity without a code change.
- Third-party assets stay under their own licenses: Bootstrap 5 (MIT), Bootstrap Icons (MIT).
