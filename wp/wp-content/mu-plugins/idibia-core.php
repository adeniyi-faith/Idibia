<?php
/**
 * Plugin Name: Idibia Core Tables
 * Description: Creates Idibia custom tables on first load. Place in /wp-content/mu-plugins/
 * Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'idibia_maybe_create_tables' );

function idibia_maybe_create_tables() {
    $has_v1 = (bool) get_option( 'idibia_tables_v1' );
    $has_v2 = (bool) get_option( 'idibia_tables_v2' );

    if ( $has_v1 && $has_v2 ) return;

    idibia_create_tables();

    if ( ! $has_v1 ) {
        update_option( 'idibia_tables_v1', true );
    }

    update_option( 'idibia_tables_v2', true );
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
        `verify_code`      VARCHAR(10)      NULL,
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
        ('notif_failed_payout', '1');" );
}
