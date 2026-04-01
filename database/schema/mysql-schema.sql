/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `account_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `current_account_id` bigint unsigned NOT NULL,
  `voucher_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `transaction_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `exchange_rate` decimal(10,6) NOT NULL DEFAULT '1.000000',
  `transaction_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_transactions_current_account_id_foreign` (`current_account_id`),
  KEY `account_transactions_voucher_id_foreign` (`voucher_id`),
  KEY `account_transactions_order_id_foreign` (`order_id`),
  KEY `account_transactions_tenant_id_current_account_id_index` (`tenant_id`,`current_account_id`),
  KEY `account_transactions_tenant_id_transaction_date_index` (`tenant_id`,`transaction_date`),
  CONSTRAINT `account_transactions_current_account_id_foreign` FOREIGN KEY (`current_account_id`) REFERENCES `current_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_transactions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `account_transactions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_transactions_voucher_id_foreign` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `event` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_foreign` (`user_id`),
  KEY `activity_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `activity_logs_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  CONSTRAINT `activity_logs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `advances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `advances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `voucher_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `currency_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `requested_at` date NOT NULL,
  `repayment_date` date DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `advances_employee_id_foreign` (`employee_id`),
  KEY `advances_approved_by_foreign` (`approved_by`),
  KEY `advances_voucher_id_foreign` (`voucher_id`),
  KEY `advances_tenant_id_employee_id_index` (`tenant_id`,`employee_id`),
  KEY `advances_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `advances_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `advances_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `advances_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `advances_voucher_id_foreign` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `app_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `data` json DEFAULT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_notifications_user_id_is_read_created_at_index` (`user_id`,`is_read`,`created_at`),
  KEY `app_notifications_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  CONSTRAINT `app_notifications_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `app_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_statement_csv_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_statement_csv_imports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `row_count` smallint unsigned NOT NULL,
  `rows` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_statement_csv_imports_user_id_foreign` (`user_id`),
  KEY `bank_statement_csv_imports_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  CONSTRAINT `bank_statement_csv_imports_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_statement_csv_imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_registers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_registers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `current_balance` decimal(18,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `description` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_registers_tenant_id_code_unique` (`tenant_id`,`code`),
  KEY `cash_registers_tenant_id_is_active_index` (`tenant_id`,`is_active`),
  CONSTRAINT `cash_registers_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chart_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chart_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chart_accounts_tenant_id_code_unique` (`tenant_id`,`code`),
  CONSTRAINT `chart_accounts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `current_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `current_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `employee_id` bigint unsigned DEFAULT NULL,
  `vehicle_id` bigint unsigned DEFAULT NULL,
  `account_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `currency_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `credit_limit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payment_term_days` smallint unsigned NOT NULL DEFAULT '30',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `current_accounts_customer_id_foreign` (`customer_id`),
  KEY `current_accounts_employee_id_foreign` (`employee_id`),
  KEY `current_accounts_vehicle_id_foreign` (`vehicle_id`),
  KEY `current_accounts_tenant_id_account_type_index` (`tenant_id`,`account_type`),
  KEY `current_accounts_tenant_id_customer_id_index` (`tenant_id`,`customer_id`),
  CONSTRAINT `current_accounts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `current_accounts_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `current_accounts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `current_accounts_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. Main Warehouse, HQ, Site B',
  `address_line` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TR',
  `contact_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_addresses_customer_id_foreign` (`customer_id`),
  KEY `customer_addresses_tenant_id_customer_id_index` (`tenant_id`,`customer_id`),
  CONSTRAINT `customer_addresses_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_addresses_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `partner_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SAP BP / iş ortağı no',
  `tax_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trade_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_term_days` smallint unsigned NOT NULL DEFAULT '30',
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customers_tenant_id_legal_name_index` (`tenant_id`,`legal_name`),
  CONSTRAINT `customers_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_numbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_numbers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `pin_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sas_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SAP SAS ile eşleşme',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `order_id` bigint unsigned DEFAULT NULL,
  `shipment_id` bigint unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_numbers_tenant_id_pin_code_unique` (`tenant_id`,`pin_code`),
  KEY `delivery_numbers_order_id_foreign` (`order_id`),
  KEY `delivery_numbers_shipment_id_foreign` (`shipment_id`),
  KEY `delivery_numbers_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `delivery_numbers_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_numbers_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_numbers_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `national_id` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'T.C. kimlik no',
  `blood_group` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kan grubu',
  `is_driver` tinyint(1) NOT NULL DEFAULT '0',
  `license_class` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ehliyet sınıfı',
  `license_valid_until` date DEFAULT NULL,
  `src_valid_until` date DEFAULT NULL COMMENT 'SRC belge bitiş',
  `psychotechnical_valid_until` date DEFAULT NULL COMMENT 'Psikoteknik bitiş',
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_tenant_id_national_id_unique` (`tenant_id`,`national_id`),
  KEY `employees_user_id_foreign` (`user_id`),
  CONSTRAINT `employees_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fiscal_opening_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fiscal_opening_balances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `chart_account_id` bigint unsigned NOT NULL,
  `fiscal_year` smallint unsigned NOT NULL,
  `opening_debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `opening_credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fob_tenant_acct_year_uq` (`tenant_id`,`chart_account_id`,`fiscal_year`),
  KEY `fiscal_opening_balances_chart_account_id_foreign` (`chart_account_id`),
  CONSTRAINT `fiscal_opening_balances_chart_account_id_foreign` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fiscal_opening_balances_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fuel_intakes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fuel_intakes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `vehicle_id` bigint unsigned NOT NULL,
  `liters` decimal(14,3) NOT NULL,
  `odometer_km` decimal(14,2) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fuel_intakes_vehicle_id_foreign` (`vehicle_id`),
  KEY `fuel_intakes_tenant_id_recorded_at_index` (`tenant_id`,`recorded_at`),
  CONSTRAINT `fuel_intakes_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fuel_intakes_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fuel_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fuel_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `fuel_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,4) NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `recorded_at` date NOT NULL,
  `source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fuel_prices_tenant_id_recorded_at_index` (`tenant_id`,`recorded_at`),
  CONSTRAINT `fuel_prices_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `sku` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg',
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_items_tenant_id_sku_unique` (`tenant_id`,`sku`),
  CONSTRAINT `inventory_items_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_stocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_stocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `quantity` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_stocks_warehouse_id_inventory_item_id_unique` (`warehouse_id`,`inventory_item_id`),
  KEY `inventory_stocks_tenant_id_foreign` (`tenant_id`),
  KEY `inventory_stocks_inventory_item_id_foreign` (`inventory_item_id`),
  CONSTRAINT `inventory_stocks_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_stocks_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_stocks_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `entry_date` date NOT NULL,
  `reference` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `memo` text COLLATE utf8mb4_unicode_ci,
  `source_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_key` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `journal_entries_tenant_source_unique` (`tenant_id`,`source_type`,`source_key`),
  KEY `journal_entries_user_id_foreign` (`user_id`),
  CONSTRAINT `journal_entries_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_entries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `journal_entry_id` bigint unsigned NOT NULL,
  `chart_account_id` bigint unsigned NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_lines_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `journal_lines_chart_account_id_foreign` (`chart_account_id`),
  CONSTRAINT `journal_lines_chart_account_id_foreign` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `journal_lines_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `leaves` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_count` smallint unsigned NOT NULL DEFAULT '1',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leaves_employee_id_foreign` (`employee_id`),
  KEY `leaves_approved_by_foreign` (`approved_by`),
  KEY `leaves_tenant_id_employee_id_index` (`tenant_id`,`employee_id`),
  KEY `leaves_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `leaves_tenant_id_start_date_index` (`tenant_id`,`start_date`),
  CONSTRAINT `leaves_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leaves_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leaves_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `maintenance_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenance_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `vehicle_id` bigint unsigned NOT NULL,
  `assigned_to` bigint unsigned DEFAULT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'periodic',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `scheduled_date` date NOT NULL,
  `completed_date` date DEFAULT NULL,
  `km_at_service` int DEFAULT NULL,
  `next_km` int DEFAULT NULL,
  `cost` decimal(18,2) DEFAULT NULL,
  `service_provider` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `maintenance_schedules_vehicle_id_foreign` (`vehicle_id`),
  KEY `maintenance_schedules_assigned_to_foreign` (`assigned_to`),
  KEY `maintenance_schedules_tenant_id_scheduled_date_index` (`tenant_id`,`scheduled_date`),
  KEY `maintenance_schedules_tenant_id_vehicle_id_index` (`tenant_id`,`vehicle_id`),
  KEY `maintenance_schedules_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `maintenance_schedules_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `maintenance_schedules_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_schedules_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `order_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sas_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SAP satınalma / PO referansı',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `ordered_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `due_date` date DEFAULT NULL,
  `currency_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `freight_amount` decimal(15,2) DEFAULT NULL,
  `exchange_rate` decimal(12,6) DEFAULT NULL,
  `distance_km` decimal(10,2) DEFAULT NULL,
  `tonnage` decimal(12,3) DEFAULT NULL,
  `gross_weight_kg` decimal(14,3) DEFAULT NULL,
  `tara_weight_kg` decimal(14,3) DEFAULT NULL,
  `net_weight_kg` decimal(14,3) DEFAULT NULL,
  `moisture_percent` decimal(8,4) DEFAULT NULL,
  `incoterms` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loading_site` text COLLATE utf8mb4_unicode_ci,
  `unloading_site` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_tenant_id_order_number_unique` (`tenant_id`,`order_number`),
  KEY `orders_customer_id_foreign` (`customer_id`),
  KEY `orders_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `orders_tenant_id_due_date_index` (`tenant_id`,`due_date`),
  CONSTRAINT `orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orders_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payrolls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payrolls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `gross_salary` decimal(18,2) NOT NULL,
  `deductions` json DEFAULT NULL,
  `net_salary` decimal(18,2) NOT NULL,
  `currency_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `pdf_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payrolls_unique_period` (`tenant_id`,`employee_id`,`period_start`),
  KEY `payrolls_employee_id_foreign` (`employee_id`),
  KEY `payrolls_approved_by_foreign` (`approved_by`),
  KEY `payrolls_tenant_id_employee_id_index` (`tenant_id`,`employee_id`),
  KEY `payrolls_tenant_id_period_start_index` (`tenant_id`,`period_start`),
  CONSTRAINT `payrolls_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payrolls_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payrolls_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personnel_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personnel_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present',
  `note` text COLLATE utf8mb4_unicode_ci,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personnel_attendances_tenant_id_employee_id_date_unique` (`tenant_id`,`employee_id`,`date`),
  KEY `personnel_attendances_employee_id_foreign` (`employee_id`),
  KEY `personnel_attendances_approved_by_foreign` (`approved_by`),
  KEY `personnel_attendances_tenant_id_date_index` (`tenant_id`,`date`),
  CONSTRAINT `personnel_attendances_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `personnel_attendances_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `personnel_attendances_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pricing_conditions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pricing_conditions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `material_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_from` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `route_to` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `distance_km` decimal(8,1) NOT NULL DEFAULT '0.0',
  `base_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `price_per_ton` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `min_tonnage` decimal(8,2) NOT NULL DEFAULT '0.00',
  `valid_from` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pricing_conditions_customer_id_foreign` (`customer_id`),
  KEY `pricing_conditions_tenant_id_customer_id_index` (`tenant_id`,`customer_id`),
  KEY `pricing_conditions_tenant_id_is_active_index` (`tenant_id`,`is_active`),
  CONSTRAINT `pricing_conditions_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pricing_conditions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `public_reference_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_id` bigint unsigned NOT NULL,
  `vehicle_id` bigint unsigned DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planned',
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `pod_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipments_public_reference_token_unique` (`public_reference_token`),
  KEY `shipments_order_id_foreign` (`order_id`),
  KEY `shipments_vehicle_id_foreign` (`vehicle_id`),
  KEY `shipments_tenant_id_order_id_index` (`tenant_id`,`order_id`),
  CONSTRAINT `shipments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shipments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shipments_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `is_secret` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_settings_tenant_id_key_unique` (`tenant_id`,`key`),
  KEY `tenant_settings_tenant_id_index` (`tenant_id`),
  CONSTRAINT `tenant_settings_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trip_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trip_expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `vehicle_id` bigint unsigned NOT NULL,
  `employee_id` bigint unsigned DEFAULT NULL,
  `shipment_id` bigint unsigned DEFAULT NULL,
  `expense_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `expense_date` date NOT NULL,
  `odometer_km` decimal(10,1) DEFAULT NULL,
  `receipt_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trip_expenses_vehicle_id_foreign` (`vehicle_id`),
  KEY `trip_expenses_employee_id_foreign` (`employee_id`),
  KEY `trip_expenses_shipment_id_foreign` (`shipment_id`),
  KEY `trip_expenses_tenant_id_vehicle_id_index` (`tenant_id`,`vehicle_id`),
  KEY `trip_expenses_tenant_id_expense_date_index` (`tenant_id`,`expense_date`),
  CONSTRAINT `trip_expenses_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trip_expenses_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trip_expenses_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trip_expenses_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `users_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `plate` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Şasi no',
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manufacture_year` smallint unsigned DEFAULT NULL,
  `inspection_valid_until` date DEFAULT NULL COMMENT 'Muayene geçerlilik',
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicles_tenant_id_plate_unique` (`tenant_id`,`plate`),
  CONSTRAINT `vehicles_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vouchers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `cash_register_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `amount` decimal(18,2) NOT NULL,
  `currency_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `voucher_date` date NOT NULL,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `document_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vouchers_cash_register_id_foreign` (`cash_register_id`),
  KEY `vouchers_order_id_foreign` (`order_id`),
  KEY `vouchers_approved_by_foreign` (`approved_by`),
  KEY `vouchers_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `vouchers_tenant_id_type_index` (`tenant_id`,`type`),
  KEY `vouchers_tenant_id_voucher_date_index` (`tenant_id`,`voucher_date`),
  CONSTRAINT `vouchers_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vouchers_cash_register_id_foreign` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vouchers_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vouchers_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouses_tenant_id_code_unique` (`tenant_id`,`code`),
  CONSTRAINT `warehouses_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/*M!999999\- enable the sandbox mode */ 
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_08_14_170933_add_two_factor_columns_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_03_28_000001_create_tenants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_03_28_000002_add_tenant_id_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_03_28_000003_create_customers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_03_28_000004_create_vehicles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_03_29_000001_create_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_03_29_000002_create_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_03_29_104656_create_employees_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_03_29_120000_add_tracking_and_pod_to_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_03_29_133852_create_chart_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_03_29_133857_create_journal_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_03_29_133858_create_journal_lines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_03_29_134835_add_source_to_journal_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_03_29_140000_create_bank_statement_csv_imports_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_03_29_142721_create_fiscal_opening_balances_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_03_29_184954_create_warehouses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_03_29_184956_create_inventory_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_03_29_184957_create_inventory_stocks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_03_30_000001_add_sas_no_to_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_03_30_000002_create_delivery_numbers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_03_30_100000_add_kantar_moisture_to_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_03_30_110000_create_fuel_intakes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_03_30_163529_create_fuel_prices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_03_30_192854_create_cash_registers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_03_30_192857_create_vouchers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_03_31_000000_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_03_31_100001_create_leaves_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_03_31_100002_create_advances_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_03_31_100003_create_payrolls_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_03_31_045631_create_maintenance_schedules_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_03_31_081249_create_personnel_attendances_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_03_31_081543_create_activity_logs_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_03_31_081928_create_app_notifications_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_03_31_085826_create_tenant_settings_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_03_31_132709_create_customer_addresses_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_03_31_194653_create_current_accounts_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_03_31_194656_create_account_transactions_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_03_31_195228_create_pricing_conditions_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_03_31_195605_create_trip_expenses_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_04_01_064516_add_due_date_to_orders_table',8);
