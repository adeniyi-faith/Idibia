<?php
/**
 * Plugin Name: Idibia Core Tables
 * Description: Creates Idibia custom tables on first load. Place in /wp-content/mu-plugins/
 * Version: 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'idibia_maybe_create_tables' );

function idibia_maybe_create_tables() {
    $has_v1 = (bool) get_option( 'idibia_tables_v1' );
    $has_v2 = (bool) get_option( 'idibia_tables_v2' );
    $has_v3 = (bool) get_option( 'idibia_tables_v3' );

    if ( $has_v1 && $has_v2 && $has_v3 ) return;

    idibia_create_tables();

    if ( ! $has_v1 ) {
        update_option( 'idibia_tables_v1', true );
    }

    update_option( 'idibia_tables_v2', true );
    update_option( 'idibia_tables_v3', true );
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
        `nin`              TEXT             NULL COMMENT 'National ID Number — encrypted AES-256-CBC',
        `bvn`              TEXT             NULL COMMENT 'Bank Verification Number — encrypted AES-256-CBC',
        `id_doc_type`      VARCHAR(80)      NULL,
        `vehicle_type`     ENUM('bike','car','van','keke') NULL,
        `vehicle_plate`    VARCHAR(20)      NULL,
        `vehicle_model`    VARCHAR(80)      NULL,
        `bank_name`        VARCHAR(120)     NULL,
        `account_number`   VARCHAR(30)      NULL,
        `emergency_name`   VARCHAR(120)     NULL,
        `emergency_phone`  VARCHAR(30)      NULL,
        `selfie_path`      VARCHAR(255)     NULL,
        `id_front_path`    VARCHAR(255)     NULL,
        `id_back_path`     VARCHAR(255)     NULL,
        `vehicle_photo_path` VARCHAR(255)   NULL,
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


    idibia_upgrade_driver_kyc_columns();
}

function idibia_upgrade_driver_kyc_columns() {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_drivers';

    idibia_add_column_if_missing( $table, 'id_doc_type', '`id_doc_type` VARCHAR(80) NULL AFTER `bvn`' );
    idibia_add_column_if_missing( $table, 'bank_name', '`bank_name` VARCHAR(120) NULL AFTER `vehicle_model`' );
    idibia_add_column_if_missing( $table, 'account_number', '`account_number` VARCHAR(30) NULL AFTER `bank_name`' );
    idibia_add_column_if_missing( $table, 'emergency_name', '`emergency_name` VARCHAR(120) NULL AFTER `account_number`' );
    idibia_add_column_if_missing( $table, 'emergency_phone', '`emergency_phone` VARCHAR(30) NULL AFTER `emergency_name`' );
    idibia_add_column_if_missing( $table, 'selfie_path', '`selfie_path` VARCHAR(255) NULL AFTER `emergency_phone`' );
    idibia_add_column_if_missing( $table, 'id_front_path', '`id_front_path` VARCHAR(255) NULL AFTER `selfie_path`' );
    idibia_add_column_if_missing( $table, 'id_back_path', '`id_back_path` VARCHAR(255) NULL AFTER `id_front_path`' );
    idibia_add_column_if_missing( $table, 'vehicle_photo_path', '`vehicle_photo_path` VARCHAR(255) NULL AFTER `id_back_path`' );
    idibia_add_column_if_missing( $table, 'insurance_doc_path', '`insurance_doc_path` VARCHAR(255) NULL AFTER `vehicle_photo_path`' );

    $wpdb->query( "ALTER TABLE `$table` MODIFY `nin` TEXT NULL COMMENT 'National ID Number — encrypted AES-256-CBC'" );
    $wpdb->query( "ALTER TABLE `$table` MODIFY `bvn` TEXT NULL COMMENT 'Bank Verification Number — encrypted AES-256-CBC'" );
}

function idibia_add_column_if_missing( string $table, string $column, string $definition ) {
    global $wpdb;
    $exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `$table` LIKE %s", $column ) );
    if ( ! $exists ) {
        $wpdb->query( "ALTER TABLE `$table` ADD COLUMN $definition" );
    }
}
