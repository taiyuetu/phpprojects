# MiniCRM — PHP MVC CRM Application

A lightweight, dependency-free CRM built on a small custom PHP MVC framework.
No Composer packages required — just PHP and SQLite. Designed to be easy to
read, extend, and maintain: every layer has one job and the folders map
directly to the MVC pattern.

## Features

- **Auth** — register / login / logout, bcrypt password hashing, CSRF-protected forms, session-based auth guard
- **Customers** — full CRUD, search, activity/notes timeline, related leads & deals
- **Leads** — full CRUD, status filter (new / contacted / qualified / lost), one-click "convert to deal"
- **Deals** — full CRUD, kanban-style pipeline board grouped by stage, pipeline value totals
- **Dashboard** — key stats (customers, open leads, pipeline value) and recent activity
- Clean Bootstrap 5 UI, responsive sidebar layout

## Tech / Architecture

```
crm/
├── app/
│   ├── config/config.php     # DB path & app settings (env-var driven)
│   ├── core/                 # The "framework": Router, Controller, Model, Database, helpers
│   ├── controllers/          # One controller per resource (Auth, Dashboard, Customer, Lead, Deal)
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

## Requirements

- PHP 8.0+ with the `pdo_sqlite` extension enabled
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
controller pre-loading), attachment copy-on-convert, and an HTTP smoke test that
logs in through the real Router → Controller → View stack.

## Architecture notes

- **Autoloading** — `app/core/autoloader.php` registers an `spl_autoload_register`
  that maps class names to `core/`, `models/` and `controllers/`. Views may call
  model static helpers (`Order::statusLabel()`, `Attachment::fileIcon()`…) safely;
  controllers never need to "pre-load" classes before rendering.
- **Views are dumb** — they never instantiate models or query the DB; controllers
  pass every array they need (including attachments).

## Extending it

## Extending the database

- **New table / index / trigger** → add it to `database/schema.sql`, then run `php database/migrate.php`.
  schema.sql is re-applied on every run, so new tables appear on both fresh and existing DBs.
- **Structural change to an existing table** (e.g. `ALTER TABLE … ADD COLUMN`) → create a new
  numbered file `database/migrations/NNN_name.sql`; the runner applies it exactly once and records
  it in the `_migrations` table. See `database/migrations/README.md`.

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
- Rotate the demo admin password (or delete the seed user) before using this beyond local development.
