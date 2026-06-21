<?php
/**
 * Plugin Name: Idibia Cron Scheduler
 * Description: Registers WordPress cron jobs for scheduled dispatch, offer expiry, and trip auto-timeout.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'cron_schedules', function ( array $schedules ): array {
    $schedules['idibia_every_minute'] = [
        'interval' => 60,
        'display'  => 'Every Minute (Idibia)',
    ];
    $schedules['idibia_every_two_minutes'] = [
        'interval' => 120,
        'display'  => 'Every 2 Minutes (Idibia)',
    ];
    return $schedules;
} );

add_action( 'init', 'idibia_schedule_cron_events' );

function idibia_schedule_cron_events(): void {
    if ( ! wp_next_scheduled( 'idibia_cron_scheduled_dispatch' ) ) {
        wp_schedule_event( time(), 'idibia_every_two_minutes', 'idibia_cron_scheduled_dispatch' );
    }
    if ( ! wp_next_scheduled( 'idibia_cron_offer_expiry' ) ) {
        wp_schedule_event( time(), 'idibia_every_minute', 'idibia_cron_offer_expiry' );
    }
    if ( ! wp_next_scheduled( 'idibia_cron_trip_timeout' ) ) {
        wp_schedule_event( time(), 'idibia_every_two_minutes', 'idibia_cron_trip_timeout' );
    }
}

add_action( 'idibia_cron_scheduled_dispatch', 'idibia_cron_scheduled_dispatch' );
add_action( 'idibia_cron_offer_expiry',       'idibia_cron_offer_expiry' );
add_action( 'idibia_cron_trip_timeout',       'idibia_cron_trip_timeout' );
