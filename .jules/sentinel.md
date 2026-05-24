## 2026-05-20 - Add CSRF Protection to Admin Endpoints
**Vulnerability:** Admin endpoints (`admin/api.php` and `admin.php`) were missing nonce (CSRF) verification for state-changing POST requests.
**Learning:** Even if endpoints check for authentication (`is_user_logged_in()`) and authorization (`current_user_can('manage_options')`), they remain vulnerable to CSRF without an explicit, per-session nonce validation. This could allow attackers to trick admins into executing arbitrary actions.
**Prevention:** Always generate a secure nonce via `wp_create_nonce()` on the frontend, include it in POST requests (via FormData or JSON), and strictly validate it server-side using `wp_verify_nonce()` before processing actions.
## 2026-05-24 - Missing CSRF Nonce Validation in Driver Wallet API
**Vulnerability:** The driver wallet API endpoints ( and  via POST) were executing without verifying a CSRF nonce, relying solely on cookie-based authentication, which left them vulnerable to Cross-Site Request Forgery.
**Learning:** Even internal API endpoints that correctly authenticate the user session () must validate a specific nonce on state-changing or sensitive data retrieval requests to ensure the request is intentionally initiated by the application frontend.
**Prevention:** Always verify a uniquely generated action nonce via `wp_verify_nonce()` in the API handler before fulfilling the request. Ensure the frontend correctly passes this nonce (e.g., via `URLSearchParams`).
## 2023-11-06 - Missing CSRF Nonce Validation in Driver Wallet API
**Vulnerability:** The driver wallet API endpoints (`get_wallet` and `request_payout` via POST) were executing without verifying a CSRF nonce, relying solely on cookie-based authentication, which left them vulnerable to Cross-Site Request Forgery.
**Learning:** Even internal API endpoints that correctly authenticate the user session (`$GLOBALS['auth_driver_id']`) must validate a specific nonce on state-changing or sensitive data retrieval requests to ensure the request is intentionally initiated by the application frontend.
**Prevention:** Always verify a uniquely generated action nonce via `wp_verify_nonce()` in the API handler before fulfilling the request. Ensure the frontend correctly passes this nonce (e.g., via `URLSearchParams`).
