 

## 🧩 Feature: Extend Print File Labels to Support Multiple Registries

### 🔎 Data Source Reference

Registry dropdown must be populated from:

```sql
SELECT id, name, code
FROM klas.dbo.registries
WHERE is_active = 1
```

---

## 1️⃣ Registry Dropdown Logic (Backend + UI)

### ✅ Behaviour

* Populate **Registry** dropdown using the `registries` lookup table.
* **Exclude Lands Registry** from this dropdown:

  * `code = 'LANDS'` must not appear.
* This dropdown applies **only to non-Lands registries**.

### 🔧 Developer Tasks

* Backend:

  * Create a service/repository method:
    `getPrintableRegistries()`
  * Filter:

    ```sql
    WHERE code != 'LANDS'
    ```
* Frontend:

  * Bind dropdown to API response.
  * Display `name`, submit `code` or `id`.

---

## 2️⃣ File Fetching Logic (Core Change)

### ✅ Rule

For **non-Lands registries**, file numbers and tracking IDs must come from:

```
file_indexings
```

Filtered by:

* `general_registry` (matches registry `code`)
* Other existing filters (year, batch, etc.)

### 🔧 Developer Tasks

* Update file loader query:

  ```sql
  SELECT *
  FROM file_indexings
  WHERE general_registry = :selected_registry
  ```
* Ensure:

  * No Lands records are fetched here
  * Existing Lands logic remains untouched

---

## 3️⃣ Print Mode Rules (Shared Across Registries)

### 📦 Batch Limits

* **Max labels per print job:** `500`
* **Shelf/Rack capacity:** `100 labels`

### 🔧 Developer Tasks

* Validation layer:

  * Reject load if selected records > 500
  * Enforce 100-label cap per shelf before rolling over

---

## 4️⃣ Shelf / Rack Allocation Logic (Registry-Scoped)

### ✅ Rules

* Shelf/Rack identifiers (e.g. `A1`) are **registry-specific**
* Same Shelf/Rack **can exist across different registries**
* Same registry:

  * Can reuse the same Shelf/Rack in future batches
  * Must never exceed 100 labels per shelf **per batch**

### ✅ Examples

| Registry | Shelf           | Allowed |
| -------- | --------------- | ------- |
| DCIV     | A1              | ✅       |
| Deeds    | A1              | ✅       |
| DCIV     | A1 (101 labels) | ❌       |

### 🔧 Developer Tasks

* When calculating shelf usage:

  * Scope counts by:

    ```
    registry + shelf + batch
    ```
* Do **not** enforce global uniqueness on shelf names.

---

## 5️⃣ UI Behaviour Updates (Based on Screenshot)

### 🖥️ Registry Selection Area

* Replace hardcoded “Registry 1” with:

  * Dynamic registry dropdown (from lookup table)
* Disable registry dropdown when:

  * Lands Registry mode is active (existing flow)

### 📊 Capacity Indicators

* Label capacity counter (`100 / 100`) must:

  * Reset per registry
  * Reset per shelf
* Overflow behaviour:

  * Auto-roll to next shelf (A2, A3…)

---

## 6️⃣ Lands Registry (Backward Compatibility)

### 🚫 No Changes Allowed

* Lands Registry:

  * Continues using existing Print File Labels logic
  * Continues using its current dropdown
  * Does **not** depend on `general_registry`

### 🔧 Developer Safeguard

* Feature flag or conditional:

  ```php
  if ($registryCode === 'LANDS') {
      useOldLogic();
  } else {
      useNewRegistryLogic();
  }
  ```

---

## 7️⃣ Validation & Edge Cases

### ✔ Must Handle

* Registry selected but no matching files → show empty state
* Attempt to print >500 labels → block action
* Shelf at capacity → auto-increment shelf
* Mixed registries in one batch → ❌ disallowed

---

## 8️⃣ Deliverables Checklist

* [ ] Registry lookup API
* [ ] Updated registry dropdown (non-Lands)
* [ ] File loader using `file_indexings.general_registry`
* [ ] Shelf/rack registry-scoped validation
* [ ] Batch & capacity enforcement
* [ ] Lands Registry isolation preserved

---

 