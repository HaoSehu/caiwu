-- MySQL dump 10.13  Distrib 8.0.29, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: idc
-- ------------------------------------------------------
-- Server version	8.0.29

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ '0a9d1ff2-558d-11f1-9fc1-fa163e0f98af:1-55702';

--
-- Current Database: `idc`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `idc` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `idc`;

--
-- Table structure for table `account_transactions`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_transactions`
--

LOCK TABLES `account_transactions` WRITE;
/*!40000 ALTER TABLE `account_transactions` DISABLE KEYS */;
INSERT INTO `account_transactions` VALUES (1,23,'cash','consume',-20.00,108.80,'invoice',101001,'invoice',101001,'账单支付测试','system','trace-consume-50725536','2026-05-23 14:48:58','2026-05-23 14:48:58'),(2,23,'cash','admin_deduct',-8.00,100.80,'manual_adjustment',101002,'manual_adjustment',101002,'手工扣款测试','admin','trace-deduct-50725536','2026-05-24 14:48:58','2026-05-24 14:48:58'),(3,24,'cash','recharge',9.90,9.90,'payment',201001,'payment',201001,'其他用户记录','system','trace-other-50725536','2026-05-24 14:48:58','2026-05-24 14:48:58'),(4,25,'cash','recharge',100.00,100.00,'payment',301001,'payment',301001,'充值到账','system','trace-recharge-e0f2df22','2026-05-24 12:48:58','2026-05-24 12:48:58'),(5,25,'cash','refund',12.00,112.00,'payment',301002,'payment',301002,'退款到账','system','trace-refund-e0f2df22','2026-05-24 13:48:58','2026-05-24 13:48:58'),(6,25,'cash','adjust',-24.00,88.00,'manual_adjustment',301003,'manual_adjustment',301003,'系统调账','admin','trace-adjust-e0f2df22','2026-05-24 14:48:58','2026-05-24 14:48:58'),(7,26,'cash','consume',-80.00,76.00,'invoice',1,'payment',1,'余额支付账单','system','ledger-trace-e8f5914c','2026-05-24 14:33:59','2026-05-24 14:33:59'),(8,29,'cash','invoice_payment',-45.00,55.00,'invoice',6,'balance_log',1,'支付订单 ORDBAL70749CFC',NULL,NULL,'2026-05-24 14:49:00','2026-05-24 14:49:00'),(9,30,'cash','invoice_payment',-20.00,10.00,'invoice',3,'balance_log',2,'账单余额支付 INVMIXEB205F7F',NULL,NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01'),(10,47,'cash','recharge',100.00,100.00,'recharge',119534,'recharge',119534,'recharge','client','recharge-226b308c','2026-05-23 14:49:09','2026-05-23 14:49:09'),(11,47,'cash','consume',-11.20,88.80,'invoice',226200,'invoice',226200,'consume','system','consume-226b308c','2026-05-24 14:49:09','2026-05-24 14:49:09'),(12,56,'cash','recharge',5.00,15.00,'payment',8,'balance_log',3,'支付宝充值 PAY20260524224912707UQ34GXPI',NULL,NULL,'2026-05-24 14:49:12','2026-05-24 14:49:12'),(13,57,'referral_frozen','reward_frozen',5.00,5.00,'order',20,'referral_event',20,'推荐奖励冻结，来源订单 RFD9F6546AD','system','reward-refund-9f6546ad','2026-05-24 14:49:13','2026-05-24 14:49:13'),(14,57,'referral_available','reward_released',5.00,5.00,'reward',3,'referral_event',3,'冻结期结束，奖励已转为可提现','system','reward-refund-9f6546ad','2026-05-24 14:49:13','2026-05-24 14:49:13'),(15,58,'cash','invoice_refund',100.00,100.00,'invoice',11,'balance_log',4,'账单退款 INVREF9F6546AD',NULL,NULL,'2026-05-24 14:49:13','2026-05-24 14:49:13'),(16,57,'referral_available','reward_reversed',-5.00,0.00,'reward',3,'referral_event',3,'订单退款，推广奖励已撤销 #RFD9F6546AD','system','refund:refund-life-9f6546ad','2026-05-24 14:49:13','2026-05-24 14:49:13'),(17,59,'referral_frozen','reward_frozen',5.00,5.00,'order',21,'referral_event',21,'推荐奖励冻结，来源订单 RFD9702C8A9','system','reward-refund-9702c8a9','2026-05-24 14:49:14','2026-05-24 14:49:14'),(18,59,'referral_available','reward_released',5.00,5.00,'reward',4,'referral_event',4,'冻结期结束，奖励已转为可提现','system','reward-refund-9702c8a9','2026-05-24 14:49:14','2026-05-24 14:49:14'),(19,63,'referral_available','reward_released',12.00,12.00,'reward',1001,'referral_event',1001,'account-search-regression','system','account-search-38d4b68c','2026-05-24 14:49:15','2026-05-24 14:49:15');
/*!40000 ALTER TABLE `account_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_user_roles`
--

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

--
-- Dumping data for table `admin_user_roles`
--

LOCK TABLES `admin_user_roles` WRITE;
/*!40000 ALTER TABLE `admin_user_roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_user_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_users`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'ledger-admin-49e27b67','$2y$12$m5DePxIEtFsAd4pF.KxKdORpPcE0C5K3Sf9pQ3AztcSSE8un06h0G',1,'Ledger Admin',1,NULL,NULL,'2026-05-24 14:48:58','2026-05-24 14:48:58',NULL),(2,'ledger-admin-4484e7d8','$2y$12$kraFTAm.WN0E2YA6fk9KmOCH6na0x6cBNJ/1trdKT7/OiWgaobFNm',2,'Ledger Admin',1,NULL,NULL,'2026-05-24 14:48:59','2026-05-24 14:48:59',NULL),(3,'product-split-ff4d1a3c','$2y$12$jozZCZB/QsRCcjLDVhyl3ezWIDp.RXWJYWDlAABG1I5Ot8jUk7xNy',3,'Product Split',1,NULL,NULL,'2026-05-24 14:49:04','2026-05-24 14:49:04','product-split-ff4d1a3c@example.com'),(4,'product-split-idempotent-0159dfef','$2y$12$TaeGkGss9Azghg9xLvktje/DsX9WjlQYycIEqU8R1XB3juwOhtvq.',4,'Product Split Idempotent',1,NULL,NULL,'2026-05-24 14:49:04','2026-05-24 14:49:04','product-split-idempotent-0159dfef@example.com'),(5,'product-split-mb-memory-77bd3640','$2y$12$hAyV0D6h4lY7QH55yU761.uZLVR7oWureMOHtzvK9pXd0YCXcShrC',5,'Product Split MB Memory',1,NULL,NULL,'2026-05-24 14:49:04','2026-05-24 14:49:04','product-split-mb-memory-77bd3640@example.com'),(6,'product-split-generic-name-9d46f06e','$2y$12$1P.JWZ62F.EijXcoIQ91kOpnbmjcnDjvHVcyB9Nt0vVbUhyVm/ITW',6,'Product Split Generic Name',1,NULL,NULL,'2026-05-24 14:49:05','2026-05-24 14:49:05','product-split-generic-name-9d46f06e@example.com'),(7,'product-split-flow-limit-a40a1656','$2y$12$vFf2pKz.8MTyAlk5eb8.QeCB7/X1qijVkIpeJ8F1Lt6ZfMckkft7C',7,'Product Split Flow Limit',1,NULL,NULL,'2026-05-24 14:49:05','2026-05-24 14:49:05','product-split-flow-limit-a40a1656@example.com'),(8,'admin-4e6087fb','$2y$12$krPKtIOEDbQTxT/m7bJDKu5LHlSYXD3Tby96DAKW4kconDHNsbVZy',8,'Test Admin',1,NULL,NULL,'2026-05-24 14:49:11','2026-05-24 14:49:11','admin-4e6087fb@example.com'),(9,'admin-91f2ae15','$2y$12$ICAUHQ6z0Y1o28vWrLXvY.nn8Qw4aY088MQIir.J.5OiLeo7ns2Ra',9,'Test Admin',1,NULL,NULL,'2026-05-24 14:49:11','2026-05-24 14:49:11','admin-91f2ae15@example.com'),(10,'idle-admin-ab343448','$2y$12$e7BsL6bJE7bxmulzveSvLuXyqlsTWWnv6R1Kzt9ZC6I/weeUYafNW',10,'Idle Admin',1,NULL,NULL,'2026-05-24 14:49:15','2026-05-24 14:49:15','idle-admin-c3e6df61@example.com'),(11,'supplier-alias-c9bcd4db','$2y$12$LiR/n3YomlNZUwOGLARzVeLDnQQUtZULz1OsYW930PUHyn1KLGu6m',11,'Supplier Alias',1,NULL,NULL,'2026-05-24 14:49:47','2026-05-24 14:49:47','supplier-alias-c9bcd4db@example.com'),(12,'ticket-regression-admin-c0e25ebd','$2y$12$JRURRYWGajJF.Yk2nDmHKeNiRzzQM6/I9FoWWIzW8X9M0tvHQ3aNK',12,'Ticket Staff',1,NULL,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48','ticket-regression-admin-c0e25ebd@example.com'),(13,'ticket-notify-admin-9a49be0c','$2y$12$l./ECnRHd/cA.0OnIZvZveOa9stCnvIyGrKZ.42K3pNbCswjBB2xy',13,'Ticket Notify Admin',1,NULL,NULL,'2026-05-24 14:49:49','2026-05-24 14:49:49','ticket-notify-admin-9a49be0c@example.com'),(14,'upload-security-a6c7780f','$2y$12$T6DQ2aoWdhQDYO.8tuWMKu.p1I5El/f/b9mttPXeEzqwe8nYy0NUS',14,'Upload Security',1,NULL,NULL,'2026-05-24 14:49:49','2026-05-24 14:49:49','upload-security-a6c7780f@example.com'),(15,'upload-security-0cbc3c88','$2y$12$s6g4yLm9XHuaHqVbGULaoe3xOdyO0/u/JQ3SewBKlrwUDvDUbRP6a',15,'Upload Security',1,NULL,NULL,'2026-05-24 14:49:50','2026-05-24 14:49:50','upload-security-0cbc3c88@example.com'),(16,'upload-security-b1660b76','$2y$12$HJVM8FkaCidEYP0nhmlOiukLA9wILlQq2s3TQkYIGL/evvMlQ1182',16,'Upload Security',1,NULL,NULL,'2026-05-24 14:49:50','2026-05-24 14:49:50','upload-security-b1660b76@example.com');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_logs`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_logs`
--

LOCK TABLES `automation_logs` WRITE;
/*!40000 ALTER TABLE `automation_logs` DISABLE KEYS */;
INSERT INTO `automation_logs` VALUES (1,'coupon-campaign-dispatch','dispatch','coupon_campaign',2,'202605240800','{\"coupon_id\": 1, \"coupon_name\": \"Campaign Dispatch ba33b1f2 05-24 08:00\", \"scheduled_at\": \"2026-05-24 08:00:00\"}','2026-05-24 14:48:53','2026-05-24 14:48:53','2026-05-24 14:48:53');
/*!40000 ALTER TABLE `automation_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `balance_logs`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `balance_logs`
--

LOCK TABLES `balance_logs` WRITE;
/*!40000 ALTER TABLE `balance_logs` DISABLE KEYS */;
INSERT INTO `balance_logs` VALUES (1,29,'invoice_payment',-45.00,55.00,'支付订单 ORDBAL70749CFC',6,'2026-05-24 14:49:00'),(2,30,'invoice_payment',-20.00,10.00,'账单余额支付 INVMIXEB205F7F',3,'2026-05-24 14:49:01'),(3,56,'recharge',5.00,15.00,'支付宝充值 PAY20260524224912707UQ34GXPI',8,'2026-05-24 14:49:12'),(4,58,'invoice_refund',100.00,100.00,'账单退款 INVREF9F6546AD',11,'2026-05-24 14:49:13');
/*!40000 ALTER TABLE `balance_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_articles`
--

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

--
-- Dumping data for table `content_articles`
--

LOCK TABLES `content_articles` WRITE;
/*!40000 ALTER TABLE `content_articles` DISABLE KEYS */;
/*!40000 ALTER TABLE `content_articles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_categories`
--

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

--
-- Dumping data for table `content_categories`
--

LOCK TABLES `content_categories` WRITE;
/*!40000 ALTER TABLE `content_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `content_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupon_campaigns`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupon_campaigns`
--

LOCK TABLES `coupon_campaigns` WRITE;
/*!40000 ALTER TABLE `coupon_campaigns` DISABLE KEYS */;
INSERT INTO `coupon_campaigns` VALUES (1,'Campaign Dispatch bf5ec150',NULL,'[0]','08:00:00',10,24,'first_month','fixed',5.00,0.00,NULL,'[]','[]',0,NULL,1,0,'2026-05-23 22:00:00',NULL,NULL,'campaign-regression','campaign-regression-bf5ec150','2026-05-24 14:48:53','2026-05-24 14:48:53'),(2,'Campaign Dispatch ba33b1f2',NULL,'[0]','08:00:00',10,24,'first_month','fixed',5.00,0.00,NULL,'[]','[]',0,NULL,1,0,'2026-05-24 00:00:00',1,NULL,'campaign-regression','campaign-regression-ba33b1f2','2026-05-24 14:48:53','2026-05-24 14:48:53'),(3,'Campaign Dispatch bda99046',NULL,'[0]','08:00:00',10,24,'first_month','fixed',5.00,0.00,NULL,'[]','[]',0,NULL,1,0,'2026-05-23 22:00:00',NULL,NULL,'campaign-regression','campaign-regression-bda99046','2026-05-24 00:00:00','2026-05-24 00:00:00');
/*!40000 ALTER TABLE `coupon_campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` VALUES (1,2,'Campaign Dispatch ba33b1f2 05-24 08:00','CPN5CDUJQWE6W',NULL,'public','first_month','fixed',5.00,0.00,NULL,'[]','[]',0,10,NULL,0,1,0,'2026-05-24 00:00:00','2026-05-25 00:00:00','活动批次：2026-05-24 08:00','','','2026-05-24 14:48:53','2026-05-24 14:48:53'),(2,NULL,'Usage Coupon 81504841','USAGE81504841',NULL,'public','first_month','fixed',5.00,0.00,NULL,NULL,NULL,0,NULL,NULL,0,1,0,'2026-05-24 13:48:54','2026-05-25 14:48:54',NULL,NULL,NULL,'2026-05-24 14:48:54','2026-05-24 14:48:54'),(3,NULL,'Private Coupon f003706c','PRIVATEF003706C',NULL,'private','first_month','fixed',5.00,0.00,NULL,'[]','[]',0,NULL,NULL,0,1,0,'2026-05-24 13:48:55','2026-05-25 14:48:55',NULL,'coupon-regression','coupon-update-f003706c','2026-05-24 14:48:55','2026-05-24 14:48:55'),(4,NULL,'Switch Coupon b4b2e7b6','SWITCHB4B2E7B6',NULL,'private','first_month','fixed',5.00,0.00,NULL,'[]','[]',0,NULL,NULL,0,1,0,'2026-05-24 13:48:55','2026-05-25 14:48:55',NULL,'coupon-regression','coupon-switch-b4b2e7b6','2026-05-24 14:48:55','2026-05-24 14:48:55'),(5,NULL,'Limited Private Coupon 3817b2f7','LIMIT3817B2F7',NULL,'private','first_month','fixed',5.00,0.00,NULL,'[]','[]',0,2,NULL,0,1,0,'2026-05-24 13:48:56','2026-05-25 14:48:56',NULL,'coupon-regression','coupon-limit-update-3817b2f7','2026-05-24 14:48:56','2026-05-24 14:48:56'),(6,NULL,'Manual Code Coupon Updated 73e16780','MANUALUPD73E16780',NULL,'private','first_month','fixed',5.00,0.00,NULL,'[]','[]',0,NULL,NULL,0,1,0,'2026-05-24 13:48:57','2026-05-25 14:48:57',NULL,'coupon-regression','coupon-code-update-73e16780','2026-05-24 14:48:57','2026-05-24 14:48:57');
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_logs`
--

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

--
-- Dumping data for table `email_logs`
--

LOCK TABLES `email_logs` WRITE;
/*!40000 ALTER TABLE `email_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

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

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
INSERT INTO `invoice_items` VALUES (1,4,'未配置规格 #4','normal',2,109.00,0.00,218.00,'{\"order_no\": \"dd202605242249012763\", \"quantity\": 2, \"invoice_no\": \"zd202605242249012763\", \"product_name\": \"未配置规格 #4\"}','2026-05-24 14:49:01','2026-05-24 14:49:01'),(2,5,'通用NAT-2vcpu-1gib','new',1,5.00,0.00,5.00,'{\"order_no\": null, \"quantity\": 1, \"invoice_no\": \"zd202605242249019744\", \"product_name\": \"通用NAT-2vcpu-1gib\"}','2026-05-24 14:49:01','2026-05-24 14:49:01'),(3,16,'未配置规格 #32','renew',1,20.00,5.00,15.00,'{\"order_no\": null, \"quantity\": 1, \"invoice_no\": \"zd202605242249419228\", \"product_name\": \"未配置规格 #32\"}','2026-05-24 14:49:41','2026-05-24 14:49:41');
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_spec_snapshot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,'INVLEDGERE8F5914C',26,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal',80.00,0.00,NULL,1,NULL,NULL,NULL,80.00,1,'2026-05-25','2026-05-24 14:28:59','2026-05-24 14:48:59','2026-05-24 14:48:59',NULL,NULL,NULL),(2,'INV70749CFC',29,6,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal',45.00,0.00,NULL,1,NULL,NULL,NULL,45.00,1,'2026-05-25','2026-05-24 14:49:00','2026-05-24 14:49:00','2026-05-24 14:49:00',NULL,NULL,NULL),(3,'INVMIXEB205F7F',30,7,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal',50.00,0.00,NULL,1,NULL,NULL,NULL,20.00,0,'2026-05-25',NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01',NULL,NULL,NULL),(4,'zd202605242249012763',31,8,4,'未配置规格 #4','server',NULL,NULL,NULL,NULL,'normal',218.00,0.00,'monthly',2,'[]','{\"items\": [], \"quantity\": 2, \"setup_fee\": \"20.00\", \"base_amount\": \"198.00\", \"total_amount\": \"218.00\", \"config_amount\": \"0.00\", \"unit_setup_fee\": \"10.00\", \"unit_base_amount\": \"99.00\", \"unit_total_amount\": \"109.00\", \"unit_config_amount\": \"0.00\"}','[]',0.00,0,'2026-05-31',NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01',NULL,NULL,NULL),(5,'zd202605242249019744',32,9,5,'通用NAT-2vcpu-1gib','server',NULL,NULL,NULL,NULL,'new',5.00,0.00,'monthly',1,'{\"cpu\": \"2\", \"memory\": \"1024\"}','{\"items\": [{\"field\": \"cpu\", \"label\": \"cpu\", \"value\": \"2\", \"amount\": \"0.00\", \"value_label\": \"2核\"}, {\"field\": \"memory\", \"label\": \"memory\", \"value\": \"1024\", \"amount\": \"0.00\", \"value_label\": \"1G\"}], \"quantity\": 1, \"setup_fee\": \"0.00\", \"base_amount\": \"5.00\", \"total_amount\": \"5.00\", \"config_amount\": \"0.00\", \"unit_setup_fee\": \"0.00\", \"unit_base_amount\": \"5.00\", \"unit_total_amount\": \"5.00\", \"unit_config_amount\": \"0.00\"}','[]',0.00,0,'2026-05-31',NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01',NULL,NULL,NULL),(6,'INVPAIDFLOWC9CE47B5',33,10,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',9.90,0.00,NULL,1,NULL,NULL,NULL,9.90,1,'2026-05-25','2026-05-24 14:49:02','2026-05-24 14:49:02','2026-05-24 14:49:02',NULL,NULL,NULL),(7,'INVRENEWSYNC92FB1E86',34,11,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'renew',16.00,0.00,NULL,1,NULL,NULL,NULL,16.00,1,'2026-05-25','2026-05-24 14:49:02','2026-05-24 14:49:02','2026-05-24 14:49:02',NULL,NULL,NULL),(8,'INVZERO9E8CBB18',35,14,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal',0.00,0.00,NULL,1,NULL,NULL,NULL,0.00,1,'2026-05-25','2026-05-24 14:49:02','2026-05-24 14:49:02','2026-05-24 14:49:02',NULL,NULL,NULL),(10,'zd202605242249124426',56,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'recharge',5.00,0.00,NULL,1,NULL,NULL,NULL,5.00,1,'2026-05-24','2026-05-24 14:49:12','2026-05-24 14:49:12','2026-05-24 14:49:12',NULL,NULL,NULL),(11,'INVREF9F6546AD',58,20,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal',100.00,0.00,NULL,1,NULL,NULL,NULL,100.00,1,'2026-05-25','2026-05-24 14:49:13','2026-05-24 14:49:13','2026-05-24 14:49:13',NULL,NULL,NULL),(12,'zd202605242249139040',57,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'referral_credit',5.00,0.00,NULL,1,'{\"remark\": \"推广返利入账，奖励ID: 3\"}',NULL,NULL,5.00,1,'2026-05-24','2026-05-24 14:49:13','2026-05-24 14:49:13','2026-05-24 14:49:13',NULL,NULL,NULL),(13,'INVREF9702C8A9',60,21,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal',100.00,0.00,NULL,1,NULL,NULL,NULL,100.00,1,'2026-05-25','2026-05-24 14:49:14','2026-05-24 14:49:14','2026-05-24 14:49:14',NULL,NULL,NULL),(14,'zd202605242249146496',59,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'referral_credit',5.00,0.00,NULL,1,'{\"remark\": \"推广返利入账，奖励ID: 4\"}',NULL,NULL,5.00,1,'2026-05-24','2026-05-24 14:49:14','2026-05-24 14:49:14','2026-05-24 14:49:14',NULL,NULL,NULL),(15,'INVRENEW6A4F7903',65,23,NULL,NULL,NULL,9,71,7,'CPNRENEW','renew',20.00,0.00,'monthly',1,NULL,NULL,NULL,0.00,2,'2026-05-25',NULL,'2026-05-24 14:49:41','2026-05-24 14:49:41',NULL,NULL,NULL),(16,'zd202605242249419228',65,NULL,32,'未配置规格 #32','server',9,71,7,'CPNRENEW','renew',15.00,5.00,'monthly',1,'{\"source_type\": \"manual\", \"discount_amount\": \"5.00\", \"renew_service_id\": 9, \"upstream_host_id\": 0, \"supports_upstream\": false, \"local_renew_amount\": \"20.00\", \"renew_service_name\": \"Renewable Service\"}',NULL,'{\"code\": \"CPNRENEW\", \"coupon_id\": 71, \"user_coupon_id\": 7, \"discount_amount\": \"5.00\"}',0.00,0,'2026-05-31',NULL,'2026-05-24 14:49:41','2026-05-24 14:49:41',NULL,NULL,NULL);
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

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

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `media_files`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media_files`
--

LOCK TABLES `media_files` WRITE;
/*!40000 ALTER TABLE `media_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `media_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_levels`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_levels`
--

LOCK TABLES `member_levels` WRITE;
/*!40000 ALTER TABLE `member_levels` DISABLE KEYS */;
INSERT INTO `member_levels` VALUES (1,'v1','ref-test-default',0.00,300.00,5.00,1,1,NULL,'2026-05-24 14:48:52','2026-05-24 14:48:52'),(2,'闁规亽鍔忓畷妯肩驳婢跺矂鐛?76caad71','ref-76caad71',0.00,NULL,12.00,1,1,NULL,'2026-05-24 14:49:09','2026-05-24 14:49:09');
/*!40000 ALTER TABLE `member_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_admin_tables',1),(3,'0001_01_01_000002_create_product_tables',1),(4,'0001_01_01_000003_create_order_tables',1),(5,'0001_01_01_000004_create_finance_tables',1),(6,'0001_01_01_000005_create_support_tables',1),(7,'0001_01_01_000006_create_suppliers_table',1),(8,'2026_03_11_135826_create_personal_access_tokens_table',1),(9,'2026_03_12_000001_add_verification_to_users_table',1),(10,'2026_03_12_000002_enhance_verification_fields_on_users_table',1),(11,'2026_03_12_000003_expand_encrypted_id_card_column',1),(12,'2026_03_12_000005_create_sms_logs_table',1),(13,'2026_03_12_000006_create_email_logs_table',1),(14,'2026_03_13_000007_create_verification_histories_table',1),(15,'2026_03_13_000008_add_interface_type_to_suppliers_table',1),(16,'2026_03_13_000009_add_mofang_credentials_to_suppliers_table',1),(17,'2026_03_13_000010_enhance_product_catalog_tables',1),(18,'2026_03_13_000020_add_supplier_mapping_fields_to_products_table',1),(19,'2026_03_16_000021_add_product_type_to_product_groups_table',1),(20,'2026_03_18_000022_add_query_indexes_to_users_table',1),(21,'2026_03_18_000023_add_login_email_alert_to_users_table',1),(22,'2026_03_18_000024_enable_login_email_alert_by_default',1),(23,'2026_03_18_000025_add_performance_indexes_to_hot_tables',1),(24,'2026_03_19_000026_create_content_articles_table',1),(25,'2026_03_19_000027_create_content_categories_table',1),(26,'2026_03_19_000028_add_referral_fields_to_users_table',1),(27,'2026_03_19_000029_create_referral_rewards_table',1),(28,'2026_03_19_000030_create_member_levels_table',1),(29,'2026_03_19_000031_upgrade_referral_reward_workflow',1),(30,'2026_03_19_000032_create_referral_account_logs_table',1),(31,'2026_03_20_000033_add_alipay_qr_code_to_referral_withdrawals',1),(32,'2026_03_20_000033_add_company_qq_admin_note_to_users_table',1),(33,'2026_03_20_000034_add_alipay_account_fields_to_users_table',1),(34,'2026_03_23_223800_create_queue_tables',2),(35,'2026_03_24_000035_create_automation_logs_table',3),(36,'2026_03_27_000001_add_missing_performance_indexes',4),(37,'2026_03_28_000002_make_user_contacts_nullable_and_add_phone_index',4),(38,'2026_03_29_000003_add_purchase_requires_to_products_table',4),(39,'2026_03_31_000004_add_locked_pricing_to_services_table',5),(40,'2026_03_31_000005_refine_schema_naming',5),(41,'2026_03_31_000006_refine_product_relation_naming',6),(42,'2026_03_31_000007_refine_product_type_naming',7),(43,'2026_03_31_000008_refine_content_type_naming',8),(44,'2026_03_31_000009_refine_content_category_naming',9),(45,'2026_04_01_120000_add_email_to_admin_users_table',10),(46,'2026_04_01_130000_add_template_code_to_email_logs_table',10),(47,'2026_04_02_000001_drop_alipay_qr_code_columns',11),(48,'2026_04_03_190000_add_config_pricing_snapshot_to_orders_table',12),(49,'2026_04_11_000001_enable_coupon_feature_tables',13),(50,'2026_04_15_000001_drop_title_from_product_groups_table',14),(51,'2026_04_16_000001_add_order_id_to_payments_table',15),(52,'2026_04_17_000001_repair_database_analysis_indexes',16),(53,'2026_04_17_000002_repair_database_structure_drift',17),(54,'2026_04_17_000003_restore_account_transactions_table',18),(55,'2026_04_17_000004_relax_notification_log_origin_index',19),(56,'2026_04_18_000002_expand_id_card_column_length',20),(57,'2026_04_18_093001_add_seo_fields_to_content_articles',21),(58,'2026_04_18_093002_add_seo_fields_to_products',22),(59,'2026_04_18_120000_repair_users_email_index_and_cleanup_redundant_indexes',23),(60,'2026_04_19_180000_create_media_files_table',24),(61,'2026_04_20_161200_add_close_reason_to_tickets',25),(62,'2026_04_06_000001_optimize_financial_core_tables',26),(63,'2026_04_06_000002_remove_redundant_balance_columns_from_users',26),(64,'2026_04_06_000003_consolidate_user_domain_into_users',26),(65,'2026_04_06_000004_drop_redundant_user_domain_tables',26),(66,'2026_04_06_000005_drop_redundant_product_projection_tables',26),(67,'2026_04_06_000006_drop_redundant_service_projection_tables',26),(68,'2026_04_06_000007_drop_supplier_mapping_projection_table',26),(69,'2026_04_06_000008_add_supplier_product_fields_to_products',26),(70,'2026_04_06_000009_add_product_type_to_product_categories',26),(71,'2026_04_06_000010_drop_product_types_table',26),(72,'2026_04_06_000011_drop_product_groups_table',26),(73,'2026_04_06_000012_add_product_snapshots_to_orders_table',26),(74,'2026_04_06_000013_drop_order_projection_tables',26),(75,'2026_04_07_000014_drop_referral_account_logs_table',26),(76,'2026_04_07_133000_consolidate_content_and_coupon_domains',26),(77,'2026_04_07_181000_merge_user_withdraw_accounts_into_users',26),(78,'2026_04_07_190000_drop_server_configs_table',26),(79,'2026_04_07_210000_add_quantity_to_orders_and_invoice_items',26),(80,'2026_04_07_211000_enforce_users_phone_uniqueness',26),(81,'2026_04_18_000001_add_cover_image_to_content_articles',26),(82,'2026_04_21_000001_sync_user_accounts_cash_balance_from_users_balance',27),(83,'2026_04_22_000001_add_order_fields_to_invoices_table',28),(84,'2026_04_22_000001_expand_notification_log_content_columns',28),(85,'2026_04_22_000002_migrate_services_to_invoice_drop_orders',29),(86,'2026_04_22_020000_drop_orders_and_order_id_columns',30),(87,'2026_04_23_000001_normalize_hosting_panel_provider_keys',31),(88,'2026_05_08_000001_seed_instance_spec_catalog_setting',31),(89,'2026_05_08_000002_seed_cpu_model_catalog_setting',31),(90,'2026_05_10_000001_add_product_spec_snapshots_and_cleanup_product_names',32),(91,'2026_05_10_000002_drop_legacy_product_name_columns',33),(92,'2026_05_12_000001_add_remark_to_products',34),(93,'2026_05_15_210000_normalize_core_relation_columns',35),(94,'2026_05_15_210100_enforce_database_engineering_foreign_keys',35),(95,'2026_05_17_000001_add_client_notification_preferences_to_users_table',36);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_logs`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_logs`
--

LOCK TABLES `notification_logs` WRITE;
/*!40000 ALTER TABLE `notification_logs` DISABLE KEYS */;
INSERT INTO `notification_logs` VALUES (2,'sms','13881227630','100001',NULL,'您的验证码为123456，5分钟内有效。','{\"min\": \"5\", \"code\": \"123456\"}','aliyun','req-otp-6ae80083','success',NULL,NULL,'sms_verify',0,'2026-05-24 14:49:46','2026-05-24 14:49:46');
/*!40000 ALTER TABLE `notification_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `operation_logs`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `operation_logs`
--

LOCK TABLES `operation_logs` WRITE;
/*!40000 ALTER TABLE `operation_logs` DISABLE KEYS */;
INSERT INTO `operation_logs` VALUES (12,8,'client','profile.nickname.update','auth',8,'{\"nickname\": \"Updated Nickname\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:51'),(13,8,'client','PUT api/client/auth/profile','auth',NULL,'{\"params\": {\"nickname\": \"Updated Nickname\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:51'),(14,9,'client','profile.nickname.update','auth',9,'{\"nickname\": \"Logged Nickname\", \"trace_id\": \"client-profile-log-69175fe9\", \"user_agent\": \"ClientProfileRegressionTest/1.0\"}','127.0.0.1','2026-05-24 14:48:52'),(15,9,'client','PUT api/client/auth/profile','auth',NULL,'{\"params\": {\"nickname\": \"Logged Nickname\"}, \"status\": 200, \"request_id\": \"client-profile-log-69175fe9\", \"user_agent\": \"ClientProfileRegressionTest/1.0\"}','127.0.0.1','2026-05-24 14:48:52'),(16,11,'client','GET api/client/referral/overview','referral',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:52'),(17,11,'client','GET api/client/referral/account-logs','referral',NULL,'{\"params\": {\"per_page\": \"30\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:53'),(18,12,'client','GET api/client/services','services',NULL,'{\"params\": {\"page\": \"1\", \"status\": \"1\", \"page_size\": \"10\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:53'),(19,12,'client','GET api/client/services','services',NULL,'{\"params\": {\"page\": \"1\", \"page_size\": \"10\", \"status_scope\": \"active_pending\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:53'),(20,12,'client','GET api/client/services/grouped-overview','services',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:53'),(21,23,'client','GET api/client/finance/ledger','finance',NULL,'{\"params\": {\"page_size\": \"20\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:58'),(22,23,'client','GET api/client/finance/ledger','finance',NULL,'{\"params\": {\"page_size\": \"20\", \"event_type\": \"invoice_payment\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:58'),(23,23,'client','GET api/client/finance/ledger/summary','finance',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:58'),(24,1,'admin','GET api/admin/finance/ledger','finance',NULL,'{\"params\": {\"user_id\": \"25\", \"page_size\": \"20\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:58'),(25,1,'admin','GET api/admin/finance/ledger','finance',NULL,'{\"params\": {\"tab\": \"adjustment\", \"user_id\": \"25\", \"page_size\": \"20\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:58'),(26,1,'admin','GET api/admin/finance/ledger/summary','finance',NULL,'{\"params\": {\"user_id\": \"25\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:58'),(27,26,'client','invoice.paid','invoice',1,'{\"trace_id\": \"ledger-trace-e8f5914c\", \"actor_name\": \"Client User\", \"invoice_no\": \"INVLEDGERE8F5914C\"}','127.0.0.1','2026-05-24 14:48:59'),(28,26,'client','order.paid','order',4,'{\"order_no\": \"ORDLEDGERE8F5914C\", \"trace_id\": \"ledger-trace-e8f5914c\", \"actor_name\": \"Client User\"}','127.0.0.1','2026-05-24 14:48:59'),(29,2,'admin','GET api/admin/finance/ledger/7','finance',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:48:59'),(30,3,'admin','POST api/admin/products/split-preview','products',NULL,'{\"params\": {\"product_ids\": [10]}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:04'),(31,3,'admin','POST api/admin/products/split','products',NULL,'{\"params\": {\"product_ids\": [10]}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:04'),(32,4,'admin','POST api/admin/products/split','products',NULL,'{\"params\": {\"product_ids\": [14]}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:04'),(33,4,'admin','POST api/admin/products/split','products',NULL,'{\"params\": {\"product_ids\": [14]}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:04'),(34,5,'admin','POST api/admin/products/split-preview','products',NULL,'{\"params\": {\"product_ids\": [16]}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:04'),(35,6,'admin','POST api/admin/products/split-preview','products',NULL,'{\"params\": {\"product_ids\": [17]}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:05'),(36,6,'admin','POST api/admin/products/split','products',NULL,'{\"params\": {\"product_ids\": [17]}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:05'),(37,7,'admin','POST api/admin/products/split-preview','products',NULL,'{\"params\": {\"product_ids\": [19]}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:05'),(38,7,'admin','POST api/admin/products/split','products',NULL,'{\"params\": {\"product_ids\": [19]}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:05'),(39,54,'client','GET api/client/balance-logs','balance-logs',NULL,'{\"params\": {\"page_size\": \"20\", \"event_type\": \"consume\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:11'),(40,54,'client','GET api/client/balance-logs/summary','balance-logs',NULL,'{\"params\": {\"event_type\": \"consume\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:11'),(41,8,'admin','GET api/admin/referral/overview','referral',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:11'),(42,9,'admin','GET api/admin/verifications','verifications',NULL,'{\"params\": {\"keyword\": \"Verification User\", \"page_size\": \"20\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:12'),(43,9,'admin','GET api/admin/verifications/summary','verifications',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:12'),(44,9,'admin','GET api/admin/verifications/55','verifications',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:12'),(45,9,'admin','GET api/admin/verifications/55/history','verifications',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:12'),(46,57,'client','referral.reward.frozen','referral',20,'{\"order_no\": \"RFD9F6546AD\", \"reward_rate\": 5, \"member_level\": \"v1\", \"order_amount\": 100, \"reward_amount\": 5, \"referred_user_id\": 58}',NULL,'2026-05-24 14:49:13'),(47,57,'system','referral.reward.released','referral',3,'{\"order_id\": 20, \"reward_amount\": 5}',NULL,'2026-05-24 14:49:13'),(48,57,'system','referral.reward.reversed','referral',3,'{\"order_id\": 20, \"order_no\": \"RFD9F6546AD\", \"order_amount\": 100, \"reward_amount\": 5}',NULL,'2026-05-24 14:49:13'),(49,59,'client','referral.reward.frozen','referral',21,'{\"order_no\": \"RFD9702C8A9\", \"reward_rate\": 5, \"member_level\": \"v1\", \"order_amount\": 100, \"reward_amount\": 5, \"referred_user_id\": 60}',NULL,'2026-05-24 14:49:14'),(50,59,'system','referral.reward.released','referral',4,'{\"order_id\": 21, \"reward_amount\": 5}',NULL,'2026-05-24 14:49:14'),(51,10,'admin','GET api/admin/auth/info','auth',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:15'),(52,NULL,'guest','GET api/site/config','config',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0\"}','127.0.0.1','2026-05-24 14:49:27'),(53,NULL,'guest','GET api/client/auth/captcha-config','auth',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0\"}','127.0.0.1','2026-05-24 14:49:27'),(54,NULL,'guest','POST api/client/login','login',NULL,'{\"params\": {\"account\": \"2908990438@qq.com\", \"password\": \"[REDACTED]\"}, \"status\": 401, \"request_id\": \"56859f49-309c-476e-9206-4b6237ed505f\", \"user_agent\": \"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0\"}','127.0.0.1','2026-05-24 14:49:29'),(55,NULL,'guest','GET api/site/home','home',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0\"}','127.0.0.1','2026-05-24 14:49:33'),(56,NULL,'guest','GET api/site/home-hero','home-hero',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0\"}','127.0.0.1','2026-05-24 14:49:33'),(57,NULL,'guest','GET api/site/product-types','product-types',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0\"}','127.0.0.1','2026-05-24 14:49:33'),(58,NULL,'guest','GET api/site/product-types','product-types',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0\"}','127.0.0.1','2026-05-24 14:49:34'),(59,NULL,'guest','GET api/site/notices','notices',NULL,'{\"params\": {\"page\": \"1\", \"page_size\": \"6\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0\"}','127.0.0.1','2026-05-24 14:49:34'),(60,NULL,'guest','GET api/site/product-categories','product-categories',NULL,'{\"params\": {\"product_type\": \"vps\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0\"}','127.0.0.1','2026-05-24 14:49:34'),(61,NULL,'guest','GET api/site/config','config',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:45'),(62,NULL,'guest','GET api/site/product-types','product-types',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:45'),(63,NULL,'guest','GET api/site/products/35/stock','products',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:45'),(64,NULL,'guest','GET api/site/products/36','products',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:45'),(65,NULL,'guest','POST api/site/products/37/quote','products',NULL,'{\"params\": {\"config\": [], \"quantity\": 1, \"billing_cycle\": \"monthly\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:45'),(66,NULL,'guest','GET api/site/home','home',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:45'),(67,11,'admin','POST api/admin/suppliers','suppliers',NULL,'{\"params\": {\"name\": \"Mofang Alias c9bcd4db\", \"status\": 1, \"api_key\": \"[REDACTED]\", \"api_url\": \"https://supplier-c9bcd4db.example.com\", \"api_username\": \"demo\", \"interface_type\": \"hosting_panel_api\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:47'),(68,78,'client','GET api/client/tickets/8','tickets',NULL,'{\"params\": [], \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:49'),(69,15,'admin','POST api/admin/media-files','media-files',NULL,'{\"params\": {\"file\": \"C:\\\\Users\\\\USER125536\\\\AppData\\\\Local\\\\Temp\\\\phpE972.tmp\", \"group\": \"content\"}, \"status\": 200, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:50'),(70,16,'admin','POST api/admin/media-files','media-files',NULL,'{\"params\": {\"file\": \"C:\\\\Users\\\\USER125536\\\\AppData\\\\Local\\\\Temp\\\\phpEAEA.tmp\", \"group\": \"../escape\"}, \"status\": 422, \"request_id\": \"\", \"user_agent\": \"Symfony\"}','127.0.0.1','2026-05-24 14:49:50');
/*!40000 ALTER TABLE `operation_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_spec_snapshot` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (2,'CUPSYNC81504841',13,NULL,NULL,NULL,NULL,'new',2,1,NULL,20.00,5.00,0.00,'monthly',1,NULL,NULL,NULL,0,NULL,'2026-05-24 14:48:54','2026-05-24 14:48:54',NULL,NULL,NULL),(3,'CUPORDF003706C',14,NULL,NULL,NULL,NULL,'new',3,2,NULL,20.00,5.00,0.00,'monthly',1,NULL,NULL,NULL,1,'2026-05-24 14:48:55','2026-05-24 14:48:55','2026-05-24 14:48:55',NULL,NULL,NULL),(4,'ORDLEDGERE8F5914C',26,NULL,'高频云主机',NULL,NULL,'new',NULL,NULL,NULL,88.00,8.00,80.00,'monthly',1,NULL,NULL,NULL,1,'2026-05-24 14:23:59','2026-05-24 14:48:59','2026-05-24 14:48:59',NULL,NULL,NULL),(5,'ORDSESSIONEDC9760C',1,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,20.00,2.00,0.00,'monthly',1,NULL,NULL,NULL,0,NULL,'2026-05-24 14:49:00','2026-05-24 14:49:00',NULL,NULL,NULL),(6,'ORDBAL70749CFC',29,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,50.00,5.00,45.00,'monthly',1,NULL,NULL,NULL,1,'2026-05-24 14:49:00','2026-05-24 14:49:00','2026-05-24 14:49:00',NULL,NULL,NULL),(7,'ORDMIXEB205F7F',30,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,50.00,0.00,0.00,'monthly',1,NULL,NULL,NULL,0,NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01',NULL,NULL,NULL),(8,'dd202605242249012763',31,4,'未配置规格 #4','server',NULL,'new',NULL,NULL,NULL,218.00,0.00,0.00,'monthly',2,'[]','{\"items\": [], \"quantity\": 2, \"setup_fee\": \"20.00\", \"base_amount\": \"198.00\", \"total_amount\": \"218.00\", \"config_amount\": \"0.00\", \"unit_setup_fee\": \"10.00\", \"unit_base_amount\": \"99.00\", \"unit_total_amount\": \"109.00\", \"unit_config_amount\": \"0.00\"}','[]',0,NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01',NULL,NULL,NULL),(9,'dd202605242249019744',32,5,'通用NAT-2vcpu-1gib','server',NULL,'new',NULL,NULL,NULL,5.00,0.00,0.00,'monthly',1,'{\"cpu\": \"2\", \"memory\": \"1024\"}','{\"items\": [{\"field\": \"cpu\", \"label\": \"cpu\", \"value\": \"2\", \"amount\": \"0.00\", \"value_label\": \"2核\"}, {\"field\": \"memory\", \"label\": \"memory\", \"value\": \"1024\", \"amount\": \"0.00\", \"value_label\": \"1G\"}], \"quantity\": 1, \"setup_fee\": \"0.00\", \"base_amount\": \"5.00\", \"total_amount\": \"5.00\", \"config_amount\": \"0.00\", \"unit_setup_fee\": \"0.00\", \"unit_base_amount\": \"5.00\", \"unit_total_amount\": \"5.00\", \"unit_config_amount\": \"0.00\"}','[]',0,NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01',NULL,NULL,NULL),(10,'ORDPAIDFLOWC9CE47B5',33,6,'未配置规格 #6','server',NULL,'new',NULL,NULL,NULL,9.90,0.00,9.90,'monthly',1,'[]','[]',NULL,1,'2026-05-24 14:49:02','2026-05-24 14:49:02','2026-05-24 14:49:02',NULL,NULL,NULL),(11,'ORDRENEWSYNC92FB1E86',34,7,'未配置规格 #7','server',5,'renew',NULL,NULL,NULL,16.00,0.00,16.00,'monthly',1,'[]','[]',NULL,1,'2026-05-24 14:49:02','2026-05-24 14:49:02','2026-05-24 14:49:02',NULL,NULL,NULL),(12,'ORDZEROSESSFFA2EEA1',1,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,50.00,50.00,0.00,'monthly',1,NULL,NULL,NULL,0,NULL,'2026-05-24 14:49:02','2026-05-24 14:49:02',NULL,NULL,NULL),(13,'ORDZEROPAID1D6E42C7',1,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,50.00,50.00,0.00,'monthly',1,NULL,NULL,NULL,1,'2026-05-24 14:49:02','2026-05-24 14:49:02','2026-05-24 14:49:02',NULL,NULL,NULL),(14,'ORDZERO9E8CBB18',35,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,10.00,10.00,0.00,'monthly',1,NULL,NULL,NULL,1,'2026-05-24 14:49:02','2026-05-24 14:49:02','2026-05-24 14:49:02',NULL,NULL,NULL),(15,'INVPRJ947FF280',1,NULL,'投影测试配置','server',NULL,'new',NULL,NULL,NULL,99.00,9.00,0.00,'monthly',2,NULL,NULL,NULL,0,NULL,'2026-05-24 14:49:02','2026-05-24 14:49:02',NULL,NULL,NULL),(16,'ORDPROVISION9A3940E5',38,22,'未配置规格 #22','server',NULL,'new',NULL,NULL,NULL,99.00,0.00,0.00,'monthly',2,'[]','[]',NULL,0,NULL,'2026-05-24 14:49:06','2026-05-24 14:49:06',NULL,NULL,NULL),(17,'ORDPROVPENDFDEE4B63',39,23,'未配置规格 #23','server',6,'new',NULL,NULL,NULL,99.00,0.00,0.00,'monthly',2,'[]','[]',NULL,2,NULL,'2026-05-24 14:49:06','2026-05-24 14:49:06',NULL,NULL,NULL),(18,'REF76CAAD71001',48,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,888.00,0.00,888.00,'monthly',1,NULL,NULL,NULL,1,NULL,'2026-05-24 14:49:09','2026-05-24 14:49:09',NULL,NULL,NULL),(19,'REF76CAAD71002',48,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,120.00,0.00,120.00,'monthly',1,NULL,NULL,NULL,1,NULL,'2026-05-24 14:49:09','2026-05-24 14:49:09',NULL,NULL,NULL),(20,'RFD9F6546AD',58,NULL,'Refund Referral Spec','server',NULL,'new',NULL,NULL,NULL,100.00,0.00,100.00,'monthly',1,NULL,NULL,NULL,5,'2026-05-24 14:49:13','2026-05-24 14:49:13','2026-05-24 14:49:13',NULL,NULL,'refund-order-9f6546ad'),(21,'RFD9702C8A9',60,NULL,'Refund Referral Spec','server',NULL,'new',NULL,NULL,NULL,100.00,0.00,100.00,'monthly',1,NULL,NULL,NULL,1,'2026-05-24 14:49:14','2026-05-24 14:49:14','2026-05-24 14:49:14',NULL,NULL,'refund-order-9702c8a9'),(22,'RW48CD9A35',62,31,'未配置规格 #31','server',NULL,'new',NULL,NULL,NULL,99.00,0.00,99.00,'monthly',1,NULL,NULL,NULL,1,'2026-05-24 14:49:14','2026-05-24 14:49:14','2026-05-24 14:49:14',NULL,NULL,'order-search-48cd9a35'),(23,'RENEWOLD6A4F7903',65,32,'未配置规格 #32','server',9,'renew',71,7,'CPNRENEW',20.00,0.00,0.00,'monthly',1,'[]',NULL,'[]',0,NULL,'2026-05-24 14:49:41','2026-05-24 14:49:41',NULL,NULL,NULL);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

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

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_callbacks`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_callbacks`
--

LOCK TABLES `payment_callbacks` WRITE;
/*!40000 ALTER TABLE `payment_callbacks` DISABLE KEYS */;
INSERT INTO `payment_callbacks` VALUES (1,1,'payment','TRADELEDGERE8F5914C','{\"trace_id\": \"ledger-trace-e8f5914c\", \"trade_status\": \"TRADE_SUCCESS\"}',1,'2026-05-24 14:31:59',NULL,NULL,NULL,'2026-05-24 14:48:59','2026-05-24 14:48:59'),(2,2,'payment',NULL,'{\"source\": \"balance\", \"trace_id\": \"order-balance-regression\"}',1,'2026-05-24 14:49:00',NULL,NULL,NULL,'2026-05-24 14:49:00','2026-05-24 14:49:00'),(3,3,'payment',NULL,'{\"source\": \"balance_part\", \"trace_id\": \"mix-pay-regression\", \"mix_payment\": true}',1,'2026-05-24 14:49:01',NULL,NULL,NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01'),(4,4,'payment',NULL,'{\"source\": \"alipay_precreate_mix\", \"trace_id\": \"mix-pay-regression\", \"mix_payment\": true, \"balance_payment_no\": \"PAY20260524224901168MVDVWOZN\"}',0,'2026-05-24 14:49:01',NULL,NULL,NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01'),(5,5,'payment',NULL,'{\"source\": \"free_confirm\", \"trace_id\": \"order-zero-regression\"}',0,'2026-05-24 14:49:02',NULL,NULL,NULL,'2026-05-24 14:49:02','2026-05-24 14:49:02'),(6,8,'payment','TRADE-F3A9475A','{\"trade_no\": \"TRADE-F3A9475A\", \"out_trade_no\": \"PAY20260524224912707UQ34GXPI\", \"total_amount\": \"5.00\", \"trade_status\": \"TRADE_SUCCESS\"}',1,'2026-05-24 14:49:12',NULL,NULL,NULL,'2026-05-24 14:49:12','2026-05-24 14:49:12'),(7,9,'payment',NULL,'{\"refund\": {\"trace_id\": \"refund-life-9f6546ad\", \"trade_no\": \"\", \"operator_id\": 1, \"refunded_at\": \"2026-05-24 22:49:13\", \"operator_name\": \"tester\", \"refund_amount\": \"100.00\", \"refund_method\": \"balance\", \"refund_reason\": \"referral reward reverse regression\", \"original_gateway\": \"balance\", \"refund_method_label\": \"退回余额\", \"original_gateway_label\": \"余额支付\"}, \"source\": \"balance\", \"trace_id\": \"refund-payment-9f6546ad\"}',1,'2026-05-24 14:49:13',NULL,NULL,NULL,'2026-05-24 14:49:13','2026-05-24 14:49:13'),(8,9,'refund',NULL,'{\"trace_id\": \"refund-life-9f6546ad\", \"trade_no\": \"\", \"operator_id\": 1, \"refunded_at\": \"2026-05-24 22:49:13\", \"operator_name\": \"tester\", \"refund_amount\": \"100.00\", \"refund_method\": \"balance\", \"refund_reason\": \"referral reward reverse regression\", \"original_gateway\": \"balance\", \"refund_method_label\": \"退回余额\", \"original_gateway_label\": \"余额支付\"}',1,'2026-05-24 14:49:13',NULL,NULL,NULL,'2026-05-24 14:49:13','2026-05-24 14:49:13');
/*!40000 ALTER TABLE `payment_callbacks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,'PAYLEDGERE8F5914C',26,4,1,'alipay','TRADELEDGERE8F5914C',80.00,1,'{\"trace_id\": \"callback-trace-e8f5914c\", \"trade_status\": \"TRADE_SUCCESS\"}','2026-05-24 14:30:59','2026-05-24 14:48:59','2026-05-24 14:48:59',NULL,NULL,NULL),(2,'PAY202605242249008104NFZ0AIX',29,6,2,'balance',NULL,45.00,1,'{\"source\": \"balance\", \"trace_id\": \"order-balance-regression\"}','2026-05-24 14:49:00','2026-05-24 14:49:00','2026-05-24 14:49:00',NULL,NULL,NULL),(3,'PAY20260524224901168MVDVWOZN',30,7,3,'balance',NULL,20.00,1,'{\"source\": \"balance_part\", \"trace_id\": \"mix-pay-regression\", \"mix_payment\": true}','2026-05-24 14:49:01','2026-05-24 14:49:01','2026-05-24 14:49:01',NULL,NULL,NULL),(4,'PAY20260524224901183KKV8Y3ED',30,7,3,'alipay',NULL,30.00,0,'{\"source\": \"alipay_precreate_mix\", \"trace_id\": \"mix-pay-regression\", \"mix_payment\": true, \"balance_payment_no\": \"PAY20260524224901168MVDVWOZN\"}',NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01',NULL,NULL,NULL),(5,'PAY2026052422490292406RW3DZC',35,14,8,'free',NULL,0.00,1,'{\"source\": \"free_confirm\", \"trace_id\": \"order-zero-regression\"}','2026-05-24 14:49:02','2026-05-24 14:49:02','2026-05-24 14:49:02',NULL,NULL,NULL),(8,'PAY20260524224912707UQ34GXPI',56,NULL,10,'alipay','TRADE-F3A9475A',5.00,1,'{\"trade_no\": \"TRADE-F3A9475A\", \"out_trade_no\": \"PAY20260524224912707UQ34GXPI\", \"total_amount\": \"5.00\", \"trade_status\": \"TRADE_SUCCESS\"}','2026-05-24 14:49:12','2026-05-24 14:49:12','2026-05-24 14:49:12',NULL,NULL,NULL),(9,'PAY20260524224913563D3ARNQUP',58,20,11,'balance',NULL,100.00,3,'{\"refund\": {\"trace_id\": \"refund-life-9f6546ad\", \"trade_no\": \"\", \"operator_id\": 1, \"refunded_at\": \"2026-05-24 22:49:13\", \"operator_name\": \"tester\", \"refund_amount\": \"100.00\", \"refund_method\": \"balance\", \"refund_reason\": \"referral reward reverse regression\", \"original_gateway\": \"balance\", \"refund_method_label\": \"退回余额\", \"original_gateway_label\": \"余额支付\"}, \"source\": \"balance\", \"trace_id\": \"refund-payment-9f6546ad\"}','2026-05-24 14:49:13','2026-05-24 14:49:13','2026-05-24 14:49:13',NULL,NULL,NULL),(10,'PAY20260524224914253CKRHUKIZ',60,21,13,'balance',NULL,100.00,1,'{\"source\": \"balance\", \"trace_id\": \"refund-payment-9702c8a9\"}','2026-05-24 14:49:14','2026-05-24 14:49:14','2026-05-24 14:49:14',NULL,NULL,NULL),(11,'PAY20260524224941512JC97HKSM',65,23,15,'alipay',NULL,20.00,2,'{\"trace_id\": \"renew-coupon-6a4f7903\", \"closed_by\": \"client\", \"closed_reason\": \"invoice_cancelled\"}',NULL,'2026-05-24 14:49:41','2026-05-24 14:49:41',NULL,NULL,NULL);
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (3,'App\\Models\\User',10,'client-token','0e43b21b8c735216050a477e073d956a856002ee00454fd216268d5e93d13d67','[\"*\"]',NULL,NULL,'2026-05-24 14:48:52','2026-05-24 14:48:52'),(5,'App\\Models\\AdminUser',10,'idle-admin-token','246b195e7dadce5db94125a3fa7ec90fda9f891392888e2b4b6b3769039db921','[\"*\"]','2026-05-24 14:49:15',NULL,'2026-05-24 14:49:15','2026-05-24 14:49:15');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_groups`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_groups`
--

LOCK TABLES `product_groups` WRITE;
/*!40000 ALTER TABLE `product_groups` DISABLE KEYS */;
INSERT INTO `product_groups` VALUES (3,NULL,'server','Dashboard Group 2a8943a9','','dashboard-group-2a8943a9',0,1,'2026-05-24 14:48:59','2026-05-24 14:48:59'),(4,NULL,'vps','Split category ff4d1a3c','','split-category-ff4d1a3c',0,1,'2026-05-24 14:49:04','2026-05-24 14:49:04'),(5,NULL,'vps','Split idem category 0159dfef','','split-idem-category-0159dfef',0,1,'2026-05-24 14:49:04','2026-05-24 14:49:04'),(6,NULL,'vps','Split MB category 77bd3640','','split-mb-category-77bd3640',0,1,'2026-05-24 14:49:04','2026-05-24 14:49:04'),(7,NULL,'vps','Split generic category 9d46f06e','','split-generic-category-9d46f06e',0,1,'2026-05-24 14:49:05','2026-05-24 14:49:05'),(8,NULL,'vps','Split flow category a40a1656','','split-flow-category-a40a1656',0,1,'2026-05-24 14:49:05','2026-05-24 14:49:05'),(9,NULL,'server','Referral Group 48cd9a35','','referral-group-48cd9a35',0,1,'2026-05-24 14:49:14','2026-05-24 14:49:14'),(15,NULL,'vps','Imported Root 862eacac','','imported-root-862eacac',0,1,'2026-05-24 14:49:46','2026-05-24 14:49:46'),(16,15,'vps','香港云服务器 / CN2',NULL,'imported-root-862eacac-cn2',0,1,'2026-05-24 14:49:46','2026-05-24 14:49:46');
/*!40000 ALTER TABLE `product_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_group_id` bigint unsigned DEFAULT NULL,
  `product_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'vps|dedicated|hosting|domain|other',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (2,NULL,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"20.00\"}',0.00,'[]','[]',-1,1,0,NULL,0,NULL,NULL,'2026-05-24 14:48:55','2026-05-24 14:48:55',NULL),(3,3,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"12.00\"}',0.00,'[]','[]',10,1,0,NULL,0,NULL,NULL,'2026-05-24 14:48:59','2026-05-24 14:48:59',NULL),(4,NULL,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"99.00\"}',10.00,'[]','[]',8,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01',NULL),(5,NULL,'server','通用NAT',NULL,NULL,NULL,'{\"monthly\": \"5.00\"}',0.00,'[{\"sub\": [{\"id\": \"2\", \"hidden\": 0, \"option_name\": \"2核\", \"option_name_first\": \"2\"}], \"field\": \"cpu\", \"option_type\": 6}, {\"sub\": [{\"id\": \"1024\", \"hidden\": 0, \"option_name\": \"1G\", \"option_name_first\": \"1024\"}], \"field\": \"memory\", \"option_type\": 8}]','[]',9,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01',NULL),(6,NULL,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"9.90\"}',0.00,'[]','[]',-1,1,0,NULL,1,NULL,NULL,'2026-05-24 14:49:02','2026-05-24 14:49:02',NULL),(7,NULL,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"16.00\"}',0.00,'[]','[]',-1,1,0,NULL,1,NULL,NULL,'2026-05-24 14:49:02','2026-05-24 14:49:02',NULL),(8,NULL,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"99.00\"}',0.00,'[]','{\"require_verification\": true}',10,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:03','2026-05-24 14:49:03',NULL),(9,NULL,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"99.00\"}',0.00,'[]','{\"require_phone\": true}',10,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:03','2026-05-24 14:49:03',NULL),(10,4,'vps',NULL,NULL,NULL,NULL,'{\"monthly\": \"50.00\", \"quarterly\": \"150.00\"}',0.00,'[{\"id\": 61000, \"sub\": [{\"id\": 70001, \"hidden\": 0, \"pricing\": {\"monthly\": \"0.00\", \"quarterly\": \"0.00\"}, \"config_id\": 61000, \"option_name\": \"2H\", \"option_name_first\": \"2\"}], \"name\": \"CPU\", \"field\": \"cpu\", \"hidden\": 1, \"required\": 0, \"config_id\": 61000, \"parameter\": \"2|2H\", \"option_type\": 6, \"default_value\": \"2\"}, {\"id\": 61001, \"sub\": [{\"id\": 71001, \"hidden\": 0, \"pricing\": {\"monthly\": \"0.00\", \"quarterly\": \"0.00\"}, \"config_id\": 61001, \"option_name\": \"2G\", \"option_name_first\": \"2\"}], \"name\": \"内存\", \"field\": \"memory\", \"hidden\": 1, \"required\": 0, \"config_id\": 61001, \"parameter\": \"2|2G\", \"option_type\": 8, \"default_value\": \"2\"}]','{\"require_phone\": true, \"upstream_split\": {\"variant_key\": \"cpu=2;memory=2\", \"source_product_id\": 10, \"source_product_name\": \"2 vCPU 2G\"}, \"upstream_default_config\": {\"cpu\": \"2\", \"memory\": \"2\"}}',-1,1,5,'hosting_panel_api',1,1,9001,'2026-05-24 14:49:04','2026-05-24 14:49:04',NULL),(11,4,'vps',NULL,NULL,NULL,NULL,'{\"monthly\": \"70.00\", \"quarterly\": \"210.00\"}',0.00,'[{\"id\": 61000, \"sub\": [{\"id\": 70001, \"hidden\": 0, \"pricing\": {\"monthly\": \"0.00\", \"quarterly\": \"0.00\"}, \"config_id\": 61000, \"option_name\": \"2H\", \"option_name_first\": \"2\"}], \"name\": \"CPU\", \"field\": \"cpu\", \"hidden\": 1, \"required\": 0, \"config_id\": 61000, \"parameter\": \"2|2H\", \"option_type\": 6, \"default_value\": \"2\"}, {\"id\": 61001, \"sub\": [{\"id\": 71002, \"hidden\": 0, \"pricing\": {\"monthly\": \"0.00\", \"quarterly\": \"0.00\"}, \"config_id\": 61001, \"option_name\": \"4G\", \"option_name_first\": \"4\"}], \"name\": \"内存\", \"field\": \"memory\", \"hidden\": 1, \"required\": 0, \"config_id\": 61001, \"parameter\": \"4|4G\", \"option_type\": 8, \"default_value\": \"4\"}]','{\"require_phone\": true, \"upstream_split\": {\"variant_key\": \"cpu=2;memory=4\", \"source_product_id\": 10, \"source_product_name\": \"2 vCPU 2G\"}, \"upstream_default_config\": {\"cpu\": \"2\", \"memory\": \"4\"}}',-1,1,5,'hosting_panel_api',1,1,9001,'2026-05-24 14:49:04','2026-05-24 14:49:04',NULL),(12,4,'vps',NULL,NULL,NULL,NULL,'{\"monthly\": \"80.00\", \"quarterly\": \"240.00\"}',0.00,'[{\"id\": 61000, \"sub\": [{\"id\": 70002, \"hidden\": 0, \"pricing\": {\"monthly\": \"0.00\", \"quarterly\": \"0.00\"}, \"config_id\": 61000, \"option_name\": \"4H\", \"option_name_first\": \"4\"}], \"name\": \"CPU\", \"field\": \"cpu\", \"hidden\": 1, \"required\": 0, \"config_id\": 61000, \"parameter\": \"4|4H\", \"option_type\": 6, \"default_value\": \"4\"}, {\"id\": 61001, \"sub\": [{\"id\": 71001, \"hidden\": 0, \"pricing\": {\"monthly\": \"0.00\", \"quarterly\": \"0.00\"}, \"config_id\": 61001, \"option_name\": \"2G\", \"option_name_first\": \"2\"}], \"name\": \"内存\", \"field\": \"memory\", \"hidden\": 1, \"required\": 0, \"config_id\": 61001, \"parameter\": \"2|2G\", \"option_type\": 8, \"default_value\": \"2\"}]','{\"require_phone\": true, \"upstream_split\": {\"variant_key\": \"cpu=4;memory=2\", \"source_product_id\": 10, \"source_product_name\": \"2 vCPU 2G\"}, \"upstream_default_config\": {\"cpu\": \"4\", \"memory\": \"2\"}}',-1,1,5,'hosting_panel_api',1,1,9001,'2026-05-24 14:49:04','2026-05-24 14:49:04',NULL),(13,4,'vps',NULL,NULL,NULL,NULL,'{\"monthly\": \"100.00\", \"quarterly\": \"300.00\"}',0.00,'[{\"id\": 61000, \"sub\": [{\"id\": 70002, \"hidden\": 0, \"pricing\": {\"monthly\": \"0.00\", \"quarterly\": \"0.00\"}, \"config_id\": 61000, \"option_name\": \"4H\", \"option_name_first\": \"4\"}], \"name\": \"CPU\", \"field\": \"cpu\", \"hidden\": 1, \"required\": 0, \"config_id\": 61000, \"parameter\": \"4|4H\", \"option_type\": 6, \"default_value\": \"4\"}, {\"id\": 61001, \"sub\": [{\"id\": 71002, \"hidden\": 0, \"pricing\": {\"monthly\": \"0.00\", \"quarterly\": \"0.00\"}, \"config_id\": 61001, \"option_name\": \"4G\", \"option_name_first\": \"4\"}], \"name\": \"内存\", \"field\": \"memory\", \"hidden\": 1, \"required\": 0, \"config_id\": 61001, \"parameter\": \"4|4G\", \"option_type\": 8, \"default_value\": \"4\"}]','{\"require_phone\": true, \"upstream_split\": {\"variant_key\": \"cpu=4;memory=4\", \"source_product_id\": 10, \"source_product_name\": \"2 vCPU 2G\"}, \"upstream_default_config\": {\"cpu\": \"4\", \"memory\": \"4\"}}',-1,1,5,'hosting_panel_api',1,1,9001,'2026-05-24 14:49:04','2026-05-24 14:49:04',NULL),(14,5,'vps',NULL,NULL,NULL,NULL,'{\"monthly\": \"50.00\"}',0.00,'[{\"id\": 62001, \"sub\": [{\"id\": 72001, \"hidden\": 0, \"pricing\": [], \"option_name\": \"2G\", \"option_name_first\": \"2\"}], \"name\": \"内存\", \"field\": \"memory\", \"hidden\": 1, \"required\": 0, \"parameter\": \"2|2G\", \"option_type\": 8, \"default_value\": \"2\"}]','{\"upstream_split\": {\"variant_key\": \"memory=2\", \"source_product_id\": 14, \"source_product_name\": \"2G\"}, \"upstream_default_config\": {\"memory\": \"2\"}}',-1,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:04','2026-05-24 14:49:04',NULL),(15,5,'vps',NULL,NULL,NULL,NULL,'{\"monthly\": \"50.00\"}',0.00,'[{\"id\": 62001, \"sub\": [{\"id\": 72002, \"hidden\": 0, \"pricing\": [], \"option_name\": \"4G\", \"option_name_first\": \"4\"}], \"name\": \"内存\", \"field\": \"memory\", \"hidden\": 1, \"required\": 0, \"parameter\": \"4|4G\", \"option_type\": 8, \"default_value\": \"4\"}]','{\"upstream_split\": {\"variant_key\": \"memory=4\", \"source_product_id\": 14, \"source_product_name\": \"2G\"}, \"upstream_default_config\": {\"memory\": \"4\"}}',-1,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:04','2026-05-24 14:49:04',NULL),(16,6,'vps',NULL,NULL,NULL,NULL,'{\"monthly\": \"50.00\"}',0.00,'[{\"id\": 63001, \"sub\": [{\"id\": 73001, \"option_name\": \"1024\", \"option_name_first\": \"1024\"}, {\"id\": 73002, \"option_name\": \"3072\", \"option_name_first\": \"3072\"}, {\"id\": 73003, \"option_name\": \"5120\", \"option_name_first\": \"5120\"}], \"name\": \"内存\", \"field\": \"memory\", \"option_type\": 8}]','[]',-1,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:04','2026-05-24 14:49:04',NULL),(17,7,'vps',NULL,NULL,NULL,NULL,'{\"monthly\": \"50.00\"}',0.00,'[{\"id\": 64001, \"sub\": [{\"id\": 74001, \"hidden\": 0, \"pricing\": [], \"option_name\": \"2G\", \"option_name_first\": \"2\"}], \"name\": \"内存\", \"field\": \"memory\", \"hidden\": 1, \"required\": 0, \"parameter\": \"2|2G\", \"option_type\": 8, \"default_value\": \"2\"}]','{\"upstream_split\": {\"variant_key\": \"memory=2\", \"source_product_id\": 17, \"source_product_name\": \"2G\"}, \"upstream_default_config\": {\"memory\": \"2\"}}',-1,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:05','2026-05-24 14:49:05',NULL),(18,7,'vps',NULL,NULL,NULL,NULL,'{\"monthly\": \"50.00\"}',0.00,'[{\"id\": 64001, \"sub\": [{\"id\": 74002, \"hidden\": 0, \"pricing\": [], \"option_name\": \"4G\", \"option_name_first\": \"4\"}], \"name\": \"内存\", \"field\": \"memory\", \"hidden\": 1, \"required\": 0, \"parameter\": \"4|4G\", \"option_type\": 8, \"default_value\": \"4\"}]','{\"upstream_split\": {\"variant_key\": \"memory=4\", \"source_product_id\": 17, \"source_product_name\": \"2G\"}, \"upstream_default_config\": {\"memory\": \"4\"}}',-1,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:05','2026-05-24 14:49:05',NULL),(19,8,'vps',NULL,NULL,NULL,NULL,'{\"monthly\": \"50.00\"}',0.00,'[{\"id\": 63001, \"sub\": [{\"id\": 73001, \"hidden\": 0, \"pricing\": [], \"option_name\": \"2G\", \"option_name_first\": \"2048\"}], \"name\": \"memory\", \"field\": \"memory\", \"hidden\": 1, \"required\": 0, \"parameter\": \"2048|2G\", \"option_name\": \"memory|memory\", \"option_type\": 8, \"default_value\": \"2048\"}, {\"id\": 63002, \"sub\": [{\"id\": 73003, \"option_name\": \"1024GB\", \"option_name_first\": \"1024\"}, {\"id\": 73004, \"option_name\": \"3072GB\", \"option_name_first\": \"3072\"}, {\"id\": 73005, \"option_name\": \"5120GB\", \"option_name_first\": \"5120\"}], \"name\": \"flow\", \"field\": \"flow_limit\", \"option_name\": \"flow_limit|traffic\", \"option_type\": 8}]','{\"upstream_split\": {\"variant_key\": \"memory=2048\", \"source_product_id\": 19, \"source_product_name\": \"2 vCPU 2G\"}, \"upstream_default_config\": {\"memory\": \"2048\"}}',-1,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:05','2026-05-24 14:49:05',NULL),(20,8,'vps',NULL,NULL,NULL,NULL,'{\"monthly\": \"50.00\"}',0.00,'[{\"id\": 63001, \"sub\": [{\"id\": 73002, \"hidden\": 0, \"pricing\": [], \"option_name\": \"4G\", \"option_name_first\": \"4096\"}], \"name\": \"memory\", \"field\": \"memory\", \"hidden\": 1, \"required\": 0, \"parameter\": \"4096|4G\", \"option_name\": \"memory|memory\", \"option_type\": 8, \"default_value\": \"4096\"}, {\"id\": 63002, \"sub\": [{\"id\": 73003, \"option_name\": \"1024GB\", \"option_name_first\": \"1024\"}, {\"id\": 73004, \"option_name\": \"3072GB\", \"option_name_first\": \"3072\"}, {\"id\": 73005, \"option_name\": \"5120GB\", \"option_name_first\": \"5120\"}], \"name\": \"flow\", \"field\": \"flow_limit\", \"option_name\": \"flow_limit|traffic\", \"option_type\": 8}]','{\"upstream_split\": {\"variant_key\": \"memory=4096\", \"source_product_id\": 19, \"source_product_name\": \"2 vCPU 2G\"}, \"upstream_default_config\": {\"memory\": \"4096\"}}',-1,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:05','2026-05-24 14:49:05',NULL),(21,NULL,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"99.00\"}',0.00,'[]','[]',1,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:05','2026-05-24 14:49:05',NULL),(22,NULL,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"99.00\"}',0.00,'[]','[]',-1,1,0,NULL,0,2,45344,'2026-05-24 14:49:06','2026-05-24 14:49:06',NULL),(23,NULL,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"99.00\"}',0.00,'[]','[]',-1,1,0,NULL,1,3,31343,'2026-05-24 14:49:06','2026-05-24 14:49:06',NULL),(24,NULL,'vps','西安云电脑',NULL,NULL,NULL,'{\"monthly\": \"5.00\"}',0.00,'[{\"sub\": [{\"id\": \"2\", \"option_name\": \"2核\"}], \"field\": \"cpu\", \"option_type\": 6}, {\"sub\": [{\"id\": \"2048\", \"option_name\": \"2G\"}], \"field\": \"memory\", \"option_type\": 8}]','{\"provision_hostname\": {\"mode\": \"system\"}}',-1,1,0,'hosting_panel_api',1,4,9001,'2026-05-24 14:49:06','2026-05-24 14:49:06',NULL),(25,NULL,'vps','西安云电脑',NULL,NULL,NULL,'{\"monthly\": \"5.00\"}',0.00,'[{\"sub\": [{\"id\": \"2\", \"option_name\": \"2核\"}], \"field\": \"cpu\", \"option_type\": 6}, {\"sub\": [{\"id\": \"2048\", \"option_name\": \"2G\"}], \"field\": \"memory\", \"option_type\": 8}]','{\"provision_hostname\": {\"mode\": \"system\"}}',-1,1,0,'hosting_panel_api',1,5,9001,'2026-05-24 14:49:07','2026-05-24 14:49:07',NULL),(26,NULL,'vps','西安云电脑',NULL,NULL,NULL,'{\"monthly\": \"5.00\"}',0.00,'[{\"sub\": [{\"id\": \"2\", \"option_name\": \"2核\"}], \"field\": \"cpu\", \"option_type\": 6}, {\"sub\": [{\"id\": \"2048\", \"option_name\": \"2G\"}], \"field\": \"memory\", \"option_type\": 8}]','{\"provision_hostname\": {\"mode\": \"system\"}}',-1,1,0,'hosting_panel_api',1,6,9001,'2026-05-24 14:49:07','2026-05-24 14:49:07',NULL),(27,NULL,'vps','西安云电脑',NULL,NULL,NULL,'{\"monthly\": \"5.00\"}',0.00,'[{\"sub\": [{\"id\": \"2\", \"option_name\": \"2核\"}], \"field\": \"cpu\", \"option_type\": 6}, {\"sub\": [{\"id\": \"2048\", \"option_name\": \"2G\"}], \"field\": \"memory\", \"option_type\": 8}]','{\"provision_hostname\": {\"mode\": \"system\"}}',-1,1,0,'hosting_panel_api',1,7,9001,'2026-05-24 14:49:07','2026-05-24 14:49:07',NULL),(28,NULL,'vps','西安云电脑',NULL,NULL,NULL,'{\"monthly\": \"5.00\"}',0.00,'[{\"sub\": [{\"id\": \"2\", \"option_name\": \"2核\"}], \"field\": \"cpu\", \"option_type\": 6}, {\"sub\": [{\"id\": \"2048\", \"option_name\": \"2G\"}], \"field\": \"memory\", \"option_type\": 8}]','{\"provision_hostname\": {\"mode\": \"fixed\", \"value\": \"fixed-host\", \"length\": 10}}',-1,1,0,'hosting_panel_api',1,8,9001,'2026-05-24 14:49:08','2026-05-24 14:49:08',NULL),(29,NULL,'vps','西安云电脑',NULL,NULL,NULL,'{\"monthly\": \"5.00\"}',0.00,'[{\"sub\": [{\"id\": \"2\", \"option_name\": \"2核\"}], \"field\": \"cpu\", \"option_type\": 6}, {\"sub\": [{\"id\": \"2048\", \"option_name\": \"2G\"}], \"field\": \"memory\", \"option_type\": 8}]','{\"provision_hostname\": {\"mode\": \"system\"}}',-1,1,0,'hosting_panel_api',1,9,9001,'2026-05-24 14:49:08','2026-05-24 14:49:08',NULL),(30,NULL,'vps','西安云电脑',NULL,NULL,NULL,'{\"monthly\": \"5.00\"}',0.00,'[{\"sub\": [{\"id\": \"2\", \"option_name\": \"2核\"}], \"field\": \"cpu\", \"option_type\": 6}, {\"sub\": [{\"id\": \"2048\", \"option_name\": \"2G\"}], \"field\": \"memory\", \"option_type\": 8}]','{\"provision_hostname\": {\"mode\": \"system\"}}',-1,1,0,'hosting_panel_api',1,10,9001,'2026-05-24 14:49:08','2026-05-24 14:49:08',NULL),(31,9,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"99.00\"}',0.00,'[]','[]',-1,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:14','2026-05-24 14:49:14',NULL),(32,NULL,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"20.00\"}',0.00,'[]','[]',-1,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:41','2026-05-24 14:49:41',NULL),(38,16,'vps',NULL,NULL,NULL,NULL,'{\"monthly\": \"99.00\", \"annually\": \"1188.00\", \"quarterly\": \"297.00\", \"semiannually\": \"594.00\"}',10.00,'[]','{\"upstream_default_config\": {\"cpu\": \"2\", \"memory\": \"4\"}}',8,1,0,'hosting_panel_api',1,12,17786,'2026-05-24 14:49:46','2026-05-24 14:49:46',NULL),(39,NULL,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"19.90\"}',0.00,NULL,NULL,10,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL),(40,NULL,'server',NULL,NULL,NULL,NULL,'{\"monthly\": \"29.90\"}',0.00,NULL,NULL,10,1,0,NULL,0,NULL,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_account_logs`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_account_logs`
--

LOCK TABLES `referral_account_logs` WRITE;
/*!40000 ALTER TABLE `referral_account_logs` DISABLE KEYS */;
INSERT INTO `referral_account_logs` VALUES (1,11,'reward_frozen',10.00,10.00,0.00,0.00,0.00,'referral regression',NULL,NULL,'system','referral-regression','2026-05-24 14:48:52');
/*!40000 ALTER TABLE `referral_account_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_rewards`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_rewards`
--

LOCK TABLES `referral_rewards` WRITE;
/*!40000 ALTER TABLE `referral_rewards` DISABLE KEYS */;
INSERT INTO `referral_rewards` VALUES (1,48,48,18,NULL,NULL,888.00,12.00,66.00,'2026-05-25 14:49:09',NULL,0,'system','frozen reward','trace-76caad71','2026-05-24 14:49:09','2026-05-24 14:49:09','2026-05-24 14:49:09'),(2,48,48,19,NULL,NULL,120.00,12.00,14.00,'2026-05-23 14:49:09','2026-05-24 14:49:09',1,'system','released reward','trace-release-76caad71','2026-05-24 14:49:09','2026-05-24 14:49:09','2026-05-24 14:49:09'),(3,57,58,20,NULL,NULL,100.00,5.00,5.00,'2026-05-24 14:48:13','2026-05-24 14:49:13',2,'system','订单退款，推广奖励已撤销','refund:refund-life-9f6546ad','2026-05-24 14:49:13','2026-05-24 14:49:13','2026-05-24 14:49:13'),(4,59,60,21,NULL,NULL,100.00,5.00,5.00,'2026-05-24 14:48:14','2026-05-24 14:49:14',1,'system','冻结期结束，奖励已转为可提现','reward-refund-9702c8a9','2026-05-24 14:49:14','2026-05-24 14:49:14','2026-05-24 14:49:14'),(5,61,62,22,NULL,31,99.00,10.00,9.90,NULL,NULL,0,'system','reward-search-regression','reward-search-48cd9a35','2026-05-24 14:49:14','2026-05-24 14:49:14','2026-05-24 14:49:14');
/*!40000 ALTER TABLE `referral_rewards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_withdrawals`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_withdrawals`
--

LOCK TABLES `referral_withdrawals` WRITE;
/*!40000 ALTER TABLE `referral_withdrawals` DISABLE KEYS */;
INSERT INTO `referral_withdrawals` VALUES (1,48,20.00,'alipay','tester','zhifubao-76caad71',0,'pending withdrawal','admin','withdraw-pending-76caad71',NULL,'2026-05-24 14:49:09','2026-05-24 14:49:09'),(2,48,30.00,'alipay','tester','zhifubao-ok-76caad71',1,'approved withdrawal','admin','withdraw-approved-76caad71','2026-05-24 14:49:09','2026-05-24 14:49:09','2026-05-24 14:49:09'),(3,63,8.00,'alipay','Search User','13800138000',0,'withdraw-search-regression','client','withdraw-search-38d4b68c',NULL,'2026-05-24 14:49:15','2026-05-24 14:49:15');
/*!40000 ALTER TABLE `referral_withdrawals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'ledger-role-49e27b67','Ledger Role','[\"invoice.list\", \"invoice.detail\"]','2026-05-24 14:48:58','2026-05-24 14:48:58'),(2,'ledger-role-4484e7d8','Ledger Role','[\"invoice.list\", \"invoice.detail\"]','2026-05-24 14:48:58','2026-05-24 14:48:58'),(3,'product-split-ff4d1a3c','Product Split','[\"*\"]','2026-05-24 14:49:03','2026-05-24 14:49:03'),(4,'product-split-idempotent-0159dfef','Product Split Idempotent','[\"*\"]','2026-05-24 14:49:04','2026-05-24 14:49:04'),(5,'product-split-mb-memory-77bd3640','Product Split MB Memory','[\"*\"]','2026-05-24 14:49:04','2026-05-24 14:49:04'),(6,'product-split-generic-name-9d46f06e','Product Split Generic Name','[\"*\"]','2026-05-24 14:49:04','2026-05-24 14:49:04'),(7,'product-split-flow-limit-a40a1656','Product Split Flow Limit','[\"*\"]','2026-05-24 14:49:05','2026-05-24 14:49:05'),(8,'admin-role-4e6087fb','Test Role','[\"referral.list\"]','2026-05-24 14:49:11','2026-05-24 14:49:11'),(9,'admin-role-91f2ae15','Test Role','[\"verification.list\"]','2026-05-24 14:49:11','2026-05-24 14:49:11'),(10,'idle-admin-role-9379233f','Idle Admin','[\"*\"]','2026-05-24 14:49:15','2026-05-24 14:49:15'),(11,'supplier-alias-c9bcd4db','Supplier Alias','[\"product.manage\"]','2026-05-24 14:49:46','2026-05-24 14:49:46'),(12,'ticket-regression-role-c0e25ebd','Ticket Regression Role','[]','2026-05-24 14:49:47','2026-05-24 14:49:47'),(13,'ticket-notify-role-9a49be0c','Ticket Notify Role','[\"ticket.reply\"]','2026-05-24 14:49:49','2026-05-24 14:49:49'),(14,'upload-security-a6c7780f','Upload Security','[\"*\"]','2026-05-24 14:49:49','2026-05-24 14:49:49'),(15,'upload-security-0cbc3c88','Upload Security','[\"*\"]','2026-05-24 14:49:50','2026-05-24 14:49:50'),(16,'upload-security-b1660b76','Upload Security','[\"*\"]','2026-05-24 14:49:50','2026-05-24 14:49:50');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servers`
--

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

--
-- Dumping data for table `servers`
--

LOCK TABLES `servers` WRITE;
/*!40000 ALTER TABLE `servers` DISABLE KEYS */;
/*!40000 ALTER TABLE `servers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (4,8,2,NULL,NULL,'db-normalize-test','','monthly',1.00,NULL,0,NULL,NULL,0,NULL,'2026-05-24 14:48:57','2026-05-24 14:48:57',NULL,NULL,'legacy-services-4'),(5,34,7,11,NULL,'Renew Sync Test Service','renew-sync.example.com','monthly',16.00,NULL,1,'{\"supplier_id\": \"1\", \"upstream_host_id\": 58376}','2026-06-24 14:49:02',0,NULL,'2026-05-24 14:49:02','2026-05-24 14:49:02',NULL,NULL,NULL),(6,39,23,17,NULL,'Pending Service fdee4b63','pending-fdee4b63.example.com','monthly',99.00,NULL,0,'{\"provision_error\": \"上游开通处理中\"}',NULL,1,NULL,'2026-05-24 14:49:06','2026-05-24 14:49:06',NULL,NULL,NULL),(7,45,29,3001,NULL,'通用NAT-2vcpu-1gib','ltser1234567890','monthly',5.00,'{\"monthly\": {\"enabled\": true, \"base_amount\": \"5.00\", \"manual_amount\": null}, \"annually\": {\"enabled\": true, \"base_amount\": \"60.00\", \"manual_amount\": null}, \"quarterly\": {\"enabled\": true, \"base_amount\": \"15.00\", \"manual_amount\": null}, \"semiannually\": {\"enabled\": true, \"base_amount\": \"30.00\", \"manual_amount\": null}}',0,'{\"requested_config\": {\"cpu\": \"2\", \"memory\": \"2048\", \"hostname\": \"ltser1234567890\"}, \"created_from_order\": \"ORD20260404000001TEST\"}',NULL,1,NULL,'2026-05-24 14:49:08','2026-05-24 14:49:08',NULL,NULL,NULL),(8,46,30,NULL,NULL,'通用NAT-2vcpu-1gib','ser380376647391','monthly',5.00,'{\"monthly\": {\"enabled\": true, \"base_amount\": \"5.00\", \"manual_amount\": null}, \"annually\": {\"enabled\": true, \"base_amount\": \"60.00\", \"manual_amount\": null}, \"quarterly\": {\"enabled\": true, \"base_amount\": \"15.00\", \"manual_amount\": null}, \"semiannually\": {\"enabled\": true, \"base_amount\": \"30.00\", \"manual_amount\": null}}',1,'{\"os\": \"CentOS-7.6.1810-x64\", \"provider\": \"hosting_panel_api\", \"supplier_id\": 10, \"assigned_ips\": [], \"dedicated_ip\": \"\", \"requested_host\": \"ser380376647391\", \"provision_error\": null, \"upstream_status\": \"Active\", \"requested_config\": {\"cpu\": \"2\", \"memory\": \"1024\", \"hostname\": \"ser380376647391\"}, \"upstream_host_id\": 75831, \"connection_secret\": \"eyJpdiI6IkxtTjhQMmFUWVVPQWo5Y3RPK2FhZGc9PSIsInZhbHVlIjoiNTBqVnRCeURmVXg2d044cFNSVS9YUkZOajJNUTdreXQ0K1pYSUwwYWZvSjBlWmNxdzVRV0kxZGNrYkdpa1lCKzliZklWdXdQMS8xckJ2RHJsSDk4QWIydWJsNkRCbDZLUEVBUHJ2OXFheTdaV2svT3ViVllTMTlid1BrZlk0cEsiLCJtYWMiOiJjZDBjZTIzZjAyZDA0ODA5ZjI5ZTRlNDZlNDU2ZWE3ZTk2NmIwYWE0YzdkMGRlMjhkZmMwZDIzYTliNGRkMzFkIiwidGFnIjoiIn0=\", \"upstream_host_ids\": [75831], \"created_from_order\": \"ORD20260404000001TEST\", \"host_config_option\": [], \"last_provisioned_at\": \"2026-05-24 22:49:08\", \"supplier_product_id\": 9001, \"upstream_invoice_id\": 978109, \"upstream_product_id\": 831, \"connection_cached_at\": \"2026-05-24 22:49:08\", \"upstream_product_name\": \"西安云电脑 A型\"}','2026-06-24 14:49:08',1,NULL,'2026-05-24 14:49:08','2026-05-24 14:49:08',NULL,NULL,NULL),(9,65,32,0,NULL,'Renewable Service','renew-6a4f7903.example.com','monthly',20.00,NULL,1,'[]','2026-06-24 14:49:41',0,NULL,'2026-05-24 14:49:41','2026-05-24 14:49:41',NULL,NULL,NULL),(12,77,39,NULL,NULL,'实例 A','example.test','monthly',19.90,NULL,1,'[]',NULL,1,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL,NULL,NULL),(13,78,40,NULL,NULL,'实例 B','detail-endpoint.test','monthly',29.90,NULL,1,'[]',NULL,1,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL,NULL,NULL);
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

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

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (2,'notification','email_enabled','1'),(3,'notification','email_host','smtphk.qiye.aliyun.com'),(4,'notification','email_port','465'),(5,'notification','email_username','mail@coyjs.cn'),(6,'notification','email_password','SVxHTmNx6XjByT0Z'),(7,'notification','email_from_name','创欧云'),(10,'product','instance_spec_catalog','[{\"id\":\"spec_nat_2c1g\",\"value\":\"nat_2c1g\",\"text\":\"通用NAT\",\"status\":\"展示中\",\"bindings\":[{\"product_id\":5,\"status\":1}]}]'),(11,'traffic_package_catalog','items','[]'),(22,'basic','site_name','创欧云'),(23,'basic','site_logo',NULL),(24,'codex_runtime_1ac5a854','sample_key','sample-value-1ac5a854'),(25,'codex_service_a1b9cf7f','custom_key','custom-value-a1b9cf7f');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sms_logs`
--

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

--
-- Dumping data for table `sms_logs`
--

LOCK TABLES `sms_logs` WRITE;
/*!40000 ALTER TABLE `sms_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `sms_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'Split Supplier ff4d1a3c','split-supplier-ff4d1a3c','hosting_panel_api','https://example.com','tester','secret',NULL,NULL,NULL,NULL,1,0,NULL,'2026-05-24 14:49:04','2026-05-24 14:49:04'),(2,'Provision Supplier 9a3940e5','provision-9a3940e5','hosting_panel_api','https://supplier-9a3940e5.example.com','demo','secret',NULL,NULL,NULL,NULL,1,1,NULL,'2026-05-24 14:49:06','2026-05-24 14:49:06'),(3,'Provision Supplier Pending Service fdee4b63','provision-pending-fdee4b63','hosting_panel_api','https://supplier-fdee4b63.example.com','demo','secret',NULL,NULL,NULL,NULL,1,1,NULL,'2026-05-24 14:49:06','2026-05-24 14:49:06'),(4,'测试供应商-6a312741','test-hosting-6a312741','hosting_panel_api',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,'2026-05-24 14:49:06','2026-05-24 14:49:06'),(5,'测试供应商-a8e1a983','test-hosting-a8e1a983','hosting_panel_api',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,'2026-05-24 14:49:06','2026-05-24 14:49:06'),(6,'测试供应商-7d3897f4','test-hosting-7d3897f4','hosting_panel_api',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,'2026-05-24 14:49:07','2026-05-24 14:49:07'),(7,'测试供应商-f4300d18','test-hosting-f4300d18','hosting_panel_api',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,'2026-05-24 14:49:07','2026-05-24 14:49:07'),(8,'测试供应商-ee430907','test-hosting-ee430907','hosting_panel_api',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,'2026-05-24 14:49:07','2026-05-24 14:49:07'),(9,'测试供应商-cc4320e4','test-hosting-cc4320e4','hosting_panel_api',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,'2026-05-24 14:49:08','2026-05-24 14:49:08'),(10,'测试供应商-22e124d9','test-hosting-22e124d9','hosting_panel_api',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,NULL,'2026-05-24 14:49:08','2026-05-24 14:49:08'),(12,'Batch Connect Supplier 862eacac','batch-connect-862eacac','hosting_panel_api','https://supplier-862eacac.example.com','demo','secret',NULL,NULL,NULL,NULL,1,1,NULL,'2026-05-24 14:49:46','2026-05-24 14:49:46'),(13,'Mofang Alias c9bcd4db','hosting_panel_api','hosting_panel_api','https://supplier-c9bcd4db.example.com','demo','secret',NULL,NULL,NULL,NULL,1,0,NULL,'2026-05-24 14:49:47','2026-05-24 14:49:47');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_replies`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_replies`
--

LOCK TABLES `ticket_replies` WRITE;
/*!40000 ALTER TABLE `ticket_replies` DISABLE KEYS */;
INSERT INTO `ticket_replies` VALUES (1,1,74,'Initial message',0,'[]','2026-05-24 14:49:47'),(2,2,75,'Initial message',0,'[]','2026-05-24 14:49:48'),(3,2,75,'Client follow-up',0,'[]','2026-05-24 14:49:48'),(4,2,12,'Staff response',1,'[]','2026-05-24 14:49:48'),(5,9,79,'Initial message',0,'[]','2026-05-24 14:49:49');
/*!40000 ALTER TABLE `ticket_replies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
INSERT INTO `tickets` VALUES (1,74,'support','Ticket Create Regression',2,0,NULL,NULL,'2026-05-24 14:49:47','2026-05-24 14:49:47',NULL),(2,75,'support','Ticket Reply Regression',2,2,NULL,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL),(3,76,'support','Open Ticket',2,0,NULL,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL),(4,76,'support','Client Reply Ticket',2,1,NULL,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL),(5,76,'support','Staff Reply Ticket',2,2,NULL,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL),(6,76,'support','Closed Ticket',2,3,NULL,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL),(7,77,'support','Ticket Detail Regression',2,0,12,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL),(8,78,'support','Ticket Detail Endpoint Regression',2,0,13,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL),(9,79,'support','Ticket Notification Regression',2,0,NULL,NULL,'2026-05-24 14:49:49','2026-05-24 14:49:49',NULL);
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_accounts`
--

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

--
-- Dumping data for table `user_accounts`
--

LOCK TABLES `user_accounts` WRITE;
/*!40000 ALTER TABLE `user_accounts` DISABLE KEYS */;
INSERT INTO `user_accounts` VALUES (29,55.00,0.00,0.00,0.00,0.00,0.00,0,'2026-05-24 14:49:00','2026-05-24 14:49:00'),(30,10.00,0.00,0.00,0.00,0.00,0.00,0,'2026-05-24 14:49:01','2026-05-24 14:49:01'),(47,88.80,0.00,0.00,0.00,0.00,0.00,0,'2026-05-24 14:49:09','2026-05-24 14:49:09'),(48,0.00,0.00,11.00,22.00,3.00,44.00,0,'2026-05-24 14:49:09','2026-05-24 14:49:09'),(56,15.00,0.00,0.00,0.00,0.00,0.00,0,'2026-05-24 14:49:12','2026-05-24 14:49:12'),(57,0.00,0.00,0.00,0.00,0.00,0.00,0,'2026-05-24 14:49:13','2026-05-24 14:49:13'),(58,100.00,0.00,0.00,0.00,0.00,0.00,0,'2026-05-24 14:49:13','2026-05-24 14:49:13'),(59,0.00,0.00,0.00,0.00,0.00,10.00,0,'2026-05-24 14:49:14','2026-05-24 14:49:14'),(81,88.80,20.00,0.00,0.00,0.00,0.00,0,'2026-05-24 14:49:51','2026-05-24 14:49:51');
/*!40000 ALTER TABLE `user_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_coupons`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_coupons`
--

LOCK TABLES `user_coupons` WRITE;
/*!40000 ALTER TABLE `user_coupons` DISABLE KEYS */;
INSERT INTO `user_coupons` VALUES (1,2,13,'claim',1,'2026-05-24 14:48:54',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:54','2026-05-24 14:48:54'),(2,3,14,'grant',0,NULL,'2026-05-24 14:48:55',NULL,NULL,'coupon-regression','coupon-update-f003706c','2026-05-24 14:48:55','2026-05-24 14:48:55'),(4,3,16,'grant',1,NULL,'2026-05-24 14:48:55',NULL,NULL,'coupon-regression','coupon-update-f003706c','2026-05-24 14:48:55','2026-05-24 14:48:55'),(5,4,17,'claim',1,'2026-05-24 14:48:55',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:55','2026-05-24 14:48:55'),(6,4,18,'grant',1,NULL,'2026-05-24 14:48:55',NULL,NULL,'coupon-regression','coupon-switch-b4b2e7b6','2026-05-24 14:48:55','2026-05-24 14:48:55'),(7,5,19,'grant',1,NULL,'2026-05-24 14:48:56',NULL,NULL,'coupon-regression','coupon-limit-create-3817b2f7','2026-05-24 14:48:56','2026-05-24 14:48:56'),(9,5,21,'grant',1,NULL,'2026-05-24 14:48:56',NULL,NULL,'coupon-regression','coupon-limit-update-3817b2f7','2026-05-24 14:48:56','2026-05-24 14:48:56'),(10,6,22,'grant',1,NULL,'2026-05-24 14:48:57',NULL,NULL,'coupon-regression','coupon-code-create-73e16780','2026-05-24 14:48:57','2026-05-24 14:48:57');
/*!40000 ALTER TABLE `user_coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

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
  `login_notify` tinyint(1) NOT NULL DEFAULT '1' COMMENT '账号登录提醒 0关闭 1开启',
  `login_location_alert` tinyint(1) NOT NULL DEFAULT '1' COMMENT '异地登录提醒 0关闭 1开启',
  `password_change_alert` tinyint(1) NOT NULL DEFAULT '1' COMMENT '密码变更提醒 0关闭 1开启',
  `phone_change_alert` tinyint(1) NOT NULL DEFAULT '1' COMMENT '手机号变更提醒 0关闭 1开启',
  `email_change_alert` tinyint(1) NOT NULL DEFAULT '1' COMMENT '邮箱变更提醒 0关闭 1开启',
  `marketing_alert` tinyint(1) NOT NULL DEFAULT '0' COMMENT '营销提醒接收 0关闭 1开启',
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
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (8,'client-profile-72fefc6a@example.com','$2y$12$9UteJrxuJFT/k1xwAED4Feg0/RLtU7SiONsFNHcN3wRaePyHFApXe','Updated Nickname','13190630899','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:51','2026-05-24 14:48:51',NULL),(9,'client-profile-log-f5f31401@example.com','$2y$12$uDXZfwKiC10AlxM74VREUuIcRyTI70PtrZ0XB527/DDAqrVexZkqS','Logged Nickname','13659475416','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:52','2026-05-24 14:48:52',NULL),(10,'client-register-aff871bd@example.com','$2y$12$lJEqqa2cUwZez/QrkGjhMOJ47RApHwE1B/6w4Ec4cT4hwEo4nXjfC','Registered Nickname',NULL,'','','','','XS94FT',NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,'127.0.0.1','2026-05-24 14:48:52',NULL,'2026-05-24 14:48:52','2026-05-24 14:48:52',NULL),(11,'referral-regression-5a481ffe@example.com','$2y$12$gibpAvWHBERPFziTtmW..udazZjO0hfpgeYfwGMhWa/pBdjL56p5G','Referral Regression','13000609760','','','','','OHCWSG',NULL,1,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:52','2026-05-24 14:48:52',NULL),(13,'coupon-usage-81504841@example.com','$2y$12$OgHf/0aVuaSZB2Gko9dFJOT/V6aXW5WI8DOvyOUN8QJiwTkfjwLLK','','13617677588','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:54','2026-05-24 14:48:54',NULL),(14,'coupon-a-f003706c@example.com','$2y$12$JtQD9mp5..3dn3Yy.f1EF.YskMn/D0d9xEbalUCg80ZHEqK9lOX5y','','13570314452','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:54','2026-05-24 14:48:54',NULL),(15,'coupon-b-f003706c@example.com','$2y$12$jHb79OmoNsTwq35L8Sv6/OVsTQwMYeIpOf7WcGUriV3CeLIyZixu6','','13530759258','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:55','2026-05-24 14:48:55',NULL),(16,'coupon-c-f003706c@example.com','$2y$12$8tXKQLV2G6RUgC.4sgqGb.Q4zSQQt2dehghWDcpBVwrfifCGowSci','','13597380673','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:55','2026-05-24 14:48:55',NULL),(17,'coupon-claim-b4b2e7b6@example.com','$2y$12$9I4HHEz5XZeQyyvofrekL.DXzMr9Jt400D0nNLjnUQALlZwMCLyLu','','13509277777','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:55','2026-05-24 14:48:55',NULL),(18,'coupon-grant-b4b2e7b6@example.com','$2y$12$nhdmVfpYQ5WCSpgykc3GCuKezTESDQsl1qfz9gpphEEJljvfS6qzq','','13563183987','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:55','2026-05-24 14:48:55',NULL),(19,'coupon-limit-a-3817b2f7@example.com','$2y$12$xmeSinuW1U0ye3E3GlacZeZREu1/LwcgdNwaSRBMwJPSq..hwQMBm','','13521234674','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:56','2026-05-24 14:48:56',NULL),(20,'coupon-limit-b-3817b2f7@example.com','$2y$12$rHBVgrgFBOhwAGC9EUkJSOzYK8uELRzM5KTywGIvF/6cEyxdxFvEy','','13567299264','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:56','2026-05-24 14:48:56',NULL),(21,'coupon-limit-c-3817b2f7@example.com','$2y$12$yvAu5qy1SxOilV0EDn1YTe3ONG0.uYBD64FnQwqzmnsy5K8nuAQ42','','13546843394','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:56','2026-05-24 14:48:56',NULL),(22,'coupon-code-73e16780@example.com','$2y$12$ZJ/3mGlvAsjXCmskFkCrZuEcHC7beKFFtvikRU4IvzmDRznTEf.bS','','13539921964','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:57','2026-05-24 14:48:57',NULL),(23,'ledger-client-50725536@example.com','$2y$12$MxkAi0tDmAdFDS90yaAkpusxXpTYXyEOSZkbbQdEuTVXOGWjp862O','','13746726757','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,128.80,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:57','2026-05-24 14:48:57',NULL),(24,'ledger-other-50725536@example.com','$2y$12$CoCpWpjK9twsZBFW5aIlfOBVor5Sht2zQinuWyQn714NEX.qGME4O','','13981925997','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,9.90,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:58','2026-05-24 14:48:58',NULL),(25,'ledger-admin-e0f2df22@example.com','$2y$12$VkzUzOJ7RPn7mEMqbznxwelTXNDwuVVwi77bHM6yho.w/bnq3iqyi','','13608259241','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,88.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:58','2026-05-24 14:48:58',NULL),(26,'ledger-detail-e8f5914c@example.com','$2y$12$IFKpB/98YTsRwE2PMG.VT.BdbzPWx/nPq5lu2ilbFUajl43YBg1xC','','13746532711','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,76.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:59','2026-05-24 14:48:59',NULL),(27,'dashboard-service-instance-2a8943a9@example.com','$2y$12$scI0qXFgLyBqd/QzgKm65OZ72FH6EHquGl17G4oneHU7J./8kea/y','Dashboard IDC','13179042748','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:48:59','2026-05-24 14:48:59',NULL),(28,'product-owner-service-instance-2c53217e@example.com','$2y$12$v/5eL4yF6KJcldVvLRsnjeE1QK2SDucoRZrsHjEnoPC2Mp1XG4Jqy','Owner IDC','13755423595','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:00','2026-05-24 14:49:00',NULL),(29,'order-balance-70749cfc@example.com','$2y$12$wmf6yFFsiIvhRu6VYXDoH.J/VA10hn7ajRITJX1ODCHKnxyRxKJA.','Order Balance','13553872558','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,55.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:00','2026-05-24 14:49:00',NULL),(30,'mix-pay-eb205f7f@example.com','$2y$12$YrKMlrpsUCPyJogzvdoiVebaA4NLTtT.K648iPq.Q6/upubL1QYim','Mix Pay','13718504047','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,10.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01',NULL),(31,'order-quantity-1b4880ef@example.com','$2y$12$iq8Jj9ePIgYgGrv4kOfpdOJ2Gz1KCeWAYFw6jCnKIsLGj3Ill66ga','','13914657036','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01',NULL),(32,'invoice-spec-c4690f73@example.com','$2y$12$nsSRKo73JGI1bb8OhimbteDqJ2.vnd8T//G.Tixsc3fc4DzbOWl.C','','13697674888','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:01','2026-05-24 14:49:01',NULL),(33,'order-paid-flow-c9ce47b5@example.com','$2y$12$FWok4wl/2SOVXRc16e3vYuzOrf.zdRUMzo48U/k0vMN6vLLW8ebgq','','13814707646','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:02','2026-05-24 14:49:02',NULL),(34,'order-renew-sync-92fb1e86@example.com','$2y$12$XbNI2.rN92eoDYf2YRJKSOXu/Ban8552/RtOpCKqBXj1gxccps9UC','','13760215494','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:02','2026-05-24 14:49:02',NULL),(35,'order-zero-9e8cbb18@example.com','$2y$12$YBgqaT/DcUgkHXAOBuoT1OCJKylZU6/MFXa6dJDtqx40yuMn3Sf5.','Order Zero','13669191398','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,100.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:02','2026-05-24 14:49:02',NULL),(36,'purchase-verify-535402ff@example.com','$2y$12$gETYounD0odvx0lc9abVROHY9L.YZS8BoIf2NP1at/mbEkSKYfjuK','','13916320837','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:03','2026-05-24 14:49:03',NULL),(37,'purchase-phone-895c2468@example.com','$2y$12$lJ/iaHOLBdxexrF.Rtn3eey8rRclm3xCvZ6W1R1kr26YtZwrLu2Be','',NULL,'','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,1,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:03','2026-05-24 14:49:03',NULL),(38,'provision-stock-9a3940e5@example.com','$2y$12$AwjCGQASyND6XdypBaWNMOZfKyB8QKgpiivVOp1kjwmaoL2kDY8mC','','13332442624','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:06','2026-05-24 14:49:06',NULL),(39,'provision-pending-fdee4b63@example.com','$2y$12$q97yDq9brIpQ9fZPBnBQOetbfzGxtfAqD5BinvGWdzbA6X3HDe2WS','','13569508729','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:06','2026-05-24 14:49:06',NULL),(40,'provision-hostname-6a312741@example.com','$2y$12$ZOqNS6/DhIqlnKD9xWVBC.ol.RijIxhwUnAqwWPFSC.VkITX4lNyO','Provision Hostname','13746150638','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:06','2026-05-24 14:49:06',NULL),(41,'provision-hostname-a8e1a983@example.com','$2y$12$KKsU5TNQb.zfS.RMbD8BV.GQz3Fqj9ccj3fWf3ndU9pyJQUs0Yaoy','Provision Hostname','13380098220','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:07','2026-05-24 14:49:07',NULL),(42,'provision-hostname-7d3897f4@example.com','$2y$12$LqSnS4AEhphHnXGxaSySO.Qoa7RCpFk2AM0.cBbt9C92RGCPdo8Iu','Provision Hostname','13923735047','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:07','2026-05-24 14:49:07',NULL),(43,'provision-hostname-f4300d18@example.com','$2y$12$LVi0UZ49YFos0u7Vsb28Ce.Zkplr8Qdn.ZyvtUDkZP2UNc3RM..d2','Provision Hostname','13504071844','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:07','2026-05-24 14:49:07',NULL),(44,'provision-hostname-ee430907@example.com','$2y$12$LgtD760KpP.uO19qZjg86eNc/IKTsHbhZsVgnGo6s2eGJUlA/OQw2','Provision Hostname','13309946099','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:08','2026-05-24 14:49:08',NULL),(45,'provision-hostname-cc4320e4@example.com','$2y$12$3zvQLwbccbmp2DI8YmhPk.7Ay1uRQQpculHMQbHtPmCSl9vYxLUWC','Provision Hostname','13541131795','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:08','2026-05-24 14:49:08',NULL),(46,'provision-hostname-22e124d9@example.com','$2y$12$w06dn1vnEE4q5PUSUGOjIuMjWazHsDSeTrGhxm/RJuga3aYJbpSrC','Provision Hostname','13047047338','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:08','2026-05-24 14:49:08',NULL),(47,'finance-226b308c@example.com','$2y$12$HSaPbIe4S4kdZR5yQ9qoxeg7EZBlB3i3YfeccbxV9XAjMMO4gkzj2','','13134906498','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:09','2026-05-24 14:49:09',NULL),(48,'top-referrer-76caad71@example.com','$2y$12$1eprCVx95w5XcsdIcYMpZO/JOH9lzsWoQQflEKSm0Rro4aBCdHsSi','Top Referrer','13717353109','','','','',NULL,1,2,999999.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:09','2026-05-24 14:49:09',NULL),(49,'valid-referrer-4fed5be8@example.com','$2y$12$lXIrDj0hLnXSyJ1JVi8Ys.tzyg6a6XdK97b4UtQWjP.y.gA73CP9W','','13097519251','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:09','2026-05-24 14:49:09',NULL),(50,'valid-referred-4fed5be8@example.com','$2y$12$gklR.9IGEd53wyQPEz28.ewZZbFZGxHhp5TSY7DD5q2oGWEjwgnp2','','13928171241','','','','',NULL,49,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:10','2026-05-24 14:49:10',NULL),(51,'invalid-referred-4fed5be8@example.com','$2y$12$hLbRRgZBLFLNOS7oxFoNyegWch2KoK6EbkwltQLXwd8QRlz.vPeFS','','13242150044','','','','',NULL,999999,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:10','2026-05-24 14:49:10',NULL),(52,'verified-dfbe70b4@example.com','$2y$12$akMcYdmXHgG7ljBqeHYak.nppfn0kx4iOHeE2hE5CvUJo4tCs6lMy','','13099214088','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'Verified User','110101199001011234',2,'approved','cert-dfbe70b4','2026-05-24 14:49:10',NULL,NULL,NULL,'2026-05-24 14:49:10','2026-05-24 14:49:10',NULL),(53,'pending-dfbe70b4@example.com','$2y$12$KOyZW7m7YmHfUkh5wI5hRevMR.FuphQXA9ofRfYzDvzH4gSsnM8va','','13810617053','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'Pending User','110101199202023456',4,'pending',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:11','2026-05-24 14:49:11',NULL),(54,'finance-controller-01c4f396@example.com','$2y$12$eqd5TwseYYOgkCpoScXF5.ujHbyAfQpUacBbKaIiKjvSu.S2uchKq','','13440846684','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:11','2026-05-24 14:49:11',NULL),(55,'verification-controller-41d33237@example.com','$2y$12$3eLPK9VnszvHp9x8OqEgSuMfgxWC3eXuPgJTSB4pMtCHgFXgit8s.','','13946745512','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'Verification User','110101199305056789',2,'approved','cert-controller','2026-05-24 14:49:12',NULL,NULL,NULL,'2026-05-24 14:49:12','2026-05-24 14:49:12',NULL),(56,'recharge-balance-64540a2b@example.com','$2y$12$/CwlrVoin1IXODHj/fl0rePiGZf7ppaTPa/nxK68QuGloJpll7k8O','Recharge Balance','13421400692','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,15.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:12','2026-05-24 14:49:12',NULL),(57,'refund-referrer-9f6546ad@example.com','$2y$12$e6KGnJIno6fOincWmEw.Ue9/LPyxV5MZjmVPmrMDZ1QC.ErkuwyBW','Referral User 44438d36','13565926367','','','','','BTRUXY',NULL,1,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,'198.51.100.20',NULL,NULL,'2026-05-24 14:49:13','2026-05-24 14:49:13',NULL),(58,'refund-buyer-9f6546ad@example.com','$2y$12$LvBLiiCGanmI4RcbIDm.C.KgnXjqiEtdDXZQWkf/5hTqZ90dVCERe','Referral User d057220c','13708107917','','','','',NULL,57,NULL,0.00,0.00,0.00,0.00,0.00,'2026-05-24 14:49:13',100.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,'198.51.100.26',NULL,NULL,'2026-05-24 14:49:13','2026-05-24 14:49:13',NULL),(59,'refund-referrer-9702c8a9@example.com','$2y$12$SlzujG57eJGA8nIFDGC39eUYDgd7A0uvobcU9PgXy.qW0RIoVWLPi','Referral User be0b3892','13988176410','','','','','NS5KVT',NULL,1,100.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,'198.51.100.52',NULL,NULL,'2026-05-24 14:49:13','2026-05-24 14:49:14',NULL),(60,'refund-buyer-9702c8a9@example.com','$2y$12$VXoJQqxNgrJvU4WzDg4wfusFGvPOsvKCuaY8ekl00wtQc2wglDRRm','Referral User c744f746','13799827367','','','','',NULL,59,NULL,0.00,0.00,0.00,0.00,0.00,'2026-05-24 14:49:14',0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,'198.51.100.39',NULL,NULL,'2026-05-24 14:49:14','2026-05-24 14:49:14',NULL),(61,'referrer-48cd9a35@example.com','$2y$12$iHogtySPpwFtMznsnRhLsuqRMO1QIwPsZARw3IvOS/rERU9GqDUQy','Referral User c4bc7d18','13591293493','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:14','2026-05-24 14:49:14',NULL),(62,'referred-48cd9a35@example.com','$2y$12$vs0GdwdjfvEHFBVtS7TkNOkQowlGzRZiQyJWhZ4oBMYulQCqMyPZm','Referral User 29e37080','13139280560','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:14','2026-05-24 14:49:14',NULL),(63,'referral-user-38d4b68c@example.com','$2y$12$V8PyCx6vOUIhtBBT0k6IceAzA87bNFqC3DsKlrts.0DGoQBmsFzjO','Referral User d45bc220','13617049611','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:15','2026-05-24 14:49:15',NULL),(64,'idle-client-b7ff1867@example.com','$2y$12$a8KkiRIjOsK1HYubh5u.HOD62G1iww1bgTRIYkPPqYS5SRDUBJIp.','Idle Client','13680998246','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:15','2026-05-24 14:49:15',NULL),(65,'renew-coupon-6a4f7903@example.com','$2y$12$QQqGUs45y5346rk5Y3FU4ua7BwbIB..YCVCSOS9YIypt7qKxbrGni','','13782417854','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:41','2026-05-24 14:49:41',NULL),(66,'renew-idempotency-2a728795@example.com','$2y$12$Py1Rqi6VBUvYHlVBzk1OCenID8XgejOo9E.SRrw/.YZ8qoYF9f9HS','Renew Idempotency','13265606749','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:41','2026-05-24 14:49:41',NULL),(67,'renew-bind-2501c2c2@example.com','$2y$12$DZtSsnDQGRF0RUlMErOnU.vVJG9g9lnsCyQ9M2Us1jxTrTZUJd8gG','Renew Bind','13139915411','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:42','2026-05-24 14:49:42',NULL),(68,'renew-real-order-782cd7d8@example.com','$2y$12$rEYso/tDOFa/1aFUA5/XpubtQ3BqJyIu56MfytaXNYv707GYT6oU.','Renew Real Order','13862708946','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:42','2026-05-24 14:49:42',NULL),(69,'renew-order-delegate-50e8ceef@example.com','$2y$12$0hPxmGa9BFB7THYOrm6xROqdM.FXFlUOfLzlMseF3vszYBOmXhkJ6','Renew Order Delegate','13202239265','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:43','2026-05-24 14:49:43',NULL),(70,'renew-order-no-invoice-7c89cc77@example.com','$2y$12$L98hlKJhXcHFezr3S4pkmOAa5ID/VGWh4jPwU4SuY2N9O4XJjDraC','Renew Order No Invoice','13681411326','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:44','2026-05-24 14:49:44',NULL),(73,'sms-log-user-6ae80083@example.com','$2y$12$/diys6hEm6OT7vrHDAP2o.qnJ.bI.2rYNolPp5Gw.iyVEam8mE7gS','','13881227630','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:46','2026-05-24 14:49:46',NULL),(74,'ticket-create-87ae1762@example.com','$2y$12$j6zxBYPKvcE1JS3b/oUA5.c8UgeOckun..0dzFMNV0DzEeJwmCzW.','Ticket Regression','13499483165','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:47','2026-05-24 14:49:47',NULL),(75,'ticket-reply-c7e9a407@example.com','$2y$12$8/ux8g0q6AYvuwidwbeR8un6L3wqFFbjrNZNfMdFSYGh5Rr9k6cKa','Ticket Regression','13647181477','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:47','2026-05-24 14:49:47',NULL),(76,'ticket-ongoing-8f08e9c4@example.com','$2y$12$5guyoYwbeWItEUjtd0q3VOAi5wRmtm3KtFeAsRPfJmJBJzk/dum2O','Ticket Regression','13965115146','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL),(77,'ticket-detail-4a1edf8f@example.com','$2y$12$XObFv6o5SFGPNE77hCQr5erdwqC1CJachfwnh74kIO804wMXPhjWm','Ticket Regression','13598887908','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL),(78,'ticket-detail-endpoint-2d0c731d@example.com','$2y$12$3LFTR6GAAvS6DyZGIxYkcOxEqlDYtrB9Q1iFjFGSYHDIHBX3UR1Du','Ticket Regression','13746818520','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:48','2026-05-24 14:49:48',NULL),(79,'ticket-notify-9b4d5174@example.com','$2y$12$oD0fvegf7LdRxBkvDiIYaOliYmEuh/5MxkfUkL.qB5BSXOx3dDaxW','Ticket Regression','13243594233','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:49','2026-05-24 14:49:49',NULL),(80,'user-detail-invoice-only-01142181@example.com','$2y$12$p7O0C0yLJPZSYtX1yYokrOKN9sYH.eQOAQhVP9taGe2wKg2/Pao4.','Invoice Only Stats','13792556096','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'','',0,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:51','2026-05-24 14:49:51',NULL),(81,'user-read-3a296c8a@example.com','$2y$12$15i0Y2/.zCYAPnONyYK9yeB.4zxJya7OyM5w2/XQBHg2FqtoTzciW','娴嬭瘯鏄电О','13167545704','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'寮犱笁','',2,'',NULL,NULL,NULL,NULL,NULL,'2026-05-24 14:49:51','2026-05-24 14:49:51',NULL),(82,'verification-lookup-ef8df79f@example.com','$2y$12$ao4Tn6GdJvj.7LwRjIoiCewENovI3Dsmz51Vc/bIyNjwuYrZKtf4q','Verification Lookup User','13338796048','','','','',NULL,NULL,NULL,0.00,0.00,0.00,0.00,0.00,NULL,0.00,0.00,1,1,1,1,1,1,1,0,0,'鏉庡洓','320505199001010012',4,'绛夊緟璁よ瘉','CERT-EF8DF79F',NULL,NULL,NULL,NULL,'2026-05-24 14:49:51','2026-05-24 14:49:51',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `verification_histories`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_histories`
--

LOCK TABLES `verification_histories` WRITE;
/*!40000 ALTER TABLE `verification_histories` DISABLE KEYS */;
INSERT INTO `verification_histories` VALUES (1,52,'Verified User','eyJpdiI6InVwQmhuNTErODVqb1dIeDlORHhZRVE9PSIsInZhbHVlIjoiTXlXenFXVmdvVnJjQS9CSGgyUHFJOEwrMFV0bzNmcWQ5QWp0YW1jdlpJRT0iLCJtYWMiOiIxYjkyZDQ0OWVhM2MwNGY2ZTUyYjYyYWM4OTY0MWNkMTJkZWE0YzYwMjViZmY0YzFlZWUzYjIxYzljNWJiOTk5IiwidGFnIjoiIn0=',2,'approved','cert-dfbe70b4','FACE','personal','2026-05-24 14:48:11','2026-05-24 14:49:11','2026-05-24 14:49:11','2026-05-24 14:49:11');
/*!40000 ALTER TABLE `verification_histories` ENABLE KEYS */;
UNLOCK TABLES;
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-24 22:58:23
