# Car Sales System

A PHP and MySQL showroom management system for a used-car dealer: vehicle stock and costs, customers and leads, brokers (dalali) and their commissions, reservations, sales with partial payments, and profit reports.

## Features

- **Inventory**: vehicles with plate and chassis numbers, purchase details, a document checklist, and every cost spent on each car
- **Customer CRM and leads**: customer records, a follow-up pipeline for sales leads, and a test-drive register
- **Dalali / sales agents**: broker records, commission payments with receipts, and commission reports
- **Reservations and deposits** for cars held for a customer
- **Sales register**: full, partial or unpaid sales, payment collection, sale agreements and receipts (PDF), and controlled sale voiding with an archive
- **Reports**: stock, old stock, sold cars, receivables, leads pipeline and commissions, with PDF export
- **Data migration**: Excel/CSV import centre with templates for cars, customers, brokers, leads, costs, documents and historical sales
- Company settings and branding, an activity/audit log, and admin-only backups

## Security highlights

- Central security layer in `backend/includes/security.php`
- Secure session cookies (HttpOnly, SameSite, Secure over HTTPS), strict session mode, session regeneration at login and a 30-minute idle timeout
- Login throttling (5 failed attempts, then a 15-minute lock) and rate-limited password reset and change
- CSRF tokens injected into all POST forms, and tokens on action links that change data
- Role and direct-URL permission checks; reports, backups and user management are admin-only
- Prepared statements for database queries and output escaping on every page
- Browser security headers and `.htaccess` protection for database, backup and include files

## Tech stack

PHP 8 (mysqli) · MySQL · HTML · CSS · JavaScript · FPDF/FPDI (PDF generation) · Apache (XAMPP)

## Setup

1. Import `backend/database/database.sql` into MySQL.
2. Set your database details in `includes/db.php`.
3. Serve the folder with Apache (for example XAMPP) and open it in the browser.
4. Sign in with the setup account listed in `README_INSTALL.txt`, create your real admin account, then disable the setup account.

> **Portfolio note.** This public copy contains no customer data or database backups. The PDF letterhead is a neutral placeholder.

More detail: `CAR_SALES_BUSINESS_EXTENSIONS.txt`, `SECURITY_CHANGES.txt` and `README_INSTALL.txt`.
