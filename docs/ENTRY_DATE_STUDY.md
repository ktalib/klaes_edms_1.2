# Entry Date — Field Study

## Overview

The **Entry Date** (`entryDate`) field in the Occupancy Permit (OP) instrument capture form represents the date the document was entered/captured into the system. It is distinct from the **Transaction Date** (`transactionDate`), which represents the legal/official date of the transaction.

---

## Field Definition

| Attribute | Value |
|-----------|-------|
| **HTML ID** | `entryDate` |
| **HTML Name** | `entryDate` |
| **Input Type** | `date` |
| **Default Value** | Current date (`Carbon::now()->format('Y-m-d')`) |

### Template Locations

| File | Lines | Context |
|------|-------|---------|
| `resources/views/instruments/create_updated.blade.php` | 419–421 | Main instrument capture form |
| `resources/views/instruments/partials/register_modal.blade.php` | 230–231 | Registration modal (read-only) |

---

## Auto-Fill Logic

**Source:** `public/js/instruments-capture.js` (~line 2080)

When an OP serial number lookup succeeds, Entry Date is auto-filled with this priority:

```
created_at → entry_date → instrument_date → transaction_date
```

The system capture timestamp (`created_at`) takes priority over the OP's own entry date.

### Editable States

| Page / Context | Editable? |
|----------------|-----------|
| Instrument Capture create page | ✅ Yes |
| Deeds Registration modal | 🔒 Read-only |
| Registration modal | 🔒 Read-only |

Controlled in JS (~line 2549–2580) based on the current page context.

---

## Data Flow

### Frontend → Backend

1. User enters or accepts the Entry Date value in the form.
2. On submit, `new FormData(elements.registrationForm)` collects it as `entryDate`.
3. Payload is sent via `fetch()` POST to the instrument capture endpoint.

### Backend Storage

**Service:** `app/Services/InstrumentCaptureService.php` (line 270)

```php
'instrument_date' => $data['entryDate'] ?? ($data['instrumentDate'] ?? ($data['dateOfExecution'] ?? null))
```

- `entryDate` maps to the **`instrument_date`** column in the `instrument_capture` table.
- It does **NOT** map to any column in the `pra` table.

---

## Entry Date vs Transaction Date

| Aspect | Entry Date | Transaction Date |
|--------|-----------|------------------|
| **Semantic** | When document was captured/entered into the system | When the legal transaction occurred |
| **Form Field** | `entryDate` | `transactionDate` |
| **DB Column** | `instrument_capture.instrument_date` | `pra.transaction_date` |
| **UI Behavior** | Mostly read-only; editable on create page | Always editable |
| **Validation** | Not validated for PRA | `'nullable\|date'` |
| **Auto-Fill Source** | `created_at` (priority) | `transaction_date` (priority) |
| **OSS OP Flow** | Informational only | **Required** for PRA row creation |

### Key Distinction

- **Entry Date** is an operational/audit field — it records *when* the data was entered into KLAES.
- **Transaction Date** is a legal field — it records *when* the official transaction took place (e.g., OP grant date, transfer date).
- Only `transactionDate` flows into the `pra` table as `transaction_date`.
- `entryDate` is stored in `instrument_capture.instrument_date` for record-keeping.

---

## OSS OP Change-of-Name Flow

In the OSS OP flow (`ApplicationController::directOpCapture` and `captureFfrExisting`):

1. **Direct OP Capture** — `transaction_date` comes from `transactionDate` field in the OP form. Entry Date is informational only.
2. **Capture Existing (Transfer of Title)** — `transaction_date` is collected from `commission_date` (auto-filled with today's date) as a fallback since the capture existing modal has no dedicated `transaction_date` field.

### Current Behavior

- The OP form shows Entry Date auto-filled with today's date (`03/31/2026`).
- Transaction Date is a separate field the user must fill manually.
- Entry Date does not propagate to the PRA record; only Transaction Date does.

---

## Database Schema

**Table:** `instrument_capture`  
**Column:** `instrument_date` (`date`, nullable)  
**Migration:** `database/migrations/2026_01_30_160000_create_instrument_capture_table.php` (line 81)

**Table:** `pra`  
**Column:** `transaction_date` (`date`, nullable)  
**Note:** No `entry_date` or `instrument_date` column exists in `pra`.

---

## File References

| File | Key Lines | Purpose |
|------|-----------|---------|
| `resources/views/instruments/create_updated.blade.php` | 419–421 | HTML form field |
| `resources/views/instruments/partials/register_modal.blade.php` | 230–231 | Modal HTML (read-only) |
| `public/js/instruments-capture.js` | 240, 2080, 2549–2580, 4274 | JS: listeners, auto-fill, state, submission |
| `app/Services/InstrumentCaptureService.php` | 270 | Backend mapping: `entryDate` → `instrument_date` |
| `app/Http/Controllers/LandsOneStopShop/ApplicationController.php` | 1277, 1605 | OSS flow: validation & PRA creation |
| `database/migrations/2026_01_30_160000_create_instrument_capture_table.php` | 81 | Schema definition |
