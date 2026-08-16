# SPAS Mobile — Status Tracker

**Updated:** 2026-08-16
**Design doc:** [SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md](SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md)
**Client contract:** `C:\wamp64\spas_apk\API_CONTRACT.md`

Two workstreams, two machines:

- **Backend** — this repo (`c:\wamp64\www\klaes`, WAMP).
- **App** — `C:\wamp64\spas_apk`, built on `DC-02` (Android Studio + Codex).

---

## 1. At a glance

| Phase | Scope | State | Owner |
|---|---|---|---|
| 0 | API foundation | ✅ **Done** | backend |
| 1 | Capacitor shell | 🟡 **Builds, not verified on device** | app |
| 2 | Local SQLite layer | 🟡 **Written, never executed** | app |
| 3 | Offline-first CRUD | ⬜ Not started | app |
| 4 | Sync engine | ⬜ Not started | app |
| 5 | Auth & security | 🟡 Server done, client not started | both |
| 6 | QA & rollout | ⬜ Not started | both |

**Roughly:** the backend is finished. The app is about 20% in — a shell that
compiles and a schema that has never run. Phases 3 and 4 are the bulk of the
remaining work and they are all client-side.

---

## 2. Phase 0 — API foundation ✅ DONE

| Item | State |
|---|---|
| `SpaMobileService` — one validation/write path for desktop form, mobile form, API | ✅ |
| 15 `/api/spas/*` routes | ✅ |
| Schema DDL (`client_uuid`, nullable parent FK, 4 unique indexes) | ✅ dev **and production**, verified 11/11 |
| Delta pull with inclusive `>=` cursor | ✅ |
| Idempotent create on `client_uuid` | ✅ |
| Edit endpoints with `base_updated_at` optimistic concurrency | ✅ |
| Photo upload keyed by `client_uuid` | ✅ |
| Orphan linking (`/link-orphans`) | ✅ |
| LGA alias resolution at query time (+4,458 files recovered) | ✅ |
| "Awaiting location" panel on the desktop Field Map | ✅ |
| Test suite | ✅ **110 passing** |

Nothing here blocks the app.

---

## 3. Phase 1 — Capacitor shell 🟡

| Item | State | Note |
|---|---|---|
| Capacitor 7 + 8 plugins installed | ✅ | |
| Android platform added, debug APK builds | ✅ | |
| APK installs and renders | ✅ | confirmed by screenshot |
| **Buttons work** | ❌ → 🟡 | were dead (bare ES imports, no bundler); **fixed, not re-verified** |
| Verified on a physical device | ❌ | build agent logged "no device connected" |
| `server.url` production shell pointing at the live SPAS page | ⬜ | host never determined |

**Next action:** rebuild per `FIX_BRIEF_02.md` and confirm on hardware —
`Platform:` must read `android`, and Initialize DB / Run smoke test / Close DB
must all respond.

---

## 4. Phase 2 — Local SQLite layer 🟡

| Item | State |
|---|---|
| 8-table schema per plan §4.2 | ✅ written |
| Rewritten to the `window.Capacitor` global (no bundler) | ✅ |
| Passes `node --check` as ES modules | ✅ |
| **Smoke test executed on a device** | ❌ **never run** |
| Cache seeding from `/lookup/*` on login | ⬜ not started |
| Organic cache growth on every file lookup | ⬜ not started |

> A schema that has never been executed is not a deliverable. Everything in
> Phase 2 is unproven until the smoke test runs on hardware.

---

## 5. Phase 3 — Offline-first CRUD ⬜ NOT STARTED

The biggest remaining chunk, and the one with a real unknown in it.

| Item | Note |
|---|---|
| Port the SPAS Mobile UI into the app's `www/` | `mobile.blade.php` is a Blade template — Records tab, Field Records tab, Field Map tab, two bottom sheets. Converting it to static assets is unscoped work. |
| Read/write local SQLite first | every `fetch()` becomes a local read/write |
| Enqueue `sync_outbox` on every create/edit | |
| Mirror server validation client-side | table in `API_CONTRACT.md` §4 |
| Pending / synced badges on cards | |
| Preserve the enable/disable discipline on `land_use_type` / `lga` / `district` | only the active control carries each `name`, or the outbox payload posts two values for one field |
| Warn (do not block) on a missing GPS pin | product decision Q5 |
| Photo capture via `@capacitor/camera` + `@capacitor/filesystem` | write to app-private storage, keep local URIs |
| Client-side image compression | field connectivity is often 2G |

**Decide before starting:** does the app keep the `server.url` WebView (online
only, no offline value) or become a real local build? Phase 3 only means
anything if it is the latter. This is the single biggest open question left.

---

## 6. Phase 4 — Sync engine ⬜ NOT STARTED

| Item | Note |
|---|---|
| Outbox drain, FIFO oldest first | pseudocode in `API_CONTRACT.md` §6 |
| Response handling: 200 / 201 / duplicate / 409 / 422 / 404-photos / 5xx | each behaves differently — see contract §3 |
| Delta pull + local upsert, skipping locally-pending rows | |
| `Network` plugin `networkStatusChange` trigger | |
| App-resume trigger + manual "Sync Now" | |
| Retry with backoff, `attempts` / `last_error` | |
| "N records pending sync" indicator | reuse the existing `toast()` helper |
| Conflicts list for 409s | |
| Adopt the server's customary `file_number` from the push response | |
| Honour `429` + `Retry-After` | throttle is 60/min; a big drain will hit it |

---

## 7. Phase 5 — Auth & security 🟡

| Item | State |
|---|---|
| Sanctum token login/logout, `spas-mobile` ability | ✅ server |
| Token storage on device | ⬜ `@capacitor/preferences` at minimum |
| **Secure at-rest storage** | ⬜ every handset holds every land record incl. owner names + phones — a lost phone is the whole dataset |
| Stay usable offline; no forced re-auth without network | ⬜ |
| 401 handling → re-login **without discarding unsynced local data** | ⬜ |
| Documented remote revoke path | ⬜ tokens are per-device, so revoking one handset is a single row delete |

---

## 8. Phase 6 — QA & rollout ⬜ NOT STARTED

- Airplane-mode round trip: create offline → reconnect → row + photos land in SQL Server.
- Two devices pushing the same file number → one wins, other gets a 409.
- Kill the app mid-drain → outbox resumes, nothing duplicated.
- Pilot on a handful of surveyor devices.
- Monitor `sync_outbox` failure rate before wider rollout.

---

## 9. Open decisions — need your call

| # | Question | Blocks |
|---|---|---|
| 1 | **Q3: iOS as well as Android?** | signing setup, plugin choices |
| 2 | **Q4: Field Map when offline** — hide the tab, or show cached pins with no tiles? | Phase 3 map work |
| 3 | **Server host for `server.url`** | Phase 1 production shell. `.env` only has `127.0.0.1:8000`, useless from a phone |
| 4 | **Does the app stay a WebView shell, or become a real local build?** | all of Phase 3 |
| 5 | **`Kunchi`** — 16 files reference it, but it is not in the `lgas` table. Missing reference row, or mis-filed files? | `lga:normalize` |
| 6 | **Run `lga:normalize --apply`?** Would change 4,682 rows, 943 left unresolved | optional clean-up only |
| 7 | **Deletions don't sync.** A record deleted in the office never disappears from a device | accept, or build it |
| 8 | **API throttle 60/min** — raise it for `/api/spas/*`, or have the client back off? | Phase 4 |

---

## 10. Backend backlog — optional, nothing depends on it

| Item | Why it is not urgent |
|---|---|
| `users.assigned_lga` / `assigned_district` | app passes LGA explicitly for now |
| Run `lga:normalize --apply` | aliases already resolve at query time |
| Delete/tombstone sync | deletion is rare in this workflow |
| Raise throttle for `/api/spas/*` | only matters once a real drain is tested |

---

## 11. Uncommitted right now

```
M app/Http/Controllers/Api/Spas/SpasSyncController.php
M app/Http/Controllers/SpecialAssignmentController.php
M app/Services/SpaMobileService.php
M docs/plans/SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md
M resources/views/special_assignment/field_data/index.blade.php
M routes/api.php
M tests/Feature/Spas/SpasSyncApiTest.php
M tests/Feature/Spas/SpasWebFormTest.php
?? app/Console/Commands/NormalizeFileIndexingLga.php
?? docs/plans/SPAS_MOBILE_STATUS.md
```

Housekeeping: `C:\wamp64\spas_apk` now holds `app-debug.apk` (28 MB) and
`spas_apk.zip` (92 MB). Both are gitignored, but delete the zip once it has been
moved between machines.

---

## 12. Critical path

```
1. Rebuild the APK with the JS fix        (app, DC-02)
2. Verify on a real device — buttons,
   Initialize DB, smoke test, Close DB    (app)   <- Phases 1 + 2 close here
3. Decide: WebView shell or local build?  (you)   <- gates everything below
4. Port the UI into the app               (app)   <- Phase 3, the big one
5. Build the sync engine                  (app)   <- Phase 4
6. Token storage + offline session        (app)   <- Phase 5
7. Airplane-mode round trip, pilot        (both)  <- Phase 6
```

Step 2 is the immediate one. Step 3 is the decision that shapes the rest.
