with open('quote-api.php', 'r') as f:
    content = f.read()

old_fare = """// 3. Calculate fare
// Get platform settings or use defaults
$min_fare = (float) get_option( 'sd_min_fare', 800 );
$base_fare = 500;
$per_km_rates = [
    'bike' => 100,
    'keke' => 150,
    'car'  => 200,
    'van'  => 400
];

$rate_per_km = $per_km_rates[$vehicle] ?? 100;
$calculated_fare = $base_fare + ( $distance_km * $rate_per_km );
$final_fare = max( $min_fare, $calculated_fare );"""

new_fare = """// 3. Calculate fare
// Get platform settings or use defaults
$min_fare = (float) idibia_get_setting( 'min_fare', 800 );
$max_radius = (float) idibia_get_setting( 'max_delivery_radius_km', 50 );

if ( $distance_km > $max_radius ) {
    wp_send_json_error( [ 'message' => "The destination is too far. Our maximum delivery radius is {$max_radius}km." ] );
}

$base_fare = 500;
$per_km_rates = [
    'bike' => 100,
    'keke' => 150,
    'car'  => 200,
    'van'  => 400
];

$rate_per_km = $per_km_rates[$vehicle] ?? 100;
$calculated_fare = $base_fare + ( $distance_km * $rate_per_km );
$final_fare = max( $min_fare, $calculated_fare );"""

content = content.replace(old_fare, new_fare)

with open('quote-api.php', 'w') as f:
    f.write(content)
