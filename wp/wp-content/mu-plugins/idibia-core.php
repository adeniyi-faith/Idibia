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
    $target_version = 7;

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
            ADD COLUMN `payment_status` ENUM('pending','authorized','captured','failed','refunded') NOT NULL DEFAULT 'pending' AFTER `duration_mins`,
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
            `status` ENUM('pending','authorized','captured','failed','refunded') NOT NULL DEFAULT 'pending',
            `admin_notes` TEXT NULL,
            `reviewed_by` BIGINT UNSIGNED NULL,
            `reviewed_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `trip_id` (`trip_id`)
        ) $charset;" );

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
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `trip_id` (`trip_id`)
        ) $charset;" );

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
}

function idibia_add_saved_addresses_column(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_customers';
    $wpdb->query( "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `saved_addresses` TEXT NULL AFTER `phone`" );
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
        `emergency_name`   VARCHAR(120)     NULL,
        `emergency_relationship` VARCHAR(40) NULL,
        `emergency_phone`  VARCHAR(30)      NULL,
        `emergency_address` VARCHAR(255)    NULL,
        `selfie_path`      VARCHAR(255)     NULL,
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
        ('manual_payment_instructions', 'Transfer the exact fare, then upload your receipt for admin approval.'),
        ('paystack_enabled', '0'),
        ('paystack_public_key', ''),
        ('paystack_secret_key', ''),
        ('flutterwave_enabled', '0'),
        ('flutterwave_public_key', ''),
        ('flutterwave_secret_key', '');" );
}
