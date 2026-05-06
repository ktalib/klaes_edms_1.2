# Print Manager Integration — ST RoFO & ST Certificate of Occupancy

> **Goal**: Replace the legacy "First Print / Second Print / Third Print" batch pattern on the
> RoFO and CofO pages (`/programmes/rofo` and `/programmes/certificates`) with the global
> **Print Manager** component (`<x-print-manager />` + `SmartPrintManager.open()`).

---

## Background — How the Print Manager Works

| File | Purpose |
|------|---------|
| `resources/views/components/print-manager.blade.php` | Alpine.js modal component |
| `public/js/print-manager.js` | Helper: `SmartPrintManager.open(ref, type, url)` |
| `app/Http/Controllers/PrintManagerController.php` | Endpoints: `log`, `checkStatus`, `batchLog` |
| `print_logs` table | Tracks every print action per `reference_number` + `document_type` |

### Two operating modes (controlled by `isSingleStepType` in the component)

| Mode | Sequence | When used |
|------|----------|-----------|
| **Multi-step** | 3-step: Original → Duplicate → Triplicate (watermarks) | Default for most docs |
| **Single-step** | 1-step: Original only (no watermark progression) | When `docType` is in the `isSingleStepType` list |

### Existing print flow for reference (Lands module RoFO)
Already integrated in `resources/views/land_rofos/index.blade.php`:
```js
SmartPrintManager.open('{{ $rec->file_number }}', 'Land RofO', '{{ route("land-rofos.print", $rec->id) }}');
```
This opens the Print Manager modal in **multi-step** mode (Original / Duplicate / Triplicate),
and after completion offers **Certified True Copy (CTC)**.

---

## What Must Change

### 1. ST RoFO (`/programmes/rofo`) — PuA "Generated" Tab

**Current behaviour** (in `rofo_pua_tables.blade.php`, lines 163-198):
- The action menu has 3 hardcoded links: "First Print", "Second Print", "Third Print"
- Each link maps to `ORIGINAL`, `DUPLICATE`, `TRIPLICATE` watermark, enabled sequentially
  based on `print_counter`
- These links open a direct URL — no modal, no logging to `print_logs`

**Required change**:
- **Remove** the 3 individual print links (First/Second/Third Print)
- **Add** a single **"Print Original"** action that calls:
  ```js
  SmartPrintManager.open(
      '{{ $summary->primary_file_no }}',
      'ST RoFO',
      '{{ route("programmes.print_rofo", $firstUnitId) }}?batch_primary={{ $summary->main_application_id }}'
  );
  ```
- The Print Manager opens in **multi-step** mode:
  - "Print Original" → generates the full set: **Original, Duplicate, Triplicate** at once
    (batch prints all PuA units under the parent primary)
  - After completion → "Print Certified True Copy" button appears for CTC generation
- Also add an **Individual Batch** option per PuA unit (in the PuA units modal) that follows
  the same pipeline but prints only that single unit

### 2. ST RoFO (`/programmes/rofo`) — SuA "Generated" Tab

**Current behaviour** (in `rofo_sua_tables.blade.php`, lines 111-132):
- Same 3 hardcoded links: "First Print", "Second Print", "Third Print"
- Each SUA is standalone (no parent), so these print individually

**Required change**:
- **Remove** the 3 individual print links
- **Add** a single **"Print Original"** action that calls:
  ```js
  SmartPrintManager.open(
      '{{ $unitApplication->fileno }}',
      'ST RoFO',
      '{{ route("programmes.view_rofo", $unitApplication->id) }}'
  );
  ```
- The Print Manager opens in **multi-step** mode (same as PuA)
- SUAs are standalone — no parent grouping, so batch prints only the single unit
- After Original → CTC flow is the same

### 3. ST CofO (`/programmes/certificates`) — PuA "Generated" Tab

**Current behaviour** (in `certificates.blade.php`, lines 641-662):
- The "Batch Print Unit Certificates" link and other buttons are **commented out**
- No print functionality is active on this tab

**Required change**:
- **Uncomment and replace** with a Print Manager action:
  ```js
  SmartPrintManager.open(
      '{{ $primary->fileno }}',
      'ST CofO',
      '{{ route("programmes.print_cofo_batch", $primary->id) }}'
  );
  ```
- The Print Manager opens in **single-step** mode (add `'ST CofO'` to `isSingleStepType` list):
  - **No** Original/Duplicate/Triplicate watermark progression — watermarks should be **hidden**
  - "Print Original" → prints all PuA unit certificates under the parent at once
  - After completion → "Print Certified True Copy" button appears for CTC
- Also provide an **Individual Batch** option per PuA unit (same pipeline, single unit)

### 4. ST CofO (`/programmes/certificates`) — SuA "Generated" Tab

**Current behaviour**: Similar to PuA — no print actions active yet

**Required change**:
- Add Print Manager action per SUA unit:
  ```js
  SmartPrintManager.open(
      '{{ $application->fileno }}',
      'ST CofO',
      '{{ route("programmes.print_cofo", $application->id) }}'
  );
  ```
- Uses **single-step** mode (same as PuA CofO — no watermarks)
- SUAs are standalone, so batch = single unit only
- Same Original → CTC flow

---

## Changes to the Print Manager Component

### `isSingleStepType` update
In `print-manager.blade.php` (line 213) and `PrintManagerController.php` (line 52),
add `'ST CofO'` to the single-step list:

```js
// print-manager.blade.php
get isSingleStepType() {
    return ['Recommendation For Grant', 'ST CofO'].includes(this.docType);
}
```

```php
// PrintManagerController.php
$isSingleStep = in_array($request->document_type, ['Recommendation For Grant', 'ST CofO']);
```

### Watermark hiding for ST CofO
The print template for ST CofO should **not render** the Original/Duplicate/Triplicate
watermark text. The `?status=` parameter will still be passed but the template should
conditionally hide it when `document_type === 'ST CofO'`.

---

## Summary Table

| Page | Tab | Application Type | Print Mode | Watermarks | Parent Batch? |
|------|-----|-----------------|------------|------------|---------------|
| `/programmes/rofo` | Generated PuA | PuA | Multi-step | Original/Duplicate/Triplicate | ✅ All PuAs under primary |
| `/programmes/rofo` | Generated SuA | SuA | Multi-step | Original/Duplicate/Triplicate | ❌ Single unit only |
| `/programmes/certificates` | Generated PuA | PuA | Single-step | Hidden | ✅ All PuAs under primary |
| `/programmes/certificates` | Generated SuA | SuA | Single-step | Hidden | ❌ Single unit only |

---

## Routes Referenced

| Route Name | Purpose |
|------------|---------|
| `programmes.print_rofo` | Print a single ST RoFO document |
| `programmes.view_rofo` | View/print a single ST RoFO |
| `programmes.print_cofo_batch` | Print all CofO certificates under a primary |
| `programmes.print_cofo` | Print a single CofO certificate |
| `print-manager.status` | Check print status for a reference |
| `print-manager.log` | Log a single print action |
| `print-manager.batch-log` | Log a batch of print actions |

> **Read the agent skill file**: `.agent/skills/klaes/SKILL.md`