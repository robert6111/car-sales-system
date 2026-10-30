# Car Sales System

A showroom management system for a used-car dealer: vehicle stock and the costs spent on each car, customers and sales leads, brokers (dalali) and their commissions, reservations, sales with part payments, and profit reports.

## How it works

```
Lead / walk-in ──► Test drive ──► Reservation (deposit) ──┐
                                                          ▼
Car bought (IN_STOCK) ──► costs added ──►  SALE  ──► car marked SOLD
                                            │  ├──► payments ──► UNPAID / PARTIAL / PAID
                                            │  └──► dalali commission ──► recorded as a car cost
                                            └──► (void) ──► archived, car returned to IN_STOCK
```

**Profit is calculated per car** as sale price minus (purchase price + every cost recorded on the car). Broker commissions are saved as a car cost too, so profit is always net of commission.

**Recording a sale is all-or-nothing.** The sale runs inside one database transaction:

1. Insert the sale.
2. Mark the car `SOLD`, only if it is still `IN_STOCK`. If another user sold it a moment earlier, no row changes and the whole sale is cancelled.
3. Record the first payment.
4. Convert the reservation into the sale, again only if it hasn't changed in the meantime.
5. Record the dalali commission as both a commission entry and a car cost.
6. Recalculate the payment status (`UNPAID`, `PARTIAL` or `PAID`) from the sum of payments.
7. Commit. If any step fails, everything is rolled back and nothing is half-saved.

**Voiding a sale never deletes history.** The original sale, its payments and its commission are copied as JSON into `voided_sales` with the reason and the user who voided it. Only then are the live records removed and the car returned to stock, all in one transaction.

## Security design

All protection lives in `backend/includes/security.php`, loaded by every page:

- **Hardened sessions:** strict mode, HttpOnly and SameSite cookies (Secure over HTTPS), a new session ID at login, and logout after 30 minutes of inactivity.
- **Login lockout:** 5 failed attempts lock the login for 15 minutes. Password reset and password change have the same limit.
- **Role-based access by URL:** a permission map links every page to the roles allowed to use it. Admins get everything; staff cannot open reports, imports, settings, the audit log, backups or user management, even by typing the URL directly.
- **CSRF protection:** a 32-byte random token is checked on every POST with `hash_equals`.
- **Signed action links:** risky links, such as disabling a user or downloading a backup, carry an HMAC-SHA256 signature of the action and the record ID, keyed with the session's secret token. A link made for one record cannot be reused for another.
- **Backups** are admin-only and limited to 3 per hour; the backup folder is blocked from direct browser access.
- **Prepared statements** for queries and output escaped through a single `e()` helper on every page.
- **Audit log:** important changes are recorded with the user, the record, and the values before and after.
- **Security headers:** `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` and `Permissions-Policy`.

## Database design

| Area | Tables |
|---|---|
| Stock | `cars`, `car_costs`, `car_documents` |
| Selling | `customers`, `leads`, `test_drives`, `reservations`, `car_sales`, `sale_payments`, `voided_sales` |
| Brokers | `dalalis`, `dalali_referrals`, `dalali_commissions` |
| System | `users`, `company_settings`, `audit_logs`, `import_batches`, `system_migrations` |

The database upgrades itself safely: `backend/includes/business_schema.php` adds new tables and columns when they are missing and tracks completed upgrades in `system_migrations`, without deleting existing data.

## Project structure

```
includes/db.php             Database connection (runs the safe schema upgrade)
backend/includes/           security.php (security layer), business helpers, schema upgrade, Excel reader
backend/cars/ costs/        Vehicle stock and per-car costs
backend/sales/              Sales, payments, receipts, sale agreements and voiding
backend/dalali/             Brokers, commission payments and receipts
backend/customers/ leads/ testdrives/ reservations/   Customer pipeline
backend/reports/            Stock, old stock, sold cars, receivables, leads and commissions (with PDF)
backend/imports/            Excel/CSV import centre with templates
backend/audit/ settings/ users/ backup/   Admin areas
vendor/                     FPDF and FPDI (PDF generation)
```

## Tech stack

PHP 8 (mysqli) · MySQL · HTML · CSS · JavaScript · FPDF/FPDI · Apache (XAMPP)

## Setup

1. Import `backend/database/database.sql` into MySQL.
2. Set your database details in `includes/db.php`.
3. Copy the project into XAMPP's `htdocs` and open it in the browser.
4. Sign in with the setup account listed in `README_INSTALL.txt`, create your real admin account, then disable the setup account.

> **Portfolio note.** This public copy contains no customer data or database backups. The PDF letterhead is a neutral placeholder.

## Author

Robert Baraka Kunenga, Computer Engineering, OSTIM Technical University · [github.com/robert6111](https://github.com/robert6111)
