1. **Implement `loadDriverDetail` in `assets/js/admin.js`:**
   - Define `loadDriverDetail(driverId)` which calls `adminApi('get_driver', {driver_id: driverId})`.
   - Populate `driverModalTitle` with "Driver Details" and driver's name or ID.
   - Construct `driverModalBody` using driver's details (name, email, phone, status, kyc, vehicle info, rating, trips, wallet balance, bank details, etc.). Use nice CSS grids similar to `openTripDetail`.
   - Add `closeDriverModal()` to handle modal close: `document.getElementById('driverModal').classList.remove('open');`
   - Show the modal: `document.getElementById('driverModal').classList.add('open');`
2. **Review existing HTML in `components/admin/modals.php`:**
   - The modal with `id="driverModal"` exists in `components/admin/modals.php` as verified.
3. **Test code logic locally**
   - Run unit tests or local server script using `find tests -name "*.php" -exec php {} +` to ensure no syntax errors.
4. **Pre-commit step:**
   - Complete pre commit steps to ensure proper testing, verification, review, and reflection are done by calling the pre commit instruction tool.
5. **Submit changes.**
