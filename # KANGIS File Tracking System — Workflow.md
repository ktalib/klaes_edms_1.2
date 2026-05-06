# KANGIS File Tracking System — Workflow Update Specification

## 📌 Overview

This document defines the **updated workflow logic and UI behavior** for the existing KANGIS File Tracking System.

> ⚠️ Important:
> This is **NOT a new system design**.
> It is an **update/refinement of the current system**, focusing on:

* Step control (activation/visibility)
* Clear approval flow
* Removal of redundant routing (e.g., multiple “Receiving Office” paths)
* Introduction of a structured **post-approval processing stage**

---

# 🎯 Objectives of the Update

* Ensure only valid steps are actionable at any time
* Standardize file movement across roles
* Improve UI clarity for users
* Eliminate ambiguous workflow branches
* Introduce a clean **Department Processing Queue**

---

# 🔄 Updated Workflow Summary

## Steps Definition

| Step   | Name           | Role            | Status                |
| ------ | -------------- | --------------- | --------------------- |
| Step 1 | Submission     | KANGIS Registry | Active by default     |
| Step 2 | Recommendation | Director GIS    | Locked initially      |
| Step 3 | Approval       | DG KANGIS       | Locked initially      |
| Step 4 | Processing     | Department      | Locked until approval |

---

# 🧩 Step-by-Step Workflow Logic

## 🔹 Step 1 — Submission (KANGIS Registry)

### Description

* File is created and registered
* Sent to Director GIS for recommendation

### Action

* Submit / Forward

### Result

* `status = submitted`
* `step = 2`
* `current_office = DIRECTOR_GIS`
 so when i can to jalingo you were the only person that  i had with, in yalo and jalingo, 
 and no one esle , just you
---

## 🔹 Step 2 — Recommendation (Director GIS)

### Description

Director reviews the file and decides whether to recommend it.

### Available Actions

* ✅ Recommend for Approval
* ❌ Not Recommend

---

### Logic

#### If Recommended

```
status = recommended
step = 3
current_office = DG_KANGIS
```

#### If Not Recommended

```
status = not_recommended
step = 1
current_office = KANGIS_REGISTRY
```

---

## 🔹 Step 3 — Approval (DG KANGIS)

### Description

Final decision stage.

### Available Actions

* ✅ Approve
* ❌ Reject

---

### Logic

#### If Approved

```
status = approved
step = 4
current_office = DEPARTMENT_QUEUE
```

#### If Rejected

```
status = rejected
step = 1  // or terminate process depending on business rule
current_office = KANGIS_REGISTRY
```

---

## 🔹 Step 4 — Processing (Department Dashboard)

### Description

This is a **newly clarified stage** introduced in the update.

After approval:

* File is no longer sent to a generic “Receiving Office”
* Instead, it enters a **Department Queue**

---

### Department Examples

* Deeds Department
* Land Records
* Planning

---

### Action

* Assign file to a department
* Begin processing

### Logic

```
status = in_processing
current_department = <assigned_department>
```

---

# 🚫 Removed / Deprecated Flow

The following have been removed:

* ❌ Multiple “Receiving Office” endpoints
* ❌ Duplicate routing paths after approval
* ❌ Ambiguous handoffs between departments

---

# ✅ New Standard Flow

```
Submission → Recommendation → Approval → Department Processing
```

---

# 🎨 UI/UX Behavior

## Step Activation Rules

### Initial State

* Step 1 → Active
* Step 2 → Disabled (greyed)
* Step 3 → Disabled (greyed)
* Step 4 → Disabled or hidden

---

### Progression

#### After Step 1

* Step 1 → Completed
* Step 2 → Active

#### After Step 2 (Recommended)

* Step 2 → Completed
* Step 3 → Active

#### After Step 3 (Approved)

* Step 3 → Completed
* Step 4 → Active

---

## UI State Classes (Suggested)

| State     | Meaning           |
| --------- | ----------------- |
| active    | Current step      |
| completed | Finished step     |
| disabled  | Not yet available |

---

# 🧠 Backend State Management

## Core Fields (Existing System — Ensure Alignment)

* `step`
* `status`
* `current_office`
* `current_department` (new usage emphasis)

---

## Recommended Status Values

* submitted
* recommended
* not_recommended
* approved
* rejected
* in_processing

---

# 🗂️ Audit / Tracking (Recommended Enhancement)

To improve traceability, introduce or ensure use of a movement log table:

### Table: `file_movements`

| Column      | Description   |
| ----------- | ------------- |
| id          | Primary key   |
| file_id     | file    |
| from_office | Source        |
| to_office   | Destination   |
| action      | Action taken  |
| comment     | Optional note |
| created_at  | Timestamp     |

---

# 🔐 Role Responsibilities

| Role            | Responsibility          |
| --------------- | ----------------------- |
| KANGIS Registry | File submission         |
| Director GIS    | Recommendation decision |
| DG KANGIS       | Final approval          |
| Department      | Processing              |

---

# ⚠️ Implementation Notes

* Do NOT rebuild the system — apply updates to existing logic
* Ensure backward compatibility with current data
* UI should reflect **state, not just step number**
* Prevent users from skipping steps
* Enforce role-based permissions per step

---

# ✅ Summary

This update introduces:

* Controlled step progression
* Clear approval chain
* Structured post-approval processing
* Removal of redundant workflow paths

The system is now:
✔ Predictable
✔ Traceable
✔ Scalable

---

# 🚀 Next Steps (Optional Enhancements)

* Stepper UI component (Bootstrap / Vue)
* AJAX-based step transitions
* Notification system (on step change)
* Dashboard filtering by `current_office` / `department`

---
