<?php
function wp_remote_get($url, $args=[]) {
    if (strpos($url, 'nominatim') !== false) {
        return ['response' => ['code' => 200], 'body' => json_encode([['lat' => 6.5, 'lon' => 3.3]])];
    }
    if (strpos($url, 'openrouteservice') !== false) {
        return ['response' => ['code' => 200], 'body' => json_encode(['features' => [['properties' => ['summary' => ['distance' => 5000, 'duration' => 600]]]]])];
    }
    return new WP_Error();
}
function wp_remote_retrieve_response_code($res) { return $res['response']['code'] ?? 500; }
function wp_remote_retrieve_body($res) { return $res['body'] ?? ''; }
function is_wp_error($obj) { return $obj instanceof WP_Error; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function idibia_get_setting($k, $d) { return $d; }

class WP_Error {
    public $code, $message;
    public function __construct($code='', $message='') { $this->code = $code; $this->message = $message; }
}

define('IDIBIA_NOMINATIM_URL', 'mock-nominatim');
define('IDIBIA_ORS_URL', 'mock-ors');
define('IDIBIA_ORS_API_KEY', 'ORS_API_KEY_REPLACE_ME');

define('ABSPATH', 1);

require_once __DIR__ . '/../wp/wp-content/mu-plugins/idibia-map-helpers.php';

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

$geo = idibia_geocode_address('Lagos');
assert_equals(6.5, $geo['lat'] ?? 0, 'Geocode returns lat');
assert_equals(3.3, $geo['lng'] ?? 0, 'Geocode returns lng');

$route = idibia_calculate_route(6.5, 3.3, 6.6, 3.4);
assert_equals(true, isset($route['distance_km']), 'Route has distance');
assert_equals(true, isset($route['duration_mins']), 'Route has duration');

if ($failed > 0) {
    echo "$failed tests failed\n";
    exit(1);
} else {
    echo "All tests passed\n";
    exit(0);
}
