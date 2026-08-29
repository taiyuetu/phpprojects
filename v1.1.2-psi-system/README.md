# PSI System — Purchase · Sales · Inventory

A clean, dependency-free PHP MVC web application for managing purchases, sales,
products, and stock levels. Built to be **easy to run, read, and extend** —
no Composer, no framework lock-in, just plain PHP organized the right way.

---

## Features

- **Dashboard** — revenue, spend, stock value, low-stock alerts, recent stock movements
- **Products** — full CRUD, SKU, category, cost/sale price, reorder level, per-product stock history
- **Categories, Suppliers, Customers** — simple CRUD masters
- **Purchases** — multi-line purchase orders with expected arrival date and notes;
  supports **partial arrivals** — record multiple arrivals over time, each
  automatically updating product stock. Full arrival history is tracked per order.
- **Sales** — multi-line sales invoices; stock automatically decreases on save, with
  server-side validation that blocks overselling (atomic DB transaction, rolls back on failure)
- **Inventory Ledger** — full audit trail of every stock movement (purchase / sale / manual adjustment)
- **Reports** — sales report, purchase report, and stock valuation report, each with date filtering
- **Auth** — simple session-based login, CSRF protection on every form

---

## Quick Start

**Requirements:** PHP 8.0+ with the `pdo_sqlite` extension (bundled with PHP by default).

```bash
# 1. Initialize the database (creates SQLite file + default admin + demo data)
php database/setup.php

# 2. Start the built-in PHP server, pointed at the public/ folder
php -S localhost:8000 -t public

# 3. Open the app
open http://localhost:8000
```

**Default login:** `admin@psi.local` / `admin123`

### Using Apache/Nginx instead
Point your virtual host's document root at `public/` and make sure
`public/.htaccess` is honored (Apache: `AllowOverride All` + `mod_rewrite` enabled).
For Nginx, add an equivalent `try_files $uri $uri/ /index.php?$query_string;` rule.

### Switching to MySQL
Edit `config/config.php`:
```php
'db' => [
    'driver' => 'mysql',
    'host' => '127.0.0.1', 'name' => 'psi_system', 'user' => 'root', 'pass' => '...'
],
```
Then import `database/schema.sql` into MySQL (adjust `AUTOINCREMENT` → `AUTO_INCREMENT`
and `TEXT` → `VARCHAR` where you'd like stricter typing — SQLite is forgiving either way)
and run `database/setup.php` again to seed the admin user.

---

## Project Structure

```
psi-system/
├── app/
│   ├── Core/              # The "framework": Router, Model, Controller, Database, Auth
│   ├── Controllers/       # One controller per module (thin — delegates to Models)
│   ├── Models/            # One model per table + the business logic that touches the DB
│   └── Views/             # Plain PHP templates, grouped by module; layouts/main.php wraps them
├── config/config.php      # App name, DB driver/credentials, session name
├── database/
│   ├── schema.sql         # Full table definitions
│   └── setup.php          # Run once to create + seed the database
├── public/                # Web root — only this folder should be exposed by your server
│   ├── index.php          # Front controller: bootstraps autoload, session, routes
│   ├── .htaccess          # Apache clean-URL rewrite rules
│   └── assets/css/style.css
└── routes/web.php         # Every URL → Controller@method, in one file
```

### Why this shape is easy to maintain

- **No magic.** The router is ~50 lines and the base Model/Controller are each
  under 100 lines. A new developer can read the whole framework in Core/ in
  under 15 minutes — there's no hidden convention to reverse-engineer.
- **One responsibility per file.** Controllers stay thin (validate input,
  call a Model method, redirect). All business rules — like "a sale can't
  exceed available stock" — live in the Model (`Sale::createWithItems()`),
  so they can't be bypassed by another controller and are easy to unit test.
- **Consistent CRUD pattern.** Every simple entity (Category, Supplier,
  Customer) follows the exact same index/create/store/edit/update/delete
  shape. Once you understand one, you understand all of them — and adding
  a new master-data type is a copy-paste-rename exercise.
- **Single source of truth for stock.** All stock changes — whether from a
  purchase arrival, a sale, or a manual product edit — flow through
  `Product::increaseStock()` / `Product::decreaseStock()`, which always
  write a row to `inventory_transactions`. That's what powers the ledger,
  the dashboard's "recent movements," and the per-product history — for free,
  from one code path. Purchase arrivals are tracked separately in
  `purchase_arrivals`, allowing multiple partial deliveries per order.
- **Plain SQL, no ORM magic.** `Model::raw()` lets any model run a hand-written
  query for joins/aggregates without fighting a query builder. You can always
  see exactly what SQL runs.

---

## How to Extend

**Add a new module (e.g. "Warehouses"):**
1. Add a table to `database/schema.sql` (and re-run `setup.php`, or `ALTER TABLE` manually).
2. Create `app/Models/Warehouse.php` extending `App\Core\Model` with `protected static string $table = 'warehouses';`.
3. Create `app/Controllers/WarehouseController.php` — copy `CategoryController.php` as a template.
4. Create views under `app/Views/warehouses/` — copy `app/Views/categories/`.
5. Add routes in `routes/web.php`.
6. Add a sidebar link in `app/Views/layouts/main.php`.

**Add a new report:** add a method to `ReportController.php`, a route, and a view under `app/Views/reports/`.

**Change the look:** everything is themed through CSS variables at the top of
`public/assets/css/style.css` — no build step, no preprocessor.

---

## Security Notes

- Passwords are hashed with `password_hash()` / verified with `password_verify()`.
- All state-changing forms include a CSRF token, checked via `verifyCsrf()`.
- All SQL uses parameterized queries (PDO prepared statements) — no string-concatenated SQL.
- Every route except `/login` requires an authenticated session (enforced in `public/index.php`).

This is a solid foundation for an internal tool; for a public-facing production
deployment you'd want to add things like role-based permissions (the `role`
column on `users` is a starting point), rate limiting on login, and HTTPS enforcement.
