## 2024-05-24 - Cron N+1 Query Optimization
**Learning:** The cron script `cron.php` previously used an N+1 query pattern where it fetched all pending trips and then checked each trip individually using a COUNT(*) query in a loop.
**Action:** Replace looped secondary queries with a single query using `NOT EXISTS` or `JOIN` to reduce round-trips to the database and improve script execution time, especially as table size grows.
