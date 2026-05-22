1. **Explore APIs and DB logic:**
   - Modify `admin/api.php` to add `get_trip`, `get_customer`, `get_payment`, `get_dispute`.
   - Update `get_driver` to return more details (recent trips, etc.).
   - `get_trip`: Query `sd_trips`, join with `sd_customers`, `sd_drivers`, `sd_payments`. Query `sd_trip_events` for timeline.
   - `get_customer`: Query `sd_customers`. Query `sd_trips` where customer_id = ID.
   - `get_payment`: Query `sd_payments`. Query related trip.
   - `get_dispute`: Query `sd_disputes`. Query related trip and customer.

2. **Update Admin Detail Components & Modals**:
   - `components/admin/modals.php`:
     - Keep `tripModal` but enlarge it, add timeline.
     - Add `customerModal`.
     - Add `driverModal`.
     - Update `disputeModal` to show timeline and read-only mode if resolved.
   - `assets/css/admin.css` (if needed for larger modals or just use inline).

3. **Update Javascript**:
   - `assets/js/admin.js`:
     - Implement `openTripDetailFromData(trip)` or `loadTripDetail(tripId)`.
     - Implement `loadDriverDetail(driverId)`.
     - Implement `loadCustomerDetail(customerId)`.
     - Replace shallow details with full details fetching.
     - Action functions inside modals (e.g. `saveCustomerNotes()`, `suspendDriver()`, etc.).

4. **Verify changes and ensure no regressions**:
   - The UI and flow for Phase 8 must ensure an operator can open any entity and understand what happened.
   - All mutations must use backend auth, nonces, transactions.
   - Edge cases: Closed disputes read-only except for reopen. Conflicting status changes handled.

5. **Run Pre Commit Instructions**:
   - Call `pre_commit_instructions` tool to run required checks before submission.

6. **Submit**:
   - Submit the code.
