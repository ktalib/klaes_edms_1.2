# KANGIS Indexing Flow

## Overview

The KANGIS Indexing process supports the creation and linkage of three independent files:

1. **Land File**
2. **Old KANGIS File**
3. **New KANGIS File (if applicable)**

Each file is indexed independently but maintains relationships through **Related File Numbers** and **Parent Property ID (ParentPropID)**.

---

# 1. Index the Land File

* Index the **Land File** as a **standalone** file.
* During indexing, quote/reference any **related file number(s)** (e.g., the Old KANGIS File Number).
* The Land File will receive:

  * Its own **Property ID (PropID)**.
  * A **ParentPropID**, which is the PropID of the Old KANGIS File.

---

# 2. Index the Old KANGIS File

Index the **Old KANGIS File** as a standalone file.

During indexing:

* Reference the **Land File** as a related file.
* The Old KANGIS File receives:

  * Its own **Property ID (PropID)**.
* Since this is the parent record, **ParentPropID is not required**.

---

# 3. Index the New KANGIS File (Optional)

If the indexing officer selects **"New KANGIS File Number"**:

The system should automatically:

* Generate/backfill the new KANGIS File Number record.
* Create a standalone indexing record for the new KANGIS File.
* Link it to the related Land File and Old KANGIS File.

The New KANGIS File will receive:

* Its own **Property ID (PropID)**.
* A **ParentPropID**, which is the PropID of the Old KANGIS File.

---

# 4. Property ID Rules

There are **three independent Property IDs**.

| File            | Own PropID | ParentPropID      |
| --------------- | ---------- | ----------------- |
| Old KANGIS File | Yes        | —                 |
| Land File       | Yes        | Old KANGIS PropID |
| New KANGIS File | Yes        | Old KANGIS PropID |

### Example

| File            | PropID | ParentPropID |
| --------------- | ------ | ------------ |
| Old KANGIS File | 1001  | —            |
| Land File       | 2001  | 1001        |
| New KANGIS File | 3001  | 1001        |

---

# 5. File Relationships

Each file remains independent but references the others through **Related File Numbers**.

```text
                     Old KANGIS File
                 File No: MLKN 1934
                 PropID : 1001
                       │
          ┌────────────┴────────────┐
          │                         │
          │ ParentPropID=1001      │ ParentPropID=1001
          │                         │
   Land File                 New KANGIS File
 File No: RES-2025-101       File No: KN67890
 PropID : 2001              PropID : 3001
```

---

# 6. Business Rules

* Each file has its own independent indexing record.
* Each file has its own unique Property ID (PropID).
* The **Old KANGIS File** is the **parent property**.
* The **Land File** and the **New KANGIS File** store the Old KANGIS File's PropID as their **ParentPropID**.
* Every file should be searchable independently.
* Opening any of the three files should display the other related file numbers.
* Relationships between files should be maintained through the **Related File Numbers** table, while the **ParentPropID** maintains the property hierarchy.

This approach preserves independent file identities while ensuring that all related records can be traced back to the original (Old KANGIS) property.
