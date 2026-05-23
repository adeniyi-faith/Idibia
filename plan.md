1. **Optimize Cron N+1 Query in `cron.php`**
   - The cron job `cron.php` queries for trips to redispatch, and then inside a loop, queries the database for pending offers for each trip to ensure it shouldn't be skipped.
   - This creates an N+1 query bottleneck which degrades performance as the number of pending trips increases.
   - We will replace this loop with a single query using `NOT EXISTS` to fetch only the trips that are eligible for redispatch directly.
2. Complete pre-commit steps to ensure proper testing, verification, review, and reflection are done.
3. **Submit**
   - I will submit the PR with clear documentation on the performance impact.
