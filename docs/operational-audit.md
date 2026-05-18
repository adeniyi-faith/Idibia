# Idibia Operational Completion Audit

This audit is grounded in the current repository state. It does not assume a greenfield rebuild. The platform already has a WordPress/PHP backend, custom WordPress mu-plugin tables, customer booking, driver dispatch, manual payment proof review, ratings, support-ticket creation, and an admin shell. The remaining work is primarily about completing the missing end-to-end wiring, replacing visual placeholders with real operations, and hardening production paths.

## 1. Executive system status

Idibia is partially operational, not production-complete. The strongest completed areas are the data schema, basic customer and driver authentication, quote creation, booking, dispatch offers, driver state transitions, manual transfer proof upload/review, trip feed polling, and support/rating submission. The weakest areas are admin operations coverage, payout execution, revenue analytics, legal/compliance controls, notification management, real-time deployment configuration, social/password-reset auth, operational reporting, and polished dashboard states.

Approximate completion by dimension:

- **Technical:** 55%. Core tables, API endpoints, transactions, nonces, rate limits, Pusher abstractions, uploads, and polling exist. Production gaps remain in gateway payments, payout rails, background jobs, export/reporting, policy storage, password reset, social auth, stale-driver handling, queue retries, and full admin audit coverage.
- **Functional:** 50%. A user can register, quote, book, upload proof, be dispatched to a driver, track status, open support, and rate a completed trip. Admins can review KYC, list real trips/drivers/customers/disputes/payouts, save payment settings, and review manual payments. Many visible controls still only toast, toggle CSS, or show static numbers.
- **Logical:** 45%. Happy paths exist, but important operational sequences are incomplete: no-driver recovery, driver cancellation/reassignment, proof-of-delivery upload, customer cancellation, scheduled trip dispatch, payout creation/execution, refund execution, password reset, social sign-in, and masked calling.
- **UI/UX:** 45%. The three dashboards are visually broad but unevenly wired. Loading states exist in several sections, but many empty/error states are thin, several labels are static, and multiple buttons present capabilities that are not implemented.

## 2. Repository-grounded technical audit

### 2.1 Architecture and database

The backend is a WordPress/PHP application using custom mu-plugin tables. The migration target is version 5, and the schema includes trips, dispatch offers, driver locations, payments, wallet ledger, payouts, admin audit logs, ratings, notifications, support tickets/messages, uploaded evidence, customers, sessions, drivers, driver sessions, disputes, and settings. This is a solid starting point, but many tables are ahead of the application layer.

Working pieces:

- Custom schema creation and migrations exist.
- Trip lifecycle, dispatch offers, payments, wallet ledger, payouts, ratings, support, disputes, notifications, and settings have tables.
- Shared helpers handle JSON cleanup, actor lookup, trip events, transactions, rate limiting, Pusher configuration, Pusher broadcasts, notifications, uploads, and wallet crediting.

Gaps:

- The migration code uses raw `ALTER TABLE` statements in some places without defensive checks for every added column. Existing installs can fail if a partially migrated table already has one column but not others.
- There are no declared foreign keys, so application code must enforce referential integrity everywhere.
- Some admin-only tables are created lazily from API code as well as migrations, which makes table ownership unclear.
- There is no background worker/cron layer for expiring offers, retrying dispatch after `no_driver`, processing payouts, sending notification digests, escalating support tickets, or stale GPS cleanup.
- Settings exist, but only a subset of the visible settings UI persists to the settings table.

### 2.2 Authentication, identity, and security

Working pieces:

- Customer and driver registration/login/verification handlers exist.
- Customer and driver APIs check WordPress login and account type through `auth-helper.php`.
- Several API endpoints verify nonces and rate-limit sensitive operations.
- Driver heartbeat and trip action endpoints require driver authentication and driver action nonces.
- Pusher private channels are authenticated and scoped to the relevant driver or trip.

Gaps:

- Customer login fields are prefilled with demo credentials in the UI, which is unsafe for production and makes the experience look demo-like.
- Customer and driver “forgot password” controls are toast-only and do not connect to a password reset backend.
- Customer social login buttons route to the normal login function instead of Google/Apple OAuth.
- OTP delivery is not a real SMS flow; email verification is present, while phone/SMS promises are not fully implemented.
- Admin logout is toast-only in the visible admin navigation and does not call the existing logout endpoint.
- Admin global search and notification read state are UI-only.

### 2.3 Maps, quotes, and booking

Working pieces:

- Quote API geocodes pickup/drop-off, calculates route distance/duration, prices by vehicle type, stores a five-minute transient quote, and returns a quote ID.
- Booking requires a valid quote, creates a trip, creates a manual-transfer payment record, logs the trip, notifies participants, and starts dispatch.
- Customer trip history API returns the customer’s trips.

Gaps:

- The quote is transient-only; expired quotes are unrecoverable and not visible to admin support.
- Scheduled pickup UI exists, but scheduling is local UI state only and is not submitted into quote/booking APIs.
- Address entry lacks robust autocomplete/select-from-map UX. The backend geocodes plain text, which can produce ambiguous pickup/drop-off points.
- The booking flow has no payment authorization gate before dispatch. Current production behavior dispatches before payment proof approval unless operations decide manual-transfer pending is acceptable.
- No customer cancellation endpoint exists despite the trip feed advertising cancel capability for searching/offered/accepted trips.

### 2.4 Dispatch, driver offers, and trip state machine

Working pieces:

- Dispatch selects active, approved, online drivers by vehicle type, excludes drivers with active trips, creates offer rows, and moves the trip from searching to offered or no_driver.
- Driver offer acceptance runs inside a transaction, prevents already-active drivers from taking another trip, assigns the trip atomically, expires competing offers, logs events, and notifies participants.
- Driver actions advance the trip through arriving, arrived_pickup, picked_up, arrived_dropoff, and completed states.
- Completion checks the delivery PIN when one exists and credits the driver only after captured payment.

Gaps:

- There is no customer-side cancellation or admin reassignment flow.
- There is no driver-side cancel/abort/emergency offload after acceptance.
- `no_driver` is a terminal-feeling state unless another process manually redispatches; no scheduled retry/backoff loop is present.
- Offer expiry is helper-driven but not backed by a durable scheduler, so expired offers depend on API traffic.
- Proof-of-delivery database fields exist, but the driver UI/API do not upload or require proof-of-delivery files.
- Completion is allowed after `picked_up` or `arrived_dropoff`, which may be intentional, but it bypasses a strict “arrived at drop-off first” requirement.

### 2.5 Real-time and notifications

Working pieces:

- Pusher configuration, server-side trigger signing, public browser configuration, private trip and private driver channels, and channel auth exist.
- Customer and driver dashboards subscribe to Pusher when configured and fall back to polling.
- Driver heartbeat updates GPS and can broadcast location to an active trip channel.
- Notifications are inserted for trip events and admin/customer/driver recipients.

Gaps:

- Pusher is disabled unless credentials are configured, and the browser falls back to polling.
- There is no UI for users to view persisted notifications in customer/driver dashboards.
- Admin notifications are static hardcoded examples, not loaded from the notifications table.
- No service worker, push notification registration, SMS, email template management, or notification preferences exist.
- Polling intervals can become expensive under load, especially for tracking and driver offers.

### 2.6 Payments, wallet, payouts, and finance

Working pieces:

- Booking creates a manual-transfer payment record.
- Customer tracking includes a manual payment proof panel when manual transfer is configured.
- Payment proof upload validates file size/type, stores proof files, updates payment/trip state, logs events, and notifies participants.
- Admin payment settings can persist manual transfer bank details and future gateway keys.
- Admin manual payment review can approve/reject proofs and set payment/trip status.
- Wallet crediting exists after trip completion when payment is captured.
- Payout tables and admin payout list/update APIs exist.

Gaps:

- Paystack and Flutterwave fields are marked “future” and have no collection, webhook, verification, refund, or reconciliation implementation.
- Payout execution is status update only; it does not call a bank transfer/payment provider API.
- There is no automated payout creation from wallet balances.
- Driver bank details are incomplete in onboarding/dashboard, so real payouts have nowhere reliable to send funds.
- Revenue dashboard values are hardcoded and not calculated from `sd_trips`, `sd_payments`, or ledger tables.
- Refund selection in dispute resolution records resolution metadata but does not execute a payment reversal.

### 2.7 Support, disputes, ratings, and safety

Working pieces:

- Customers and drivers can create support tickets and safety reports, add messages, and upload evidence through `support-api.php`.
- Ratings can be submitted only after completion, are scoped to the trip actor, and customer ratings update driver averages.
- Admin dispute list and resolution API exist.

Gaps:

- The customer/driver dashboards have prompts for support but no full inbox, thread view, support status list, or attachment viewer.
- There is no admin support ticket inbox separate from disputes.
- Disputes appear to depend on rows already existing in `sd_disputes`; support tickets do not automatically become disputes under defined rules.
- Safety reporting opens support but does not trigger an operational escalation workflow beyond notifications.
- Driver rating UI is absent or minimal; customer post-trip modal exists but must be tied to actual terminal trip state in all flows.

## 3. Dashboard placeholder audit

## 3.1 Admin dashboard

### 3.1.1 Sidebar, topbar, global search, notifications, export, logout

Status: **mostly placeholder.**

- The sidebar routes between panels, but some badges are static and not driven by `get_dashboard_stats`.
- The topbar date says “Live · Sat Apr 11, 2026” instead of using the current server/client date.
- Global search only shows a toast when the query is long enough. It should search across trips, customers, drivers, payments, support tickets, and disputes, then navigate to the selected entity.
- Notification panel is entirely hardcoded. It must load rows from `sd_notifications`, support filtering/unread state, and call a backend read/mark-all-read endpoint.
- Export button only toasts. It must call export endpoints for dashboard CSV/PDF reports and respect the active panel/filter.
- Logout only toasts in the nav. It must submit to the real logout endpoint or WordPress logout route.

Dependencies to build: admin notification APIs, cross-entity search API, export/report endpoints, dynamic badge counts, admin logout action.

### 3.1.2 Platform Overview

Status: **partially wired.**

- Metrics for active drivers, online drivers, trips today, revenue today, KYC pending, completion rate, pickup time, open disputes, escalated disputes, suspended drivers, and recent trips are wired through `get_dashboard_stats`.
- Recent Deliveries has a real container and “View all” navigation.
- Activity Trend is a static chart with hardcoded bar heights and labels. It should be computed from real trip counts/revenue by day.
- The page subtitle is static to Port Harcourt metro; it should be either configurable or removed if not operationally true.

Dependencies to build: daily analytics endpoint, configurable operating region setting, export support for overview data.

### 3.1.3 KYC Review Queue

Status: **mostly wired for review, incomplete for document operations.**

- Tabs, filters, queue loading, detail overlay, approve/reject actions, and admin audit logging are backed by `get_drivers`, `get_driver`, and `kyc_action`.
- The detail overlay includes real rendered driver data, but the document review area is limited by whatever KYC upload metadata exists.
- The visible “Bank details pending” and KYC policy controls are not tied to complete driver bank verification or policy enforcement.
- Reject reasons are fixed UI strings and not configurable.

Dependencies to build: driver bank account collection/verification, document preview/download controls, KYC policy settings, audit-visible reason codes, optional background check provider integration if that UI remains.

### 3.1.4 Live Operations

Status: **partially wired.**

- Driver list, online count, active trip count, last location, and refresh age load from `get_live_ops`.
- The map is a visual grid with animated/static points rather than a real map with driver coordinates and trip routes.
- Filter buttons only toast and do not filter by availability, in-trip status, delayed trips, vehicle type, or stale GPS.
- There is no admin action to intervene in a trip, reassign a driver, contact a driver/customer, or force redispatch.

Dependencies to build: real map provider component, ops filters, stale-location logic, admin trip intervention APIs, support/escalation shortcuts.

### 3.1.5 Trips

Status: **partially wired.**

- Trips list, search, category filter, status filter, pagination, and basic trip modal are wired to `get_trips`.
- Export button only toasts.
- Trip detail modal is assembled from list-row arguments and does not fetch authoritative trip details, event timeline, payment record, support tickets, location trail, or proof files.
- There is no admin cancellation, reassignment, refund, receipt resend, or manual status correction flow.

Dependencies to build: trip-detail API, trip event/payment/support joins, export endpoint, admin-safe trip actions, audit logging for every mutation.

### 3.1.6 Revenue Analytics

Status: **placeholder.**

- Monthly revenue, net commission, driver payouts, average daily, revenue by day, revenue by category, same-day deliveries, referral revenue, and gateway success are hardcoded.
- Download CSV only toasts.
- No backend endpoint currently supplies this panel’s metrics.

Dependencies to build: finance analytics API over trips/payments/ledger/payouts, CSV export, date-range filters, category grouping, gateway success metrics, reconciliation rules.

### 3.1.7 Driver Payouts

Status: **partially wired.**

- Payout metrics, list, filters, pagination, search, and “Release visible” use `get_payouts` and `process_payout`.
- Processing a payout changes status only; it does not transfer money, validate bank details, lock ledger entries, generate a provider reference, or handle provider failure callbacks.
- Tax portal buttons only toast.

Dependencies to build: payout creation job, bank account verification, payment-provider transfer integration, idempotency keys, failure/retry states, tax report generation.

### 3.1.8 Drivers

Status: **partially wired.**

- Driver list/search/filter can load real driver rows.
- Suspend action exists in `admin/api.php`, but the visible generic `confirmSuspend` function only toasts in some flows.
- Reinstatement/ban examples in the suspended area are static or toast-only depending on rendered row.
- There is no driver detail page with KYC files, wallet ledger, trip history, ratings, support flags, bank data, live location, and admin notes.

Dependencies to build: driver detail API/UI, real suspend/reinstate/ban actions, bank details view, wallet ledger view, support complaint aggregation, audit log display.

### 3.1.9 Customers

Status: **partially wired list, incomplete operations.**

- Customer list API exists.
- Customer search UI exists, but customer operations are underdeveloped.
- No customer detail page exists for trips, payments, refunds, support tickets, contact status, notification history, or account suspension.
- Referral/customer loyalty/reports shown in the UI are placeholder/report-toast areas.

Dependencies to build: customer detail API/UI, account status actions, customer payment/support history, referral model if the UI remains.

### 3.1.10 Disputes

Status: **partially wired.**

- Dispute list/search/filter/pagination and resolution API exist.
- Resolution modal collects action, refund amount, and notes, but the resolution action text is free-form UI and not a constrained backend workflow.
- Refund amounts are recorded but no gateway/manual refund execution happens.
- There is no evidence viewer, message thread, SLA timer, assignment, escalation ownership, or customer/driver reply workflow in admin.

Dependencies to build: dispute-detail API, evidence viewer, support-message bridge, refund execution, admin assignment/SLA, structured resolution actions.

### 3.1.11 Settings

Status: **mixed; payment settings are partially real, most policies are placeholders.**

- Manual transfer and future gateway credential fields can save through `savePaymentSettings`.
- Commission/pricing fields are visible but are not connected to all options used by quote/booking.
- KYC policy toggles are CSS-only and not saved.
- Notification policy toggles are CSS-only and not saved.
- Legal/compliance buttons only toast.
- Manual Payment Reviews is wired to the manual payments API.
- General “Save Changes” button only toasts.

Dependencies to build: unified settings schema/API, quote pricing settings integration, KYC policy enforcement, notification preference jobs, legal document CRUD/view routes.

## 3.2 Customer dashboard

### 3.2.1 Onboarding and authentication

Status: **partially wired with demo remnants.**

- Login, registration, email verification, and resend flows call backend handlers.
- Demo credentials are prefilled in the login form and must be removed.
- Google/Apple buttons call normal login and are placeholders.
- Forgot password only toasts and must connect to a reset flow.
- The UI references password/OTP in the same field, but the backend authenticates password, not OTP login.

Dependencies to build: password reset, optional OAuth, production-safe empty login fields, clear copy for email verification vs OTP.

### 3.2.2 Main booking map and package form

Status: **partially wired.**

- Customer can enter pickup/drop-off/category/vehicle and call quote/booking APIs.
- Quote is real and booking is real.
- Map UI is mostly visual and not a true interactive map picker.
- Schedule modal is UI-only; scheduled pickup does not persist to booking.
- Address ambiguity, validation, route preview, and quote-expired UI need stronger handling.

Dependencies to build: autocomplete/map picker, schedule fields in quote/booking, expired quote recovery, unavailable-service-area state, clear loading/error states.

### 3.2.3 Activity/trip history

Status: **partially wired.**

- `customer-trips-api.php` returns recent trips, and the dashboard has trip card styles and activity tab rendering.
- Trip action buttons such as track/reorder/support are inconsistent: tracking can work for real trip IDs, but reorder/cancel/support shortcuts are not fully built as durable flows.
- Empty history and failed fetch states need stronger UI.

Dependencies to build: trip detail/history screen, reorder endpoint/flow, customer cancellation endpoint, support shortcut with trip context, pagination beyond 50 trips.

### 3.2.4 Live tracking

Status: **partially wired.**

- Tracking calls `trip-feed-api.php`, subscribes to Pusher when configured, falls back to polling, and renders driver/trip/timeline/payment data.
- Contact button only toasts “Starting masked relay call”; no masked calling or chat exists.
- Safety opens support, which is real, but it uses prompt-style input and not a full safety workflow.
- Share tracking uses browser share/link behavior but there is no public/limited tracking token endpoint shown.
- “Simulate End” opens the post-trip modal and must be removed or restricted from production.
- The map animation is not a real route/driver map.

Dependencies to build: masked call/chat provider, public tracking-token endpoint, real map route rendering, production removal of simulate button, cancellation/support actions, terminal-state receipt/rating gating.

### 3.2.5 Manual payment proof

Status: **partially wired and usable for manual transfer.**

- Manual transfer details render from payment settings.
- Customer can upload JPG/PNG/PDF proof for a trip.
- Payment status is shown and admin can review proofs.

Gaps:

- There is no card/gateway payment flow.
- The customer is dispatched before proof approval under current booking behavior.
- Rejected proof resubmission UX is minimal.
- There is no receipt download after capture beyond toast-style email receipt placeholders.

Dependencies to build: payment policy decision before dispatch, gateway checkout/webhooks, receipts, rejected proof reason display, payment status notifications.

### 3.2.6 Post-trip rating and receipt

Status: **partially wired but UI-triggering is incomplete.**

- Rating API exists and enforces completed trips.
- Post-trip modal has star buttons and feedback chips.
- Receipt email button only toasts.
- The “Done” action should submit rating for the actual completed trip and close only after success; all terminal flows need to open the modal from real state, not the simulate button.

Dependencies to build: terminal trip detection, receipt API, customer receipt download/email, feedback-chip persistence or removal, duplicate-rating handling in UI.

### 3.2.7 Account/settings/support

Status: **partial.**

- Profile API exists for customer profile data.
- Support ticket creation exists.
- Full account editing, notification preferences, payment methods, support inbox, and saved addresses are missing or UI-only if present.

Dependencies to build: account profile edit UI, saved addresses table/API, notification preferences, support inbox/thread view, payment method management if gateways are added.

## 3.3 Driver dashboard

### 3.3.1 Driver registration, login, email verification, and KYC

Status: **mostly wired for basic onboarding.**

- Driver registration/login/verification handlers exist.
- The driver onboarding wizard collects identity, vehicle, files, and submits KYC to `driver-kyc-handler.php`.
- Admin can approve/reject drivers.

Gaps:

- Password reset is missing.
- Bank details for payouts are not fully captured/verified.
- KYC document quality, background check, vehicle inspection, and policy toggles are not enforced.
- Rejection reason display/resubmission flow needs completion.

Dependencies to build: bank account form/verification, KYC resubmission state, policy-based required documents, driver password reset, admin rejection feedback display.

### 3.3.2 Approval/pending state

Status: **partially wired.**

- Driver initial context can decide whether an approved driver sees the dashboard or setup.
- Pending/rejected copy exists in the wizard flow.

Gaps:

- There is no full status center showing exactly what is missing, rejected, or next.
- Notifications from admin KYC decisions are not surfaced as an inbox.

Dependencies to build: KYC status endpoint/UI, rejected-document resubmission, notifications list.

### 3.3.3 Online/offline and heartbeat

Status: **wired.**

- Driver online toggle calls `driver-toggle-online.php`.
- Heartbeat posts geolocation to `driver-heartbeat-api.php`, validates approved/active driver state, stores GPS, returns offers and active trip, and broadcasts active-trip location.

Gaps:

- No stale GPS UI or forced offline after inactivity.
- Browser geolocation denial falls back awkwardly and does not educate the driver.
- Battery/data usage and background tracking behavior are not addressed.

Dependencies to build: stale-location cron, geolocation permission UX, offline reason states, optional mobile app/background tracking strategy.

### 3.3.4 Offers and active trip execution

Status: **mostly wired for happy path.**

- Offers render from heartbeat/offer APIs.
- Accept/decline actions are wired to `driver-trip-action-api.php`.
- Active-trip buttons advance the state machine.
- Navigation button opens Google Maps.

Gaps:

- Contact button only toasts or relies on masked placeholder data; no relay call/chat.
- Safety support uses prompt input rather than a robust emergency/report flow.
- Driver cancellation, no-show, package not ready, wrong address, customer unreachable, and reassignment are missing.
- Delivery proof upload is missing despite schema fields.
- Delivery PIN input UX must be robust on completion; the API requires it, but the active-trip UI must collect it reliably.

Dependencies to build: masked contact, driver issue flows, cancellation/reassignment workflow, POD upload, PIN modal, admin escalation hooks.

### 3.3.5 Earnings, wallet, and payouts

Status: **mostly placeholder/incomplete.**

- Backend can credit wallet after completed captured payment.
- Payout/ledger tables exist.
- Driver UI shows trip history/amount styles, but full wallet ledger and payout requests are not complete.

Gaps:

- Driver cannot reliably view ledger entries, pending balance, available balance, payout status, or request payout.
- Driver bank details are not fully collected/verified.
- Payouts are not provider-executed.

Dependencies to build: driver wallet API/UI, payout request API, ledger transaction detail, bank verification, admin/provider payout execution.

### 3.3.6 Profile, documents, history, support

Status: **partial.**

- Driver profile API can read/update basic name/language fields.
- Support API can create tickets and upload evidence.
- History styles exist.

Gaps:

- Full profile edit does not cover vehicle data, phone/email changes, emergency contact, bank details, documents, or profile photo.
- Driver trip history is not exposed as a robust paginated API in the inspected files.
- Driver support inbox/thread view is missing.
- Ratings received and rating customer after completion are not complete in the UI.

Dependencies to build: driver profile/settings APIs, trip history API/UI, support inbox, rating UI, document renewal flows.

## 4. Ordered phase briefs for remaining work

### Phase 1 — Production safety cleanup and dashboard truth pass

**What is being built:** Remove demo-only UI behavior and make every visible control either functional or explicitly disabled with real explanatory copy while preserving every operational feature surface for later wiring. This includes removing prefilled customer credentials, restricting “Simulate End” behind an explanatory disabled production state, replacing toast-only logout with real logout, disabling social login until OAuth exists, disabling placeholder exports/tax/legal reports until endpoints exist, and replacing hardcoded admin notification counts/dates with dynamic or neutral states. Do not completely remove crucial operational features; keep their UI surfaces visible as disabled or neutral states when the backend is not ready.

**Why it belongs at this stage:** This is the lowest-risk, highest-trust phase. It prevents operators and testers from believing placeholder controls are real, and it avoids leaking demo credentials or test affordances into production.

**Inputs and dependencies:** Existing dashboard PHP files, existing logout handler, existing admin stats endpoint, current authentication handlers.

**Expected outputs:** A user sees no fake credentials, no simulation controls, no misleading hardcoded financial/notification counts, and no button that silently pretends to perform operational work. Disabled features clearly say what is unavailable.

**Architectural constraints:** Do not remove already-working API integrations. Prefer feature flags, neutral placeholders, or disabled states for future modules; do not delete crucial feature surfaces merely because their backend is incomplete. Keep current PHP/vanilla JS style unless a broader frontend refactor is intentionally started later.

**Edge cases and failure states:** If an API is unavailable, show a real error state and retry option. If a feature is disabled, the UI must not mutate local state as if the action succeeded.

### Phase 2 — Unified admin settings and policy persistence

**What is being built:** Create a single settings API/UI contract for pricing, commission, manual transfer details, future gateway credentials, KYC policies, notification policies, service area, legal document links/content, and dashboard region labels. Wire all settings inputs and toggles to `sd_settings`, validate values, and make quote/booking read pricing settings from the same source.

**Why it belongs at this stage:** Pricing, payment policy, service area, KYC requirements, and notifications influence later payments, dispatch, and compliance work. They must be centralized before additional flows depend on them.

**Inputs and dependencies:** Existing `sd_settings` table, existing manual payment settings helpers, quote API pricing logic, admin settings panel.

**Expected outputs:** Admins can save and reload all visible settings. Quote pricing uses configured commission/minimum fare/rates/radius. KYC and notification policy toggles persist even if their enforcement is implemented in later phases.

**Architectural constraints:** Keep secrets out of client payloads except public keys. Mask secret keys in responses. Audit every settings change in `sd_admin_audit_logs`.

**Edge cases and failure states:** Invalid pricing must be rejected with field-level messages. Secret fields left blank during edit must not overwrite existing secrets. Missing settings must fall back to safe defaults.

### Phase 3 — Customer booking completeness: scheduling, cancellation, saved addresses, and trip details

**What is being built:** Finish the customer booking lifecycle by adding scheduled pickup persistence, quote expiration/retry UX, saved addresses, real trip detail/history, customer cancellation before pickup, reorder, and support-with-trip-context from history/tracking.

**Why it belongs at this stage:** Booking is the top-of-funnel customer function. It must be complete before tightening dispatch/payment policies, because every later module depends on reliable trip states and customer intent.

**Inputs and dependencies:** Existing quote API, booking handler, customer trip API, trip feed API, support API, trips table scheduled fields.

**Expected outputs:** Customers can schedule a pickup, book with clear quote validity, cancel eligible trips, view full trip details/timeline/payment/support status, reuse addresses, reorder past routes, and open contextual support tickets.

**Architectural constraints:** Respect existing trip/dispatch statuses. Cancellation must log a trip event, notify participants, expire pending offers, and never cancel a completed trip. Saved addresses should be a dedicated table or well-structured user meta, not ad hoc local storage.

**Edge cases and failure states:** Expired quotes must lead to one-click requote. Cancellation after driver arrival should be rejected or routed to support according to policy. Saved addresses must handle deleted/invalid locations gracefully.

### Phase 4 — Dispatch operations hardening and admin intervention

**What is being built:** Add durable dispatch recovery and admin intervention: scheduled offer expiry, automatic redispatch/backoff for `no_driver` and expired offers, customer/driver cancellation state machine, admin reassignment, admin force-cancel, stale driver-location handling, and active trip issue codes.

**Why it belongs at this stage:** The current dispatch happy path works, but real operations fail at edge cases. This phase makes the marketplace resilient before scaling payments and payouts.

**Inputs and dependencies:** Existing dispatch helpers, driver action API, trip events, notifications, admin trips/live ops panels, WordPress cron or an equivalent scheduled runner.

**Expected outputs:** Trips do not get stuck in offered/no_driver states, admins can intervene safely, cancelled trips have clear responsibility/reason codes, drivers who stop heartbeating are treated as stale, and all interventions are audited and visible in trip timelines.

**Architectural constraints:** Use transactions for any reassignment/cancellation that touches trips/offers/wallet/payment state. Keep all state changes behind server APIs, never client-only transitions.

**Edge cases and failure states:** Two admins reassigning the same trip must not double-assign it. A driver accepting while an admin cancels must resolve with one authoritative state. Redispatch must not spam the same declined driver indefinitely.

### Phase 5 — Real map, tracking token, masked contact, and safety workflows

**What is being built:** Replace decorative map elements with real route/driver map components, add limited public tracking links, integrate or stub a real masked call/chat provider behind server APIs, and replace prompt-based safety/support reports with structured forms and escalation states.

**Why it belongs at this stage:** Once dispatch states are reliable, users need trustworthy visibility and communication. Tracking and contact are central to delivery confidence and safety.

**Inputs and dependencies:** Existing geocoded trip data, driver locations, trip feed, Pusher helper, support API, notification table.

**Expected outputs:** Customers and admins see actual pickup/drop-off/driver coordinates on a map. Share links use expiring tokens and reveal only safe trip information. Contact buttons initiate a real provider-backed relay or clearly record a contact request. Safety reports capture category, severity, evidence, and alert admins.

**Architectural constraints:** Do not expose raw phone numbers. Public tracking tokens must be unguessable, expiring, scoped, and revocable. Keep Pusher private channels for authenticated dashboards.

**Edge cases and failure states:** If maps fail, show address/timeline fallback. If masked contact provider fails, log the failed attempt and offer support escalation. Expired share links must show a safe expired state.

### Phase 6 — Payment policy, gateways, receipts, refunds, and reconciliation

**What is being built:** Decide and implement the production payment model: manual-transfer-before-dispatch or dispatch-before-approval with operational risk flags. Add Paystack/Flutterwave checkout, webhook verification, payment capture/failure/refund states, receipt generation/email/download, rejected-proof resubmission UX, and reconciliation dashboards.

**Why it belongs at this stage:** Payments must be correct before wallet payouts are made. Trip completion, driver credits, refunds, and revenue analytics depend on trustworthy payment state.

**Inputs and dependencies:** Existing `sd_payments`, payment proof handler, admin manual review APIs, settings keys for gateways, trip feed payment payload, wallet credit helper.

**Expected outputs:** Customers can pay by configured provider, payment state updates only from verified events or admin review, receipts are available, refunds are executable/audited, and finance staff can reconcile payments against trips.

**Architectural constraints:** Webhooks must be idempotent. Never trust client-supplied payment success. Secret keys must remain server-side. Manual proof approval must keep the existing audit/event pattern.

**Edge cases and failure states:** Duplicate webhooks must not double-capture or double-credit. Partial refunds must update payment and trip records consistently. Gateway downtime must fall back to configured manual-transfer behavior if enabled.

### Phase 7 — Driver wallet, bank details, payout execution, and tax reports

**What is being built:** Complete driver finance: collect/verify bank details, expose wallet ledger and payout history to drivers, create payout requests from available balances, lock ledger entries during payout processing, integrate bank transfer provider execution, handle failures/retries, and generate driver tax/withholding reports.

**Why it belongs at this stage:** Payouts should only be executed once payment capture/refund logic is reliable. This phase turns existing ledger/payout tables into a real driver finance product.

**Inputs and dependencies:** Completed payment capture/refund behavior, existing wallet ledger helper, `sd_payouts`, driver profile/KYC records, admin payout APIs.

**Expected outputs:** Drivers can see earnings and payout status. Admins can release payouts that actually transfer money or record a manual transfer reference. Failed payouts return funds to available balance or retry safely. Tax report buttons generate real files.

**Architectural constraints:** Use idempotency keys/provider references. Ledger entries must be append-only; avoid editing historical financial records. Bank details must be encrypted or protected according to deployment capabilities.

**Edge cases and failure states:** Provider success after timeout must not create duplicate payouts. A refunded trip after payout must create a clawback/adjustment entry rather than mutating old ledger rows. Invalid bank details must block payout and notify the driver.

### Phase 8 — Admin entity detail centers and support/dispute operations

**What is being built:** Add full admin detail screens for trips, drivers, customers, payments, support tickets, and disputes. Include timelines, messages, evidence, internal notes, assignment, SLA, status changes, refunds, suspensions, contact/escalation actions, and audit logs.

**Why it belongs at this stage:** Admins need complete operational context to manage the now-hardened booking, dispatch, payment, and payout systems. This phase removes reliance on shallow list modals.

**Inputs and dependencies:** Existing list APIs, support/dispute tables, trip events, payments, ratings, notifications, admin audit logs, completed refund/payment flows.

**Expected outputs:** An operator can open any entity and understand what happened, what is pending, who owns it, what evidence exists, and what actions are allowed next.

**Architectural constraints:** All admin mutations must use backend authorization, nonces, transactions where needed, and audit logs. Evidence downloads should be permission-checked and not expose raw upload paths publicly.

**Edge cases and failure states:** Closed disputes should be read-only except for admin-superuser reopen. Evidence missing from disk should show a recoverable warning. Conflicting status changes must return clear errors.

### Phase 9 — Notifications, inboxes, templates, and escalation jobs

**What is being built:** Build notification centers for admin, customer, and driver dashboards; add mark-read/unread APIs; add email/SMS/push template management; implement scheduled escalation jobs for KYC SLA, dispute SLA, stale active trips, failed payouts, and unresolved safety reports.

**Why it belongs at this stage:** Once operational workflows exist, notifications become the system glue. The current notification table is underused and static UI must be replaced with real inboxes.

**Inputs and dependencies:** Existing notifications table/helper, support/dispute/KYC/payout states, settings notification policies, Pusher configuration.

**Expected outputs:** Users can see actionable notifications. Admins receive real-time and persisted alerts. Escalations are created automatically when policies are violated. Notification preferences influence delivery channels.

**Architectural constraints:** Separate notification creation from delivery. Pusher/browser push/SMS/email failures must not roll back core business transactions. Templates should be versioned or at least centrally stored.

**Edge cases and failure states:** Duplicate escalation jobs must not create duplicate alerts. Read state must be per recipient. Delivery failures must be logged and retried according to policy.

### Phase 10 — Analytics, exports, reporting, and production observability

**What is being built:** Replace static revenue/activity/customer/referral charts with real analytics endpoints and exports. Add CSV/PDF generation, date-range filters, operational KPIs, logs/health checks, error monitoring hooks, load testing scripts, and expanded automated tests.

**Why it belongs at this stage:** Analytics should reflect stable production workflows. Building it after operational flows prevents charts from encoding temporary or incorrect state assumptions.

**Inputs and dependencies:** Completed trips, payments, payouts, support/dispute workflows, settings, existing tests, CI workflow.

**Expected outputs:** Admin revenue, activity, category, gateway, payout, KYC, support, and customer metrics are accurate and exportable. Developers/operators can monitor API health, dispatch latency, payment webhook success, and polling/Pusher usage.

**Architectural constraints:** Analytics queries must be indexed and paginated. Large exports should stream or generate async files rather than blocking PHP requests. Tests should cover APIs and state transitions, not only helper functions.

**Edge cases and failure states:** Empty date ranges should show empty charts, not fake examples. Export generation failures must be visible. Slow analytics queries must not degrade booking/dispatch APIs.

## 5. Highest-risk blockers before production

1. Payment capture/refund/reconciliation is not production-complete.
2. Payout execution is not real money movement.
3. Admin revenue analytics and several admin operational controls are placeholders.
4. Customer and driver cancellation/reassignment edge cases can leave trips stuck.
5. Real-time is optional and falls back to polling; this must be capacity-tested.
6. Masked contact, public tracking links, proof of delivery, and full support inboxes are missing.
7. Password reset, social auth, settings persistence, and notification inboxes are incomplete.

