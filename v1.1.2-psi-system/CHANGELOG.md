# Changelog

All notable changes to the PSI System are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this
project adheres to [Semantic Versioning](https://semver.org/).

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
