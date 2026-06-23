<?php
/** Idibia — Admin API: Admin Users & Role-Based Access Control */

// --- ADMIN USERS (RBAC) ---

function idibia_admin_get_roles() {
    if ( ! idibia_admin_has_permission('view_admin_users') ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
    }
    global $wpdb;

    // Fetch roles
    $roles = $wpdb->get_results( "SELECT id, name, description, is_system FROM `{$wpdb->prefix}sd_roles` ORDER BY id ASC", ARRAY_A );

    // Fetch permissions per role
    $role_perms = $wpdb->get_results( "SELECT role_id, permission FROM `{$wpdb->prefix}sd_role_permissions`", ARRAY_A );

    $perms_map = [];
    foreach($role_perms as $rp) {
        $perms_map[$rp['role_id']][] = $rp['permission'];
    }

    foreach($roles as &$role) {
        $role['permissions'] = $perms_map[$role['id']] ?? [];
    }

    wp_send_json_success( $roles );
}

function idibia_admin_get_my_permissions() {
    global $wpdb, $admin_id;
    $perms = [];

    if ( ! $admin_id ) {
        wp_send_json_error( ['message' => 'Not authenticated as admin'] );
    }

    $admin = $wpdb->get_row( $wpdb->prepare( "SELECT u.role_id, r.name as role_name FROM `{$wpdb->prefix}sd_admin_users` u JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id WHERE u.id = %d", $admin_id ) );
    if ( ! $admin ) wp_send_json_error( [ 'message' => 'Admin user not found.' ] );

    if ( $admin->role_name === 'Super Admin' ) {
        wp_send_json_success( ['is_super' => true, 'permissions' => []] );
    }

    $role_perms = $wpdb->get_col( $wpdb->prepare( "SELECT permission FROM `{$wpdb->prefix}sd_role_permissions` WHERE role_id = %d", $admin->role_id ) );

    $overrides = $wpdb->get_results( $wpdb->prepare( "SELECT permission, is_granted FROM `{$wpdb->prefix}sd_user_permission_overrides` WHERE admin_id = %d", $admin_id ) );
    $override_map = [];
    foreach ($overrides as $o) {
        $override_map[$o->permission] = (bool)$o->is_granted;
    }

    // We can just construct the final boolean map or return raw arrays
    wp_send_json_success( [
        'is_super' => false,
        'role_perms' => $role_perms,
        'overrides' => $override_map
    ] );
}

function idibia_admin_list_users() {
    if ( ! idibia_admin_has_permission('view_admin_users') ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
    }
    global $wpdb;

    $users = $wpdb->get_results( "
        SELECT u.id, u.full_name, u.email, u.avatar_path, u.status, u.last_login, u.created_at,
               r.id as role_id, r.name as role_name
        FROM `{$wpdb->prefix}sd_admin_users` u
        JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
        ORDER BY u.created_at DESC
    ", ARRAY_A );

    // Attach overrides for each user
    if ( ! empty($users) ) {
        $user_ids = array_column($users, 'id');
        $ids_sql = implode(',', array_map('intval', $user_ids));
        $overrides = $wpdb->get_results( "
            SELECT admin_id, permission, is_granted
            FROM `{$wpdb->prefix}sd_user_permission_overrides`
            WHERE admin_id IN ($ids_sql)
        ", ARRAY_A );

        $override_map = [];
        foreach($overrides as $o) {
            $override_map[$o['admin_id']][$o['permission']] = (bool)$o['is_granted'];
        }

        foreach($users as &$u) {
            $u['overrides'] = $override_map[$u['id']] ?? new stdClass();
        }
    }

    wp_send_json_success( $users );
}

function idibia_admin_create_user() {
    if ( ! idibia_admin_has_permission('create_admin_users') ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
    }
    global $wpdb;

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $full_name = sanitize_text_field( $data['full_name'] ?? '' );
    $email = sanitize_email( $data['email'] ?? '' );
    $password = $data['password'] ?? '';
    $role_id = (int) ($data['role_id'] ?? 0);
    $overrides = $data['overrides'] ?? []; // Associative array map of 'permission' => boolean

    if ( empty($full_name) || empty($email) || empty($password) || empty($role_id) ) {
        wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
    }

    if ( ! is_email($email) ) {
        wp_send_json_error( [ 'message' => 'Invalid email address.' ] );
    }

    // Check if role is Super Admin and prevent non-super admins from assigning it
    $role = $wpdb->get_row( $wpdb->prepare("SELECT name FROM `{$wpdb->prefix}sd_roles` WHERE id = %d", $role_id) );
    if ( $role && $role->name === 'Super Admin' && ! idibia_admin_has_permission('all') ) { // Assume only Super Admins effectively have 'all' permissions through the helper logic override
        // A rough check if they are Super Admin to assign Super Admin
        global $admin_id;
        $current_admin_id = $admin_id;

        $current_admin = $wpdb->get_row( $wpdb->prepare( "
            SELECT r.name
            FROM `{$wpdb->prefix}sd_admin_users` u
            JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
            WHERE u.id = %d
        ", $current_admin_id ) );

        if ( !$current_admin || $current_admin->name !== 'Super Admin' ) {
            wp_send_json_error( [ 'message' => 'Only Super Admins can create Super Admin accounts.' ], 403 );
        }
    }

    // Check email unique
    $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM `{$wpdb->prefix}sd_admin_users` WHERE email = %s", $email) );
    if ( $exists ) {
        wp_send_json_error( [ 'message' => 'Email already exists.' ] );
    }

    $password_hash = wp_hash_password($password);

    $wpdb->insert(
        "{$wpdb->prefix}sd_admin_users",
        [
            'full_name' => $full_name,
            'email' => $email,
            'password_hash' => $password_hash,
            'role_id' => $role_id,
            'force_password_change' => 1,
            'status' => 'active',
            'created_at' => gmdate('Y-m-d H:i:s')
        ],
        [ '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
    );

    $new_id = $wpdb->insert_id;

    if ( ! empty($overrides) ) {
        // Enforce that creator cannot grant permissions they do not possess
        $creator_has_all = idibia_admin_has_permission('all');
        foreach ( $overrides as $perm => $granted ) {
            $perm = sanitize_key($perm);
            if ( $granted && !$creator_has_all && !idibia_admin_has_permission($perm) ) {
                continue; // Skip granting permissions the creator lacks
            }
            $wpdb->insert(
                "{$wpdb->prefix}sd_user_permission_overrides",
                [
                    'admin_id' => $new_id,
                    'permission' => sanitize_key($perm),
                    'is_granted' => $granted ? 1 : 0
                ],
                [ '%d', '%s', '%d' ]
            );
        }
    }

    idibia_admin_audit_log('create', 'admin_user', $new_id, ['email' => $email, 'role_id' => $role_id]);

    wp_send_json_success( [ 'message' => 'Admin user created.', 'id' => $new_id ] );
}

function idibia_admin_update_user() {
    if ( ! idibia_admin_has_permission('edit_admin_users') ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
    }
    global $wpdb;

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $id = (int) ($data['id'] ?? 0);
    if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid ID.' ] );

    $full_name = sanitize_text_field( $data['full_name'] ?? '' );
    $email = sanitize_email( $data['email'] ?? '' );
    $role_id = (int) ($data['role_id'] ?? 0);
    $overrides = $data['overrides'] ?? [];
    $status = sanitize_key( $data['status'] ?? '' );

    // Validate target user
    $target = $wpdb->get_row( $wpdb->prepare("
        SELECT u.*, r.name as role_name
        FROM `{$wpdb->prefix}sd_admin_users` u
        JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
        WHERE u.id = %d
    ", $id) );

    if ( ! $target ) {
        wp_send_json_error( [ 'message' => 'User not found.' ] );
    }

    // Super admin protection
    global $admin_id;
    $current_admin_id = $admin_id;

    $current_admin = $wpdb->get_row( $wpdb->prepare( "
        SELECT r.name
        FROM `{$wpdb->prefix}sd_admin_users` u
        JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
        WHERE u.id = %d
    ", $current_admin_id ) );

    $is_current_super = ($current_admin && $current_admin->name === 'Super Admin') || current_user_can('manage_options');

    if ( $target->role_name === 'Super Admin' && ! $is_current_super ) {
        wp_send_json_error( [ 'message' => 'Cannot modify a Super Admin account.' ], 403 );
    }

    if ( ! empty($email) && $email !== $target->email ) {
        $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM `{$wpdb->prefix}sd_admin_users` WHERE email = %s AND id != %d", $email, $id) );
        if ( $exists ) wp_send_json_error( [ 'message' => 'Email already in use.' ] );
    }

    $update_data = [];
    $update_format = [];

    if ( ! empty($full_name) ) { $update_data['full_name'] = $full_name; $update_format[] = '%s'; }
    if ( ! empty($email) ) { $update_data['email'] = $email; $update_format[] = '%s'; }
    if ( ! empty($status) && in_array($status, ['active', 'inactive', 'suspended']) ) {
        $update_data['status'] = $status; $update_format[] = '%s';
    }
    if ( $role_id && $role_id !== (int)$target->role_id ) {
        // Prevent assigning Super Admin if not super admin
        if ( ! $is_current_super ) {
            $new_role = $wpdb->get_row( $wpdb->prepare("SELECT name FROM `{$wpdb->prefix}sd_roles` WHERE id = %d", $role_id) );
            if ( $new_role && $new_role->name === 'Super Admin' ) {
                wp_send_json_error( [ 'message' => 'Cannot assign Super Admin role.' ], 403 );
            }
        }
        $update_data['role_id'] = $role_id; $update_format[] = '%d';
    }

    if ( ! empty($update_data) ) {
        $update_data['updated_at'] = gmdate('Y-m-d H:i:s');
        $update_format[] = '%s';
        $wpdb->update( "{$wpdb->prefix}sd_admin_users", $update_data, ['id' => $id], $update_format, ['%d'] );
    }

    // Handle overrides update
    if ( isset($data['overrides']) ) {
        // Clear existing overrides
        $wpdb->delete( "{$wpdb->prefix}sd_user_permission_overrides", ['admin_id' => $id], ['%d'] );
        // Insert new ones
        $editor_has_all = idibia_admin_has_permission('all');
        foreach ( $overrides as $perm => $granted ) {
            $perm = sanitize_key($perm);
            if ( $granted && !$editor_has_all && !idibia_admin_has_permission($perm) ) {
                continue; // Skip granting permissions the editor lacks
            }
            $wpdb->insert(
                "{$wpdb->prefix}sd_user_permission_overrides",
                [
                    'admin_id' => $id,
                    'permission' => sanitize_key($perm),
                    'is_granted' => $granted ? 1 : 0
                ],
                [ '%d', '%s', '%d' ]
            );
        }
    }

    idibia_admin_audit_log('update', 'admin_user', $id, $update_data);
    wp_send_json_success( [ 'message' => 'User updated successfully.' ] );
}

function idibia_admin_suspend_user() {
    if ( ! idibia_admin_has_permission('suspend_delete_admin_users') ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
    }
    global $wpdb;

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id = (int) ($data['id'] ?? 0);
    $action_type = sanitize_key($data['action_type'] ?? 'suspend'); // 'suspend' or 'activate'

    if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid ID.' ] );

    $target = $wpdb->get_row( $wpdb->prepare("
        SELECT u.*, r.name as role_name
        FROM `{$wpdb->prefix}sd_admin_users` u
        JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
        WHERE u.id = %d
    ", $id) );

    if ( ! $target ) {
        wp_send_json_error( [ 'message' => 'User not found.' ] );
    }

    global $admin_id;
    $current_admin_id = $admin_id;
    if ( $id === $current_admin_id ) {
        wp_send_json_error( [ 'message' => 'You cannot suspend yourself.' ], 403 );
    }

    $current_admin = $wpdb->get_row( $wpdb->prepare( "
        SELECT r.name
        FROM `{$wpdb->prefix}sd_admin_users` u
        JOIN `{$wpdb->prefix}sd_roles` r ON u.role_id = r.id
        WHERE u.id = %d
    ", $current_admin_id ) );
    $is_current_super = ($current_admin && $current_admin->name === 'Super Admin') || current_user_can('manage_options');

    if ( $target->role_name === 'Super Admin' && ! $is_current_super ) {
        wp_send_json_error( [ 'message' => 'Cannot suspend a Super Admin.' ], 403 );
    }

    $new_status = $action_type === 'activate' ? 'active' : 'suspended';

    $wpdb->update(
        "{$wpdb->prefix}sd_admin_users",
        [ 'status' => $new_status, 'updated_at' => gmdate('Y-m-d H:i:s') ],
        [ 'id' => $id ],
        [ '%s', '%s' ],
        [ '%d' ]
    );

    idibia_admin_audit_log($action_type, 'admin_user', $id);
    wp_send_json_success( [ 'message' => "User $action_type successful." ] );
}
