CAR SALES SYSTEM - XAMPP INSTALLATION / UPGRADE

A) EXISTING INSTALLATION WITH REAL DATA
---------------------------------------
1. Back up the existing car_sales_system folder and database first.
2. Replace/update the application files with this build.
3. DO NOT re-import backend/database/database.sql into a database that already contains real data.
4. Start Apache and MySQL in XAMPP.
5. Open the normal system URL and login.
6. The new car-sales business tables/columns are added automatically and non-destructively by:
   backend/includes/business_schema.php
7. Open Company Settings and enter the showroom/company details and logo.

B) FRESH INSTALLATION
---------------------
1. Copy the folder named car_sales_system into C:\xampp\htdocs\
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin.
4. Import: backend/database/database.sql
5. Open: http://localhost/car_sales_system/
6. Login with:
   Email: setup@carsales.local
   Password: SetupAdmin@2026!
7. The business extensions are created automatically on first use.
8. Open Company Settings and enter the real showroom/company details and logo.
9. Create the real admin user from Manage Users > Add User.
10. Logout and login with the real admin.
11. Disable the setup user from Manage Users.

RECOMMENDED BUSINESS TEST FLOW
------------------------------
Login
> Company Settings / Branding
> Add Car (plate number may be left blank for an unregistered/imported vehicle; chassis number is required)
> Add Vehicle Documents
> Add Customer or Sales Lead
> Add Dalali when a buyer is referred by an agent
> Schedule Test Drive
> Reserve Car / Record Deposit
> Convert Reservation to Sale
> Record full or partial payment
> Print Sale Receipt
> Print Vehicle Sale Agreement
> Check outstanding balances
> Pay dalali commission with payment method/reference and print commission receipt
> Use Activity Log to review important actions
> Void an incorrect sale from the sales register when necessary; a reason and archive record are kept
> View Reports
> Backup Now > Download Backup

EXCEL / CSV DATA IMPORT
-----------------------
Admin users can open: Import from Excel

Supported imports:
- Cars / stock
- Customers
- Dalali
- Sales leads
- Vehicle documents
- Vehicle costs
- Historical sales

Recommended import order:
Cars > Customers / Dalali > Costs / Documents / Leads > Historical Sales

Each import type includes a downloadable .xlsx template. Fill the first worksheet named "Import Data". The second worksheet is an example only and is not imported.

The importer converts spreadsheet rows into normal system records, so imported cars, customers, dalali, costs, documents, leads and sales can be searched, edited, reported on and used by the rest of the system.

Supported file formats: .xlsx and .csv
Old .xls files should first be opened in Excel or LibreOffice and saved as .xlsx.

For vehicle-linked imports, chassis number is the dependable matching field. Plate number is optional because newly imported/unregistered vehicles may not have one yet.

NOTES
-----
- Original cars, costs, sales, reports, users, security and backup features remain in place.
- Existing plate numbers remain unchanged. New/unregistered vehicles can be saved without a plate number.
- Chassis number remains mandatory and should be unique for vehicle identification/import matching.
- Dalali are external business contacts, NOT system users. They do not receive private system login access by default.
- Dalali commission is recorded as a vehicle cost so the existing profit logic includes the commission.
- Paid dalali commissions can record payment method/reference, who paid them and a printable receipt.
- Sale receipts, agreements and commission receipts use the company details configured under Company Settings.
- Voided sales are archived with the original transaction snapshot, reason, user and time instead of silently disappearing.
- Activity Log records important business actions for accountability.
