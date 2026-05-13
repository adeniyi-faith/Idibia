<?php
/**
 * Plugin Name: Idibia Core Tables
 * Description: Creates Idibia custom tables on first load. Place in /wp-content/mu-plugins/
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'idibia_maybe_create_tables' );

function idibia_maybe_create_tables() {
    if ( get_option( 'idibia_tables_v1' ) ) return;
    idibia_create_tables();
    update_option( 'idibia_tables_v1', true );
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
}
