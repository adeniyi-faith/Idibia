with open('quote-api.php', 'r') as f:
    content = f.read()

# We need to make rates dynamic. Wait, what about the commission? The prompt says: "Quote pricing uses configured commission/minimum fare/rates/radius."
# The commission should be used. However, quote pricing only shows `fare`. The `commission` is actually `platform_pct` in `idibia-dispatch-helpers.php` perhaps. Let's look at `quote-api.php` again.

# Let's replace the hardcoded per_km_rates and base_fare with `idibia_get_setting` calls.

old_fare = """$base_fare = 500;
$per_km_rates = [
    'bike' => 100,
    'keke' => 150,
    'car'  => 200,
    'van'  => 400
];

$rate_per_km = $per_km_rates[$vehicle] ?? 100;
$calculated_fare = $base_fare + ( $distance_km * $rate_per_km );"""

new_fare = """$base_fare = (float) idibia_get_setting( 'base_fare', 500 );
$rate_per_km = (float) idibia_get_setting( 'rate_per_km_' . $vehicle, [
    'bike' => 100,
    'keke' => 150,
    'car'  => 200,
    'van'  => 400
][$vehicle] ?? 100 );

$calculated_fare = $base_fare + ( $distance_km * $rate_per_km );

// Get the commission, we might pass it as a parameter, or maybe we don't need to add it to the quote if the quote represents total fare that includes commission anyway.
$commission_pct = (int) idibia_get_setting( 'platform_commission_pct', 20 );"""

content = content.replace(old_fare, new_fare)

# We also need to store platform_pct in quote data so that when it's booked, the right commission is assigned.
# Wait, let's look at the quote_data array.

old_array = """    'distance_km'    => $distance_km,
    'duration_mins'  => $duration_mins,
    'fare_estimate'  => $final_fare,
    'created_at'     => time()"""

new_array = """    'distance_km'    => $distance_km,
    'duration_mins'  => $duration_mins,
    'fare_estimate'  => $final_fare,
    'platform_pct'   => $commission_pct,
    'created_at'     => time()"""

content = content.replace(old_array, new_array)

with open('quote-api.php', 'w') as f:
    f.write(content)
