# Changelog

All notable changes to the PSI System are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this
project adheres to [Semantic Versioning](https://semver.org/).

## [1.4.0] - 2026-08-29

### Added
- **Purchase Order arrival tracking** — added new fields to Purchase Orders:
  - Expected Arrival Date (预计到货日期)
  - Notes (备注信息)
- **Partial arrival support** — new `purchase_arrivals` table to record multiple
  arrivals per Purchase Order. Each arrival records the date, quantity, notes,
  and the user who recorded it. Product stock is updated automatically with
  each arrival.
- **Arrival history view** — Purchase Order detail page now shows a complete
  arrival history table with all recorded arrivals, plus a form to record new
  arrivals.
- **Arrived Qty column** on Purchase Orders list page showing cumulative
  arrived quantity for each order.
- **PurchaseItem model** — new model for purchase line items with
  `byPurchase()` helper.
- **PurchaseArrival model** — new model with `byPurchase()`,
  `totalArrivedQty()`, and `recordArrival()` methods. The `recordArrival()`
  method handles stock updates atomically in a transaction.

### Changed
- **Stock update logic** — product stock is no longer increased when a Purchase
  Order is created. Stock is only increased when arrivals are recorded,
  following the actual business flow (order → receive → update stock).
- `Purchase::withItems()` now includes `arrivals` and `total_arrived_qty` data.
- `Purchase::filterPaginated()` includes a subquery for `total_arrived_qty`.
- Purchase Order form labels now include Chinese translations for clarity.

### Database
- Added `purchase_arrivals` table with foreign keys to `purchases` and `users`.
- Migration script: `database/add_purchase_arrivals.php`

## [1.3.0] - 2026-08-28

### Added
- **Product search on purchase/sale forms** — replaced the product `<select>`
  dropdown with a search-as-you-type autocomplete input + a traditional select
  fallback. Users can either type to search by name/SKU or browse the full
  dropdown list. Selecting a product auto-fills the unit cost/price.
- **Separated add-item area from items list** on purchase and sale forms. The
  "Add Item" section (search + qty + cost + Add button) is visually distinct
  from the items table below. Items in the list show the product name as
  read-only text with only a delete button — no re-selection. Adding the same
  product again increments the quantity on the existing row.
- **Date range filtering** on Purchase Orders and Sales Invoices list pages.
  Filter bar includes From/To date inputs, a Filter button, and a Clear button
  that appears when any filter is active.
- **Pagination (20 items/page)** on every list page: Products, Categories,
  Suppliers, Customers, Purchases, Sales, Inventory Ledger, and Change Logs.
  Includes page numbers with ellipsis for large ranges, prev/next buttons, and
  a "Showing X–Y of Z" info label. All active filters are preserved when
  navigating between pages.
- **Reusable pagination partial** (`partials/pagination.php`) that auto-detects
  the current URL and query parameters.

### Changed
- `Product::filterPaginated()` returns `['rows', 'total', 'page', 'perPage',
  'pages']` for paginated product queries.
- `Purchase::filterPaginated()` and `Sale::filterPaginated()` support combined
  text search + date range + pagination.
- `HasCustomFields::filterWithCustomFieldsPaginated()` added to the trait,
  shared by Category, Supplier, and Customer.
- `InventoryTransaction::filterPaginated()` and
  `ChangeLog::getAllPaginated()` / `getRecentChangesPaginated()` added.
- All list controllers updated to read a `page` query parameter and pass
  `$pagination` data to views.

### Fixed
- Database setup: `database.sqlite` was 0 bytes on fresh clone. The setup
  script (`database/setup.php`) now needs to be run once to apply the schema
  and seed the default admin user.

## [1.2.0] - 2026-08-27

### Added
- **Product gallery** — upload multiple images per product, show thumbnails in
  the products list, and view them in a lightbox (prev/next + keyboard
  navigation).
- **Search** on every listing page: products, categories, suppliers, customers,
  purchases, sales, and the inventory ledger.
- **Product CSV import/export**, including ZIP archives that bundle a CSV with
  an `images/` folder whose files are matched to products by SKU (e.g.
  `tqb0001-1.jpg`, `tqb0001(1).jpg` → SKU `tqb0001`).
- **Supplier & Customer CSV import/export** through a shared `CsvImportExport`
  trait.
- **Product list filter form** (search + category + stock status), where the
  filtered result set is also exportable to CSV.
- **Data-driven custom fields** for products, suppliers, customers and
  categories. Fields are declared once per model and automatically rendered in
  the form, list, filter and CSV; values live in a JSON `attributes` column and
  are filtered with SQLite `json_extract`.
- **`$fillable` whitelist** on every model to prevent mass-assignment.
- **Friendly validation** — duplicate SKU/name checks return flash messages
  instead of raw errors, plus a global exception handler and a `debug` config
  flag so production never shows stack traces.

### Changed
- Extracted CSV import/export into a reusable `CsvImportExport` trait.
- Extracted custom-field logic into a `HasCustomFields` trait and shared view
  partials (`partials/custom_fields_*`).
- `Product::filter()` now handles search, category, stock status and custom
  fields through a single query path.

### Fixed
- Product update now guards against a missing product before editing.

## [1.1.1] - baseline

Initial feature set: dashboard; products, categories, suppliers and customers
CRUD; purchases and sales with automatic stock movement and an inventory
ledger; reports; session authentication with CSRF protection; and a change-log
audit trail with data compression and archiving.
