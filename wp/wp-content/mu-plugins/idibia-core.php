<?php
/**
 * Plugin Name: Idibia Core Tables
 * Description: Creates Idibia custom tables on first load. Place in /wp-content/mu-plugins/
 * Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'idibia_maybe_create_tables' );

function idibia_maybe_create_tables() {
    $current_version = (int) get_option( 'idibia_db_version', 0 );
    $target_version = 19;

    // Handle legacy v1/v2 options if they exist
    $has_v1 = (bool) get_option( 'idibia_tables_v1' );
    $has_v2 = (bool) get_option( 'idibia_tables_v2' );
    if ( $current_version === 0 && $has_v1 && $has_v2 ) {
        $current_version = 2;
        update_option( 'idibia_db_version', 2 );
    }

    if ( $current_version >= $target_version ) return;

    if ( $current_version < 2 ) {
        idibia_create_tables();
        update_option( 'idibia_db_version', 2 );
        $current_version = 2;
    }

    if ( $current_version < 3 ) {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Phase 1: Update sd_trips table
        $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_trips`
            ADD COLUMN `pickup_lat` DECIMAL(10,8) NULL AFTER `pickup`,
            ADD COLUMN `pickup_lng` DECIMAL(11,8) NULL AFTER `pickup_lat`,
            ADD COLUMN `dropoff_lat` DECIMAL(10,8) NULL AFTER `dropoff`,
            ADD COLUMN `dropoff_lng` DECIMAL(11,8) NULL AFTER `dropoff_lat`,
            ADD COLUMN `pickup_address` VARCHAR(255) NULL AFTER `pickup_lng`,
            ADD COLUMN `dropoff_address` VARCHAR(255) NULL AFTER `dropoff_lng`,
            ADD COLUMN `package_metadata` TEXT NULL AFTER `dropoff_address`,
            ADD COLUMN `service_category` VARCHAR(50) NULL AFTER `package_metadata`,
            ADD COLUMN `vehicle_type` ENUM('bike','car','van','keke') NULL AFTER `service_category`,
            ADD COLUMN `scheduled_time` DATETIME NULL AFTER `vehicle_type`,
            ADD COLUMN `fare_estimate` DECIMAL(10,2) NULL AFTER `fare`,
            ADD COLUMN `final_fare` DECIMAL(10,2) NULL AFTER `fare_estimate`,
            ADD COLUMN `distance_km` DECIMAL(8,2) NULL AFTER `final_fare`,
            ADD COLUMN `duration_mins` INT UNSIGNED NULL AFTER `distance_km`,
            ADD COLUMN `payment_status` ENUM('pending','pending_proof','proof_submitted','approved','rejected','authorized','captured','failed','refunded') NOT NULL DEFAULT 'pending' AFTER `duration_mins`,
            ADD COLUMN `dispatch_status` ENUM('searching','offered','accepted','arriving','arrived_pickup','picked_up','arrived_dropoff','completed','cancelled','no_driver') NOT NULL DEFAULT 'searching' AFTER `payment_status`,
            ADD COLUMN `cancellation_reason` VARCHAR(255) NULL AFTER `dispatch_status`,
            ADD COLUMN `delivery_pin` VARCHAR(10) NULL AFTER `cancellation_reason`,
            ADD COLUMN `proof_of_delivery_path` VARCHAR(255) NULL AFTER `delivery_pin`,
            ADD COLUMN `searching_at` DATETIME NULL AFTER `accepted_at`,
            ADD COLUMN `arrived_at` DATETIME NULL AFTER `searching_at`;"
        );


        // Phase 1: Add new lifecycle tables
        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_trip_events` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `trip_id` BIGINT UNSIGNED NOT NULL,
            `event_type` VARCHAR(50) NOT NULL,
            `event_data` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `trip_id` (`trip_id`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_driver_locations` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `driver_id` BIGINT UNSIGNED NOT NULL,
            `lat` DECIMAL(10,8) NOT NULL,
            `lng` DECIMAL(11,8) NOT NULL,
            `heading` DECIMAL(5,2) NULL,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `driver_id` (`driver_id`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_dispatch_offers` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `trip_id` BIGINT UNSIGNED NOT NULL,
            `driver_id` BIGINT UNSIGNED NOT NULL,
            `status` ENUM('pending','accepted','declined','expired') NOT NULL DEFAULT 'pending',
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `trip_id` (`trip_id`),
            KEY `driver_id` (`driver_id`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_payments` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `trip_id` BIGINT UNSIGNED NOT NULL,
            `customer_id` BIGINT UNSIGNED NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `provider` VARCHAR(50) NOT NULL,
            `provider_ref` VARCHAR(100) NULL,
            `proof_path` VARCHAR(255) NULL,
            `status` ENUM('pending','pending_proof','proof_submitted','approved','rejected','authorized','captured','failed','refunded') NOT NULL DEFAULT 'pending',
            `admin_notes` TEXT NULL,
            `reviewed_by` BIGINT UNSIGNED NULL,
            `reviewed_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `trip_id` (`trip_id`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_campaigns` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NOT NULL,
            `target_trips` INT UNSIGNED NOT NULL,
            `bonus_amount` DECIMAL(10,2) NOT NULL,
            `start_time` DATETIME NOT NULL,
            `end_time` DATETIME NOT NULL,
            `status` ENUM('active','inactive','completed') NOT NULL DEFAULT 'active',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `status` (`status`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_campaign_payouts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `campaign_id` BIGINT UNSIGNED NOT NULL,
            `driver_id` BIGINT UNSIGNED NOT NULL,
            `trips_at_completion` INT UNSIGNED NOT NULL,
            `bonus_paid` DECIMAL(10,2) NOT NULL,
            `paid_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `campaign_driver` (`campaign_id`, `driver_id`),
            KEY `campaign_id` (`campaign_id`),
            KEY `driver_id` (`driver_id`)
        ) $charset;" );

        $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_campaigns` ADD COLUMN IF NOT EXISTS `eligible_vehicle_types` TEXT NULL COMMENT 'JSON array or null for all'" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_wallet_ledger` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `driver_id` BIGINT UNSIGNED NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `entry_type` ENUM('earning','commission','bonus','refund','penalty','payout') NOT NULL,
            `reference_id` BIGINT UNSIGNED NULL COMMENT 'Links to trip_id, payout_id, etc.',
            `description` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `driver_id` (`driver_id`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_payouts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `driver_id` BIGINT UNSIGNED NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `status` ENUM('pending','processing','paid','failed') NOT NULL DEFAULT 'pending',
            `provider_ref` VARCHAR(100) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `driver_id` (`driver_id`)
        ) $charset;" );


        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_admin_audit_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `admin_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `action` VARCHAR(80) NOT NULL,
            `entity_type` VARCHAR(80) NOT NULL,
            `entity_id` BIGINT UNSIGNED NULL,
            `metadata` LONGTEXT NULL,
            `ip` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `action` (`action`),
            KEY `entity` (`entity_type`, `entity_id`),
            KEY `created_at` (`created_at`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_ratings` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `trip_id` BIGINT UNSIGNED NOT NULL,
            `reviewer_id` BIGINT UNSIGNED NOT NULL,
            `reviewer_type` ENUM('customer','driver') NOT NULL,
            `subject_id` BIGINT UNSIGNED NOT NULL,
            `rating` TINYINT UNSIGNED NOT NULL,
            `comment` TEXT NULL,
            `flagged` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `trip_id` (`trip_id`)
        ) $charset;" );
        $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_ratings` ADD COLUMN IF NOT EXISTS `flagged` TINYINT(1) NOT NULL DEFAULT 0" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_notifications` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `user_type` ENUM('customer','driver','admin') NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `body` TEXT NOT NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id_type` (`user_id`, `user_type`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_support_tickets` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `creator_id` BIGINT UNSIGNED NOT NULL,
            `creator_type` ENUM('customer','driver') NOT NULL,
            `trip_id` BIGINT UNSIGNED NULL,
            `category` VARCHAR(100) NOT NULL,
            `status` ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `creator` (`creator_id`, `creator_type`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_support_messages` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ticket_id` BIGINT UNSIGNED NOT NULL,
            `sender_id` BIGINT UNSIGNED NOT NULL,
            `sender_type` ENUM('customer','driver','admin') NOT NULL,
            `message` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `ticket_id` (`ticket_id`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_uploaded_evidence` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference_id` BIGINT UNSIGNED NOT NULL COMMENT 'ticket_id or dispute_id',
            `reference_type` ENUM('ticket','dispute') NOT NULL,
            `uploader_id` BIGINT UNSIGNED NOT NULL,
            `uploader_type` ENUM('customer','driver','admin') NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `reference` (`reference_id`, `reference_type`)
        ) $charset;" );

        update_option( 'idibia_db_version', 3 );
        $current_version = 3;
    }

    if ( $current_version < 4 ) {
        idibia_widen_driver_verify_code_column();
        update_option( 'idibia_db_version', 4 );
        $current_version = 4;
    }


    if ( $current_version < 5 ) {
        idibia_add_manual_payment_columns();
        update_option( 'idibia_db_version', 5 );
        $current_version = 5;
    }

    if ( $current_version < 6 ) {
            idibia_add_customer_rating_and_prefs_columns();
    idibia_add_saved_addresses_column();
        update_option( 'idibia_db_version', 6 );
        $current_version = 6;
    }

    if ( $current_version < 7 ) {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_tracking_tokens` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `token` VARCHAR(64) NOT NULL,
            `trip_id` BIGINT UNSIGNED NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `token` (`token`),
            KEY `trip_id` (`trip_id`)
        ) $charset;" );
        update_option( 'idibia_db_version', 7 );
        $current_version = 7;
    }

    if ( $current_version < 8 ) {
        global $wpdb;
        $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_trips` MODIFY COLUMN `payment_status` ENUM('pending','pending_proof','proof_submitted','approved','rejected','authorized','captured','failed','refunded') NOT NULL DEFAULT 'pending'" );
        $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_payments` MODIFY COLUMN `status` ENUM('pending','pending_proof','proof_submitted','approved','rejected','authorized','captured','failed','refunded') NOT NULL DEFAULT 'pending'" );
        update_option( 'idibia_db_version', 8 );
        $current_version = 8;
    }

    if ( $current_version < 9 ) {
        global $wpdb;
        $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_drivers` ADD COLUMN IF NOT EXISTS `avatar_path` VARCHAR(255) NULL AFTER `selfie_path`" );
        update_option( 'idibia_db_version', 9 );
        $current_version = 9;
    }

    if ( $current_version < 10 ) {
        global $wpdb;
        $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_customers` ADD COLUMN IF NOT EXISTS `avatar_path` VARCHAR(255) NULL AFTER `saved_addresses`" );
        update_option( 'idibia_db_version', 10 );
        $current_version = 10;
    }

    if ( $current_version < 11 ) {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_admin_users` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `full_name` VARCHAR(120) NOT NULL,
            `email` VARCHAR(180) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `avatar_path` VARCHAR(255) NULL,
            `role_id` BIGINT UNSIGNED NOT NULL,
            `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
            `force_password_change` TINYINT(1) NOT NULL DEFAULT 1,
            `last_login` DATETIME NULL,
            `last_login_ip` VARCHAR(45) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`),
            KEY `role_id` (`role_id`),
            KEY `status` (`status`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_roles` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(80) NOT NULL,
            `description` TEXT NULL,
            `is_system` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_role_permissions` (
            `role_id` BIGINT UNSIGNED NOT NULL,
            `permission` VARCHAR(80) NOT NULL,
            PRIMARY KEY (`role_id`, `permission`),
            KEY `permission` (`permission`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_user_permission_overrides` (
            `admin_id` BIGINT UNSIGNED NOT NULL,
            `permission` VARCHAR(80) NOT NULL,
            `is_granted` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`admin_id`, `permission`),
            KEY `permission` (`permission`)
        ) $charset;" );

        // Insert default roles
        $wpdb->query( "INSERT IGNORE INTO `{$wpdb->prefix}sd_roles` (`id`, `name`, `description`, `is_system`) VALUES
            (1, 'Super Admin', 'Unrestricted access. Cannot be edited, demoted, or deleted by anyone below Super Admin level.', 1),
            (2, 'Admin', 'Broad operational access. Cannot manage Super Admins or touch financial execution controls.', 1),
            (3, 'Moderator', 'Operational and review access.', 1),
            (4, 'Staff', 'Limited, read-heavy access for day-to-day monitoring.', 1);"
        );

        // Define permissions array for default roles
        $permissions = [
            'Super Admin' => [
                'view_live_map', 'filter_intervene_trips', 'force_redispatch', 'force_cancel_trip',
                'view_trips', 'admin_cancel_trip', 'correct_trip_status', 'export_trips',
                'view_kyc', 'approve_reject_kyc', 'config_kyc_policy',
                'view_customers', 'suspend_customer', 'view_customer_history',
                'view_drivers', 'suspend_reinstate_driver', 'view_driver_wallet',
                'view_payments', 'approve_reject_payment', 'execute_payouts', 'view_export_revenue', 'issue_refunds',
                'view_disputes', 'assign_resolve_disputes', 'view_download_evidence',
                'view_notifications', 'send_manual_notifications',
                'view_settings', 'edit_pricing_commission', 'edit_payment_credentials', 'edit_kyc_notif_policies', 'edit_legal_docs',
                'view_admin_users', 'create_admin_users', 'edit_admin_users', 'suspend_delete_admin_users'
            ],
            'Admin' => [
                'view_live_map', 'filter_intervene_trips', 'force_redispatch', 'force_cancel_trip',
                'view_trips', 'admin_cancel_trip', 'correct_trip_status', 'export_trips',
                'view_kyc', 'approve_reject_kyc', 'config_kyc_policy',
                'view_customers', 'suspend_customer', 'view_customer_history',
                'view_drivers', 'suspend_reinstate_driver', 'view_driver_wallet',
                'view_payments', 'approve_reject_payment', 'view_export_revenue', 'issue_refunds',
                'view_disputes', 'assign_resolve_disputes', 'view_download_evidence',
                'view_notifications', 'send_manual_notifications',
                'view_settings', 'edit_pricing_commission', 'edit_kyc_notif_policies', 'edit_legal_docs',
                'view_admin_users'
            ],
            'Moderator' => [
                'view_live_map', 'filter_intervene_trips',
                'view_trips',
                'view_kyc', 'approve_reject_kyc',
                'view_customers', 'view_customer_history',
                'view_drivers', 'view_driver_wallet',
                'view_payments', 'approve_reject_payment',
                'view_disputes', 'assign_resolve_disputes', 'view_download_evidence',
                'view_notifications', 'send_manual_notifications'
            ],
            'Staff' => [
                'view_live_map',
                'view_trips',
                'view_kyc',
                'view_customers',
                'view_drivers',
                'view_disputes',
                'view_notifications'
            ]
        ];

        foreach ($permissions as $role_name => $perms) {
            $role_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$wpdb->prefix}sd_roles` WHERE name = %s", $role_name));
            if ($role_id) {
                foreach ($perms as $perm) {
                    $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `{$wpdb->prefix}sd_role_permissions` (`role_id`, `permission`) VALUES (%d, %s)", $role_id, $perm));
                }
            }
        }

        update_option( 'idibia_db_version', 11 );
        $current_version = 11;
    }

    if ( $current_version < 12 ) {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Add wallet balance to customers
        $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_customers` ADD COLUMN IF NOT EXISTS `wallet_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `referred_by`" );

        // Customer wallet ledger
        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_customer_wallet_ledger` (
            `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `customer_id`  BIGINT UNSIGNED NOT NULL,
            `amount`       DECIMAL(10,2)   NOT NULL,
            `entry_type`   ENUM('referral_bonus','credit','debit') NOT NULL,
            `reference_id` BIGINT UNSIGNED NULL,
            `description`  VARCHAR(255)    NULL,
            `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `customer_id` (`customer_id`)
        ) $charset;" );

        update_option( 'idibia_db_version', 12 );
        $current_version = 12;
    }

    if ( $current_version < 13 ) {
        global $wpdb;

        // Backfill settings rows that were missing from the original defaults
        $wpdb->query( "INSERT IGNORE INTO `{$wpdb->prefix}sd_settings` (`setting_key`, `setting_value`) VALUES
            ('nominatim_url', ''),
            ('ors_url', ''),
            ('ors_api_key', ''),
            ('pusher_app_id', ''),
            ('pusher_key', ''),
            ('pusher_secret', ''),
            ('pusher_cluster', ''),
            ('legal_terms_url', ''),
            ('legal_privacy_url', ''),
            ('legal_location_url', ''),
            ('legal_license_url', '');" );

        update_option( 'idibia_db_version', 13 );
        $current_version = 13;
    }

    if ( $current_version < 14 ) {
        global $wpdb;

        $wpdb->query( "INSERT IGNORE INTO `{$wpdb->prefix}sd_settings` (`setting_key`, `setting_value`) VALUES
            ('dispatch_retry_limit', '3'),
            ('trip_timeout_minutes', '10'),
            ('scheduled_dispatch_advance_minutes', '10');" );

        update_option( 'idibia_db_version', 14 );
        $current_version = 14;
    }

    if ( $current_version < 15 ) {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // KYC resubmission history column
        $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_drivers` ADD COLUMN IF NOT EXISTS `kyc_rejection_history` LONGTEXT NULL" );

        // Per-vehicle-type KYC policy table
        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_kyc_policy` (
            `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `vehicle_type`        ENUM('bike','car','van','keke') NOT NULL,
            `required_documents`  LONGTEXT NOT NULL DEFAULT '[]',
            `selfie_required`     TINYINT(1) NOT NULL DEFAULT 1,
            `min_age`             INT NOT NULL DEFAULT 18,
            `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `vehicle_type` (`vehicle_type`)
        ) $charset;" );

        // Default policies
        $wpdb->query( "INSERT IGNORE INTO `{$wpdb->prefix}sd_kyc_policy` (`vehicle_type`, `required_documents`, `selfie_required`, `min_age`) VALUES
            ('bike',  '[\"government_id\",\"drivers_license\",\"vehicle_registration\"]', 1, 18),
            ('car',   '[\"government_id\",\"drivers_license\",\"vehicle_insurance\",\"vehicle_registration\"]', 1, 21),
            ('van',   '[\"government_id\",\"drivers_license\",\"vehicle_insurance\",\"vehicle_registration\",\"proof_of_ownership\"]', 1, 21),
            ('keke',  '[\"government_id\",\"drivers_license\",\"vehicle_registration\"]', 1, 18);" );

        update_option( 'idibia_db_version', 15 );
        $current_version = 15;
    }

    if ( $current_version < 16 ) {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_blacklist` (
            `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `identifier_type`     ENUM('phone','email','device_id') NOT NULL,
            `identifier_value`    VARCHAR(255) NOT NULL,
            `reason`              TEXT NOT NULL,
            `banned_by_admin_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `identifier` (`identifier_type`, `identifier_value`(191)),
            KEY `identifier_value` (`identifier_value`(191))
        ) $charset;" );

        update_option( 'idibia_db_version', 16 );
        $current_version = 16;
    }

    if ( $current_version < 17 ) {
        global $wpdb;

        // Extend customer wallet ledger enum to support topup and refund entry types.
        // The table was created at v12 with only referral_bonus/credit/debit.
        $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_customer_wallet_ledger`
            MODIFY COLUMN `entry_type` ENUM('referral_bonus','credit','debit','topup','refund') NOT NULL" );

        update_option( 'idibia_db_version', 17 );
        $current_version = 17;
    }

    if ( $current_version < 18 ) {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_email_log` (
            `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `to_email`      VARCHAR(255)    NOT NULL,
            `subject`       VARCHAR(255)    NOT NULL,
            `status`        ENUM('sent','failed') NOT NULL DEFAULT 'sent',
            `error_message` TEXT            NULL,
            `sent_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `to_email` (`to_email`),
            KEY `sent_at`  (`sent_at`)
        ) $charset;" );

        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_broadcasts` (
            `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title`           VARCHAR(255)    NOT NULL,
            `body`            TEXT            NOT NULL,
            `target_type`     VARCHAR(50)     NOT NULL,
            `target_value`    VARCHAR(255)    NULL,
            `send_email`      TINYINT(1)      NOT NULL DEFAULT 0,
            `recipient_count` INT UNSIGNED    NOT NULL DEFAULT 0,
            `scheduled_at`    DATETIME        NULL,
            `sent_at`         DATETIME        NULL,
            `created_by`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `target_type` (`target_type`),
            KEY `sent_at`     (`sent_at`)
        ) $charset;" );

        $wpdb->query( "INSERT IGNORE INTO `{$wpdb->prefix}sd_settings` (`setting_key`, `setting_value`) VALUES
            ('smtp_host',       ''),
            ('smtp_port',       '587'),
            ('smtp_username',   ''),
            ('smtp_password',   ''),
            ('smtp_from_email', ''),
            ('smtp_from_name',  'Idibia');" );

        update_option( 'idibia_db_version', 18 );
        $current_version = 18;
    }

    if ( $current_version < 19 ) {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Add before_state/after_state to audit log for full before-and-after snapshots
        $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_admin_audit_logs`
            ADD COLUMN IF NOT EXISTS `before_state` LONGTEXT NULL AFTER `metadata`,
            ADD COLUMN IF NOT EXISTS `after_state`  LONGTEXT NULL AFTER `before_state`" );

        // Track every settings change: who changed what and when
        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_settings_history` (
            `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `setting_key`         VARCHAR(80)     NOT NULL,
            `old_value`           TEXT            NULL,
            `new_value`           TEXT            NULL,
            `changed_by_admin_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `changed_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `setting_key`         (`setting_key`),
            KEY `changed_by_admin_id` (`changed_by_admin_id`),
            KEY `changed_at`          (`changed_at`)
        ) $charset;" );

        update_option( 'idibia_db_version', 19 );
        $current_version = 19;
    }
}

function idibia_add_customer_rating_and_prefs_columns(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_customers';
    $wpdb->query( "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `rating` DECIMAL(3,2) NOT NULL DEFAULT 0.00 AFTER `status`" );
    $wpdb->query( "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `notification_preferences` TEXT NULL AFTER `rating`" );
}

function idibia_add_saved_addresses_column(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_customers';
    // `ADD COLUMN IF NOT EXISTS` is MariaDB-only and is a syntax error on MySQL,
    // so check for the column explicitly before adding it.
    $columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table`", 0 );
    if ( is_array( $columns ) && ! in_array( 'saved_addresses', $columns, true ) ) {
        $wpdb->query( "ALTER TABLE `$table` ADD COLUMN `saved_addresses` TEXT NULL AFTER `phone`" );
    }
}



function idibia_add_manual_payment_columns(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_payments';
    $wpdb->query( "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `proof_path` VARCHAR(255) NULL AFTER `provider_ref`" );
    $wpdb->query( "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `admin_notes` TEXT NULL AFTER `status`" );
    $wpdb->query( "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `reviewed_by` BIGINT UNSIGNED NULL AFTER `admin_notes`" );
    $wpdb->query( "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `reviewed_at` DATETIME NULL AFTER `reviewed_by`" );
}

function idibia_widen_driver_verify_code_column(): void {
    global $wpdb;
    $wpdb->query( "ALTER TABLE `{$wpdb->prefix}sd_drivers` MODIFY `verify_code` VARCHAR(255) NULL" );
}

function idibia_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    // Customers table
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_customers` (
        `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `full_name`      VARCHAR(120)     NOT NULL,
        `email`          VARCHAR(180)     NOT NULL,
        `phone`          VARCHAR(30)      NOT NULL DEFAULT '',
        `saved_addresses` TEXT            NULL,
        `password_hash`  VARCHAR(255)     NOT NULL,
        `email_verified` TINYINT(1)       NOT NULL DEFAULT 0,
        `verify_code`    VARCHAR(10)      NULL,
        `verify_expires` DATETIME         NULL,
        `referral_code`  VARCHAR(20)      NOT NULL DEFAULT '',
        `referred_by`    BIGINT UNSIGNED  NULL,
                `status`         ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
        `rating`           DECIMAL(3,2)     NOT NULL DEFAULT 0.00,
        `notification_preferences` TEXT     NULL,
        `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (`id`),
        UNIQUE KEY   `email`         (`email`),
        UNIQUE KEY   `referral_code` (`referral_code`),
        KEY          `phone`         (`phone`)
    ) $charset;" );

    // Sessions table
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_sessions` (
        `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `customer_id`  BIGINT UNSIGNED NOT NULL,
        `token`        VARCHAR(96)     NOT NULL,
        `expires_at`   DATETIME        NOT NULL,
        `ip`           VARCHAR(45)     NULL,
        `user_agent`   VARCHAR(300)    NULL,
        `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token`       (`token`),
        KEY        `customer_id` (`customer_id`)
    ) $charset;" );


    // Drivers table
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_drivers` (
        `id`               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `full_name`        VARCHAR(120)     NOT NULL,
        `email`            VARCHAR(180)     NOT NULL,
        `phone`            VARCHAR(30)      NOT NULL DEFAULT '',
        `password_hash`    VARCHAR(255)     NOT NULL,
        `email_verified`   TINYINT(1)       NOT NULL DEFAULT 0,
        `verify_code`      VARCHAR(255)     NULL,
        `verify_expires`   DATETIME         NULL,
        `nin`              VARCHAR(255)     NULL COMMENT 'National ID Number — encrypted AES-256-CBC',
        `bvn`              VARCHAR(255)     NULL COMMENT 'Bank Verification Number — encrypted AES-256-CBC',
        `id_doc_type`      VARCHAR(40)      NULL,
        `vehicle_type`     ENUM('bike','car','van','keke') NULL,
        `vehicle_year`     VARCHAR(4)       NULL,
        `vehicle_plate`    VARCHAR(20)      NULL,
        `vehicle_model`    VARCHAR(80)      NULL,
        `vehicle_color`    VARCHAR(40)      NULL,
        `bank_name`        VARCHAR(120)     NULL,
        `account_holder_name` VARCHAR(120)  NULL,
        `account_number`   VARCHAR(20)      NULL,
        `secondary_bank_name` VARCHAR(120)  NULL,
        `secondary_account_holder` VARCHAR(120) NULL,
        `secondary_account_number` VARCHAR(20)  NULL,
        `emergency_name`   VARCHAR(120)     NULL,
        `emergency_relationship` VARCHAR(40) NULL,
        `emergency_phone`  VARCHAR(30)      NULL,
        `emergency_address` VARCHAR(255)    NULL,
        `selfie_path`      VARCHAR(255)     NULL,
        `avatar_path`      VARCHAR(255)     NULL,
        `id_front_path`    VARCHAR(255)     NULL,
        `id_back_path`     VARCHAR(255)     NULL,
        `vehicle_photo_path` VARCHAR(255)   NULL,
        `vehicle_interior_photo_path` VARCHAR(255) NULL,
        `vehicle_front_photo_path` VARCHAR(255) NULL,
        `vehicle_rear_photo_path` VARCHAR(255) NULL,
        `vehicle_license_doc_path` VARCHAR(255) NULL,
        `insurance_doc_path` VARCHAR(255)   NULL,
        `kyc_status`       ENUM('pending','under_review','approved','rejected') NOT NULL DEFAULT 'pending',
        `kyc_notes`        TEXT             NULL,
        `is_online`        TINYINT(1)       NOT NULL DEFAULT 0,
        `rating`           DECIMAL(3,2)     NOT NULL DEFAULT 0.00,
        `total_trips`      INT UNSIGNED     NOT NULL DEFAULT 0,
        `wallet_balance`   DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
        `status`           ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
        `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email` (`email`),
        KEY `kyc_status` (`kyc_status`),
        KEY `is_online` (`is_online`)
    ) $charset;" );

    // Driver sessions table
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_driver_sessions` (
        `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `driver_id`    BIGINT UNSIGNED NOT NULL,
        `token`        VARCHAR(96)     NOT NULL,
        `expires_at`   DATETIME        NOT NULL,
        `ip`           VARCHAR(45)     NULL,
        `user_agent`   VARCHAR(300)    NULL,
        `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token` (`token`),
        KEY `driver_id` (`driver_id`)
    ) $charset;" );

    // Trips table
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_trips` (
        `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `trip_ref`       VARCHAR(20)      NOT NULL,
        `customer_id`    BIGINT UNSIGNED  NOT NULL,
        `driver_id`      BIGINT UNSIGNED  NULL,
        `pickup`         VARCHAR(255)     NOT NULL,
        `dropoff`        VARCHAR(255)     NOT NULL,
        `category`       ENUM('bike','car','van','keke') NOT NULL DEFAULT 'bike',
        `status`         ENUM('pending','accepted','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
        `fare`           DECIMAL(10,2)    NULL,
        `platform_pct`   TINYINT UNSIGNED NOT NULL DEFAULT 20,
        `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `accepted_at`    DATETIME         NULL,
        `completed_at`   DATETIME         NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `trip_ref` (`trip_ref`),
        KEY `customer_id` (`customer_id`),
        KEY `driver_id` (`driver_id`),
        KEY `status` (`status`)
    ) $charset;" );

    // Disputes table
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_disputes` (
        `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `trip_id`         BIGINT UNSIGNED NOT NULL,
        `customer_id`     BIGINT UNSIGNED NOT NULL,
        `driver_id`       BIGINT UNSIGNED NULL,
        `category`        VARCHAR(80)     NOT NULL,
        `description`     TEXT            NULL,
        `status`          ENUM('open','escalated','resolved') NOT NULL DEFAULT 'open',
        `resolution`      TEXT            NULL,
        `refund_amount`   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
        `admin_notes`     TEXT            NULL,
        `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `resolved_at`     DATETIME        NULL,
        PRIMARY KEY (`id`),
        KEY `trip_id` (`trip_id`),
        KEY `status` (`status`)
    ) $charset;" );

    // Settings table
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sd_settings` (
        `setting_key`   VARCHAR(80)   NOT NULL,
        `setting_value` TEXT          NULL,
        PRIMARY KEY (`setting_key`)
    ) $charset;" );

    // Default platform settings
    $wpdb->query( "INSERT IGNORE INTO `{$wpdb->prefix}sd_settings` (`setting_key`, `setting_value`) VALUES
        ('platform_commission_pct', '20'),
        ('surge_multiplier_cap', '2.5'),
        ('min_fare', '800'),
        ('max_delivery_radius_km', '50'),
        ('kyc_auto_flag_blurry', '1'),
        ('kyc_require_vehicle_inspection', '1'),
        ('kyc_72h_sla_alert', '1'),
        ('kyc_background_check', '0'),
        ('notif_kyc_queue', '1'),
        ('notif_dispute_escalation', '1'),
        ('notif_daily_revenue', '1'),
        ('notif_failed_payout', '1'),
        ('payment_active_provider', 'manual_transfer'),
        ('manual_bank_name', ''),
        ('manual_account_name', ''),
        ('manual_account_number', ''),
        ('manual_payment_instructions', 'Transfer the exact fare to the account below, then upload your payment receipt.'),
        ('paystack_enabled', '0'),
        ('paystack_public_key', ''),
        ('paystack_secret_key', ''),
        ('flutterwave_enabled', '0'),
        ('flutterwave_public_key', ''),
        ('flutterwave_secret_key', ''),
        ('nominatim_url', ''),
        ('ors_url', ''),
        ('ors_api_key', ''),
        ('pusher_app_id', ''),
        ('pusher_key', ''),
        ('pusher_secret', ''),
        ('pusher_cluster', ''),
        ('legal_terms_url', ''),
        ('legal_privacy_url', ''),
        ('legal_location_url', ''),
        ('legal_license_url', '');" );
}
