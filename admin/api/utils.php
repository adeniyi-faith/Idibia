<?php
/** Idibia — Admin API Shared Utilities */

if ( ! function_exists( 'idibia_admin_audit_log' ) ) :
function idibia_admin_audit_log( string $action, string $entity_type, int $entity_id, array $metadata = [] ): void {
    global $wpdb;
    $table = $wpdb->prefix . 'sd_admin_audit_logs';
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `$table` (
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
    ) " . $wpdb->get_charset_collate() );
    $wpdb->insert( $table, [
        'admin_id'    => get_current_user_id(),
        'action'      => $action,
        'entity_type' => $entity_type,
        'entity_id'   => $entity_id,
        'metadata'    => wp_json_encode( $metadata ),
        'ip'          => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
        'user_agent'  => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ),
        'created_at'  => gmdate( 'Y-m-d H:i:s' ),
    ], [ '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ] );
}
endif;

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
