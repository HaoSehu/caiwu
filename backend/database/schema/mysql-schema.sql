/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `account_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `account_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `change_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `balance_after` decimal(12,2) NOT NULL DEFAULT '0.00',
  `source_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `origin_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_id` bigint unsigned DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trace_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_transactions_user_account_created_idx` (`user_id`,`account_type`,`created_at`,`id`),
  KEY `account_transactions_user_event_created_idx` (`user_id`,`event_type`,`created_at`),
  KEY `account_transactions_origin_idx` (`origin_type`,`origin_id`),
  KEY `account_transactions_trace_id_idx` (`trace_id`),
  KEY `account_transactions_created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_roles` (
  `admin_user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  UNIQUE KEY `admin_user_roles_admin_role_unique` (`admin_user_id`,`role_id`),
  KEY `admin_user_roles_role_id_idx` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `nickname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0=禁用 1=正常',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_users_username_unique` (`username`),
  KEY `admin_users_role_id_index` (`role_id`),
  KEY `admin_users_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `automation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `automation_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_id` bigint unsigned NOT NULL,
  `rule_key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `meta` json DEFAULT NULL,
  `executed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `automation_logs_unique_rule` (`task_key`,`action`,`object_type`,`object_id`,`rule_key`),
  KEY `automation_logs_object_idx` (`object_type`,`object_id`),
  KEY `automation_logs_task_key_index` (`task_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `balance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `balance_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `event_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'recharge|consume|refund|adjust',
  `change_amount` decimal(12,2) NOT NULL COMMENT '正=增 负=减',
  `balance_after` decimal(12,2) NOT NULL COMMENT '变动后余额',
  `remark` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `reference_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `balance_logs_type_index` (`event_type`),
  KEY `balance_logs_user_created_at_idx` (`user_id`,`created_at`),
  KEY `balance_logs_user_type_created_at_idx` (`user_id`,`event_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `content_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_articles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `content_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'notice|help',
  `category_id` bigint unsigned DEFAULT NULL,
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(220) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keywords` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=草稿 1=已发布 2=已下线',
  `is_pinned` tinyint NOT NULL DEFAULT '0',
  `is_recommended` tinyint NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `view_count` int unsigned NOT NULL DEFAULT '0',
  `publish_at` timestamp NULL DEFAULT NULL,
  `last_published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trace_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_articles_slug_unique` (`slug`),
  KEY `idx_content_type_status_publish` (`content_type`,`status`,`publish_at`),
  KEY `idx_content_type_pin_sort` (`content_type`,`is_pinned`,`sort_order`,`id`),
  KEY `idx_content_type_recommend` (`content_type`,`is_recommended`,`publish_at`),
  KEY `idx_content_category_type` (`category_name`,`content_type`),
  KEY `content_articles_created_by_index` (`created_by`),
  KEY `content_articles_updated_by_index` (`updated_by`),
  KEY `idx_content_article_type_category` (`content_type`,`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `content_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `content_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'notice|help',
  `name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0=禁用 1=启用',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_content_category_type_name` (`content_type`,`name`),
  UNIQUE KEY `uniq_content_category_type_slug` (`content_type`,`slug`),
  KEY `idx_content_category_type_status_sort` (`content_type`,`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupon_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupon_campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weekdays` json DEFAULT NULL,
  `trigger_time` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_quantity` int unsigned NOT NULL DEFAULT '1',
  `valid_duration_hours` int unsigned DEFAULT NULL,
  `discount_scope` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'first_month',
  `discount_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `min_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `max_discount_amount` decimal(12,2) DEFAULT NULL,
  `billing_cycles` json DEFAULT NULL,
  `product_ids` json DEFAULT NULL,
  `first_order_only` tinyint(1) NOT NULL DEFAULT '0',
  `per_user_limit` int unsigned DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `last_dispatched_at` timestamp NULL DEFAULT NULL,
  `last_coupon_id` bigint unsigned DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trace_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coupon_campaigns_status_sort_idx` (`status`,`sort_order`),
  KEY `coupon_campaigns_trigger_status_idx` (`trigger_time`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `coupon_campaign_id` bigint unsigned DEFAULT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distribution_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `discount_scope` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'first_month',
  `discount_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `min_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `max_discount_amount` decimal(12,2) DEFAULT NULL,
  `billing_cycles` json DEFAULT NULL,
  `product_ids` json DEFAULT NULL,
  `first_order_only` tinyint(1) NOT NULL DEFAULT '0',
  `total_usage_limit` int unsigned DEFAULT NULL,
  `per_user_limit` int unsigned DEFAULT NULL,
  `used_count` int unsigned NOT NULL DEFAULT '0',
  `status` tinyint NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `starts_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trace_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`),
  KEY `coupons_campaign_status_idx` (`coupon_campaign_id`,`status`),
  KEY `coupons_status_sort_idx` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error_msg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_logs_to_email_index` (`to_email`),
  KEY `email_logs_to_email_created_at_idx` (`to_email`,`created_at`),
  KEY `email_logs_status_created_at_idx` (`status`,`created_at`),
  KEY `email_logs_template_code_index` (`template_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `item_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `line_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `meta_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_invoice_id_index` (`invoice_id`),
  CONSTRAINT `fk_invoice_items_invoice_id` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_spec_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_type_snapshot` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_id` bigint unsigned DEFAULT NULL,
  `coupon_id` bigint unsigned DEFAULT NULL,
  `user_coupon_id` bigint unsigned DEFAULT NULL,
  `coupon_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT 'normal|renew|manual',
  `amount` decimal(12,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `billing_cycle` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `config_snapshot` json DEFAULT NULL,
  `config_pricing_snapshot` json DEFAULT NULL,
  `coupon_snapshot` json DEFAULT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=未付 1=已付 2=已取消 3=逾期',
  `due_date` date NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作人',
  `trace_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '链路追踪号',
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_invoice_no_unique` (`invoice_no`),
  KEY `invoices_user_id_index` (`user_id`),
  KEY `invoices_status_due_date_index` (`status`,`due_date`),
  KEY `invoices_user_status_id_idx` (`user_id`,`status`,`id`),
  KEY `invoices_status_paid_at_idx` (`status`,`paid_at`),
  KEY `invoices_order_id_idx` (`order_id`),
  KEY `invoices_trace_id_idx` (`trace_id`),
  KEY `invoices_product_id_idx` (`product_id`),
  KEY `invoices_service_id_idx` (`service_id`),
  CONSTRAINT `fk_invoices_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_invoices_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '相对路径，如 /uploads/content/20260419/cover_xxx.jpg',
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '完整访问 URL',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned NOT NULL DEFAULT '0' COMMENT '文件大小(字节)',
  `width` int unsigned DEFAULT NULL COMMENT '图片宽度',
  `height` int unsigned DEFAULT NULL COMMENT '图片高度',
  `group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'content' COMMENT '分组: content, avatar, brand 等',
  `uploaded_by` bigint unsigned NOT NULL DEFAULT '0' COMMENT '上传管理员ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `media_files_group_index` (`group`),
  KEY `media_files_uploaded_by_index` (`uploaded_by`),
  KEY `media_files_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_levels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sales_amount_min` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sales_amount_max` decimal(12,2) DEFAULT NULL,
  `reward_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0=禁用 1=启用',
  `sort_order` int NOT NULL DEFAULT '0',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_levels_code_unique` (`code`),
  KEY `idx_member_level_status_sort` (`status`,`sort_order`),
  KEY `idx_member_level_sales_range` (`sales_amount_min`,`sales_amount_max`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `channel` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `params_json` json DEFAULT NULL,
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error_msg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `origin_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_logs_channel_created_at_idx` (`channel`,`created_at`),
  KEY `notification_logs_recipient_created_at_idx` (`recipient`,`created_at`),
  KEY `notification_logs_request_id_idx` (`request_id`),
  KEY `notification_logs_origin_idx` (`origin_type`,`origin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operation_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'admin|client',
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `context` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `operation_logs_user_id_user_type_index` (`user_id`,`user_type`),
  KEY `operation_logs_module_created_at_index` (`module`,`created_at`),
  KEY `operation_logs_user_type_created_at_idx` (`user_id`,`user_type`,`created_at`),
  KEY `operation_logs_module_subject_created_idx` (`module`,`subject_id`,`created_at`,`id`),
  KEY `operation_logs_created_at_idx` (`created_at`),
  KEY `operation_logs_user_created_at_idx` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_spec_snapshot` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_type_snapshot` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_id` bigint unsigned DEFAULT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'new|renew|upgrade|downgrade',
  `coupon_id` bigint unsigned DEFAULT NULL,
  `user_coupon_id` bigint unsigned DEFAULT NULL,
  `coupon_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `billing_cycle` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `config_snapshot` json DEFAULT NULL,
  `config_pricing_snapshot` json DEFAULT NULL,
  `coupon_snapshot` json DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=待付款 1=已付款 2=开通中 3=已完成 4=已取消 5=已退款',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作人',
  `trace_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '链路追踪号',
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_no_unique` (`order_no`),
  KEY `orders_user_id_index` (`user_id`),
  KEY `orders_status_index` (`status`),
  KEY `orders_user_status_id_idx` (`user_id`,`status`,`id`),
  KEY `orders_created_at_idx` (`created_at`),
  KEY `orders_status_type_created_at_idx` (`status`,`type`,`created_at`,`id`),
  KEY `orders_coupon_id_idx` (`coupon_id`),
  KEY `orders_user_coupon_id_idx` (`user_coupon_id`),
  KEY `orders_trace_id_idx` (`trace_id`),
  KEY `orders_product_id_idx` (`product_id`),
  KEY `orders_service_status_id_idx` (`service_id`,`status`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_callbacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_callbacks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned NOT NULL,
  `callback_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_trade_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` json DEFAULT NULL,
  `is_verified` tinyint NOT NULL DEFAULT '0',
  `received_at` timestamp NULL DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trace_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_callbacks_payment_type_unique` (`payment_id`,`callback_type`),
  KEY `payment_callbacks_verified_received_idx` (`is_verified`,`received_at`),
  KEY `payment_callbacks_gateway_trade_no_idx` (`gateway_trade_no`),
  KEY `payment_callbacks_trace_id_idx` (`trace_id`),
  CONSTRAINT `fk_payment_callbacks_payment_id` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_no` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `gateway` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'alipay|wechat|stripe|balance',
  `trade_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=待支付 1=成功 2=失败 3=已退款',
  `callback_raw` json DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作人',
  `trace_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '链路追踪号',
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_payment_no_unique` (`payment_no`),
  UNIQUE KEY `payments_gateway_trade_no_unique` (`gateway`,`trade_no`),
  KEY `payments_user_id_index` (`user_id`),
  KEY `payments_trade_no_index` (`trade_no`),
  KEY `payments_invoice_gateway_status_id_idx` (`invoice_id`,`gateway`,`status`,`id`),
  KEY `payments_invoice_status_created_at_idx` (`invoice_id`,`status`,`created_at`,`id`),
  KEY `payments_status_paid_at_idx` (`status`,`paid_at`),
  KEY `payments_user_status_created_idx` (`user_id`,`status`,`created_at`),
  KEY `payments_trace_id_idx` (`trace_id`),
  KEY `payments_order_status_idx` (`order_id`,`status`),
  CONSTRAINT `fk_payments_invoice_id` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payments_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_group_id` bigint unsigned DEFAULT NULL COMMENT '上级分组ID',
  `product_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other' COMMENT '所属顶级商品种类',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slogan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '分组标语',
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_visible` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_groups_slug_unique` (`slug`),
  KEY `product_groups_parent_id_index` (`parent_group_id`),
  KEY `product_groups_product_type_parent_id_index` (`product_type`,`parent_group_id`),
  KEY `product_groups_parent_visible_sort_id_idx` (`parent_group_id`,`is_visible`,`sort_order`,`id`),
  KEY `product_groups_type_parent_sort_id_idx` (`product_type`,`parent_group_id`,`sort_order`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_group_id` bigint unsigned DEFAULT NULL,
  `product_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'vps|dedicated|hosting|domain|other',
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pricing` json NOT NULL COMMENT '{"monthly":99,"quarterly":270,"yearly":999}',
  `setup_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `config_options` json DEFAULT NULL COMMENT '可选配置项',
  `purchase_requires` json DEFAULT NULL COMMENT '购买限制，如 {"require_verification":true,"require_phone":true}',
  `stock` int NOT NULL DEFAULT '-1' COMMENT '-1=不限',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0=下架 1=上架',
  `sort_order` int NOT NULL DEFAULT '0',
  `provision_module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_setup` tinyint NOT NULL DEFAULT '0' COMMENT '0=手动开通 1=自动开通',
  `supplier_id` bigint unsigned DEFAULT NULL COMMENT '供应商接口ID',
  `supplier_product_id` bigint unsigned DEFAULT NULL COMMENT '供应商商品ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_group_id_index` (`product_group_id`),
  KEY `products_type_status_index` (`product_type`,`status`),
  KEY `products_supplier_id_index` (`supplier_id`),
  KEY `products_group_status_sort_id_idx` (`product_group_id`,`status`,`sort_order`,`id`),
  KEY `products_supplier_product_status_id_idx` (`supplier_id`,`supplier_product_id`,`status`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_account_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_account_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `event_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'reward_frozen|reward_released|withdraw_apply|withdraw_approved|withdraw_rejected',
  `change_amount` decimal(12,2) NOT NULL COMMENT '正=增加 负=减少',
  `frozen_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `available_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pending_withdrawal_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `withdrawn_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `reference_id` bigint unsigned DEFAULT NULL,
  `reference_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trace_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_referral_account_user_type` (`user_id`,`event_type`),
  KEY `idx_referral_account_related` (`reference_type`,`reference_id`),
  KEY `referral_account_logs_created_at_index` (`created_at`),
  KEY `idx_referral_account_user_created_idx` (`user_id`,`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_rewards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `referrer_user_id` bigint unsigned NOT NULL,
  `referred_user_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `order_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `reward_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `reward_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `available_at` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=冻结中 1=已发放 2=已回退',
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trace_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rewarded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `referral_rewards_order_id_unique` (`order_id`),
  KEY `idx_referral_reward_referrer_status` (`referrer_user_id`,`status`),
  KEY `idx_referral_reward_referred_status` (`referred_user_id`,`status`),
  KEY `referral_rewards_product_id_index` (`product_id`),
  KEY `referral_rewards_rewarded_at_index` (`rewarded_at`),
  KEY `referral_rewards_invoice_id_idx` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_withdrawals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_withdrawals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `method` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alipay' COMMENT 'balance|alipay',
  `account_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `account_no` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=待处理 1=已通过 2=已拒绝',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trace_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_referral_withdraw_user_status` (`user_id`,`status`),
  KEY `idx_referral_withdraw_status_created` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permissions` json NOT NULL COMMENT '权限标识数组',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hostname` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_config` json DEFAULT NULL,
  `max_accounts` int NOT NULL DEFAULT '0',
  `current_accounts` int NOT NULL DEFAULT '0',
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `domain` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `billing_cycle` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `locked_pricing` json DEFAULT NULL COMMENT '锁定续费定价，null=跟随商品，有值=锁定不受商品调价影响',
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=待开通 1=正常 2=已暂停 3=已到期 4=已取消',
  `provision_data` json DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `auto_renew` tinyint NOT NULL DEFAULT '1',
  `suspended_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作人',
  `trace_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '链路追踪号',
  PRIMARY KEY (`id`),
  KEY `services_user_id_index` (`user_id`),
  KEY `services_expires_at_index` (`expires_at`),
  KEY `services_user_status_id_idx` (`user_id`,`status`,`id`),
  KEY `services_status_expires_at_id_idx` (`status`,`expires_at`,`id`),
  KEY `services_trace_id_idx` (`trace_id`),
  KEY `services_product_id_idx` (`product_id`),
  KEY `services_order_id_idx` (`order_id`),
  KEY `services_invoice_id_idx` (`invoice_id`),
  CONSTRAINT `fk_services_invoice_id` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_services_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_services_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_group_key_unique` (`group_key`,`item_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sms_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `params` json DEFAULT NULL,
  `content` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aliyun',
  `request_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_msg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sms_logs_phone_index` (`phone`),
  KEY `sms_logs_phone_created_at_idx` (`phone`,`created_at`),
  KEY `sms_logs_status_created_at_idx` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `interface_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mofang_finance_api' COMMENT '接口种类',
  `api_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '接口地址',
  `api_username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '接口用户名',
  `api_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '接口密钥',
  `contact_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0=停用 1=启用',
  `sort_order` int NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_code_unique` (`code`),
  KEY `suppliers_status_sort_order_index` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_replies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_staff` tinyint NOT NULL DEFAULT '0',
  `attachments` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_replies_ticket_id_index` (`ticket_id`),
  KEY `ticket_replies_ticket_created_id_idx` (`ticket_id`,`created_at`,`id`),
  CONSTRAINT `fk_ticket_replies_ticket_id` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `department` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'support',
  `subject` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` tinyint NOT NULL DEFAULT '1' COMMENT '1=低 2=中 3=高 4=紧急',
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=开启 1=客户回复 2=员工回复 3=已关闭',
  `service_id` bigint unsigned DEFAULT NULL,
  `assignee_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `close_reason` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'admin, client, auto',
  PRIMARY KEY (`id`),
  KEY `tickets_user_id_index` (`user_id`),
  KEY `tickets_user_status_updated_at_idx` (`user_id`,`status`,`updated_at`),
  KEY `tickets_status_updated_at_idx` (`status`,`updated_at`),
  KEY `tickets_user_updated_at_idx` (`user_id`,`updated_at`,`id`),
  KEY `tickets_service_id_idx` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_accounts` (
  `user_id` bigint unsigned NOT NULL,
  `cash_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `credit_limit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `referral_frozen_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `referral_available_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `referral_pending_withdrawal_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `referral_withdrawn_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `version` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_accounts_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `receive_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'claim',
  `status` tinyint NOT NULL DEFAULT '1',
  `claimed_at` timestamp NULL DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trace_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_coupons_coupon_user_unique` (`coupon_id`,`user_id`),
  KEY `user_coupons_user_status_idx` (`user_id`,`status`),
  KEY `user_coupons_coupon_status_idx` (`coupon_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `qq` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `alipay_real_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `alipay_account` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `referral_code` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referrer_user_id` bigint unsigned DEFAULT NULL,
  `member_level_id` bigint unsigned DEFAULT NULL,
  `total_sales_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `referral_frozen_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `referral_available_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `referral_withdrawing_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `referral_withdrawn_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `referred_at` timestamp NULL DEFAULT NULL,
  `balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `credit_limit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0=禁用 1=正常',
  `login_email_alert` tinyint NOT NULL DEFAULT '1' COMMENT '登录邮件提醒 0关闭 1开启',
  `is_verified` tinyint NOT NULL DEFAULT '0' COMMENT '0=未认证 1=已认证',
  `real_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '真实姓名',
  `id_card` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `verification_status` tinyint NOT NULL DEFAULT '0' COMMENT '0=未认证 1=认证中 2=已认证 3=认证失败',
  `verification_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '实名认证状态描述',
  `verification_certify_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '实名认证平台 certify_id',
  `verified_at` timestamp NULL DEFAULT NULL COMMENT '实名认证通过时间',
  `last_login_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `admin_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_referral_code_unique` (`referral_code`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_status_id_idx` (`status`,`id`),
  KEY `users_verification_mix_idx` (`is_verified`,`verification_status`,`id`),
  KEY `users_verification_status_id_idx` (`verification_status`,`id`),
  KEY `users_created_at_idx` (`created_at`),
  KEY `users_verification_certify_id_idx` (`verification_certify_id`),
  KEY `users_login_email_alert_index` (`login_email_alert`),
  KEY `users_referrer_user_id_index` (`referrer_user_id`),
  KEY `users_member_level_id_index` (`member_level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verification_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `real_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `id_card` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `verification_status` tinyint NOT NULL DEFAULT '1' COMMENT '1=认证中 2=已认证 3=认证失败',
  `verification_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `verification_certify_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_biz_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FACE',
  `verification_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'personal',
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `verification_histories_user_id_submitted_at_index` (`user_id`,`submitted_at`),
  KEY `verification_histories_verification_certify_id_index` (`verification_certify_id`),
  KEY `verification_histories_user_id_id_idx` (`user_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_admin_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_product_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'0001_01_01_000003_create_order_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'0001_01_01_000004_create_finance_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'0001_01_01_000005_create_support_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'0001_01_01_000006_create_suppliers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_03_11_135826_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_03_12_000001_add_verification_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_03_12_000002_enhance_verification_fields_on_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_03_12_000003_expand_encrypted_id_card_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_03_12_000005_create_sms_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_03_12_000006_create_email_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_03_13_000007_create_verification_histories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_03_13_000008_add_interface_type_to_suppliers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_03_13_000009_add_mofang_credentials_to_suppliers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_03_13_000010_enhance_product_catalog_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_03_13_000020_add_supplier_mapping_fields_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_03_16_000021_add_product_type_to_product_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_03_18_000022_add_query_indexes_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_03_18_000023_add_login_email_alert_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_03_18_000024_enable_login_email_alert_by_default',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_03_18_000025_add_performance_indexes_to_hot_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_03_19_000026_create_content_articles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_03_19_000027_create_content_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_03_19_000028_add_referral_fields_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_03_19_000029_create_referral_rewards_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_03_19_000030_create_member_levels_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_03_19_000031_upgrade_referral_reward_workflow',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_03_19_000032_create_referral_account_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_03_20_000033_add_alipay_qr_code_to_referral_withdrawals',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_03_20_000033_add_company_qq_admin_note_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_03_20_000034_add_alipay_account_fields_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_03_23_223800_create_queue_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_03_24_000035_create_automation_logs_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_03_27_000001_add_missing_performance_indexes',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_03_28_000002_make_user_contacts_nullable_and_add_phone_index',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_03_29_000003_add_purchase_requires_to_products_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_03_31_000004_add_locked_pricing_to_services_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_03_31_000005_refine_schema_naming',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_03_31_000006_refine_product_relation_naming',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_03_31_000007_refine_product_type_naming',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_03_31_000008_refine_content_type_naming',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_03_31_000009_refine_content_category_naming',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_04_01_120000_add_email_to_admin_users_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_04_01_130000_add_template_code_to_email_logs_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_04_02_000001_drop_alipay_qr_code_columns',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_04_03_190000_add_config_pricing_snapshot_to_orders_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_04_11_000001_enable_coupon_feature_tables',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_04_15_000001_drop_title_from_product_groups_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_04_16_000001_add_order_id_to_payments_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_04_17_000001_repair_database_analysis_indexes',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_04_17_000002_repair_database_structure_drift',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_04_17_000003_restore_account_transactions_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_04_17_000004_relax_notification_log_origin_index',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_04_18_000002_expand_id_card_column_length',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_04_18_093001_add_seo_fields_to_content_articles',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_04_18_093002_add_seo_fields_to_products',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_04_18_120000_repair_users_email_index_and_cleanup_redundant_indexes',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_04_19_180000_create_media_files_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_04_20_161200_add_close_reason_to_tickets',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_04_06_000001_optimize_financial_core_tables',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_04_06_000002_remove_redundant_balance_columns_from_users',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_04_06_000003_consolidate_user_domain_into_users',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_04_06_000004_drop_redundant_user_domain_tables',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_04_06_000005_drop_redundant_product_projection_tables',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_04_06_000006_drop_redundant_service_projection_tables',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_04_06_000007_drop_supplier_mapping_projection_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_04_06_000008_add_supplier_product_fields_to_products',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_04_06_000009_add_product_type_to_product_categories',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_04_06_000010_drop_product_types_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_04_06_000011_drop_product_groups_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_04_06_000012_add_product_snapshots_to_orders_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_04_06_000013_drop_order_projection_tables',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_04_07_000014_drop_referral_account_logs_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2026_04_07_133000_consolidate_content_and_coupon_domains',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_04_07_181000_merge_user_withdraw_accounts_into_users',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_04_07_190000_drop_server_configs_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_04_07_210000_add_quantity_to_orders_and_invoice_items',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2026_04_07_211000_enforce_users_phone_uniqueness',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2026_04_18_000001_add_cover_image_to_content_articles',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2026_04_21_000001_sync_user_accounts_cash_balance_from_users_balance',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2026_04_22_000001_add_order_fields_to_invoices_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2026_04_22_000001_expand_notification_log_content_columns',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2026_04_22_000002_migrate_services_to_invoice_drop_orders',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2026_04_22_020000_drop_orders_and_order_id_columns',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2026_04_23_000001_normalize_hosting_panel_provider_keys',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2026_05_08_000001_seed_instance_spec_catalog_setting',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2026_05_08_000002_seed_cpu_model_catalog_setting',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2026_05_10_000001_add_product_spec_snapshots_and_cleanup_product_names',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2026_05_10_000002_drop_legacy_product_name_columns',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2026_05_12_000001_add_remark_to_products',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2026_05_15_210000_normalize_core_relation_columns',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2026_05_15_210100_enforce_database_engineering_foreign_keys',35);
