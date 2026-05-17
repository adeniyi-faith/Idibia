<?php
/**
 * Plugin Name: Idibia Configuration
 * Description: Stores global configuration constants.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Map Providers Configuration ──────────────────────────────────────────

// Nominatim Geocoding API URL
if ( ! defined( 'IDIBIA_NOMINATIM_URL' ) ) {
    define( 'IDIBIA_NOMINATIM_URL', 'https://nominatim.openstreetmap.org/search' );
}

// OpenRouteService Directions API URL
if ( ! defined( 'IDIBIA_ORS_URL' ) ) {
    define( 'IDIBIA_ORS_URL', 'https://api.openrouteservice.org/v2/directions/driving-car' );
}

// OpenRouteService API Key
if ( ! defined( 'IDIBIA_ORS_API_KEY' ) ) {
    define( 'IDIBIA_ORS_API_KEY', 'ORS_API_KEY_REPLACE_ME' );
}


// ── Pusher Realtime Configuration ────────────────────────────────────────
// Replace these placeholders before enabling realtime in production.
if ( ! defined( 'IDIBIA_PUSHER_APP_ID' ) ) {
    define( 'IDIBIA_PUSHER_APP_ID', 'PUSHER_APP_ID_REPLACE_ME' );
}

if ( ! defined( 'IDIBIA_PUSHER_KEY' ) ) {
    define( 'IDIBIA_PUSHER_KEY', 'PUSHER_KEY_REPLACE_ME' );
}

if ( ! defined( 'IDIBIA_PUSHER_SECRET' ) ) {
    define( 'IDIBIA_PUSHER_SECRET', 'PUSHER_SECRET_REPLACE_ME' );
}

if ( ! defined( 'IDIBIA_PUSHER_CLUSTER' ) ) {
    define( 'IDIBIA_PUSHER_CLUSTER', 'PUSHER_CLUSTER_REPLACE_ME' );
}
