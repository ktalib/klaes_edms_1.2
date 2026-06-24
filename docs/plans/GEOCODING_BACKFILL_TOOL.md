# Plan: Bulk Geocoding & Backfill Tool (Python + UI)

> **For the implementing AI agent.** Build a standalone desktop/web tool that takes a
> CSV export of `file_indexings`, geocodes the rows that are missing coordinates,
> lets a human review/adjust the pins on a map, then exports a clean **backfill CSV**
> that is loaded back into SQL Server to populate `latitude`/`longitude`.

---

## 1. Context & Goal

The Klaes file-indexing app now stores `latitude`/`longitude` per indexed file (set
via a Google Maps "Apply & Pin on Map" control on the create form). Thousands of
**existing** records have no coordinates. We need a one-off / repeatable batch tool to
geocode them in bulk, with a human-in-the-loop map UI for verification, and a safe
backfill path into the live database.

**End-to-end flow:**

```
SQL Server  ──export──►  source CSV  ──►  [Python Tool UI]  ──►  backfill CSV  ──►  SQL Server
 (SSMS query)            (file_indexings)   geocode + review        (id,lat,lng)      (UPDATE)
```

The source query (run in SSMS, save as CSV):

```sql
SELECT [id], [file_number], [file_title], [location], [plot_number],
       [district], [lga], [latitude], [longitude]
FROM [klas].[dbo].[file_indexings];
```

---

## 2. Tech Stack (standard Python packages)

| Concern | Package | Why |
|---|---|---|
| UI | **streamlit** | Fastest way to ship a data + map UI; no JS needed |
| Data | **pandas** | CSV read/write, filtering, dedupe |
| Geocoding | **googlemaps** | Official Google Maps Python client (same key as the web app) |
| Interactive map | **folium** + **streamlit-folium** | Pan/zoom, draggable markers, popups inside Streamlit |
| Rate limiting | **time** (stdlib) + simple throttle | Stay under Google QPS limits |
| (Optional) direct DB | **pyodbc** / **sqlalchemy** | Read/write SQL Server without manual CSV step |

`requirements.txt`:
```
streamlit>=1.36
pandas>=2.2
googlemaps>=4.10
folium>=0.16
streamlit-folium>=0.21
python-dotenv>=1.0
pyodbc>=5.1        # optional, only if direct-DB mode is used
```

Run with: `streamlit run app.py`

---

## 3. Project Structure

```
geocoding_tool/
├── app.py                  # Streamlit UI entrypoint
├── geocoder.py             # address building + Google geocode calls + cache
├── backfill.py             # produces backfill CSV + optional SQL/DB writer
├── config.py               # loads .env (API key, paths, throttle)
├── requirements.txt
├── .env                    # GOOGLE_MAPS_API_KEY=...
├── data/
│   ├── source/             # dropped-in exports from SSMS
│   ├── cache/geocode_cache.json   # address -> {lat,lng} cache (avoid re-billing)
│   └── output/             # generated backfill_coordinates_YYYYMMDD.csv
└── README.md
```

---

## 4. Geocoding Logic (`geocoder.py`)

Mirror the **exact** address-building rules already used in the web app
([create_indexing.blade.php](../../resources/views/fileindexing/addons/create_indexing.blade.php),
`buildGeocodeAddress`) so coordinates are consistent between bulk and manual entry:

1. Build a **clean** address (NOT the labelled `location` string):
   `"{plot_number}, {district}, {lga}, KANO, NIGERIA"`
   - Drop empty / `SELECT ...` placeholder parts.
   - Strip `STREET:`, `DISTRICT:`, `LGA:`, `STATE:` labels if falling back to `location`.
2. Call `gmaps.geocode(address, region='ng')`.
3. Round results to **7 decimals** (matches the web app, ~1 cm).
4. **Cache** every lookup in `data/cache/geocode_cache.json` keyed by the clean address
   string — re-runs and duplicate addresses cost nothing and stay fast.
5. **Throttle**: sleep ~50–100 ms between live calls; batch in chunks; show progress.
6. Record a per-row `geocode_status`: `OK | ZERO_RESULTS | SKIPPED_HAS_COORDS | ERROR`
   and `geocode_confidence` from `results[0]['geometry']['location_type']`
   (`ROOFTOP` > `RANGE_INTERPOLATED` > `GEOMETRIC_CENTER` > `APPROXIMATE`).

**Skip rule:** by default, only geocode rows where `latitude`/`longitude` are blank.
Provide a checkbox to force re-geocode all.

---

## 5. UI Design (`app.py`, Streamlit)

Single page, top-to-bottom sections:

### 5a. Import
- `st.file_uploader` for the source CSV (or pick from `data/source/`).
- Validate required columns: `id, file_number, file_title, location, plot_number,
  district, lga, latitude, longitude`. Show a clear error if any are missing.
- Summary metrics (`st.metric`): total rows, already-have-coords, to-be-geocoded.

### 5b. Process
- Controls: **[ ] Re-geocode rows that already have coords**, throttle slider.
- **▶ Start Geocoding** button → runs `geocoder` over the eligible rows with
  `st.progress` + live counts (OK / not-found / errors).
- Results table (`st.dataframe`) with filters by `geocode_status`. Colour-code
  not-found / low-confidence rows so a human can spot them.

### 5c. Review on Map (human-in-the-loop)
- **folium** map centred on Kano (`11.99, 8.53`, zoom ~11), `Esri.WorldImagery`
  satellite tiles to match the app.
- Plot every geocoded row as a **draggable** marker; popup shows
  `file_number`, `file_title`, address, status, confidence.
- Use a **MarkerCluster** so thousands of pins stay performant.
- A row-selector (`st.selectbox` on `file_number`) flies to and highlights a single
  pin for precise adjustment.
- Capture marker drags via `streamlit-folium`'s returned state
  (`st_folium(..., returned_objects=['last_active_drawing','all_drawings'])`) and
  write the adjusted lat/lng back into the dataframe. Show an "edited" badge per row.
- Custom marker icon to mirror the app's blue house pin (folium `DivIcon` with the
  same SVG, or `folium.Icon(color='blue', icon='home')`).

### 5d. Export Backfill
- **⬇ Export Backfill CSV** → writes `data/output/backfill_coordinates_<timestamp>.csv`
  containing **only** rows with valid, changed coordinates:
  ```
  id, latitude, longitude
  ```
  (keep it minimal and id-keyed so the DB update is unambiguous and safe).
- Also offer a **full annotated CSV** (all input columns + `geocode_status`,
  `geocode_confidence`, `edited`) for audit.
- `st.download_button` for both files.

---

## 6. Backfill into SQL Server (`backfill.py`)

Provide **two** options; the agent should implement Option A (always works) and
Option B (optional convenience):

### Option A — CSV → SQL UPDATE (default, no DB creds needed)
Generate a ready-to-run `backfill.sql` alongside the CSV, OR document this import:

1. In SSMS, bulk-load `backfill_coordinates_*.csv` into a temp table:
   ```sql
   CREATE TABLE #bf (id BIGINT, latitude DECIMAL(10,8), longitude DECIMAL(11,8));
   BULK INSERT #bf FROM 'C:\path\backfill_coordinates_YYYYMMDD.csv'
     WITH (FORMAT='CSV', FIRSTROW=2);

   UPDATE fi
     SET fi.latitude = b.latitude,
         fi.longitude = b.longitude
   FROM [klas].[dbo].[file_indexings] fi
   JOIN #bf b ON b.id = fi.id
   WHERE fi.latitude IS NULL AND fi.longitude IS NULL;   -- guard: don't clobber manual pins
   ```
   The `WHERE … IS NULL` guard protects coordinates a user set by hand. Offer a
   variant without the guard for forced overwrite.

### Option B — direct DB write (pyodbc)
- If `.env` has SQL Server creds, add a **"Write to database"** button that runs the
  same id-keyed `UPDATE` in a transaction, in batches of ~500, with a dry-run preview
  (row count) first and an explicit confirm. Never auto-commit without confirmation.

> **Important:** match the live schema — `file_indexings.latitude` is
> `DECIMAL(10,8)` and `longitude` is `DECIMAL(11,8)` (see migration
> [2026_06_21_000001_add_geocoordinates_to_file_indexings_table.php](../../database/migrations/2026_06_21_000001_add_geocoordinates_to_file_indexings_table.php)).
> Reuse the same `GOOGLE_MAPS_API_KEY` from the app's `.env`; ensure **Geocoding API**
> is enabled and billing is active on that key.

---

## 7. Implementation Steps (ordered)

1. Scaffold project structure + `requirements.txt` + `.env` loading (`config.py`).
2. `geocoder.py`: clean-address builder (port `buildGeocodeAddress`), cached geocode,
   throttle, status/confidence. Unit-test the address builder against a few rows.
3. `app.py` §5a Import + validation + metrics.
4. `app.py` §5b Process with progress + results table.
5. `app.py` §5c folium map with clustered, draggable markers + drag-capture.
6. `backfill.py` + §5d export (minimal `id,lat,lng` + full audit CSV).
7. Option A `backfill.sql` generation; document the SSMS BULK INSERT path.
8. (Optional) Option B pyodbc direct writer with dry-run + confirm.
9. `README.md`: setup, run, full export→geocode→review→backfill walkthrough.

---

## 8. Verification

1. Export ~50 real rows from `file_indexings` (mix of with/without coords) to CSV.
2. Run the tool; confirm rows **with** coords are skipped, **without** are geocoded.
3. Confirm the cache file is written and a second run makes **no** new API calls.
4. On the map, drag a marker — confirm the row's lat/lng update and it's flagged edited.
5. Export backfill CSV; confirm it contains only valid `id,lat,lng` rows.
6. Run the SQL UPDATE against a **staging** copy of `file_indexings`; confirm the
   guarded update fills only NULL coordinate rows and leaves manual pins intact.
7. Spot-check 3–5 updated rows on the live web form's map — pins should land correctly.

---

## 9. Notes / Guardrails

- **Cost control:** caching + skip-existing + throttle keeps usage inside the
  $200/month free Maps credit for typical volumes.
- **Data safety:** backfill is id-keyed and NULL-guarded by default; always dry-run.
- **Consistency:** address rules and 7-decimal rounding must match the web app so
  bulk and manual coordinates are interchangeable.
- **Low-confidence handling:** surface `APPROXIMATE` results prominently for human
  review — these are the rows most likely to need a manual drag.
