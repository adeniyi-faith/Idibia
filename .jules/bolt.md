## 2025-02-23 - [Refactored DOM manipulation to avoid multiple repaints]
**Learning:** Found a performance bottleneck in `assets/js/dashboard.js` where `container.innerHTML += ...` was used inside a loop over trip items. This is an anti-pattern as it causes multiple expensive DOM repaints and reflows, and requires O(N²) string parsing.
**Action:** Instead, always accumulate the HTML string in a local variable during the loop, and update the DOM (`container.innerHTML = tripsHtml`) just once after the loop completes. Watch out for this pattern in other files across the codebase.

## 2026-05-21 - Fix N+1 query in driver campaign progress
**Learning:** The driver dashboard (`driver.php`) originally calculated campaign progress by issuing a `SELECT COUNT(id)` query against `sd_trips` inside a loop iterating over active campaigns. This resulted in an N+1 query problem, heavily degrading performance when multiple campaigns run simultaneously.
**Action:** Replaced the loop-based queries with a single query that fetches the `completed_at` timestamps for all relevant trips within the min(start_time) and max(end_time) bounds of all active campaigns. Count logic is now handled in-memory within PHP, vastly reducing database round-trips.
