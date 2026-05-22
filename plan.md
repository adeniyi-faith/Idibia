1. **Explore and set up tools (no-op action step)**
   - Initial exploration is done, setting up detailed steps below.

2. **Create driver-wallet-api.php**
   - Use `write_file` to create `driver-wallet-api.php` containing logic for 'get_wallet' (fetching wallet balance, ledger entries, and payout history) and 'request_payout' (validating balance and bank details, inserting into sd_payouts, deducting from sd_drivers wallet, and inserting an earning deduction ledger entry into sd_wallet_ledger).
   - Require `wp-auth-config.php` and `idibia-helpers.php`.
   - Validate `$GLOBALS['auth_driver_id']` is set.

3. **Verify driver-wallet-api.php creation**
   - Use `read_file` to verify `driver-wallet-api.php` was created correctly.

4. **Update Driver UI for Earnings and Payouts**
   - Use `replace_with_git_merge_diff` to edit `components/driver/main-app.php`. Add a 'Wallet & Payouts' section in the Earnings tab, displaying current wallet balance and a 'Request Payout' button. Add a modal (`modal-request-payout`) for payout requests. Add tables/lists to display wallet ledger and payout history.
   - Use `replace_with_git_merge_diff` to edit `assets/js/driver.js`. Add functions `loadWalletData` and `requestPayout`. Wire the 'Request Payout' button to the `request_payout` action. Ensure `switchTab('earnings')` also fetches wallet data.

5. **Verify Driver UI updates**
   - Use `read_file` on `components/driver/main-app.php` and `assets/js/driver.js` to ensure edits were applied correctly.

6. **Refactor Admin Payout Execution**
   - Use `replace_with_git_merge_diff` to modify `admin/api.php`. In `idibia_admin_process_payout`, update the logic so that if a payout is marked as 'failed', the amount is refunded to the driver's `wallet_balance` in `sd_drivers` and a 'refund' entry is inserted into `sd_wallet_ledger`. Remove the logic that deducts the wallet balance *only* when the payout is marked as 'paid', because the deduction now happens when the payout is *requested*.
   - Modify `idibia_admin_sync_pending_payouts` to deduct the wallet balance and create a 'payout' ledger entry when it automatically generates pending payouts.

7. **Verify Admin Payout Execution modifications**
   - Use `read_file` on `admin/api.php` to verify the refactoring is correct.

8. **Implement Admin Tax/Withholding Reports**
   - Use `replace_with_git_merge_diff` to add new switch cases in `admin/api.php` for `export_tax_summary`, `export_driver_wht`, and `export_vat_schedule`. These will generate and return CSV data.
   - Use `replace_with_git_merge_diff` in `assets/js/admin.js` to add functions `downloadTaxSummary`, `downloadDriverWht`, and `downloadVatSchedule` that fetch the CSVs and trigger browser downloads. Wire these up to the existing buttons in `components/admin/panel-payouts.php`.

9. **Verify Admin Tax Reports modifications**
   - Use `read_file` to verify changes in `admin/api.php`, `assets/js/admin.js`, and `components/admin/panel-payouts.php`.

10. **Run Tests**
   - Run all standalone PHP tests using `find tests -name "*.php" -exec php {} +` to verify correctness.

11. **Pre commit steps**
   - Complete pre-commit steps to ensure proper testing, verification, review, and reflection are done.

12. **Submit**
   - Submit the change with branch name, commit message, title, and description.
