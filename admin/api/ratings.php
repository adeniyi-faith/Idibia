<?php
/** Idibia — Admin API: Ratings */

function idibia_admin_get_ratings(): void {
    global $wpdb;

    $ratings_table = $wpdb->prefix . 'sd_ratings';
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $ratings_table ) ) !== $ratings_table ) {
        wp_send_json_success( [ 'ratings' => [], 'total' => 0, 'page' => 1, 'per_page' => 20 ] );
    }

    $page     = max( 1, absint( $_GET['page'] ?? 1 ) );
    $per_page = 20;
    $offset   = ( $page - 1 ) * $per_page;

    $reviewer_type = sanitize_key( $_GET['reviewer_type'] ?? '' );
    $subject_type  = sanitize_key( $_GET['subject_type'] ?? '' );
    $rating_val    = absint( $_GET['rating'] ?? 0 );
    $trip_id       = absint( $_GET['trip_id'] ?? 0 );
    $date_from     = sanitize_text_field( $_GET['date_from'] ?? '' );
    $date_to       = sanitize_text_field( $_GET['date_to'] ?? '' );
    $flagged_only  = ! empty( $_GET['flagged_only'] );

    $where   = [];
    $params  = [];

    if ( $reviewer_type === 'customer' || $reviewer_type === 'driver' ) {
        $where[]  = 'r.reviewer_type = %s';
        $params[] = $reviewer_type;
    }
    if ( $rating_val >= 1 && $rating_val <= 5 ) {
        $where[]  = 'r.rating = %d';
        $params[] = $rating_val;
    }
    if ( $trip_id > 0 ) {
        $where[]  = 'r.trip_id = %d';
        $params[] = $trip_id;
    }
    if ( $date_from ) {
        $where[]  = 'r.created_at >= %s';
        $params[] = $date_from . ' 00:00:00';
    }
    if ( $date_to ) {
        $where[]  = 'r.created_at <= %s';
        $params[] = $date_to . ' 23:59:59';
    }
    if ( $flagged_only ) {
        $where[] = "r.flagged = 1";
    }

    $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

    $base_sql = "FROM `{$wpdb->prefix}sd_ratings` r
        LEFT JOIN `{$wpdb->prefix}sd_drivers`   rd ON rd.id   = r.reviewer_id AND r.reviewer_type = 'driver'
        LEFT JOIN `{$wpdb->prefix}sd_customers` rc ON rc.id   = r.reviewer_id AND r.reviewer_type = 'customer'
        LEFT JOIN `{$wpdb->prefix}sd_drivers`   sd ON sd.id   = r.subject_id  AND r.reviewer_type = 'customer'
        LEFT JOIN `{$wpdb->prefix}sd_customers` sc ON sc.id   = r.subject_id  AND r.reviewer_type = 'driver'
        LEFT JOIN `{$wpdb->prefix}sd_trips`      t  ON t.id   = r.trip_id
        $where_sql";

    $count_sql = "SELECT COUNT(*) $base_sql";
    $rows_sql  = "SELECT r.id, r.trip_id, r.reviewer_id, r.reviewer_type, r.subject_id, r.rating, r.comment,
            r.created_at, r.flagged,
            COALESCE(rd.full_name, rc.full_name) AS reviewer_name,
            COALESCE(sd.full_name, sc.full_name) AS subject_name,
            t.trip_ref
        $base_sql ORDER BY r.created_at DESC LIMIT %d OFFSET %d";

    $count_params = $params;
    $rows_params  = array_merge( $params, [ $per_page, $offset ] );

    $total = (int) ( $count_params
        ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$count_params ) )
        : $wpdb->get_var( $count_sql ) );

    $rows = $rows_params
        ? $wpdb->get_results( $wpdb->prepare( $rows_sql, ...$rows_params ), ARRAY_A )
        : $wpdb->get_results( $rows_sql, ARRAY_A );

    if ( $wpdb->last_error ) {
        wp_send_json_error( [ 'message' => 'Ratings query error: ' . $wpdb->last_error ] );
    }

    wp_send_json_success( [ 'ratings' => $rows ?: [], 'total' => $total, 'page' => $page, 'per_page' => $per_page ] );
}

function idibia_admin_delete_rating(): void {
    global $wpdb;
    $rating_id = absint( $_POST['rating_id'] ?? 0 );
    if ( ! $rating_id ) {
        wp_send_json_error( [ 'message' => 'rating_id required.' ], 400 );
    }

    $rating = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, reviewer_type, subject_id FROM `{$wpdb->prefix}sd_ratings` WHERE id = %d LIMIT 1",
        $rating_id
    ), ARRAY_A );

    if ( ! $rating ) {
        wp_send_json_error( [ 'message' => 'Rating not found.' ], 404 );
    }

    $deleted = $wpdb->delete( $wpdb->prefix . 'sd_ratings', [ 'id' => $rating_id ], [ '%d' ] );
    if ( false === $deleted ) {
        wp_send_json_error( [ 'message' => 'Could not delete rating.' ] );
    }

    $reviewer_type = $rating['reviewer_type'];
    $subject_id    = (int) $rating['subject_id'];

    if ( $reviewer_type === 'customer' ) {
        $avg = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(rating) FROM `{$wpdb->prefix}sd_ratings` WHERE reviewer_type = 'customer' AND subject_id = %d",
            $subject_id
        ) );
        $wpdb->update( $wpdb->prefix . 'sd_drivers', [ 'rating' => round( $avg, 2 ) ], [ 'id' => $subject_id ], [ '%f' ], [ '%d' ] );
    } else {
        $avg = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(rating) FROM `{$wpdb->prefix}sd_ratings` WHERE reviewer_type = 'driver' AND subject_id = %d",
            $subject_id
        ) );
        $wpdb->update( $wpdb->prefix . 'sd_customers', [ 'rating' => round( $avg, 2 ) ], [ 'id' => $subject_id ], [ '%f' ], [ '%d' ] );
    }

    wp_send_json_success( [ 'message' => 'Rating deleted and averages recalculated.' ] );
}

function idibia_admin_flag_rating(): void {
    global $wpdb;
    $rating_id = absint( $_POST['rating_id'] ?? 0 );
    if ( ! $rating_id ) {
        wp_send_json_error( [ 'message' => 'rating_id required.' ], 400 );
    }

    $current = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT flagged FROM `{$wpdb->prefix}sd_ratings` WHERE id = %d LIMIT 1",
        $rating_id
    ) );

    $new_flag = $current ? 0 : 1;
    $updated  = $wpdb->update(
        $wpdb->prefix . 'sd_ratings',
        [ 'flagged' => $new_flag ],
        [ 'id' => $rating_id ],
        [ '%d' ],
        [ '%d' ]
    );

    if ( false === $updated ) {
        wp_send_json_error( [ 'message' => 'Could not update flag.' ] );
    }

    wp_send_json_success( [ 'message' => $new_flag ? 'Rating flagged for review.' : 'Flag removed.', 'flagged' => (bool) $new_flag ] );
}

function idibia_admin_get_subject_ratings(): void {
    global $wpdb;
    $subject_type = sanitize_key( $_GET['subject_type'] ?? '' );
    $subject_id   = absint( $_GET['subject_id'] ?? 0 );

    if ( ! $subject_id || ! in_array( $subject_type, [ 'driver', 'customer' ], true ) ) {
        wp_send_json_error( [ 'message' => 'subject_type (driver|customer) and subject_id are required.' ], 400 );
    }

    $reviewer_type = $subject_type === 'driver' ? 'customer' : 'driver';

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.id, r.trip_id, r.reviewer_id, r.reviewer_type, r.rating, r.comment, r.created_at, r.flagged,
            COALESCE(rd.full_name, rc.full_name) AS reviewer_name,
            t.trip_ref
        FROM `{$wpdb->prefix}sd_ratings` r
        LEFT JOIN `{$wpdb->prefix}sd_drivers`   rd ON rd.id = r.reviewer_id AND r.reviewer_type = 'driver'
        LEFT JOIN `{$wpdb->prefix}sd_customers` rc ON rc.id = r.reviewer_id AND r.reviewer_type = 'customer'
        LEFT JOIN `{$wpdb->prefix}sd_trips`      t  ON t.id = r.trip_id
        WHERE r.subject_id = %d AND r.reviewer_type = %s
        ORDER BY r.created_at DESC
        LIMIT 50",
        $subject_id,
        $reviewer_type
    ), ARRAY_A );

    $breakdown = array_fill( 1, 5, 0 );
    foreach ( $rows ?: [] as $row ) {
        $star = (int) $row['rating'];
        if ( isset( $breakdown[ $star ] ) ) {
            $breakdown[ $star ]++;
        }
    }

    $avg = count( $rows ) ? array_sum( array_column( $rows, 'rating' ) ) / count( $rows ) : 0;

    wp_send_json_success( [
        'ratings'   => $rows ?: [],
        'total'     => count( $rows ),
        'avg'       => round( $avg, 2 ),
        'breakdown' => $breakdown,
    ] );
}
