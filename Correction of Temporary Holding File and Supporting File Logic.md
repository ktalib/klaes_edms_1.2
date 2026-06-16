 

# Correction of Temporary Holding File and Supporting File Logic

Using **COM-2026-122** as the current example:

The first workflow performed was a **Merger**. During this process:

* A **Temporary Holding File Number** was created: **TEMP-81813**
* The resulting **Supporting File Number** was: **COM-2026-122**

Later, a second workflow (**Subdivision**) was performed using the **Continue Existing Chain** option:
*  **Temporary Holding File Number** was created: **TEMP-81813**
* The Supporting File remained **COM-2026-122**
* A new linked legacy file was **RES-2024-5534**

At this stage, both workflows are correctly linked and maintained under:

* Supporting File Number: **COM-2026-122**
* Temporary Holding File Number: **TEMP-81813**

The current implementation is working correctly in this area. However, the missing logic is the activation behavior.

---

# 1. Rename the Option

Change the label:

**Current:**

```
Use a Temporary Holding FileNo
```

**New:**

```
Activate Supporting FileNo & Temporary Holding FileNo
```

The purpose of this option is to indicate that the user wants to preserve the Supporting File Number and Temporary Holding File Number across multiple legacy workflows.

---

# 2. Workflow Sequence Is Not Fixed

There is no official starting sequence for legacy workflow linkage.

The user can start with any workflow, for example:

* Merger
* Subdivision
* Plot Extension
* Change of Purpose

The order does not matter.

If the user activates **Supporting FileNo & Temporary Holding FileNo**, the system must:

* Keep the Supporting File active.
* Keep the Temporary Holding File active.
* Do **not** deprecate or decommission the linked legacy files after the workflow is completed.
* Allow the user to continue additional legacy workflows using **Continue Existing Chain**.

---

# 3. When Activation Is Not Selected

If the user does **not** activate the **Supporting FileNo & Temporary Holding FileNo** option, it means the current workflow is the final workflow in the chain.

In this case, the system should:

* Complete the linkage.
* Deprecate/decommission the legacy file(s) involved in the current workflow.
* Keep the final processed destination file active.

---

# Example: Final Change of Purpose Workflow

Continuing with the COM-2026-122 example:

Previous workflows:

1. Merger → Created Supporting File: **COM-2026-122**
2. Subdivision → Linked legacy file: **RES-2024-5534**

Now the final workflow is **Change of Purpose**.

At this stage, the user does **not** need to activate **Supporting FileNo & Temporary Holding FileNo** again because no additional workflow will follow.

The user will select:

* **Legacy File:** RES-2024-5534
* **Changed Purpose / New File:** COM-2026-122

When this final workflow is saved:

* The legacy file **RES-2024-5534** should be deprecated/decommissioned.
* The Supporting File **COM-2026-122** should remain active.
* The Temporary Holding File chain can be considered completed.

---

# Important Business Rule

The legacy workflow process is not linear. A user can start with:

* Subdivision → Merger → Change of Purpose
* Merger → Plot Extension → Subdivision
* Plot Extension → Change of Purpose
* Any other valid sequence

The system must not assume any fixed order.

The **Activate Supporting FileNo & Temporary Holding FileNo** option determines whether the chain remains open for additional legacy workflows or whether the current workflow is the final one and the legacy files should be decommissioned.

---

This logic ensures that a Supporting File (such as **COM-2026-122**) is never wrongly decommissioned while it is still required for additional legacy workflow linkages.
