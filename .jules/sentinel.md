## 2026-05-20 - Add CSRF Protection to Admin Endpoints
**Vulnerability:** Admin endpoints (`admin/api.php` and `admin.php`) were missing nonce (CSRF) verification for state-changing POST requests.
**Learning:** Even if endpoints check for authentication (`is_user_logged_in()`) and authorization (`current_user_can('manage_options')`), they remain vulnerable to CSRF without an explicit, per-session nonce validation. This could allow attackers to trick admins into executing arbitrary actions.
**Prevention:** Always generate a secure nonce via `wp_create_nonce()` on the frontend, include it in POST requests (via FormData or JSON), and strictly validate it server-side using `wp_verify_nonce()` before processing actions.
