# How "Has New KANGIS FileNo" + "Has Transaction" Are Saved

Covers the two sections shown on the **File Indexing → Create** form
(`/fileindexing/create`) in the screenshot: **Has New KANGIS FileNo (KN Series)**
and **Has Transaction** (repeatable transaction rows). Both live inside the
"File Number & Tracking ID" card.

Source: [resources/views/fileindexing/addons/partials/sections/file_identification.blade.php:171-315](../../resources/views/fileindexing/addons/partials/sections/file_identification.blade.php#L171-L315)

## 1. The two sections, and what they are

| Section | Purpose | Distinct from |
|---|---|---|
| **Has New KANGIS FileNo (KN Series)** | Attaches a KN-series reference number (e.g. `KN1234`) to the file being indexed. | The legacy **KANGIS FileNo Placeholder** field higher up the card — that one is required whenever the General Registry is "KANGIS"; this one is an optional, independently-gated checkbox available in *any* registry. |
| **Has Transaction** | A repeatable list of instrument transactions (CofO, Deed of Assignment, Mortgage, etc.) tied to the New KANGIS file number, one shared `prop_id`. | The Billing / Ground-Rent repeater and the RoFO/Occupancy-Permit cards — those are separate sections with their own tables. |

Both are hidden by default and only rendered in normal create mode
(`@unless($isNewKnMode)` — the dedicated `?url=new_kn` "Track New File" flow has
its own KN section instead, see `new-kangis-wrapper` further down the same
partial).

## 2. Client-side: building the payload

All logic lives in [public/js/fileindexing/create-indexing-dialog.js](../../public/js/fileindexing/create-indexing-dialog.js).

### 2.1 "Has New KANGIS FileNo"

- `initializeHasNewKangisFileno()` (~line 8669) wires the checkbox: checking
  `#has-new-kangis-fileno` reveals `#has-new-kangis-fields`.
- The **Select** button doesn't let you type a number — it opens
  `GlobalFileNoModal` on its `newkangis` tab (`openNewKangisPicker()`,
  ~line 8704). Whatever file number you pick is written into both:
  - `#new_kangis_file_no_display` (readonly, what you see)
  - `#new_kangis_file_no_hidden` (the actual `name="new_kangis_file_no"` value that gets submitted)
- `validateHasNewKangisFileno()` (~line 8740) blocks submission if the checkbox
  is ticked but no number was ever selected.

### 2.2 "Has Transaction"

- `initializeNewKangisTransactions()` (~line 8771) wires the checkbox
  (`#has-new-kangis-transaction`) to show/hide `#nk-transaction-card`.
- **Add Transaction** clones the first `.nk-transaction-row` as a template
  (`addBtn` click handler, ~line 8828) and appends it; each row after the
  first gets a **Remove** button.
- `collectNewKangisTransactions()` (~line 8854) runs at submit time and reads
  every visible row's inputs (`.nk-txn-instrument`, `.nk-txn-date`,
  `.nk-txn-serial`, `.nk-txn-page`, `.nk-txn-vol`, `.nk-txn-reg-date`,
  `.nk-txn-reg-time`, `.nk-txn-grantor`, `.nk-txn-grantee`) into an array of
  plain objects. **Fully-empty rows are silently dropped** — a blank "Add
  Transaction" row you never filled in is not sent.
- Unchecking the box (or a registry change to KANGIS/SLTR — see the earlier
  change to the same file) calls `resetNewKangisTransactions()`, which
  collapses back to a single blank row.

### 2.3 What actually gets POSTed

`submitFileIndexingForm()` assembles one JSON object (`formData`) and sends it
as the raw body of the request — **not** a multipart form:

```js
// public/js/fileindexing/create-indexing-dialog.js:5686-5696
kangis_file_type: document.getElementById('kangis_file_type')?.value || null,
mls_file_no: document.getElementById('mls_file_no_hidden')?.value || null,
kangis_file_no: document.getElementById('kangis_file_no_hidden')?.value || null,
new_kangis_file_no: document.getElementById('new_kangis_file_no_hidden')?.value || null,
has_new_kangis_fileno: document.getElementById('has-new-kangis-fileno')?.checked ? 1 : 0,
has_new_kangis_transaction: document.getElementById('has-new-kangis-transaction')?.checked ? 1 : 0,
transactions: window.collectNewKangisTransactions(),
```

This is fetched to `fileindexing.store` (create) or `fileindexing.update`
(edit) with `Content-Type: application/json` (~line 6091-6098).

## 3. Server-side: validation

[app/Http/Controllers/FileIndexingController.php:3171-3193](../../app/Http/Controllers/FileIndexingController.php#L3171-L3193)

```php
'has_new_kangis_fileno' => 'nullable|boolean',
'new_kangis_file_no' => [
    Rule::requiredIf(fn () => $request->boolean('has_new_kangis_fileno')),
    'nullable', 'string', 'max:255',
],
'has_new_kangis_transaction' => 'nullable|boolean',
'transactions' => 'nullable|array',
'transactions.*.instrument_type'  => 'nullable|string|max:255',
'transactions.*.transaction_date' => 'nullable|date',
'transactions.*.serial_no'        => 'nullable|string|max:100',
'transactions.*.page_no'          => 'nullable|string|max:100',
'transactions.*.vol_no'           => 'nullable|string|max:100',
'transactions.*.reg_date'         => 'nullable|date',
'transactions.*.reg_time'         => 'nullable|string|max:20',
'transactions.*.grantor'          => 'nullable|string|max:500',
'transactions.*.grantee'          => 'nullable|string|max:500',
```

`new_kangis_file_no` is the only field in this group that's actually
*required* — and only conditionally, when `has_new_kangis_fileno` is truthy.
Everything under `transactions.*` is `nullable`, so partially-filled or empty
rows never block the save (the client already strips fully-empty rows before
sending).

## 4. Server-side: where each piece is persisted

### 4.1 `new_kangis_file_no` (and its siblings) → `file_indexings` row itself

`new_kangis_file_no`, `kangis_file_type`, `mls_file_no`, and `kangis_file_no`
are all in `FileIndexing::$fillable` / `FileIndexing::columnWhitelist()`
([app/Models/FileIndexing.php:94-97, 211-214](../../app/Models/FileIndexing.php#L94-L97)),
so they're written directly onto the `file_indexings` row as ordinary columns
in the main insert/update — same transaction as the rest of the file record.

`has_new_kangis_fileno` itself is **not** persisted anywhere — it's purely a
client-side UI gate / server-side validation trigger, not a stored flag.

### 4.2 Transactions → the `pra` table (or `CofO_staging` for CofO rows)

After the main `file_indexings` row is saved, `store()` calls:

```php
// app/Http/Controllers/FileIndexingController.php:4001
$this->syncFileIndexingTransactions($request, $fileIndexing, $propIdForStore);
```

`syncFileIndexingTransactions()` ([app/Http/Controllers/FileIndexingController.php:4655-4757](../../app/Http/Controllers/FileIndexingController.php#L4655-L4757)):

1. Bails immediately if `has_new_kangis_transaction` wasn't checked, or if
   `transactions` is empty.
2. Resolves the New KANGIS file number (`new_kangis_file_no` from the request,
   falling back to the just-saved `$fileIndexing->new_kangis_file_no`) and the
   legacy KANGIS placeholder — both get stamped onto every transaction row.
3. Loops each transaction row and builds one payload per row:
   - `mlsFNo` = the file's `file_number`
   - `NewKANGISFileno` / `kangisFileNo` = the resolved identifiers above
   - `transaction_type` / `instrument_type` = the row's Instrument Type
   - `transaction_date`, `deeds_date` (← Registration Date), `deeds_time` (← Registration Time)
   - `serialNo`, `pageNo`, `volumeNo`
   - `Grantor`/`party_1`, `Grantee`/`party_2`
   - `land_use`, `plot_no`, `location`, `lgsaOrCity` — copied from the parent file record, not the row
   - `source` = `'File Indexing'`, `created_by`/`updated_by` = current user
   - `prop_id` — the same `prop_id` allocated/resolved for the parent file (see the [[propid-per-parcel-not-per-file]] rule: one parcel `prop_id` is shared across every transaction on the file)
4. **Routing by instrument type**: if `instrument_type === 'Certificate of Occupancy'`
   (case-insensitive), the row is redirected into `CofO_staging` instead of
   `pra`, with extra CofO-specific columns (`cofo_type`, `cofo_date`,
   `transaction_time`, `regNo` — built from serial/page/vol via
   `formatRegistrationNumber()`). Every other instrument type goes to `pra`.
5. Insert failures are caught and logged per-row (`Log::warning`) rather than
   aborting the whole save — one bad transaction row doesn't roll back the
   file indexing record.
6. If at least one row was saved, the parent `file_indexings` row is updated:
   `$fileIndexing->update(['has_transaction' => 1])` — this is the only trace
   left on the file record itself that transactions exist; the actual data
   lives in `pra`/`CofO_staging`.

### 4.3 Net effect

- One `file_indexings` row per file, carrying the KN-series number as a plain
  column plus a `has_transaction` boolean flag.
- N rows in `pra` (or `CofO_staging` for CofO instruments) — one per non-empty
  transaction row submitted, each pointing back at the file via `mlsFNo` /
  `NewKANGISFileno` / `kangisFileNo`, and all sharing one `prop_id`.
- No foreign key enforces the link; retrieval is by matching `mlsFNo` back to
  `file_indexings.file_number` (see `getTransactionsAttribute`-style lookups
  elsewhere in the codebase, e.g. line 2461 counting `has_transaction`).
