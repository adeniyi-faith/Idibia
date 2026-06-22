<?php
/** Idibia — Admin API Shared Utilities */

if ( ! function_exists( 'idibia_admin_audit_log' ) ) :
/**
 * Write one row to the admin audit log.
 *
 * @param string     $action       Action name, e.g. 'save_settings', 'process_payout'.
 * @param string     $entity_type  What kind of thing was changed, e.g. 'settings', 'payout'.
 * @param int        $entity_id    The ID of that thing (0 if not applicable).
 * @param array      $metadata     Any extra data you want to record.
 * @param array|null $before_state Snapshot of the record BEFORE the change.
 * @param array|null $after_state  Snapshot of the record AFTER the change.
 */
function idibia_admin_audit_log(
    string $action,
    string $entity_type,
    int $entity_id,
    array $metadata = [],
    ?array $before_state = null,
    ?array $after_state  = null
): void {
    global $wpdb, $admin_id;
    $table = $wpdb->prefix . 'sd_admin_audit_logs';

    // Ensure the table (and the new columns) exist even if the migration hasn't run yet.
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `$table` (
        `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `admin_id`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `action`       VARCHAR(80)     NOT NULL,
        `entity_type`  VARCHAR(80)     NOT NULL,
        `entity_id`    BIGINT UNSIGNED NULL,
        `metadata`     LONGTEXT        NULL,
        `before_state` LONGTEXT        NULL,
        `after_state`  LONGTEXT        NULL,
        `ip`           VARCHAR(45)     NULL,
        `user_agent`   VARCHAR(255)    NULL,
        `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `action`    (`action`),
        KEY `entity`    (`entity_type`, `entity_id`),
        KEY `created_at`(`created_at`)
    ) " . $wpdb->get_charset_collate() );

    // Use the Idibia custom admin ID first; fall back to the WP user ID.
    $resolved_admin_id = ( isset( $admin_id ) && $admin_id > 0 )
        ? (int) $admin_id
        : get_current_user_id();

    $wpdb->insert( $table, [
        'admin_id'     => $resolved_admin_id,
        'action'       => $action,
        'entity_type'  => $entity_type,
        'entity_id'    => $entity_id,
        'metadata'     => wp_json_encode( $metadata ),
        'before_state' => $before_state !== null ? wp_json_encode( $before_state ) : null,
        'after_state'  => $after_state  !== null ? wp_json_encode( $after_state )  : null,
        'ip'           => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR']     ?? '' ) ),
        'user_agent'   => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ),
        'created_at'   => gmdate( 'Y-m-d H:i:s' ),
    ], [ '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ] );
}
endif;

/**
 * Return a paginated, filtered view of the audit log.
 * Requires 'view_admin_users' permission (checked in the router).
 */
function idibia_admin_get_audit_log(): void {
    global $wpdb;

    [ $page, $per_page, $offset ] = idibia_page_args();

    $admin_filter       = absint( $_GET['admin_id']     ?? 0 );
    $action_filter      = sanitize_key( $_GET['action_filter'] ?? '' );
    $entity_type_filter = sanitize_key( $_GET['entity_type']   ?? '' );
    $date_from          = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) );
    $date_to            = sanitize_text_field( wp_unslash( $_GET['date_to']   ?? '' ) );

    $where = [ '1=1' ];
    $args  = [];

    if ( $admin_filter ) {
        $where[] = 'a.admin_id = %d';
        $args[]  = $admin_filter;
    }
    if ( $action_filter ) {
        $where[] = 'a.action = %s';
        $args[]  = $action_filter;
    }
    if ( $entity_type_filter ) {
        $where[] = 'a.entity_type = %s';
        $args[]  = $entity_type_filter;
    }
    if ( $date_from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
        $where[] = 'DATE(a.created_at) >= %s';
        $args[]  = $date_from;
    }
    if ( $date_to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
        $where[] = 'DATE(a.created_at) <= %s';
        $args[]  = $date_to;
    }

    $sql_where = implode( ' AND ', $where );
    $table     = $wpdb->prefix . 'sd_admin_audit_logs';

    $total = (int) $wpdb->get_var( idibia_sql(
        "SELECT COUNT(*) FROM `$table` a WHERE $sql_where",
        $args
    ) );

    $rows = $wpdb->get_results( idibia_sql(
        "SELECT a.*, u.full_name AS admin_name
         FROM `$table` a
         LEFT JOIN `{$wpdb->prefix}sd_admin_users` u ON u.id = a.admin_id
         WHERE $sql_where
         ORDER BY a.created_at DESC
         LIMIT %d OFFSET %d",
        array_merge( $args, [ $per_page, $offset ] )
    ), ARRAY_A ) ?: [];

    foreach ( $rows as &$row ) {
        $row['id']        = (int) $row['id'];
        $row['admin_id']  = (int) $row['admin_id'];
        $row['entity_id'] = (int) $row['entity_id'];
        foreach ( [ 'metadata', 'before_state', 'after_state' ] as $col ) {
            if ( ! empty( $row[ $col ] ) ) {
                $row[ $col ] = json_decode( $row[ $col ], true );
            }
        }
    }
    unset( $row );

    wp_send_json_success( [
        'logs'     => $rows,
        'page'     => $page,
        'per_page' => $per_page,
        'total'    => $total,
    ] );
}

function idibia_require_method( string $method ): void {
    if ( $_SERVER['REQUEST_METHOD'] !== $method ) {
        http_response_code( 405 );
        wp_send_json_error( [ 'message' => 'Method not allowed.' ] );
    }
}

function idibia_sql( string $sql, array $args = [] ): string {
    global $wpdb;
    return $args ? $wpdb->prepare( $sql, $args ) : $sql;
}

function idibia_page_args(): array {
    $page     = max( 1, absint( $_GET['page'] ?? 1 ) );
    $per_page = min( 100, max( 1, absint( $_GET['per_page'] ?? 20 ) ) );
    return [ $page, $per_page, ( $page - 1 ) * $per_page ];
}
