1. **Add `data-setting` attributes in `admin.php` for all settings**
   - The admin UI has fields for pricing (commission, min fare, etc.), KYC policies, and notification policies that are currently hardcoded or missing `data-setting` attributes. I will update `admin.php` to add these attributes to all input fields, matching the keys in `sd_settings` (e.g., `platform_commission_pct`, `surge_multiplier_cap`, `min_fare`, `max_delivery_radius_km`, `kyc_auto_flag_blurry`, `notif_kyc_queue`, etc.).

2. **Implement `idibia_payment_settings` and `idibia_payment_public_payload`**
   - Define these functions in `wp/wp-content/mu-plugins/idibia-helpers.php`.
   - `idibia_payment_settings()` should read the payment-related rows from `sd_settings` and structure them (masking secret keys).
   - `idibia_payment_public_payload($trip_id)` should return the payment settings safe for public consumption (e.g., bank details, instructions) by fetching them from `sd_settings`.

3. **Update `quote-api.php` to use dynamic pricing settings**
   - Replace the hardcoded `min_fare` default and retrieve `min_fare`, `max_delivery_radius_km`, etc., using `get_option` or querying `sd_settings` directly using a new helper function or `wpdb` to ensure quotes respect the admin configuration.
   - For `sd_settings`, since it's a custom table, I need a helper `idibia_get_setting($key, $default)` in `idibia-helpers.php` to fetch values easily. I will implement this function.

4. **Mask secrets in responses**
   - In `idibia_payment_settings()` or `api.php`, ensure `paystack_secret_key` and `flutterwave_secret_key` are masked when sent to the frontend `get_settings` action. The save endpoint should check if the secret is masked (e.g. `********`) and ignore updating it if it hasn't changed.

5. **Ensure field validation in `idibia_admin_save_settings`**
   - Add validation logic in `admin/api.php` -> `idibia_admin_save_settings()` to reject invalid pricing values (e.g., negative commission or fare) and return field-level error messages. Ensure secret keys left blank or masked aren't overwritten.

6. **Pre-commit and submit**
   - Run pre-commit instructions, test, and submit.
