<?php
require_once __DIR__ . '/wp-auth-config.php';

$auth_type = 'driver';
require_once __DIR__ . '/auth-helper.php';

global $wpdb;
$driver_id = (int) $GLOBALS['auth_driver_id'];
$row = $wpdb->get_row( $wpdb->prepare(
    "SELECT kyc_status, status, kyc_notes FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1",
    $driver_id
) );

if ( ! $row ) {
    wp_send_json_error( [ 'message' => 'Driver record not found.' ] );
}

wp_send_json_success( [
    'kyc_status' => $row->kyc_status,
    'status'     => $row->status,
    'is_approved' => $row->kyc_status === 'approved' && $row->status === 'active',
    'kyc_notes'  => $row->kyc_notes ?? '',
] );
