# Quick Search & File Location – Updated Business Rules

## 1. In Archive

When Quick Search determines that a file is **In Archive**, the system should display a:

**Send File Search Request to SCB** button.

The Front Desk officer sends the request to SCB.

### SCB Response

The SCB officer receives the request on the mobile application and must select one of the following options:

* Found
* Not Found

### 1a. SCB Selects "Found"

The file status becomes:

```text
IN_ARCHIVE - FOUND
```

The Front Desk user will see the SCB feedback in the Quick Search dashboard.

When the Front Desk opens the record, a **Log File** button should be displayed.

Selecting **Log File** should:

* Redirect the user to `/create-file-tracker`
* Automatically backfill:

  * File Number
  * File Title
* Allow the Front Desk officer to complete the remaining tracking details.

**Note:** At this stage, the file has not yet been logged out, therefore the system should NOT display the "Print Tracking Sheet" option.

### 1b. SCB Selects "Not Found"

The file status becomes:

```text
IN_ARCHIVE - NOT FOUND
```

When the Front Desk opens the record, the system should display:

**Refer to Original Registry**

The user can then print the **Refer to Original Registry Sheet**.

---

## 2. In Pool Office

The workflow is identical to the Archive process.

When Quick Search determines that a file is **In Pool Office**, the system should display:

**Send File Search Request to SCB**

The SCB officer responds with:

* Found
* Not Found

### 2a. Found

Status:

```text
IN_POOL_OFFICE - FOUND
```

Actions available to Front Desk:

* Log File

The Log File button redirects to:

```text
/create-file-tracker
```

and automatically backfills:

* File Number
* File Title

The Front Desk officer completes the remaining tracking information.

### 2b. Not Found

Status:

```text
IN_POOL_OFFICE - NOT FOUND
```

Actions available:

* Refer to Original Registry
* Print Refer to Original Registry Sheet

---

## 3. Pending File (Blind Request)

A Pending File (Blind Request) refers to a file that has not yet been indexed and therefore cannot be located through normal file searches.

When a file is not indexed:

Display:

**Send Blind Request to SCB Monitor**

The Front Desk officer sends the Blind Request to SCB.

The SCB team will then manually search for the file and provide feedback.

---

## 4. SCB Feedback Dashboard

The **Log File** button should not be displayed immediately after sending a request because the Front Desk user may continue searching for other files.

Instead, the Quick Search & File Location screen should contain a section showing all responses received from SCB.

Example:

| File No | File Title   | Location Type | SCB Response |
| ------- | ------------ | ------------- | ------------ |
| KN 3071 | ABC Property | Archive       | Found        |
| KN 4588 | XYZ Property | Pool Office   | Not Found    |

When the Front Desk user clicks a record, the system should display the SCB feedback and available actions.

### If SCB Response = Found

Display:

* Log File

Action:

* Redirect to `/create-file-tracker`
* Auto-fill:

  * File Number
  * File Title
* Front Desk completes the remaining tracking details.

### If SCB Response = Not Found

Display:

* Refer to Original Registry

Action:

* Generate and print the Refer to Original Registry Sheet.
* No Log File button should be displayed.

---

## Final Status Values

```text
IN_ARCHIVE
IN_ARCHIVE_FOUND
IN_ARCHIVE_NOT_FOUND

IN_POOL_OFFICE
IN_POOL_OFFICE_FOUND
IN_POOL_OFFICE_NOT_FOUND

PENDING_FILE
BLIND_REQUEST_SENT

REFER_TO_ORIGINAL_REGISTRY
```
