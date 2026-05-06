# PageTyping Index Optimization — Incident & Safe Re-run Guide

**Date:** 2026-04-22  
**Database:** `klas` (SQL Server, `sqlsrv` connection)  
**Affected tables:** `file_indexings`, `scannings`, `pagetypings`

---

## 1. What Happened

The PageTyping performance optimization script was launched in SSMS. It hung for ~12+ minutes. The web app (Apache / Laravel) froze because dozens of sessions piled up waiting on locks.

### Root cause (from `sys.dm_exec_requests`)

- Script session (e.g. `155`) was stuck on:
  ```
  ALTER INDEX [IX_file_indexings_file_number] ON [file_indexings] REBUILD
  wait_type = LCK_M_SCH_M   (schema-modification lock)
  blocking_session_id = 58  (a PageTyping dashboard COUNT query)
  ```
- The REBUILD could not acquire `Sch-M` because a long-running dashboard `COUNT(*) ... GROUP BY f.[id] HAVING COUNT(DISTINCT p.[id]) ...` query held intent-shared (`IS`) locks.
- While the REBUILD waited, **every new app request** that needed `file_indexings` metadata (`LCK_M_SCH_S` on `sys.columns`) queued behind the script.
- Result: a long blocking chain — 30+ sessions in `LCK_M_S` / `LCK_M_SCH_S` / `LCK_M_IS` waits.
- Killing the script releases everyone; then the next long dashboard query (e.g. session `60`) often becomes the new head blocker and must also be killed.

---

## 2. Immediate Recovery (when the app is frozen)

Run these in a **new SSMS query window**:

### 2.1 Identify the blocker chain

```sql
SELECT 
    r.session_id,
    r.blocking_session_id,
    r.status,
    r.wait_type,
    r.wait_time / 1000.0 AS wait_seconds,
    DB_NAME(r.database_id) AS db_name,
    t.text AS running_sql,
    s.login_name,
    s.host_name,
    s.program_name
FROM sys.dm_exec_requests r
CROSS APPLY sys.dm_exec_sql_text(r.sql_handle) t
JOIN sys.dm_exec_sessions s ON s.session_id = r.session_id
WHERE r.session_id <> @@SPID
ORDER BY r.blocking_session_id DESC, r.wait_time DESC;
```

Find the session that has **many others blocked behind it** and **`blocking_session_id = 0`** (the head of the chain).

### 2.2 Kill the head blocker

```sql
-- Replace NN with the head blocker session id
KILL NN;
```

### 2.3 Verify the chain cleared

```sql
SELECT session_id, blocking_session_id, wait_type, wait_time/1000.0 AS sec
FROM sys.dm_exec_requests
WHERE blocking_session_id <> 0
ORDER BY wait_time DESC;
```

Should return 0 rows (or very few, briefly). Repeat `KILL` if a new head emerges.

---

## 3. Why the Original Script Hung

| Section | Risk | Notes |
|---|---|---|
| 1. `CREATE INDEX` | Medium | Needs `Sch-M`. Waits behind any long reader. |
| **2. `ALTER INDEX ... REBUILD` cursor** | **HIGH** | Offline REBUILD blocks all readers/writers. This is the trap. |
| 3. `UPDATE STATISTICS` | Low | Fast, usually non-blocking at this scale. |
| 4–5. Metadata `SELECT`s | None | Instant. |

At ~90K–100K rows, **Section 2 is unnecessary** — fragmentation savings are trivial and the risk is huge.

---

## 4. Safe Re-run Procedure

### 4.1 Use this reduced, non-blocking script

```sql
SET LOCK_TIMEOUT 15000;  -- Fail any statement that waits >15s instead of hanging

PRINT '====== CREATING INDEXES (safe mode) ======';

-- Index 1
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_scannings_file_indexing_id' AND object_id = OBJECT_ID('scannings'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_scannings_file_indexing_id]
    ON [dbo].[scannings] ([file_indexing_id] ASC)
    INCLUDE ([created_at], [status]);
END

-- Index 2
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_pagetypings_file_indexing_id' AND object_id = OBJECT_ID('pagetypings'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_pagetypings_file_indexing_id]
    ON [dbo].[pagetypings] ([file_indexing_id] ASC)
    INCLUDE ([typed_by], [created_at]);
END

-- Index 3
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_file_indexings_registry' AND object_id = OBJECT_ID('file_indexings'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_file_indexings_registry]
    ON [dbo].[file_indexings] ([registry] ASC)
    INCLUDE ([file_number], [file_title], [district], [lga]);
END

-- Index 4
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_file_indexings_is_updated' AND object_id = OBJECT_ID('file_indexings'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_file_indexings_is_updated]
    ON [dbo].[file_indexings] ([is_updated] ASC)
    INCLUDE ([id], [file_number]);
END

-- Index 5
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_scannings_file_id_created' AND object_id = OBJECT_ID('scannings'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_scannings_file_id_created]
    ON [dbo].[scannings] ([file_indexing_id] ASC, [created_at] DESC);
END

-- Index 6
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_pagetypings_file_id_typed_by' AND object_id = OBJECT_ID('pagetypings'))
BEGIN
    CREATE NONCLUSTERED INDEX [IX_pagetypings_file_id_typed_by]
    ON [dbo].[pagetypings] ([file_indexing_id] ASC, [typed_by] ASC);
END

PRINT '====== UPDATING STATISTICS ======';
UPDATE STATISTICS [file_indexings];
UPDATE STATISTICS [scannings];
UPDATE STATISTICS [pagetypings];

PRINT '====== DONE ======';
```

### 4.2 Execution checklist

1. **Pick a quiet window** (lunch break / after hours).
2. Run the blocker query first (§2.1). If anything long-running is active, wait or kill it.
3. Run the reduced script above (§4.1).
4. If any statement fails with `Lock request time out period exceeded` (error 1222), simply re-run it — `IF NOT EXISTS` makes it idempotent.
5. **Do NOT** run the original Section 2 REBUILD cursor unless you are certain the app is idle.

---

## 5. Follow-Up Action Items

### 5.1 Dashboard COUNT query is the real bottleneck

The query that triggered the block is run concurrently by many users:

```sql
SELECT COUNT(*) as cnt FROM (
  SELECT f.[id]
  FROM [file_indexings] f
  INNER JOIN [scannings]  s ON f.[id] = s.[file_indexing_id]
  INNER JOIN [pagetypings] p ON f.[id] = p.[file_indexing_id]
  GROUP BY f.[id]
  HAVING COUNT(DISTINCT p.[id]) >= COUNT(DISTINCT s.[id]) AND COUNT(DISTINCT s.[id]) > 0
) t
```
Recommendations:
- Cache the count (Laravel cache, 30–60s TTL) — it does not need to be real-time.
- Or rewrite using `EXISTS` / pre-aggregated subqueries to avoid full `GROUP BY` every call.
- Add a debounce/throttle so each page load only fires it once.

### 5.2 `select name from sys.columns where object_id = object_id('file_indexings')`

This is a Laravel schema introspection call. It multiplies blocking because it runs on almost every request touching the table. Consider:
- `Schema::hasColumn()` caching.
- Or explicit `$fillable` / strict casts so Laravel stops introspecting.

### 5.3 Optional — enable READ_COMMITTED_SNAPSHOT

If not already enabled, it massively reduces reader/writer blocking:

```sql
ALTER DATABASE klas SET READ_COMMITTED_SNAPSHOT ON WITH ROLLBACK IMMEDIATE;
```

(Requires a brief exclusive lock on the DB — do this in a maintenance window.)

---

## 6. Lessons Learned

- Never run `ALTER INDEX ... REBUILD` inside a cursor on hot tables during business hours.
- Always use `SET LOCK_TIMEOUT` in ad-hoc DBA scripts so they fail fast instead of freezing the app.
- Small tables (<1M rows) rarely need REBUILD. `UPDATE STATISTICS` plus proper indexes is enough.
- When the app freezes, check `sys.dm_exec_requests` for the head blocker (`blocking_session_id = 0` with many followers) — killing it is the fastest recovery.
