<?php

// Mock enough of WP to run our plugin code directly.
$mock_options = [
    'idibia_db_version' => 0,
    'idibia_tables_v1'  => false,
    'idibia_tables_v2'  => false,
];
$queries_run = [];

function get_option($key, $default = false) {
    global $mock_options;
    return $mock_options[$key] ?? $default;
}

function update_option($key, $value) {
    global $mock_options;
    $mock_options[$key] = $value;
}

function add_action($hook, $callback) { }

class MockWPDB {
    public $prefix = 'wp_';
    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
    public function query($query) {
        global $queries_run;
        $queries_run[] = $query;
        return true;
    }
}
$wpdb = new MockWPDB();

define('ABSPATH', __DIR__ . '/../');

require_once __DIR__ . '/../wp/wp-content/mu-plugins/idibia-core.php';

// Trigger the migration
idibia_maybe_create_tables();

// Check results
$expected_tables = [
    'wp_sd_trip_events',
    'wp_sd_driver_locations',
    'wp_sd_dispatch_offers',
    'wp_sd_payments',
    'wp_sd_wallet_ledger',
    'wp_sd_payouts',
    'wp_sd_ratings',
    'wp_sd_notifications',
    'wp_sd_support_tickets',
    'wp_sd_support_messages',
    'wp_sd_uploaded_evidence'
];

$tables_created = 0;
$alter_trips_found = false;

foreach ($queries_run as $q) {
    if (strpos($q, 'ALTER TABLE `wp_sd_trips`') !== false) {
        $alter_trips_found = true;
    }
    foreach ($expected_tables as $table) {
        if (strpos($q, "CREATE TABLE IF NOT EXISTS `$table`") !== false) {
            $tables_created++;
        }
    }
}

echo "Version set to: " . get_option('idibia_db_version') . "\n";
echo "ALTER TABLE wp_sd_trips found: " . ($alter_trips_found ? "YES" : "NO") . "\n";
echo "New lifecycle tables created: $tables_created / " . count($expected_tables) . "\n";

if ($alter_trips_found && $tables_created === count($expected_tables) && get_option('idibia_db_version') === 5) {
    echo "SUCCESS\n";
    exit(0);
} else {
    echo "FAILED\n";
    exit(1);
}
