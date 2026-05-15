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
