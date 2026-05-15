<?php

// Mock WP and DB
$mock_options = [];
function get_option($k, $d=false) { global $mock_options; return $mock_options[$k] ?? $d; }
function wp_json_encode($data) { return json_encode($data); }
function current_time($type) { return gmdate('Y-m-d H:i:s'); }
define('ABSPATH', 1);
define('ARRAY_A', 'ARRAY_A');

class MockWPDB {
    public $prefix = 'wp_';
    public $queries = [];
    public $mock_results = [];

    public function prepare($query, ...$args) { return vsprintf(str_replace('%d', '%s', str_replace('%f', '%s', $query)), $args); }
    public function insert($t, $d, $f) { $this->queries[] = "INSERT $t"; return 1; }
    public function update($t, $d, $w, $f, $wf) { $this->queries[] = "UPDATE $t"; return 1; }
    public function query($q) { $this->queries[] = $q; return true; }

    public function get_results($q, $type = '') {
        $this->queries[] = $q;
        return $this->mock_results;
    }
    public function get_row($q, $type = '') {
        $this->queries[] = $q;
        return isset($this->mock_results[0]) ? (object)$this->mock_results[0] : null;
    }
}
$wpdb = new MockWPDB();

// Mock helpers
function idibia_transaction_start() {}
function idibia_transaction_commit() {}
function idibia_transaction_rollback() {}
function idibia_log_event() { return true; }

require_once __DIR__ . '/../wp/wp-content/mu-plugins/idibia-dispatch-helpers.php';

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

// 1. Test finding candidates (empty)
$wpdb->mock_results = [];
$c = idibia_find_dispatch_candidates(1, 'bike', 6.5, 3.3);
assert_equals(0, count($c), 'No candidates returns empty array');

// 2. Test finding candidates (found)
$wpdb->mock_results = [['driver_id' => 99, 'lat' => 6.51, 'lng' => 3.31, 'distance' => 2.5]];
$c = idibia_find_dispatch_candidates(1, 'bike', 6.5, 3.3);
assert_equals(1, count($c), 'Candidates returned correctly');
assert_equals(99, $c[0]['driver_id'], 'Correct driver ID returned');

// 3. Test creating offer
$wpdb->queries = [];
$res = idibia_create_dispatch_offer(10, 99);
assert_equals(true, $res, 'Offer creation successful');
$has_insert = false;
$has_update = false;
foreach ($wpdb->queries as $q) {
    if (strpos($q, 'INSERT wp_sd_dispatch_offers') !== false) $has_insert = true;
    if (strpos($q, 'UPDATE wp_sd_trips') !== false) $has_update = true;
}
assert_equals(true, $has_insert, 'Inserted into offers table');
assert_equals(true, $has_update, 'Updated trip status');

if ($failed > 0) {
    echo "$failed tests failed\n";
    exit(1);
} else {
    echo "All tests passed\n";
    exit(0);
}
