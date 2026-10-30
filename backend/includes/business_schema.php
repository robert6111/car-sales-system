<?php
/**
 * Non-destructive business schema extension.
 * Runs after the normal database connection and only adds missing columns/tables.
 * Existing car, cost, sale, user and backup data is preserved.
 */
if (!isset($conn) || !($conn instanceof mysqli)) {
    return;
}

function car_sales_column_exists(mysqli $conn, string $table, string $column): bool {
    $tableSafe = $conn->real_escape_string($table);
    $columnSafe = $conn->real_escape_string($column);
    $r = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
    return $r && $r->num_rows > 0;
}

function car_sales_add_column(mysqli $conn, string $table, string $column, string $definition): void {
    if (!car_sales_column_exists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

// Optional vehicle sales-specific details. Original required fields remain unchanged.
car_sales_add_column($conn, 'cars', 'stock_type', "enum('OWNED','CONSIGNMENT','TRADE_IN') NOT NULL DEFAULT 'OWNED' AFTER `status`");
car_sales_add_column($conn, 'cars', 'make', "varchar(80) DEFAULT NULL AFTER `car_name`");
car_sales_add_column($conn, 'cars', 'model', "varchar(80) DEFAULT NULL AFTER `make`");
car_sales_add_column($conn, 'cars', 'model_year', "smallint DEFAULT NULL AFTER `model`");
car_sales_add_column($conn, 'cars', 'color', "varchar(50) DEFAULT NULL AFTER `model_year`");
car_sales_add_column($conn, 'cars', 'mileage', "int DEFAULT NULL AFTER `color`");
car_sales_add_column($conn, 'cars', 'transmission', "varchar(40) DEFAULT NULL AFTER `mileage`");
car_sales_add_column($conn, 'cars', 'fuel_type', "varchar(40) DEFAULT NULL AFTER `transmission`");
car_sales_add_column($conn, 'cars', 'engine_number', "varchar(80) DEFAULT NULL AFTER `chassis_number`");
car_sales_add_column($conn, 'cars', 'asking_price', "decimal(12,2) DEFAULT NULL AFTER `purchase_price`");
car_sales_add_column($conn, 'cars', 'supplier_name', "varchar(120) DEFAULT NULL AFTER `purchase_date`");
car_sales_add_column($conn, 'cars', 'supplier_phone', "varchar(40) DEFAULT NULL AFTER `supplier_name`");
car_sales_add_column($conn, 'cars', 'owner_name', "varchar(120) DEFAULT NULL AFTER `supplier_phone`");
car_sales_add_column($conn, 'cars', 'owner_phone', "varchar(40) DEFAULT NULL AFTER `owner_name`");
car_sales_add_column($conn, 'cars', 'yard_location', "varchar(120) DEFAULT NULL AFTER `owner_phone`");
car_sales_add_column($conn, 'cars', 'vehicle_notes', "text DEFAULT NULL AFTER `yard_location`");

// Customer relationship records.
$conn->query("CREATE TABLE IF NOT EXISTS `customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `national_id` varchar(80) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `customer_type` enum('BUYER','SELLER','BOTH') NOT NULL DEFAULT 'BUYER',
  `source` varchar(80) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customers_phone_idx` (`phone`),
  KEY `customers_created_by_idx` (`created_by`),
  CONSTRAINT `customers_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Expand the existing leads table without removing its original columns.
car_sales_add_column($conn, 'leads', 'customer_id', "int DEFAULT NULL AFTER `id`");
car_sales_add_column($conn, 'leads', 'email', "varchar(150) DEFAULT NULL AFTER `phone`");
car_sales_add_column($conn, 'leads', 'source', "varchar(80) DEFAULT NULL AFTER `email`");
car_sales_add_column($conn, 'leads', 'budget_min', "decimal(12,2) DEFAULT NULL AFTER `source`");
car_sales_add_column($conn, 'leads', 'budget_max', "decimal(12,2) DEFAULT NULL AFTER `budget_min`");
car_sales_add_column($conn, 'leads', 'lead_stage', "enum('NEW','CONTACTED','FOLLOW_UP','NEGOTIATING','WON','LOST') NOT NULL DEFAULT 'NEW' AFTER `budget_max`");
car_sales_add_column($conn, 'leads', 'next_follow_up', "date DEFAULT NULL AFTER `lead_stage`");
car_sales_add_column($conn, 'leads', 'notes', "text DEFAULT NULL AFTER `next_follow_up`");
car_sales_add_column($conn, 'leads', 'lost_reason', "varchar(255) DEFAULT NULL AFTER `notes`");
car_sales_add_column($conn, 'leads', 'created_by', "int DEFAULT NULL AFTER `lost_reason`");
car_sales_add_column($conn, 'leads', 'updated_at', "timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");

// Dalali are business contacts, not system users. They do not receive login access by default.
$conn->query("CREATE TABLE IF NOT EXISTS `dalalis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `phone` varchar(40) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `national_id` varchar(80) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `default_commission_type` enum('FIXED','PERCENT') NOT NULL DEFAULT 'FIXED',
  `default_commission_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `dalalis_phone_idx` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

car_sales_add_column($conn, 'leads', 'dalali_id', "int DEFAULT NULL AFTER `source`");

$conn->query("CREATE TABLE IF NOT EXISTS `dalali_referrals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dalali_id` int NOT NULL,
  `lead_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `car_id` int DEFAULT NULL,
  `status` enum('OPEN','WON','LOST') NOT NULL DEFAULT 'OPEN',
  `notes` text DEFAULT NULL,
  `referred_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `referral_dalali_idx` (`dalali_id`),
  KEY `referral_lead_idx` (`lead_id`),
  KEY `referral_customer_idx` (`customer_id`),
  KEY `referral_car_idx` (`car_id`),
  CONSTRAINT `referral_dalali_fk` FOREIGN KEY (`dalali_id`) REFERENCES `dalalis` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referral_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `referral_car_fk` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Reservations / deposits keep a car commercially available in the original stock logic while recording the hold separately.
$conn->query("CREATE TABLE IF NOT EXISTS `reservations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `car_id` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `customer_name` varchar(120) NOT NULL,
  `customer_phone` varchar(40) DEFAULT NULL,
  `deposit_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reserve_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('ACTIVE','CONVERTED','CANCELLED','EXPIRED','REFUNDED') NOT NULL DEFAULT 'ACTIVE',
  `notes` text DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `reservations_car_idx` (`car_id`),
  KEY `reservations_customer_idx` (`customer_id`),
  CONSTRAINT `reservations_car_fk` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reservations_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reservations_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Extend sales for customer, dalali and payment tracking.
car_sales_add_column($conn, 'car_sales', 'customer_id', "int DEFAULT NULL AFTER `car_id`");
car_sales_add_column($conn, 'car_sales', 'dalali_id', "int DEFAULT NULL AFTER `customer_id`");
car_sales_add_column($conn, 'car_sales', 'reservation_id', "int DEFAULT NULL AFTER `dalali_id`");
car_sales_add_column($conn, 'car_sales', 'payment_status', "enum('PAID','PARTIAL','UNPAID') NOT NULL DEFAULT 'PAID' AFTER `sale_date`");
car_sales_add_column($conn, 'car_sales', 'payment_method', "varchar(60) DEFAULT NULL AFTER `payment_status`");

$conn->query("CREATE TABLE IF NOT EXISTS `sale_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` varchar(60) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payments_sale_idx` (`sale_id`),
  CONSTRAINT `payments_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `car_sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_user_fk` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS `dalali_commissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dalali_id` int NOT NULL,
  `sale_id` int NOT NULL,
  `car_id` int NOT NULL,
  `cost_id` int DEFAULT NULL,
  `commission_type` enum('FIXED','PERCENT') NOT NULL DEFAULT 'FIXED',
  `commission_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('PENDING','PAID','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `due_date` date DEFAULT NULL,
  `paid_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commission_sale_unique` (`sale_id`),
  KEY `commission_dalali_idx` (`dalali_id`),
  KEY `commission_car_idx` (`car_id`),
  CONSTRAINT `commission_dalali_fk` FOREIGN KEY (`dalali_id`) REFERENCES `dalalis` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commission_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `car_sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commission_car_fk` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commission_cost_fk` FOREIGN KEY (`cost_id`) REFERENCES `car_costs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Car document checklist (metadata only; keeps installation simple and local).
$conn->query("CREATE TABLE IF NOT EXISTS `car_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `car_id` int NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `document_number` varchar(100) DEFAULT NULL,
  `status` enum('AVAILABLE','PENDING','MISSING') NOT NULL DEFAULT 'AVAILABLE',
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `documents_car_idx` (`car_id`),
  CONSTRAINT `documents_car_fk` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Test-drive register: a normal showroom sales workflow.
$conn->query("CREATE TABLE IF NOT EXISTS `test_drives` (
  `id` int NOT NULL AUTO_INCREMENT,
  `car_id` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `customer_name` varchar(120) NOT NULL,
  `customer_phone` varchar(40) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `test_date` datetime NOT NULL,
  `outcome` enum('SCHEDULED','COMPLETED','CANCELLED','INTERESTED','NOT_INTERESTED') NOT NULL DEFAULT 'SCHEDULED',
  `notes` text DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `test_car_idx` (`car_id`),
  KEY `test_customer_idx` (`customer_id`),
  CONSTRAINT `test_car_fk` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `test_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `test_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// One-time migration marker prevents genuinely unpaid NEW sales from being mistaken for legacy sales.
$conn->query("CREATE TABLE IF NOT EXISTS `system_migrations` (
  `migration_key` varchar(120) NOT NULL,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$migrationCheck = $conn->query("SELECT migration_key FROM system_migrations WHERE migration_key='business_v2_payment_backfill' LIMIT 1");
if (!$migrationCheck || $migrationCheck->num_rows === 0) {
    // Sales created before payment tracking existed are carried forward as fully paid once.
    $conn->query("INSERT INTO sale_payments (sale_id, amount, payment_date, method, notes)
                 SELECT cs.id, cs.sale_price, cs.sale_date, 'Legacy / Existing Sale', 'Automatically carried forward during business module upgrade'
                 FROM car_sales cs
                 LEFT JOIN sale_payments sp ON sp.sale_id = cs.id
                 WHERE sp.id IS NULL");
    $conn->query("INSERT IGNORE INTO system_migrations (migration_key) VALUES ('business_v2_payment_backfill')");
}

// Keep payment status aligned with recorded payments.
$conn->query("UPDATE car_sales cs
             LEFT JOIN (SELECT sale_id, SUM(amount) paid FROM sale_payments GROUP BY sale_id) p ON p.sale_id = cs.id
             SET cs.payment_status = CASE
               WHEN IFNULL(p.paid,0) >= cs.sale_price THEN 'PAID'
               WHEN IFNULL(p.paid,0) > 0 THEN 'PARTIAL'
               ELSE 'UNPAID' END");

// Production-ready business settings, audit, voiding and import support.
// Newly imported vehicles may not have a registration plate yet. Chassis number remains mandatory/unique.
$conn->query("UPDATE cars SET plate_number=NULL WHERE plate_number='' OR TRIM(plate_number)=''");
$plateInfo = $conn->query("SHOW COLUMNS FROM cars LIKE 'plate_number'");
if ($plateInfo && ($plateCol = $plateInfo->fetch_assoc())) {
    if (stripos((string)$plateCol['Null'], 'YES') === false) {
        $conn->query("ALTER TABLE cars MODIFY plate_number varchar(50) NULL DEFAULT NULL");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS `company_settings` (
  `id` tinyint NOT NULL DEFAULT 1,
  `company_name` varchar(150) NOT NULL DEFAULT 'Car Sales System',
  `tagline` varchar(180) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `tin_number` varchar(100) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `currency` varchar(12) NOT NULL DEFAULT 'TZS',
  `logo_path` varchar(255) DEFAULT 'backend/includes/images/logo.png',
  `sale_terms` text DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `company_settings_user_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$conn->query("INSERT IGNORE INTO company_settings (id,company_name,currency,sale_terms) VALUES (1,'Car Sales System','TZS','The buyer confirms that the vehicle details and agreed price stated in this agreement are correct. Any outstanding balance remains payable according to the agreed payment arrangement. Vehicle handover and ownership-transfer documents shall be completed according to the parties'' agreed process and applicable requirements.')");

$conn->query("CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` varchar(80) DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `before_data` longtext DEFAULT NULL,
  `after_data` longtext DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_user_idx` (`user_id`),
  KEY `audit_entity_idx` (`entity_type`,`entity_id`),
  KEY `audit_created_idx` (`created_at`),
  CONSTRAINT `audit_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS `voided_sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `original_sale_id` int NOT NULL,
  `car_id` int NOT NULL,
  `sale_date` date DEFAULT NULL,
  `buyer_name` varchar(120) DEFAULT NULL,
  `sale_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `void_reason` varchar(500) NOT NULL,
  `voided_by` int DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sale_payload` longtext DEFAULT NULL,
  `payments_payload` longtext DEFAULT NULL,
  `commission_payload` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `voided_car_idx` (`car_id`),
  KEY `voided_original_idx` (`original_sale_id`),
  CONSTRAINT `voided_car_fk` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `voided_user_fk` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

car_sales_add_column($conn, 'dalali_commissions', 'payment_method', "varchar(60) DEFAULT NULL AFTER `paid_date`");
car_sales_add_column($conn, 'dalali_commissions', 'payment_reference', "varchar(120) DEFAULT NULL AFTER `payment_method`");
car_sales_add_column($conn, 'dalali_commissions', 'paid_by', "int DEFAULT NULL AFTER `payment_reference`");

$conn->query("CREATE TABLE IF NOT EXISTS `import_batches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `import_type` varchar(60) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `rows_total` int NOT NULL DEFAULT 0,
  `rows_imported` int NOT NULL DEFAULT 0,
  `rows_skipped` int NOT NULL DEFAULT 0,
  `error_summary` longtext DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `import_user_idx` (`created_by`),
  CONSTRAINT `import_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

?>
