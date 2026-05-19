<?php
/** Idibia — Public Tracking View */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/wp/wp-content/mu-plugins/idibia-dispatch-helpers.php';
$trip_id = (int) $row->trip_id;

$trip = $wpdb->get_row( $wpdb->prepare(
    "SELECT t.*, c.first_name AS customer_name
     FROM `{$wpdb->prefix}sd_trips` t
     LEFT JOIN `{$wpdb->prefix}sd_customers` c ON c.id = t.customer_id
     WHERE t.id = %d LIMIT 1",
    $trip_id
), ARRAY_A );

if ( ! $trip ) {
    die('Trip not found.');
}

$driver_name = 'Pending';
$driver_vehicle = 'Pending';
if ( ! empty( $trip['driver_id'] ) ) {
    $driver = $wpdb->get_row( $wpdb->prepare( "SELECT first_name, vehicle_type, vehicle_plate FROM `{$wpdb->prefix}sd_drivers` WHERE id = %d LIMIT 1", $trip['driver_id'] ), ARRAY_A );
    if ( $driver ) {
        $driver_name = $driver['first_name'];
        $driver_vehicle = $driver['vehicle_type'] . ' (' . $driver['vehicle_plate'] . ')';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=0">
<title>Idibia — Track Package</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<style>
body { background: var(--surface); display:flex; flex-direction:column; height: 100dvh; margin:0; }
.tracking-header { padding: 20px; background: var(--white); box-shadow: var(--shadow-sm); z-index: 10;}
.map-container { flex: 1; min-height: 200px; z-index: 1; }
.info-panel { background: var(--white); padding: 20px; box-shadow: var(--shadow-md); z-index: 10; border-top-left-radius: 20px; border-top-right-radius: 20px; margin-top: -20px;}
</style>
</head>
<body>
<div class="tracking-header">
    <h3 style="margin:0;">Live Tracking: <?php echo esc_html($trip['trip_ref']); ?></h3>
    <p style="margin: 4px 0 0; color: var(--text-secondary); font-size: 14px;">Package for <?php echo esc_html($trip['customer_name']); ?></p>
</div>
<div id="map" class="map-container"></div>
<div class="info-panel">
    <div style="display:flex; justify-content:space-between; margin-bottom: 15px;">
        <div>
            <div style="font-size: 12px; color: var(--text-muted); font-weight:700; text-transform:uppercase;">Status</div>
            <div style="font-weight: 600; font-size: 16px; color: var(--text-primary);"><?php echo esc_html(ucfirst(str_replace('_', ' ', $trip['dispatch_status']))); ?></div>
        </div>
        <div style="text-align:right;">
            <div style="font-size: 12px; color: var(--text-muted); font-weight:700; text-transform:uppercase;">Driver</div>
            <div style="font-weight: 600; font-size: 16px; color: var(--text-primary);"><?php echo esc_html($driver_name); ?></div>
        </div>
    </div>

    <div style="margin-bottom: 10px;">
        <div style="font-size: 12px; color: var(--text-muted); font-weight:700; text-transform:uppercase;">Pickup</div>
        <div style="font-weight: 500; font-size: 14px;"><?php echo esc_html($trip['pickup_address'] ?: $trip['pickup']); ?></div>
    </div>
    <div>
        <div style="font-size: 12px; color: var(--text-muted); font-weight:700; text-transform:uppercase;">Drop-off</div>
        <div style="font-weight: 500; font-size: 14px;"><?php echo esc_html($trip['dropoff_address'] ?: $trip['dropoff']); ?></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const lat = <?php echo (float) ($trip['pickup_lat'] ?? 6.5244); ?>;
    const lng = <?php echo (float) ($trip['pickup_lng'] ?? 3.3792); ?>;

    const map = L.map('map', { zoomControl: false }).setView([lat, lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    <?php if ($trip['pickup_lat'] && $trip['dropoff_lat']): ?>
    L.polyline([
        [<?php echo (float) $trip['pickup_lat']; ?>, <?php echo (float) $trip['pickup_lng']; ?>],
        [<?php echo (float) $trip['dropoff_lat']; ?>, <?php echo (float) $trip['dropoff_lng']; ?>]
    ], {color: 'var(--navy)', weight: 5, opacity: 0.7}).addTo(map);
    <?php endif; ?>
});
</script>
</body>
</html>
