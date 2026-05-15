<?php

// Mock enough of WP
$mock_transients = [];
function set_transient($transient, $value, $expiration = 0) {
    global $mock_transients;
    $mock_transients[$transient] = $value;
}
function get_transient($transient) {
    global $mock_transients;
    return $mock_transients[$transient] ?? false;
}
function is_user_logged_in() { return true; }
function get_current_user_id() { return 123; }
function get_user_meta($user_id, $key, $single) {
    if ($key === 'idibia_account_type') return 'customer';
    if ($key === 'idibia_customer_id') return 456;
    return false;
}
function wp_json_encode($data) { return json_encode($data); }

class MockWPDB {
    public $prefix = 'wp_';
    public $queries = [];
    public function insert($table, $data, $format) {
        $this->queries[] = "INSERT INTO $table";
        return 1;
    }
    public function query($query) {
        $this->queries[] = $query;
        return true;
    }
}
$wpdb = new MockWPDB();

define('ABSPATH', __DIR__ . '/../');

// Define idibia_json_response as a no-op so the require doesn't fail if we can't redefine it
// but we'll mock the exit so we don't actually exit
// Actually, it's safer to just require the file. It only defines functions.

require_once __DIR__ . '/../wp/wp-content/mu-plugins/idibia-helpers.php';

$failed = 0;

function assert_equals($expected, $actual, $message) {
    global $failed;
    if ($expected !== $actual) {
        echo "FAIL: $message\n";
        echo "  Expected: " . json_encode($expected) . "\n";
        echo "  Actual:   " . json_encode($actual) . "\n";
        $failed++;
        return false;
    }
    echo "PASS: $message\n";
    return true;
}

// Test idibia_get_current_actor
assert_equals(456, idibia_get_current_actor('customer'), 'Actor customer returns profile id');
assert_equals(0, idibia_get_current_actor('driver'), 'Actor driver returns 0 for customer login');

// Test idibia_log_event
$log_result = idibia_log_event(1, 'test_event', ['foo' => 'bar']);
assert_equals(true, $log_result, 'idibia_log_event returns true');
assert_equals("INSERT INTO wp_sd_trip_events", $wpdb->queries[0], 'idibia_log_event inserts row');

// Test idibia_check_rate_limit
assert_equals(true, idibia_check_rate_limit('login', '127.0.0.1', 2), 'Rate limit attempt 1');
assert_equals(true, idibia_check_rate_limit('login', '127.0.0.1', 2), 'Rate limit attempt 2');
assert_equals(false, idibia_check_rate_limit('login', '127.0.0.1', 2), 'Rate limit attempt 3 (should fail)');

if ($failed > 0) {
    echo "$failed tests failed\n";
    exit(1);
} else {
    echo "All tests passed\n";
    exit(0);
}
