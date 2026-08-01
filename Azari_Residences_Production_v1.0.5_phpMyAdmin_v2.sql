-- THE AZARI RESIDENCES — v1.0.5 production database
-- Upgraded from the verified v1.0.4 production baseline on 2026-08-02.
-- Schema + one administrator + permission/configuration baseline only.
-- No customers, properties, bookings, payments, tickets, reviews, requests, logs, jobs, sessions, or demo inventory.
-- Admin login: admin@azariadmin.com / 12345678
-- Change this password immediately after first login.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `amenities`;
CREATE TABLE `amenities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `icon` VARCHAR(255) NOT NULL DEFAULT 'check_circle',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `description` TEXT NULL,
  `sort_order` INT NOT NULL DEFAULT '0',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `amenities_is_active_index` (`is_active`),
  UNIQUE KEY `amenities_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `amenity_property`;
CREATE TABLE `amenity_property` (
  `property_id` BIGINT UNSIGNED NOT NULL,
  `amenity_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`property_id`, `amenity_id`),
  CONSTRAINT `fk_amenity_property_amenity_id_amenities` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_amenity_property_property_id_properties` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(255) NOT NULL,
  `subject_type` VARCHAR(255) NULL,
  `subject_id` BIGINT UNSIGNED NULL,
  `request_id` VARCHAR(255) NULL,
  `ip_address` VARCHAR(255) NULL,
  `user_agent` TEXT NULL,
  `old_values` TEXT NULL,
  `new_values` TEXT NULL,
  `metadata` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_request_id_index` (`request_id`),
  KEY `audit_logs_subject_id_index` (`subject_id`),
  KEY `audit_logs_subject_type_index` (`subject_type`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_subject_type_subject_id_index` (`subject_type`, `subject_id`),
  CONSTRAINT `fk_audit_logs_actor_id_users` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `backup_runs`;
CREATE TABLE `backup_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `driver` VARCHAR(255) NOT NULL,
  `path` VARCHAR(255) NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'started',
  `size_bytes` INT NULL,
  `checksum` VARCHAR(255) NULL,
  `started_at` DATETIME NOT NULL,
  `finished_at` DATETIME NULL,
  `verified_at` DATETIME NULL,
  `safe_error` TEXT NULL,
  `metadata` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `backup_runs_status_created_at_index` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `blocked_dates`;
CREATE TABLE `blocked_dates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `property_id` BIGINT UNSIGNED NOT NULL,
  `starts_on` DATE NOT NULL,
  `ends_on` DATE NOT NULL,
  `reason` VARCHAR(255) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `blocked_dates_ends_on_index` (`ends_on`),
  KEY `blocked_dates_starts_on_index` (`starts_on`),
  KEY `blocked_dates_property_id_index` (`property_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `booking_add_on_booking`;
CREATE TABLE `booking_add_on_booking` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `booking_add_on_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT '1',
  `unit_price` DECIMAL(15,2) NOT NULL,
  `line_total` DECIMAL(15,2) NOT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_add_on_booking_booking_id_booking_add_on_id_unique` (`booking_id`, `booking_add_on_id`),
  CONSTRAINT `fk_booking_add_on_booking_booking_add_on_id_booking_add_ons` FOREIGN KEY (`booking_add_on_id`) REFERENCES `booking_add_ons` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_booking_add_on_booking_booking_id_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `booking_add_ons`;
CREATE TABLE `booking_add_ons` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `pricing_type` VARCHAR(255) NOT NULL DEFAULT 'flat',
  `price` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `sort_order` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `booking_add_ons_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `booking_guests`;
CREATE TABLE `booking_guests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `position` INT NOT NULL,
  `first_name` VARCHAR(255) NOT NULL,
  `last_name` VARCHAR(255) NOT NULL,
  `is_lead` TINYINT(1) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `booking_guests_is_lead_index` (`is_lead`),
  KEY `booking_guests_type_index` (`type`),
  UNIQUE KEY `booking_guests_booking_id_type_position_unique` (`booking_id`, `type`, `position`),
  CONSTRAINT `fk_booking_guests_booking_id_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `booking_holds`;
CREATE TABLE `booking_holds` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `property_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `token` VARCHAR(255) NOT NULL,
  `check_in` DATE NOT NULL,
  `check_out` DATE NOT NULL,
  `adults` INT NOT NULL DEFAULT '1',
  `children` INT NOT NULL DEFAULT '0',
  `rooms` INT NOT NULL DEFAULT '1',
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `booking_holds_expires_at_index` (`expires_at`),
  KEY `booking_holds_check_out_index` (`check_out`),
  KEY `booking_holds_check_in_index` (`check_in`),
  UNIQUE KEY `booking_holds_token_unique` (`token`),
  KEY `booking_holds_property_id_index` (`property_id`),
  CONSTRAINT `fk_booking_holds_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `booking_identity_links`;
CREATE TABLE `booking_identity_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `booking_guest_id` BIGINT UNSIGNED NULL,
  `user_identity_document_id` BIGINT UNSIGNED NULL,
  `guest_identity_document_id` BIGINT UNSIGNED NULL,
  `is_booking_owner` TINYINT(1) NOT NULL DEFAULT '0',
  `linked_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `booking_identity_links_is_booking_owner_index` (`is_booking_owner`),
  UNIQUE KEY `booking_guest_identity_unique` (`booking_id`, `booking_guest_id`),
  CONSTRAINT `fk_booking_identity_links_linked_by_users` FOREIGN KEY (`linked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_booking_identity_links_guest_identity_document_id_guest_i` FOREIGN KEY (`guest_identity_document_id`) REFERENCES `guest_identity_documents` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_booking_identity_links_user_identity_document_id_user_ide` FOREIGN KEY (`user_identity_document_id`) REFERENCES `user_identity_documents` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_booking_identity_links_booking_guest_id_booking_guests` FOREIGN KEY (`booking_guest_id`) REFERENCES `booking_guests` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_booking_identity_links_booking_id_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `booking_status_histories`;
CREATE TABLE `booking_status_histories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `changed_by` BIGINT UNSIGNED NULL,
  `from_status` VARCHAR(255) NULL,
  `to_status` VARCHAR(255) NOT NULL,
  `note` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `metadata` TEXT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_booking_status_histories_changed_by_users` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_booking_status_histories_booking_id_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `voucher_id` BIGINT UNSIGNED NULL,
  `voucher_code` VARCHAR(64) NULL,
  `discount_total` DECIMAL(14,2) NOT NULL DEFAULT '0.00',
  `voucher_snapshot` JSON NULL,
  `property_id` BIGINT UNSIGNED NOT NULL,
  `guest_name` VARCHAR(255) NOT NULL,
  `guest_email` VARCHAR(255) NOT NULL,
  `guest_phone` VARCHAR(255) NULL,
  `check_in` DATE NOT NULL,
  `check_out` DATE NOT NULL,
  `adults` INT NOT NULL DEFAULT '1',
  `children` INT NOT NULL DEFAULT '0',
  `rooms` INT NOT NULL DEFAULT '1',
  `status` VARCHAR(255) NOT NULL DEFAULT 'pending',
  `verification_status` VARCHAR(255) NOT NULL DEFAULT 'unverified',
  `currency` VARCHAR(255) NOT NULL DEFAULT 'USD',
  `subtotal` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `tax_total` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `total` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `guest_notes` TEXT NULL,
  `admin_notes` TEXT NULL,
  `approved_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `hold_token` VARCHAR(255) NULL,
  `nightly_rate` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `nights` INT NOT NULL DEFAULT '1',
  `fee_total` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `add_on_total` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `tax_rate` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `pricing_snapshot` TEXT NULL,
  `room_assignment_locked_at` DATETIME NULL,
  `payment_transfer_locked_at` DATETIME NULL,
  `modified_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `guest_first_name` VARCHAR(255) NULL,
  `guest_last_name` VARCHAR(255) NULL,
  `nationality` VARCHAR(255) NULL,
  `address` VARCHAR(255) NULL,
  `city` VARCHAR(255) NULL,
  `country` VARCHAR(255) NULL,
  `arrival_time` TIME NULL,
  `paid_at` DATETIME NULL,
  `receipt_number` VARCHAR(255) NULL,
  `payment_reference` VARCHAR(255) NULL,
  `cancellation_reason` TEXT NULL,
  `checked_in_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `cancelled_by` BIGINT UNSIGNED NULL,
  `cancellation_internal_note` TEXT NULL,
  `cancellation_payment_note` TEXT NULL,
  `external_refund_reference` VARCHAR(255) NULL,
  `checked_out_at` DATETIME NULL,
  `no_show_at` DATETIME NULL,
  `check_in_reversed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `bookings_status_index` (`status`),
  UNIQUE KEY `bookings_reference_unique` (`reference`),
  UNIQUE KEY `bookings_receipt_number_unique` (`receipt_number`),
  KEY `bookings_property_id_index` (`property_id`),
  KEY `bookings_voucher_id_index` (`voucher_id`),
  UNIQUE KEY `bookings_payment_reference_unique` (`payment_reference`),
  KEY `bookings_paid_at_index` (`paid_at`),
  KEY `bookings_hold_token_index` (`hold_token`),
  KEY `bookings_expires_at_index` (`expires_at`),
  KEY `bookings_check_out_index` (`check_out`),
  KEY `bookings_check_in_index` (`check_in`),
  CONSTRAINT `fk_bookings_cancelled_by_users` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_bookings_voucher_id_vouchers` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_bookings_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `buildings`;
CREATE TABLE `buildings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `location_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `floors` INT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `sort_order` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `buildings_is_active_index` (`is_active`),
  UNIQUE KEY `buildings_code_unique` (`code`),
  CONSTRAINT `fk_buildings_location_id_locations` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` LONGTEXT NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `communication_logs`;
CREATE TABLE `communication_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel` VARCHAR(255) NOT NULL,
  `template` VARCHAR(255) NOT NULL,
  `booking_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `service_request_id` BIGINT UNSIGNED NULL,
  `recipient` VARCHAR(512) NOT NULL,
  `masked_recipient` VARCHAR(512) NULL,
  `provider` VARCHAR(255) NULL,
  `provider_reference` VARCHAR(512) NULL,
  `provider_status` VARCHAR(64) NULL,
  `status_updated_at` DATETIME NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'queued',
  `queued_at` DATETIME NULL,
  `sent_at` DATETIME NULL,
  `delivered_at` DATETIME NULL,
  `failed_at` DATETIME NULL,
  `safe_error` TEXT NULL,
  `provider_error_code` VARCHAR(64) NULL,
  `retry_count` INT NOT NULL DEFAULT '0',
  `meta` LONGTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `communication_logs_channel_status_index` (`channel`, `status`),
  CONSTRAINT `fk_communication_logs_service_request_id_service_requests` FOREIGN KEY (`service_request_id`) REFERENCES `service_requests` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_communication_logs_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_communication_logs_booking_id_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `content_blocks`;
CREATE TABLE `content_blocks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page` VARCHAR(255) NOT NULL DEFAULT 'home',
  `key` VARCHAR(255) NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `value` LONGTEXT NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'text',
  `sort_order` INT NOT NULL DEFAULT '0',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_blocks_key_unique` (`key`),
  KEY `content_blocks_page_index` (`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` VARCHAR(255) NOT NULL,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`, `queue`, `failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `guest_identity_documents`;
CREATE TABLE `guest_identity_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_guest_id` BIGINT UNSIGNED NOT NULL,
  `document_type` VARCHAR(255) NOT NULL,
  `disk` VARCHAR(255) NOT NULL DEFAULT 'local',
  `path` TEXT NOT NULL,
  `original_name` TEXT NOT NULL,
  `mime_type` VARCHAR(255) NOT NULL,
  `size_bytes` INT NOT NULL,
  `sha256` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `review_status` VARCHAR(255) NOT NULL DEFAULT 'pending',
  `reviewed_by` BIGINT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `review_note` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `guest_identity_documents_review_status_index` (`review_status`),
  KEY `guest_identity_documents_sha256_index` (`sha256`),
  CONSTRAINT `fk_guest_identity_documents_reviewed_by_users` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_guest_identity_documents_booking_guest_id_booking_guests` FOREIGN KEY (`booking_guest_id`) REFERENCES `booking_guests` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `homepage_sections`;
CREATE TABLE `homepage_sections` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'content',
  `content` TEXT NULL,
  `background_media` VARCHAR(255) NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'published',
  `sort_order` INT NOT NULL DEFAULT '0',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `homepage_sections_is_active_index` (`is_active`),
  KEY `homepage_sections_status_index` (`status`),
  UNIQUE KEY `homepage_sections_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `identity_audit_histories`;
CREATE TABLE `identity_audit_histories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` BIGINT UNSIGNED NULL,
  `document_type` VARCHAR(255) NOT NULL,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(255) NOT NULL,
  `booking_id` BIGINT UNSIGNED NULL,
  `metadata` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `identity_audit_histories_action_index` (`action`),
  KEY `identity_audit_histories_document_type_document_id_index` (`document_type`, `document_id`),
  CONSTRAINT `fk_identity_audit_histories_booking_id_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_identity_audit_histories_actor_id_users` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `identity_types`;
CREATE TABLE `identity_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `is_system` TINYINT(1) NOT NULL DEFAULT '0',
  `sort_order` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `identity_types_is_active_index` (`is_active`),
  UNIQUE KEY `identity_types_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` INT NOT NULL,
  `pending_jobs` INT NOT NULL,
  `failed_jobs` INT NOT NULL,
  `failed_job_ids` TEXT NOT NULL,
  `options` LONGTEXT NULL,
  `cancelled_at` INT NULL,
  `created_at` INT NOT NULL,
  `finished_at` INT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` INT NOT NULL,
  `reserved_at` INT NULL,
  `available_at` INT NOT NULL,
  `created_at` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `country` VARCHAR(255) NOT NULL,
  `city` VARCHAR(255) NOT NULL,
  `address` TEXT NULL,
  `timezone` VARCHAR(255) NOT NULL DEFAULT 'Africa/Lagos',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `sort_order` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `locations_is_active_index` (`is_active`),
  UNIQUE KEY `locations_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `maintenance_periods`;
CREATE TABLE `maintenance_periods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `property_id` BIGINT UNSIGNED NOT NULL,
  `starts_on` DATE NOT NULL,
  `ends_on` DATE NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `notes` TEXT NULL,
  `blocks_booking` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `maintenance_periods_blocks_booking_index` (`blocks_booking`),
  KEY `maintenance_periods_ends_on_index` (`ends_on`),
  KEY `maintenance_periods_starts_on_index` (`starts_on`),
  KEY `maintenance_periods_property_id_index` (`property_id`),
  CONSTRAINT `fk_maintenance_periods_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `media_assets`;
CREATE TABLE `media_assets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `disk` VARCHAR(255) NOT NULL DEFAULT 'public',
  `path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(255) NOT NULL,
  `size` INT NOT NULL,
  `title` VARCHAR(255) NULL,
  `alt_text` VARCHAR(255) NULL,
  `caption` TEXT NULL,
  `description` TEXT NULL,
  `is_archived` TINYINT(1) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `media_assets_is_archived_index` (`is_archived`),
  UNIQUE KEY `media_assets_path_unique` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `navigation_items`;
CREATE TABLE `navigation_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label` VARCHAR(255) NOT NULL,
  `url` VARCHAR(255) NOT NULL,
  `location` VARCHAR(255) NOT NULL DEFAULT 'header',
  `target` VARCHAR(255) NOT NULL DEFAULT '_self',
  `icon` VARCHAR(255) NULL,
  `parent_id` BIGINT UNSIGNED NULL,
  `sort_order` INT NOT NULL DEFAULT '0',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `navigation_items_is_active_index` (`is_active`),
  KEY `navigation_items_location_index` (`location`),
  CONSTRAINT `fk_navigation_items_parent_id_navigation_items` FOREIGN KEY (`parent_id`) REFERENCES `navigation_items` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` VARCHAR(255) NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `notifiable_type` VARCHAR(255) NOT NULL,
  `notifiable_id` BIGINT UNSIGNED NOT NULL,
  `data` LONGTEXT NOT NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`, `notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `payment_events`;
CREATE TABLE `payment_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` BIGINT UNSIGNED NULL,
  `provider` VARCHAR(255) NOT NULL,
  `event_id` VARCHAR(255) NOT NULL,
  `event_type` VARCHAR(255) NULL,
  `source` VARCHAR(255) NOT NULL,
  `signature_valid` TINYINT(1) NULL,
  `processed` TINYINT(1) NOT NULL DEFAULT '0',
  `received_at` DATETIME NOT NULL,
  `processed_at` DATETIME NULL,
  `safe_payload` TEXT NULL,
  `safe_error` TEXT NULL,
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT '0',
  `next_attempt_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `payment_events_received_at_index` (`received_at`),
  KEY `payment_events_processed_index` (`processed`),
  KEY `payment_events_source_index` (`source`),
  KEY `payment_events_event_type_index` (`event_type`),
  KEY `payment_events_provider_index` (`provider`),
  UNIQUE KEY `payment_events_provider_event_id_unique` (`provider`, `event_id`),
  CONSTRAINT `fk_payment_events_payment_id_payments` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `payment_provider_statuses`;
CREATE TABLE `payment_provider_statuses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` VARCHAR(255) NOT NULL,
  `mode` VARCHAR(255) NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT '0',
  `connection_status` VARCHAR(255) NOT NULL DEFAULT 'not_tested',
  `last_checked_at` DATETIME NULL,
  `last_webhook_at` DATETIME NULL,
  `last_successful_payment_at` DATETIME NULL,
  `safe_message` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `payment_provider_statuses_connection_status_index` (`connection_status`),
  UNIQUE KEY `payment_provider_statuses_provider_unique` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `payment_verification_attempts`;
CREATE TABLE `payment_verification_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(255) NOT NULL,
  `result` VARCHAR(255) NOT NULL,
  `provider_status` VARCHAR(255) NULL,
  `reported_amount` DECIMAL(15,2) NULL,
  `reported_currency` VARCHAR(255) NULL,
  `reported_reference` VARCHAR(255) NULL,
  `safe_error` TEXT NULL,
  `safe_response` TEXT NULL,
  `attempted_at` DATETIME NOT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `payment_verification_attempts_attempted_at_index` (`attempted_at`),
  KEY `payment_verification_attempts_result_index` (`result`),
  KEY `payment_verification_attempts_provider_index` (`provider`),
  CONSTRAINT `fk_payment_verification_attempts_payment_id_payments` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(255) NOT NULL,
  `provider_reference` VARCHAR(255) NULL,
  `provider` VARCHAR(255) NOT NULL,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `guest_email` VARCHAR(255) NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `currency` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'initiated',
  `payment_method` VARCHAR(255) NULL,
  `checkout_url` VARCHAR(255) NULL,
  `initiated_at` DATETIME NULL,
  `paid_at` DATETIME NULL,
  `verified_at` DATETIME NULL,
  `failed_at` DATETIME NULL,
  `abandoned_at` DATETIME NULL,
  `provider_response_summary` TEXT NULL,
  `safe_metadata` TEXT NULL,
  `receipt_number` VARCHAR(255) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `creation_source` VARCHAR(255) NOT NULL DEFAULT 'system',
  `proof_disk` VARCHAR(255) NULL,
  `proof_path` VARCHAR(255) NULL,
  `administrative_note` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_receipt_number_unique` (`receipt_number`),
  KEY `payments_paid_at_index` (`paid_at`),
  KEY `payments_status_index` (`status`),
  KEY `payments_guest_email_index` (`guest_email`),
  KEY `payments_provider_index` (`provider`),
  KEY `payments_provider_reference_index` (`provider_reference`),
  UNIQUE KEY `payments_reference_unique` (`reference`),
  UNIQUE KEY `payments_provider_reference_unique` (`provider`, `provider_reference`),
  KEY `payments_booking_id_status_index` (`booking_id`, `status`),
  CONSTRAINT `fk_payments_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_payments_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_payments_booking_id_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `permission_role`;
CREATE TABLE `permission_role` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`, `role_id`),
  CONSTRAINT `fk_permission_role_role_id_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_permission_role_permission_id_permissions` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `permission_user`;
CREATE TABLE `permission_user` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `granted_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`permission_id`, `user_id`),
  CONSTRAINT `fk_permission_user_granted_by_users` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_permission_user_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_permission_user_permission_id_permissions` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `group` VARCHAR(255) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `permissions_group_index` (`group`),
  UNIQUE KEY `permissions_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `phone_verification_codes`;
CREATE TABLE `phone_verification_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `phone` VARCHAR(255) NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempts` INT NOT NULL DEFAULT '0',
  `used_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `phone_verification_codes_expires_at_index` (`expires_at`),
  CONSTRAINT `fk_phone_verification_codes_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `pricing_rules`;
CREATE TABLE `pricing_rules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `property_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `rule_type` VARCHAR(255) NOT NULL DEFAULT 'seasonal',
  `starts_on` DATE NULL,
  `ends_on` DATE NULL,
  `days_of_week` TEXT NULL,
  `amount` DECIMAL(15,2) NULL,
  `percentage` DECIMAL(15,2) NULL,
  `minimum_stay` INT NULL,
  `maximum_stay` INT NULL,
  `priority` INT NOT NULL DEFAULT '0',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `pricing_rules_is_active_index` (`is_active`),
  CONSTRAINT `fk_pricing_rules_property_id_properties` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `promotions`;
CREATE TABLE `promotions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'promotion',
  `summary` TEXT NULL,
  `body` LONGTEXT NULL,
  `cta_label` VARCHAR(255) NULL,
  `cta_url` TEXT NULL,
  `image_path` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT '0',
  `is_featured` TINYINT(1) NOT NULL DEFAULT '0',
  `show_on_homepage` TINYINT(1) NOT NULL DEFAULT '0',
  `starts_at` DATETIME NULL,
  `ends_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promotions_slug_unique` (`slug`),
  KEY `promotions_show_on_homepage_index` (`show_on_homepage`),
  KEY `promotions_is_active_type_index` (`is_active`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `properties`;
CREATE TABLE `properties` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id` BIGINT UNSIGNED NULL,
  `ownership_type` VARCHAR(32) NOT NULL DEFAULT 'azari',
  `owner_listing_id` BIGINT UNSIGNED NULL,
  `owner_share_percentage` DECIMAL(5,2) NOT NULL DEFAULT '0',
  `managed_for_owner` TINYINT(1) NOT NULL DEFAULT '0',
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `country` VARCHAR(255) NOT NULL DEFAULT 'Nigeria',
  `property_type` VARCHAR(255) NOT NULL,
  `bedrooms` INT NOT NULL DEFAULT '1',
  `bathrooms` INT NOT NULL DEFAULT '1',
  `max_guests` INT NOT NULL DEFAULT '2',
  `nightly_rate` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `currency` VARCHAR(255) NOT NULL DEFAULT 'USD',
  `short_description` TEXT NULL,
  `description` TEXT NULL,
  `cover_image` TEXT NULL,
  `gallery` TEXT NULL,
  `is_featured` TINYINT(1) NOT NULL DEFAULT '0',
  `is_published` TINYINT(1) NOT NULL DEFAULT '0',
  `sort_order` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `location_id` BIGINT UNSIGNED NULL,
  `building_id` BIGINT UNSIGNED NULL,
  `room_type_id` BIGINT UNSIGNED NULL,
  `code` VARCHAR(255) NULL,
  `unit_number` VARCHAR(255) NULL,
  `floor` VARCHAR(255) NULL,
  `adult_capacity` INT NOT NULL DEFAULT '2',
  `child_capacity` INT NOT NULL DEFAULT '0',
  `bed_configuration` VARCHAR(255) NULL,
  `room_size` DECIMAL(15,2) NULL,
  `check_in_time` TIME NULL,
  `check_out_time` TIME NULL,
  `weekend_rate` DECIMAL(15,2) NULL,
  `cleaning_fee` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `security_deposit` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `service_charge` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `tax_rate` DECIMAL(15,2) NOT NULL DEFAULT '0',
  `video_url` TEXT NULL,
  `virtual_tour_url` TEXT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'available',
  `internal_notes` TEXT NULL,
  `minimum_stay` INT NOT NULL DEFAULT '1',
  `maximum_stay` INT NULL,
  `same_day_booking` TINYINT(1) NOT NULL DEFAULT '0',
  `service_fee` DECIMAL(15,2) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `properties_owner_id_index` (`owner_id`),
  KEY `properties_ownership_type_index` (`ownership_type`),
  KEY `properties_owner_listing_id_index` (`owner_listing_id`),
  KEY `properties_status_index` (`status`),
  UNIQUE KEY `properties_code_unique` (`code`),
  UNIQUE KEY `properties_slug_unique` (`slug`),
  KEY `properties_is_published_index` (`is_published`),
  KEY `properties_is_featured_index` (`is_featured`),
  CONSTRAINT `fk_properties_owner_id_users` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_properties_owner_listing_id_property_listings` FOREIGN KEY (`owner_listing_id`) REFERENCES `property_listings` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_properties_room_type_id_room_types` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_properties_building_id_buildings` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_properties_location_id_locations` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `property_images`;
CREATE TABLE `property_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `property_id` BIGINT UNSIGNED NOT NULL,
  `path` TEXT NOT NULL,
  `title` VARCHAR(255) NULL,
  `alt_text` TEXT NULL,
  `caption` TEXT NULL,
  `sort_order` INT NOT NULL DEFAULT '0',
  `is_cover` TINYINT(1) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `property_images_is_cover_index` (`is_cover`),
  CONSTRAINT `fk_property_images_property_id_properties` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `rating` INT NOT NULL,
  `title` VARCHAR(255) NULL,
  `body` LONGTEXT NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'pending',
  `featured` TINYINT(1) NOT NULL DEFAULT '0',
  `admin_reply` TEXT NULL,
  `moderated_by` BIGINT UNSIGNED NULL,
  `moderated_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_booking_id_unique` (`booking_id`),
  KEY `reviews_status_featured_index` (`status`, `featured`),
  CONSTRAINT `fk_reviews_moderated_by_users` FOREIGN KEY (`moderated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_reviews_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_reviews_booking_id_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `role_user`;
CREATE TABLE `role_user` (
  `role_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `user_id`),
  CONSTRAINT `fk_role_user_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_role_user_role_id_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `room_types`;
CREATE TABLE `room_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `icon` VARCHAR(255) NOT NULL DEFAULT 'bed',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `sort_order` INT NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `room_types_is_active_index` (`is_active`),
  UNIQUE KEY `room_types_slug_unique` (`slug`),
  UNIQUE KEY `room_types_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `scheduled_task_runs`;
CREATE TABLE `scheduled_task_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL,
  `started_at` DATETIME NOT NULL,
  `finished_at` DATETIME NULL,
  `duration_ms` INT NULL,
  `summary` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `scheduled_task_runs_task_status_index` (`task`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `seasonal_prices`;
CREATE TABLE `seasonal_prices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `property_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `starts_on` DATE NOT NULL,
  `ends_on` DATE NOT NULL,
  `nightly_rate` DECIMAL(15,2) NOT NULL,
  `minimum_stay` INT NOT NULL DEFAULT '1',
  `maximum_stay` INT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `seasonal_prices_ends_on_index` (`ends_on`),
  KEY `seasonal_prices_starts_on_index` (`starts_on`),
  KEY `seasonal_prices_property_id_index` (`property_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `service_request_events`;
CREATE TABLE `service_request_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_request_id` BIGINT UNSIGNED NOT NULL,
  `actor_id` BIGINT UNSIGNED NULL,
  `from_status` VARCHAR(255) NULL,
  `to_status` VARCHAR(255) NOT NULL,
  `note` TEXT NULL,
  `guest_visible` TINYINT(1) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_service_request_events_actor_id_users` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_service_request_events_service_request_id_service_request` FOREIGN KEY (`service_request_id`) REFERENCES `service_requests` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `service_requests`;
CREATE TABLE `service_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(255) NOT NULL,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `assigned_to` BIGINT UNSIGNED NULL,
  `type` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'submitted',
  `requested_at` DATETIME NULL,
  `details` LONGTEXT NULL,
  `notes` TEXT NULL,
  `internal_notes` TEXT NULL,
  `guest_reply` TEXT NULL,
  `attachment_path` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_requests_reference_unique` (`reference`),
  KEY `service_requests_status_type_index` (`status`, `type`),
  CONSTRAINT `fk_service_requests_assigned_to_users` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_service_requests_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_service_requests_booking_id_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `ip_address` VARCHAR(255) NULL,
  `user_agent` TEXT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_last_activity_index` (`last_activity`),
  KEY `sessions_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE `site_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(255) NOT NULL,
  `value` LONGTEXT NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'text',
  `group` VARCHAR(255) NOT NULL DEFAULT 'general',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `site_settings_group_index` (`group`),
  UNIQUE KEY `site_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `staff_login_histories`;
CREATE TABLE `staff_login_histories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `ip_address` VARCHAR(255) NULL,
  `user_agent` TEXT NULL,
  `result` VARCHAR(255) NOT NULL DEFAULT 'success',
  `logged_in_at` DATETIME NULL,
  `logged_out_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `staff_login_histories_logged_in_at_index` (`logged_in_at`),
  KEY `staff_login_histories_result_index` (`result`),
  CONSTRAINT `fk_staff_login_histories_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `stay_lifecycle_events`;
CREATE TABLE `stay_lifecycle_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `actor_id` BIGINT UNSIGNED NULL,
  `event` VARCHAR(255) NOT NULL,
  `note` TEXT NULL,
  `viewer_timezone` VARCHAR(255) NULL,
  `operational_timezone` VARCHAR(255) NOT NULL DEFAULT 'Africa/Lagos',
  `ip_address` VARCHAR(255) NULL,
  `user_agent` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `stay_lifecycle_events_booking_id_created_at_index` (`booking_id`, `created_at`),
  CONSTRAINT `fk_stay_lifecycle_events_actor_id_users` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_stay_lifecycle_events_booking_id_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `support_ticket_messages`;
CREATE TABLE `support_ticket_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `support_ticket_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `body` LONGTEXT NOT NULL,
  `internal` TINYINT(1) NOT NULL DEFAULT '0',
  `attachment_path` TEXT NULL,
  `attachment_name` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_support_ticket_messages_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_support_ticket_messages_support_ticket_id_support_tickets` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `booking_id` BIGINT UNSIGNED NULL,
  `assigned_to` BIGINT UNSIGNED NULL,
  `category` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'open',
  `priority` VARCHAR(255) NOT NULL DEFAULT 'normal',
  `escalated_at` DATETIME NULL,
  `resolved_at` DATETIME NULL,
  `closed_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `first_responded_at` DATETIME NULL,
  `response_due_at` DATETIME NULL,
  `resolution_note` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_tickets_reference_unique` (`reference`),
  KEY `support_tickets_status_priority_index` (`status`, `priority`),
  CONSTRAINT `fk_support_tickets_assigned_to_users` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_support_tickets_booking_id_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_support_tickets_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(255) NOT NULL,
  `value` LONGTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `theme_revisions`;
CREATE TABLE `theme_revisions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `settings` TEXT NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `created_by` BIGINT UNSIGNED NULL,
  `published_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `theme_revisions_status_index` (`status`),
  CONSTRAINT `fk_theme_revisions_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `user_identity_documents`;
CREATE TABLE `user_identity_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `identity_type_id` BIGINT UNSIGNED NULL,
  `document_type` VARCHAR(255) NOT NULL,
  `disk` VARCHAR(255) NOT NULL DEFAULT 'private',
  `path` TEXT NOT NULL,
  `original_name` TEXT NOT NULL,
  `mime_type` VARCHAR(255) NOT NULL,
  `size_bytes` INT NOT NULL,
  `sha256` VARCHAR(255) NOT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT '1',
  `replaces_id` BIGINT UNSIGNED NULL,
  `replaced_at` DATETIME NULL,
  `review_status` VARCHAR(255) NOT NULL DEFAULT 'pending',
  `reviewed_by` BIGINT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `review_note` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `user_identity_documents_review_status_index` (`review_status`),
  KEY `user_identity_documents_is_current_index` (`is_current`),
  KEY `user_identity_documents_sha256_index` (`sha256`),
  KEY `user_identity_documents_user_id_is_current_index` (`user_id`, `is_current`),
  CONSTRAINT `fk_user_identity_documents_reviewed_by_users` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_user_identity_documents_replaces_id_user_identity_documen` FOREIGN KEY (`replaces_id`) REFERENCES `user_identity_documents` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_user_identity_documents_identity_type_id_identity_types` FOREIGN KEY (`identity_type_id`) REFERENCES `identity_types` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_user_identity_documents_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` DATETIME NULL,
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(255) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `staff_role` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `profile_photo_path` VARCHAR(255) NULL,
  `last_login_at` DATETIME NULL,
  `username` VARCHAR(255) NULL,
  `phone` VARCHAR(255) NULL,
  `avatar_path` VARCHAR(255) NULL,
  `is_admin` TINYINT(1) NOT NULL DEFAULT '0',
  `account_type` VARCHAR(255) NOT NULL DEFAULT 'customer',
  `status` VARCHAR(255) NOT NULL DEFAULT 'active',
  `last_active_at` DATETIME NULL,
  `suspended_at` DATETIME NULL,
  `suspension_reason` TEXT NULL,
  `timezone` VARCHAR(255) NULL,
  `phone_verified_at` DATETIME NULL,
  `emergency_contact_name` VARCHAR(255) NULL,
  `emergency_contact_phone` VARCHAR(255) NULL,
  `email_notifications` TINYINT(1) NOT NULL DEFAULT '1',
  `sms_notifications` TINYINT(1) NOT NULL DEFAULT '0',
  `whatsapp_notifications` TINYINT(1) NOT NULL DEFAULT '0',
  `marketing_consent` TINYINT(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `users_status_index` (`status`),
  KEY `users_account_type_index` (`account_type`),
  KEY `users_is_admin_index` (`is_admin`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_is_active_index` (`is_active`),
  KEY `users_staff_role_index` (`staff_role`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `listing_agreements`;
CREATE TABLE `listing_agreements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `version` VARCHAR(40) NOT NULL,
  `legal_name` VARCHAR(255) NOT NULL,
  `agreement_text` LONGTEXT NOT NULL,
  `signature_hash` VARCHAR(64) NOT NULL,
  `signed_ip` VARCHAR(45) NULL,
  `signed_user_agent` TEXT NULL,
  `signed_at` DATETIME NOT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `listing_agreements_user_id_version_unique` (`user_id`, `version`),
  CONSTRAINT `fk_listing_agreements_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `property_listings`;
CREATE TABLE `property_listings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(32) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `listing_agreement_id` BIGINT UNSIGNED NOT NULL,
  `approved_property_id` BIGINT UNSIGNED NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'draft',
  `property_data` JSON NOT NULL,
  `amenity_ids` JSON NULL,
  `cover_image` VARCHAR(255) NULL,
  `gallery` JSON NULL,
  `proposed_owner_share_percentage` DECIMAL(5,2) NULL,
  `approved_owner_share_percentage` DECIMAL(5,2) NULL,
  `owner_notes` TEXT NULL,
  `admin_notes` TEXT NULL,
  `decline_reason` TEXT NULL,
  `reviewed_by` BIGINT UNSIGNED NULL,
  `submitted_at` DATETIME NULL,
  `reviewed_at` DATETIME NULL,
  `approved_at` DATETIME NULL,
  `declined_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `property_listings_reference_unique` (`reference`),
  KEY `property_listings_status_index` (`status`),
  KEY `property_listings_user_id_status_index` (`user_id`, `status`),
  KEY `property_listings_listing_agreement_id_index` (`listing_agreement_id`),
  KEY `property_listings_approved_property_id_index` (`approved_property_id`),
  KEY `property_listings_reviewed_by_index` (`reviewed_by`),
  CONSTRAINT `fk_property_listings_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_property_listings_listing_agreement_id_listing_agreements` FOREIGN KEY (`listing_agreement_id`) REFERENCES `listing_agreements` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_property_listings_approved_property_id_properties` FOREIGN KEY (`approved_property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_property_listings_reviewed_by_users` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `owner_payout_profiles`;
CREATE TABLE `owner_payout_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `preferred_gateway` VARCHAR(20) NOT NULL DEFAULT 'paypal',
  `paypal_recipient` VARCHAR(255) NULL,
  `paypal_recipient_type` VARCHAR(20) NOT NULL DEFAULT 'EMAIL',
  `stripe_connected_account_id` VARCHAR(255) NULL,
  `is_verified` TINYINT(1) NOT NULL DEFAULT '0',
  `verified_by` BIGINT UNSIGNED NULL,
  `verified_at` DATETIME NULL,
  `verification_note` TEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_payout_profiles_user_id_unique` (`user_id`),
  KEY `owner_payout_profiles_verified_by_index` (`verified_by`),
  CONSTRAINT `fk_owner_payout_profiles_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_owner_payout_profiles_verified_by_users` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `vouchers`;
CREATE TABLE `vouchers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `discount_type` ENUM('percentage','fixed') NOT NULL,
  `discount_value` DECIMAL(14,2) NOT NULL,
  `maximum_discount` DECIMAL(14,2) NULL,
  `minimum_booking_value` DECIMAL(14,2) NOT NULL DEFAULT '0.00',
  `starts_at` TIMESTAMP NULL,
  `expires_at` TIMESTAMP NULL,
  `total_usage_limit` INT UNSIGNED NULL,
  `per_customer_limit` INT UNSIGNED NOT NULL DEFAULT '1',
  `is_active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vouchers_code_unique` (`code`),
  KEY `vouchers_created_by_foreign` (`created_by`),
  CONSTRAINT `vouchers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `voucher_property`;
CREATE TABLE `voucher_property` (
  `voucher_id` BIGINT UNSIGNED NOT NULL,
  `property_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`voucher_id`, `property_id`),
  KEY `voucher_property_property_id_foreign` (`property_id`),
  CONSTRAINT `voucher_property_voucher_id_foreign` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `voucher_property_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `voucher_redemptions`;
CREATE TABLE `voucher_redemptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `voucher_id` BIGINT UNSIGNED NOT NULL,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `guest_email` VARCHAR(190) NULL,
  `discount_amount` DECIMAL(14,2) NOT NULL,
  `redeemed_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_redemptions_booking_id_unique` (`booking_id`),
  KEY `voucher_redemptions_voucher_id_foreign` (`voucher_id`),
  KEY `voucher_redemptions_user_id_foreign` (`user_id`),
  KEY `voucher_redemptions_guest_email_index` (`guest_email`),
  CONSTRAINT `voucher_redemptions_voucher_id_foreign` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `voucher_redemptions_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `voucher_redemptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `withdrawal_requests`;
CREATE TABLE `withdrawal_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(32) NOT NULL,
  `idempotency_key` VARCHAR(80) NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `gateway` VARCHAR(20) NOT NULL,
  `currency` VARCHAR(3) NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'pending',
  `destination_snapshot` JSON NOT NULL,
  `provider_reference` VARCHAR(255) NULL,
  `provider_response` JSON NULL,
  `last_error` TEXT NULL,
  `owner_note` TEXT NULL,
  `admin_note` TEXT NULL,
  `rejection_reason` TEXT NULL,
  `processed_by` BIGINT UNSIGNED NULL,
  `requested_at` DATETIME NOT NULL,
  `processing_started_at` DATETIME NULL,
  `provider_sent_at` DATETIME NULL,
  `reconciliation_required_at` DATETIME NULL,
  `processed_at` DATETIME NULL,
  `failed_at` DATETIME NULL,
  `rejected_at` DATETIME NULL,
  `reconciled_by` BIGINT UNSIGNED NULL,
  `reconciled_at` DATETIME NULL,
  `reconciliation_note` TEXT NULL,
  `retry_count` INT UNSIGNED NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `withdrawal_requests_reference_unique` (`reference`),
  UNIQUE KEY `withdrawal_requests_idempotency_key_unique` (`idempotency_key`),
  KEY `withdrawal_requests_status_index` (`status`),
  KEY `withdrawal_requests_provider_reference_index` (`provider_reference`),
  KEY `withdrawal_requests_user_id_status_index` (`user_id`, `status`),
  KEY `owner_withdrawals_status_requested_idx` (`status`, `requested_at`),
  KEY `withdrawal_requests_processed_by_index` (`processed_by`),
  KEY `withdrawal_requests_reconciled_by_index` (`reconciled_by`),
  CONSTRAINT `fk_withdrawal_requests_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_withdrawal_requests_processed_by_users` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_withdrawal_requests_reconciled_by_users` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `owner_ledger_entries`;
CREATE TABLE `owner_ledger_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `property_id` BIGINT UNSIGNED NULL,
  `booking_id` BIGINT UNSIGNED NULL,
  `payment_id` BIGINT UNSIGNED NULL,
  `withdrawal_request_id` BIGINT UNSIGNED NULL,
  `type` VARCHAR(32) NOT NULL,
  `direction` VARCHAR(8) NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `currency` VARCHAR(3) NOT NULL,
  `gross_amount` DECIMAL(14,2) NULL,
  `owner_share_percentage` DECIMAL(5,2) NULL,
  `reference` VARCHAR(60) NOT NULL,
  `description` TEXT NOT NULL,
  `metadata` JSON NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_ledger_entries_reference_unique` (`reference`),
  UNIQUE KEY `owner_ledger_entries_payment_id_unique` (`payment_id`),
  KEY `owner_ledger_entries_withdrawal_request_id_index` (`withdrawal_request_id`),
  KEY `owner_ledger_entries_user_id_currency_created_at_index` (`user_id`, `currency`, `created_at`),
  KEY `owner_ledger_entries_property_id_index` (`property_id`),
  KEY `owner_ledger_entries_booking_id_index` (`booking_id`),
  CONSTRAINT `fk_owner_ledger_entries_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_owner_ledger_entries_property_id_properties` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_owner_ledger_entries_booking_id_bookings` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_owner_ledger_entries_payment_id_payments` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Required baseline records

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_07_23_090648_000001_add_azari_staff_fields_to_users_table', 2),
(5, '2026_07_23_090648_000002_create_site_settings_table', 2),
(6, '2026_07_23_090648_000003_create_content_blocks_table', 2),
(7, '2026_07_23_090648_000004_create_properties_and_amenities_tables', 2),
(8, '2026_07_23_120817_000300_create_azari_sprint_03_04_tables', 3),
(9, '2026_07_23_000001_add_admin_profile_fields_to_users_table', 4),
(10, '2026_07_23_000002_create_system_settings_table', 4),
(11, '2026_07_23_220000_create_azari_sprint_05_06_tables', 5),
(12, '2026_07_24_070000_complete_azari_sprints_05_06', 6),
(13, '2026_07_24_120000_complete_public_inventory_links', 7),
(14, '2026_07_24_180000_complete_sprint_6b_booking_guests', 8),
(15, '2026_07_24_230000_finalize_azari_sprints_05_06', 9),
(16, '2026_07_25_000000_complete_azari_sprints_07_08', 10),
(17, '2026_07_25_130000_complete_azari_sprints_09_12', 11),
(18, '2026_07_25_160000_complete_azari_sprints_13_16', 12),
(19, '2026_07_25_170000_harden_azari_sprints_13_16', 13),
(20, '2026_07_26_120000_make_external_fields_database_portable', 14),
(21,'2026_07_27_010000_make_usd_the_platform_currency',15),
(22,'2026_07_27_020000_add_whatsapp_notification_preference',15),
(23,'2026_07_27_180000_add_homepage_popup_to_promotions',16),
(24,'2026_07_27_210000_add_homepage_visibility_to_promotions',17),
(25,'2026_07_27_220000_add_property_owner_marketplace',18),
(26,'2026_07_28_200000_harden_property_owner_marketplace',19),
(27,'2026_07_28_235900_harden_owner_marketplace_production_readiness',20),
(28,'2026_07_28_235950_harden_external_integrations',21),
(29,'2026_08_01_160000_add_vouchers_to_azari_bookings',22);

INSERT INTO `permissions` (`id`, `name`, `slug`, `group`, `created_at`, `updated_at`) VALUES
(1, 'Dashboard — View', 'dashboard.view', 'Dashboard', '2026-07-25 10:09:16', '2026-07-25 10:09:16'),
(2, 'Dashboard — Create', 'dashboard.create', 'Dashboard', '2026-07-25 10:09:16', '2026-07-25 10:09:16'),
(3, 'Dashboard — Edit', 'dashboard.edit', 'Dashboard', '2026-07-25 10:09:16', '2026-07-25 10:09:16'),
(4, 'Dashboard — Delete', 'dashboard.delete', 'Dashboard', '2026-07-25 10:09:16', '2026-07-25 10:09:16'),
(5, 'Dashboard — Export', 'dashboard.export', 'Dashboard', '2026-07-25 10:09:16', '2026-07-25 10:09:16'),
(6, 'Dashboard — Manage', 'dashboard.manage', 'Dashboard', '2026-07-25 10:09:16', '2026-07-25 10:09:16'),
(7, 'Bookings — View', 'bookings.view', 'Bookings', '2026-07-25 10:09:16', '2026-07-25 10:09:16'),
(8, 'Bookings — Create', 'bookings.create', 'Bookings', '2026-07-25 10:09:16', '2026-07-25 10:09:16'),
(9, 'Bookings — Edit', 'bookings.edit', 'Bookings', '2026-07-25 10:09:16', '2026-07-25 10:09:16'),
(10, 'Bookings — Delete', 'bookings.delete', 'Bookings', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(11, 'Bookings — Export', 'bookings.export', 'Bookings', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(12, 'Bookings — Manage', 'bookings.manage', 'Bookings', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(13, 'Payments — View', 'payments.view', 'Payments', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(14, 'Payments — Create', 'payments.create', 'Payments', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(15, 'Payments — Edit', 'payments.edit', 'Payments', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(16, 'Payments — Delete', 'payments.delete', 'Payments', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(17, 'Payments — Export', 'payments.export', 'Payments', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(18, 'Payments — Manage', 'payments.manage', 'Payments', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(19, 'Properties — View', 'properties.view', 'Properties', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(20, 'Properties — Create', 'properties.create', 'Properties', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(21, 'Properties — Edit', 'properties.edit', 'Properties', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(22, 'Properties — Delete', 'properties.delete', 'Properties', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(23, 'Properties — Export', 'properties.export', 'Properties', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(24, 'Properties — Manage', 'properties.manage', 'Properties', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(25, 'Availability — View', 'availability.view', 'Availability', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(26, 'Availability — Create', 'availability.create', 'Availability', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(27, 'Availability — Edit', 'availability.edit', 'Availability', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(28, 'Availability — Delete', 'availability.delete', 'Availability', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(29, 'Availability — Export', 'availability.export', 'Availability', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(30, 'Availability — Manage', 'availability.manage', 'Availability', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(31, 'Guests — View', 'guests.view', 'Guests', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(32, 'Guests — Create', 'guests.create', 'Guests', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(33, 'Guests — Edit', 'guests.edit', 'Guests', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(34, 'Guests — Delete', 'guests.delete', 'Guests', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(35, 'Guests — Export', 'guests.export', 'Guests', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(36, 'Guests — Manage', 'guests.manage', 'Guests', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(37, 'Guest identities — View', 'guest-identities.view', 'Guest identities', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(38, 'Guest identities — Create', 'guest-identities.create', 'Guest identities', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(39, 'Guest identities — Edit', 'guest-identities.edit', 'Guest identities', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(40, 'Guest identities — Delete', 'guest-identities.delete', 'Guest identities', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(41, 'Guest identities — Export', 'guest-identities.export', 'Guest identities', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(42, 'Guest identities — Manage', 'guest-identities.manage', 'Guest identities', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(43, 'Service requests — View', 'service-requests.view', 'Service requests', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(44, 'Service requests — Create', 'service-requests.create', 'Service requests', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(45, 'Service requests — Edit', 'service-requests.edit', 'Service requests', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(46, 'Service requests — Delete', 'service-requests.delete', 'Service requests', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(47, 'Service requests — Export', 'service-requests.export', 'Service requests', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(48, 'Service requests — Manage', 'service-requests.manage', 'Service requests', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(49, 'Support Tickets View', 'support-tickets.view', 'support-tickets', '2026-07-25 10:09:17', '2026-07-25 17:13:35'),
(50, 'Support Tickets Create', 'support-tickets.create', 'support-tickets', '2026-07-25 10:09:17', '2026-07-25 17:13:35'),
(51, 'Support Tickets Edit', 'support-tickets.edit', 'support-tickets', '2026-07-25 10:09:17', '2026-07-25 17:13:35'),
(52, 'Support tickets — Delete', 'support-tickets.delete', 'Support tickets', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(53, 'Support Tickets Export', 'support-tickets.export', 'support-tickets', '2026-07-25 10:09:17', '2026-07-25 17:13:35'),
(54, 'Support Tickets Manage', 'support-tickets.manage', 'support-tickets', '2026-07-25 10:09:17', '2026-07-25 17:13:35'),
(55, 'Documents — View', 'documents.view', 'Documents', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(56, 'Documents — Create', 'documents.create', 'Documents', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(57, 'Documents — Edit', 'documents.edit', 'Documents', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(58, 'Documents — Delete', 'documents.delete', 'Documents', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(59, 'Documents — Export', 'documents.export', 'Documents', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(60, 'Documents — Manage', 'documents.manage', 'Documents', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(61, 'Communications — View', 'communications.view', 'Communications', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(62, 'Communications — Create', 'communications.create', 'Communications', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(63, 'Communications — Edit', 'communications.edit', 'Communications', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(64, 'Communications — Delete', 'communications.delete', 'Communications', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(65, 'Communications — Export', 'communications.export', 'Communications', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(66, 'Communications — Manage', 'communications.manage', 'Communications', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(67, 'Cms View', 'cms.view', 'cms', '2026-07-25 10:09:17', '2026-07-25 17:13:35'),
(68, 'CMS — Create', 'cms.create', 'CMS', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(69, 'Cms Edit', 'cms.edit', 'cms', '2026-07-25 10:09:17', '2026-07-25 17:13:35'),
(70, 'CMS — Delete', 'cms.delete', 'CMS', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(71, 'CMS — Export', 'cms.export', 'CMS', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(72, 'CMS — Manage', 'cms.manage', 'CMS', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(73, 'Reports View', 'reports.view', 'reports', '2026-07-25 10:09:17', '2026-07-25 17:13:35'),
(74, 'Reports — Create', 'reports.create', 'Reports', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(75, 'Reports — Edit', 'reports.edit', 'Reports', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(76, 'Reports — Delete', 'reports.delete', 'Reports', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(77, 'Reports Export', 'reports.export', 'reports', '2026-07-25 10:09:17', '2026-07-25 17:13:35'),
(78, 'Reports — Manage', 'reports.manage', 'Reports', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(79, 'Staff — View', 'staff.view', 'Staff', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(80, 'Staff — Create', 'staff.create', 'Staff', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(81, 'Staff — Edit', 'staff.edit', 'Staff', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(82, 'Staff — Delete', 'staff.delete', 'Staff', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(83, 'Staff — Export', 'staff.export', 'Staff', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(84, 'Staff — Manage', 'staff.manage', 'Staff', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(85, 'Settings — View', 'settings.view', 'Settings', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(86, 'Settings — Create', 'settings.create', 'Settings', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(87, 'Settings — Edit', 'settings.edit', 'Settings', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(88, 'Settings — Delete', 'settings.delete', 'Settings', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(89, 'Settings — Export', 'settings.export', 'Settings', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(90, 'Settings — Manage', 'settings.manage', 'Settings', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(91, 'Audit Logs View', 'audit-logs.view', 'audit-logs', '2026-07-25 10:09:17', '2026-07-25 17:13:35'),
(92, 'Audit logs — Create', 'audit-logs.create', 'Audit logs', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(93, 'Audit logs — Edit', 'audit-logs.edit', 'Audit logs', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(94, 'Audit logs — Delete', 'audit-logs.delete', 'Audit logs', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(95, 'Audit logs — Export', 'audit-logs.export', 'Audit logs', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(96, 'Audit logs — Manage', 'audit-logs.manage', 'Audit logs', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(97, 'System Health View', 'system-health.view', 'system-health', '2026-07-25 10:09:17', '2026-07-25 17:13:35'),
(98, 'System health — Create', 'system-health.create', 'System health', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(99, 'System health — Edit', 'system-health.edit', 'System health', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(100, 'System health — Delete', 'system-health.delete', 'System health', '2026-07-25 10:09:17', '2026-07-25 10:09:17'),
(101, 'System health — Export', 'system-health.export', 'System health', '2026-07-25 10:09:18', '2026-07-25 10:09:18'),
(102, 'System Health Manage', 'system-health.manage', 'system-health', '2026-07-25 10:09:18', '2026-07-25 17:13:35'),
(103, 'Checkin View', 'checkin.view', NULL, '2026-07-25 13:16:13', '2026-07-25 13:16:13'),
(104, 'Checkin Manage', 'checkin.manage', NULL, '2026-07-25 13:16:13', '2026-07-25 13:16:13'),
(105, 'Service Requests View', 'service_requests.view', NULL, '2026-07-25 13:16:13', '2026-07-25 13:16:13'),
(106, 'Service Requests Manage', 'service_requests.manage', NULL, '2026-07-25 13:16:13', '2026-07-25 13:16:13'),
(107, 'Reviews View', 'reviews.view', 'reviews', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(108, 'Reviews Edit', 'reviews.edit', 'reviews', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(109, 'Reviews Manage', 'reviews.manage', 'reviews', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(110, 'Promotions View', 'promotions.view', 'promotions', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(111, 'Promotions Create', 'promotions.create', 'promotions', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(112, 'Promotions Edit', 'promotions.edit', 'promotions', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(113, 'Promotions Manage', 'promotions.manage', 'promotions', '2026-07-25 17:13:35', '2026-07-25 17:13:35');

INSERT INTO `identity_types` (`id`, `name`, `slug`, `description`, `is_active`, `is_system`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Passport', 'passport', 'Government-issued passport.', 1, 1, 1, '2026-07-25 10:09:16', '2026-07-25 10:09:16'),
(2, 'National ID', 'national_id', 'Government-issued national identity card.', 1, 1, 2, '2026-07-25 10:09:16', '2026-07-25 10:09:16'),
(3, 'Driver''s Licence', 'drivers_licence', 'Government-issued driver''s licence.', 1, 1, 3, '2026-07-25 10:09:16', '2026-07-25 10:09:16'),
(4, 'Other government ID', 'other_government_id', 'An administrator-approved government-issued photo ID.', 1, 1, 4, '2026-07-25 10:09:16', '2026-07-25 10:09:16');

INSERT INTO `content_blocks` (`id`, `page`, `key`, `label`, `value`, `type`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'home', 'home.hero.eyebrow', 'Hero eyebrow', 'Private serviced residences', 'text', 10, 1, '2026-07-23 10:11:01', '2026-07-23 10:11:01'),
(2, 'home', 'home.hero.title', 'Hero title', 'Exceptional stays, thoughtfully managed.', 'text', 20, 1, '2026-07-23 10:11:01', '2026-07-23 10:11:01'),
(3, 'home', 'home.hero.body', 'Hero description', 'Discover private, fully serviced residences across our operating destinations.', 'textarea', 30, 1, '2026-07-23 10:11:01', '2026-07-23 10:11:01'),
(4, 'home', 'home.about.title', 'About title', 'A considered collection of residences.', 'text', 40, 1, '2026-07-23 10:11:01', '2026-07-23 10:11:01'),
(5, 'home', 'home.about.body', 'About description', 'Every property is selected, prepared and managed to a consistent hospitality standard.', 'textarea', 50, 1, '2026-07-23 10:11:01', '2026-07-23 10:11:01'),
(6, 'home', 'home.featured.title', 'Featured properties title', 'Featured residences', 'text', 60, 1, '2026-07-23 10:11:01', '2026-07-23 10:11:01'),
(7, 'home', 'home.services.title', 'Services title', 'Hospitality beyond the front door.', 'text', 70, 1, '2026-07-23 10:11:01', '2026-07-23 10:11:01');

INSERT INTO `homepage_sections` (`id`, `key`, `name`, `type`, `content`, `background_media`, `status`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'hero', 'Hero', 'hero', NULL, NULL, 'published', 10, 1, '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(2, 'availability', 'Availability search', 'content', NULL, NULL, 'published', 20, 1, '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(3, 'about', 'Introduction', 'content', NULL, NULL, 'published', 30, 1, '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(4, 'residences', 'Featured residences', 'properties', NULL, NULL, 'published', 40, 1, '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(5, 'services', 'Guest services', 'services', NULL, NULL, 'published', 50, 1, '2026-07-23 13:08:26', '2026-07-23 13:08:26');

INSERT INTO `navigation_items` (`id`, `label`, `url`, `location`, `target`, `icon`, `parent_id`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Residences', '#residences', 'header', '_self', NULL, NULL, 10, 1, '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(2, 'About', '#about', 'header', '_self', NULL, NULL, 20, 1, '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(3, 'Services', '#services', 'header', '_self', NULL, NULL, 30, 1, '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(4, 'Local guide', '#guide', 'header', '_self', NULL, NULL, 40, 1, '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(5, 'Contact', '#contact', 'header', '_self', NULL, NULL, 50, 1, '2026-07-23 13:08:26', '2026-07-23 13:08:26');

INSERT INTO `site_settings` (`id`, `key`, `value`, `type`, `group`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Azari Residences', 'text', 'general', '2026-07-23 10:11:01', '2026-07-25 00:47:47'),
(2, 'site_tagline', 'Private serviced residences', 'text', 'general', '2026-07-23 10:11:01', '2026-07-25 00:47:47'),
(3, 'operating_regions', 'Nigeria and Rwanda', 'text', 'branding', '2026-07-23 10:11:01', '2026-07-23 10:11:01'),
(4, 'business_name', 'Azari Luxury Properties LTD', 'text', 'general', '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(5, 'seo_title', 'Azari Residences | Exceptional serviced stays', 'text', 'general', '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(6, 'seo_description', 'Discover private, fully serviced Azari residences designed around comfort, privacy and dependable hospitality.', 'text', 'general', '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(7, 'theme_primary', '#12211b', 'text', 'theme', '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(8, 'theme_secondary', '#f3ecdd', 'text', 'theme', '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(9, 'theme_accent', '#bb8a3e', 'text', 'theme', '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(10, 'theme_background', '#f3ecdd', 'text', 'theme', '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(11, 'theme_surface', '#ffffff', 'text', 'theme', '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(12, 'theme_text', '#1c231f', 'text', 'theme', '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(13, 'theme_muted', '#6e7268', 'text', 'theme', '2026-07-23 13:08:26', '2026-07-23 13:08:26'),
(14, 'public_contact_email', 'hello@example.com', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(15, 'customer_dashboard_contact_email', 'hello@example.com', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(16, 'support_destination_email', 'hello@example.com', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(17, 'service_request_destination_email', 'hello@example.com', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(18, 'contact_phone', '+250799 643 143', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(19, 'whatsapp_number', '+250799643143', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(20, 'support_hours', 'Monday to Sunday, 8:00 AM to 8:00 PM', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(21, 'expected_response_time', 'Within one business day', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(22, 'emergency_contact_notice', 'For immediate danger, contact local emergency services.', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(23, 'public_timezone_wording', 'Times are shown in Azari''s operational timezone: Africa/Lagos', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(24, 'receipt_footer', 'Thank you for choosing THE AZARI RESIDENCES.', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(25, 'invoice_footer', 'Payment is subject to the booking terms shown at confirmation.', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(26, 'service_request_email_display', 'hello@example.com', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(27, 'cancellation_wording', 'Cancellation terms shown during booking apply.', 'textarea', 'customer_communications', '2026-07-25 17:13:35', '2026-07-25 17:13:35'),
(28, 'payment_instructions', 'Use only the payment options presented by Azari.', 'textarea', 'customer_communications', '2026-07-25 17:13:36', '2026-07-25 17:13:36'),
(29, 'verification_explanatory_text', 'This page verifies an Azari booking using privacy-safe information.', 'textarea', 'customer_communications', '2026-07-25 17:13:36', '2026-07-25 17:13:36'),
(30, 'empty_state_bookings', 'You do not have any bookings yet.', 'textarea', 'customer_communications', '2026-07-25 17:13:36', '2026-07-25 17:13:36'),
(31, 'empty_state_payments', 'No payment records are available yet.', 'textarea', 'customer_communications', '2026-07-25 17:13:36', '2026-07-25 17:13:36'),
(32, 'document_legal_text', 'This electronically generated document is valid with its verification reference.', 'textarea', 'customer_communications', '2026-07-25 17:13:36', '2026-07-25 17:13:36'),
(33, 'booking_notice', '', 'textarea', 'customer_communications', '2026-07-25 17:13:36', '2026-07-25 17:13:36');

INSERT INTO `payment_provider_statuses` (`id`, `provider`, `mode`, `enabled`, `connection_status`, `last_checked_at`, `last_webhook_at`, `last_successful_payment_at`, `safe_message`, `created_at`, `updated_at`) VALUES
(1, 'flutterwave', 'test', 1, 'not_tested', '2026-07-25 10:35:30', NULL, NULL, 'Provider is disabled.', '2026-07-25 10:09:18', '2026-07-25 11:02:29'),
(2, 'pesapal', 'test', 0, 'not_tested', '2026-07-25 10:35:30', NULL, NULL, 'Provider is disabled.', '2026-07-25 10:09:18', '2026-07-25 11:02:29'),
(3, 'intouch', 'test', 0, 'not_tested', '2026-07-25 10:35:30', NULL, NULL, 'Provider is disabled.', '2026-07-25 10:09:18', '2026-07-25 11:02:29');

-- Sole production administrator
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `staff_role`, `is_active`, `profile_photo_path`, `last_login_at`, `username`, `phone`, `avatar_path`, `is_admin`, `account_type`, `status`, `last_active_at`, `suspended_at`, `suspension_reason`, `timezone`, `phone_verified_at`, `emergency_contact_name`, `emergency_contact_phone`, `email_notifications`, `sms_notifications`, `marketing_consent`) VALUES
(1, 'Admin', 'admin@azariadmin.com', '2026-07-25 00:47:47', '$2y$12$W767OZ8gHfL2DInNevDvY.3janmP9O5QnMSp7cG4QVtlueoecbRzS', '7kguyuxXEDNmPNSMWvyt01wJoqz0xGYb4d4usVvijLxx4H8HnuehNH03mQNi', '2026-07-23 13:08:26', '2026-07-26 03:29:35', 'administrator', 1, NULL, '2026-07-26 03:06:47', 'admin', NULL, NULL, 1, 'admin', 'active', '2026-07-26 03:29:35', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0);

-- Administrator permissions
INSERT INTO `permission_user` (`permission_id`, `user_id`, `granted_by`, `created_at`, `updated_at`) VALUES
(91, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(92, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(93, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(94, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(95, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(96, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(25, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(26, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(27, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(28, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(29, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(30, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(7, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(8, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(9, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(10, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(11, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(12, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(67, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(68, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(69, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(70, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(71, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(72, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(61, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(62, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(63, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(64, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(65, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(66, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(1, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(2, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(3, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(4, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(5, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(6, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(55, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(56, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(57, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(58, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(59, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(60, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(37, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(38, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(39, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(40, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(41, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(42, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(31, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(32, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(33, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(34, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(35, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(36, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(13, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(14, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(15, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(16, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(17, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(18, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(19, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(20, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(21, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(22, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(23, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(24, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(73, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(74, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(75, 1, 1, '2026-07-25 11:02:29', '2026-07-25 11:30:17'),
(76, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(77, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(78, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(43, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(44, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(45, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(46, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(47, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(48, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(85, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(86, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(87, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(88, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(89, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(90, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(79, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(80, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(81, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(82, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(83, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:17'),
(84, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(49, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(50, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(51, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(52, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(53, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(54, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(97, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(98, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(99, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(100, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(101, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(102, 1, 1, '2026-07-25 11:02:30', '2026-07-25 11:30:18'),
(103, 1, NULL, '2026-07-25 13:16:13', '2026-07-25 13:16:13'),
(104, 1, NULL, '2026-07-25 13:16:13', '2026-07-25 13:16:13'),
(105, 1, NULL, '2026-07-25 13:16:13', '2026-07-25 13:16:13'),
(106, 1, NULL, '2026-07-25 13:16:13', '2026-07-25 13:16:13');

INSERT INTO `permissions` (`name`, `slug`, `group`, `created_at`, `updated_at`) VALUES
('Property owners — View', 'property-owners.view', 'Property owners', NOW(), NOW()),
('Property owners — Create', 'property-owners.create', 'Property owners', NOW(), NOW()),
('Property owners — Edit', 'property-owners.edit', 'Property owners', NOW(), NOW()),
('Property owners — Delete', 'property-owners.delete', 'Property owners', NOW(), NOW()),
('Property owners — Export', 'property-owners.export', 'Property owners', NOW(), NOW()),
('Property owners — Manage', 'property-owners.manage', 'Property owners', NOW(), NOW()),
('Approve or decline property listings', 'property-owners.review', 'Property owners', NOW(), NOW()),
('Owner withdrawals — View', 'owner-withdrawals.view', 'Owner withdrawals', NOW(), NOW()),
('Owner withdrawals — Create', 'owner-withdrawals.create', 'Owner withdrawals', NOW(), NOW()),
('Owner withdrawals — Edit', 'owner-withdrawals.edit', 'Owner withdrawals', NOW(), NOW()),
('Owner withdrawals — Delete', 'owner-withdrawals.delete', 'Owner withdrawals', NOW(), NOW()),
('Owner withdrawals — Export', 'owner-withdrawals.export', 'Owner withdrawals', NOW(), NOW()),
('Owner withdrawals — Manage', 'owner-withdrawals.manage', 'Owner withdrawals', NOW(), NOW()),
('Process owner withdrawals', 'owner-withdrawals.process', 'Owner withdrawals', NOW(), NOW()),
('Owner marketplace settings — View', 'owner-settings.view', 'Owner marketplace settings', NOW(), NOW()),
('Owner marketplace settings — Create', 'owner-settings.create', 'Owner marketplace settings', NOW(), NOW()),
('Owner marketplace settings — Edit', 'owner-settings.edit', 'Owner marketplace settings', NOW(), NOW()),
('Owner marketplace settings — Delete', 'owner-settings.delete', 'Owner marketplace settings', NOW(), NOW()),
('Owner marketplace settings — Export', 'owner-settings.export', 'Owner marketplace settings', NOW(), NOW()),
('Owner marketplace settings — Manage', 'owner-settings.manage', 'Owner marketplace settings', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `group` = VALUES(`group`),
  `updated_at` = VALUES(`updated_at`);

INSERT INTO `permission_user` (`permission_id`, `user_id`, `granted_by`, `created_at`, `updated_at`)
SELECT `permissions`.`id`, `users`.`id`, NULL, NOW(), NOW()
FROM `permissions`
INNER JOIN `users`
  ON LOWER(`users`.`email`) = 'admin@azariadmin.com'
LEFT JOIN `permission_user`
  ON `permission_user`.`permission_id` = `permissions`.`id`
 AND `permission_user`.`user_id` = `users`.`id`
WHERE (
    `permissions`.`slug` LIKE 'property-owners.%'
 OR `permissions`.`slug` LIKE 'owner-withdrawals.%'
 OR `permissions`.`slug` LIKE 'owner-settings.%'
)
AND `permission_user`.`permission_id` IS NULL;

INSERT INTO `site_settings` (`key`, `value`, `type`, `group`, `created_at`, `updated_at`) VALUES
('owner_listing_agreement_version', '1.0', 'text', 'property_owners', NOW(), NOW()),
('owner_default_share_percentage', '70', 'number', 'property_owners', NOW(), NOW()),
('owner_withdrawal_days', '1,2,3,4,5', 'text', 'property_owners', NOW(), NOW()),
('owner_withdrawal_minimum', '50', 'number', 'property_owners', NOW(), NOW()),
('owner_withdrawal_currency', 'USD', 'text', 'property_owners', NOW(), NOW()),
('owner_paypal_enabled', '0', 'boolean', 'property_owners', NOW(), NOW()),
('owner_stripe_enabled', '0', 'boolean', 'property_owners', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `value` = VALUES(`value`),
  `type` = VALUES(`type`),
  `group` = VALUES(`group`),
  `updated_at` = VALUES(`updated_at`);

-- v1.0.5 canonical permissions, administrator repair, and contact settings
INSERT INTO `permissions` (`name`, `slug`, `group`, `created_at`, `updated_at`) VALUES
('Promotions View', 'promotions.view', 'promotions', NOW(), NOW()),
('Promotions Create', 'promotions.create', 'promotions', NOW(), NOW()),
('Promotions Edit', 'promotions.edit', 'promotions', NOW(), NOW()),
('Promotions Manage', 'promotions.manage', 'promotions', NOW(), NOW()),
('Vouchers View', 'vouchers.view', 'vouchers', NOW(), NOW()),
('Vouchers Create', 'vouchers.create', 'vouchers', NOW(), NOW()),
('Vouchers Edit', 'vouchers.edit', 'vouchers', NOW(), NOW()),
('Vouchers Manage', 'vouchers.manage', 'vouchers', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `group` = VALUES(`group`),
  `updated_at` = VALUES(`updated_at`);

UPDATE `users`
SET `account_type` = 'admin', `staff_role` = 'administrator', `is_admin` = 1,
    `is_active` = 1, `status` = 'active', `suspended_at` = NULL,
    `suspension_reason` = NULL
WHERE LOWER(`email`) = 'admin@azariadmin.com';

UPDATE `site_settings`
SET `value` = '+250799 643 143', `type` = 'text',
    `group` = 'customer_communications', `updated_at` = NOW()
WHERE `key` = 'contact_phone';
UPDATE `site_settings`
SET `value` = '+250799643143', `type` = 'text',
    `group` = 'customer_communications', `updated_at` = NOW()
WHERE `key` = 'whatsapp_number';

UPDATE `permission_user` pu
INNER JOIN `users` u ON u.`id` = pu.`user_id`
SET pu.`granted_by` = NULL, pu.`updated_at` = NOW()
WHERE LOWER(u.`email`) = 'admin@azariadmin.com';

INSERT INTO `permission_user` (`permission_id`, `user_id`, `granted_by`, `created_at`, `updated_at`)
SELECT p.`id`, u.`id`, NULL, NOW(), NOW()
FROM `permissions` p
CROSS JOIN `users` u
LEFT JOIN `permission_user` pu
  ON pu.`permission_id` = p.`id` AND pu.`user_id` = u.`id`
WHERE LOWER(u.`email`) = 'admin@azariadmin.com'
  AND pu.`permission_id` IS NULL;


SET FOREIGN_KEY_CHECKS = 1;
