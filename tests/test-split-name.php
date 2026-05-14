<?php
/**
 * Test script for idibia_split_full_name function.
 */

// Mock WordPress environment
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
        // Mock implementation
    }
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}

// Load the file containing the function to test
require_once __DIR__ . '/../wp-auth-config.php';

/**
 * Basic assertion function
 */
function assert_equals( $expected, $actual, $message = '' ) {
    if ( $expected !== $actual ) {
        echo "FAIL: $message\n";
        echo "  Expected: " . json_encode( $expected ) . "\n";
        echo "  Actual:   " . json_encode( $actual ) . "\n";
        return false;
    }
    echo "PASS: $message\n";
    return true;
}

$test_cases = [
    'Standard name' => [
        'input'    => 'John Doe',
        'expected' => [ 'John', 'Doe' ],
    ],
    'Name with middle name' => [
        'input'    => 'John Middle Doe',
        'expected' => [ 'John', 'Middle Doe' ],
    ],
    'Single name' => [
        'input'    => 'John',
        'expected' => [ 'John', '' ],
    ],
    'Empty string' => [
        'input'    => '',
        'expected' => [ '', '' ],
    ],
    'Whitespace only' => [
        'input'    => '   ',
        'expected' => [ '', '' ],
    ],
    'Extra whitespace' => [
        'input'    => '  John   Doe  ',
        'expected' => [ 'John', 'Doe' ],
    ],
    'Multi-part last name' => [
        'input'    => 'John Van der Woodsen',
        'expected' => [ 'John', 'Van der Woodsen' ],
    ],
];

$failed = 0;
foreach ( $test_cases as $description => $data ) {
    $result = idibia_split_full_name( $data['input'] );
    if ( ! assert_equals( $data['expected'], $result, $description ) ) {
        $failed++;
    }
}

if ( $failed > 0 ) {
    echo "\n$failed tests failed!\n";
    exit( 1 );
} else {
    echo "\nAll tests passed!\n";
    exit( 0 );
}
