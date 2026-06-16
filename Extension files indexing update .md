
  File Number Extension Handling
Objective
Modify the system logic to parse file numbers containing extensions so that tracking IDs can be successfully retrieved, while ensuring the original full extension string is still saved to the database upon form submission.
1. Lookup Logic (Retrieving Tracking ID)

* Problem: When a file number includes an extension (e.g., AND EXTENSION), the system fails to match it against the awaiting base file number, making it impossible to retrieve the tracking ID.
* Solution: The system must strip out the extension string for lookup purposes. If the input contains " AND EXTENSION", the system should ignore that part and use only the base file number.
* Example:
* User Input/File: RES-2026-10 AND EXTENSION
   * System Look-up Value: RES-2026-10

2. Form Submission Logic (CRITICAL)

* Requirement: When the user submits the form, the system must not save the stripped version. It must insert the full file number, including the extension string, into the database.
* Example:
* Saved Database Value: RES-2026-10 AND EXTENSION

------------------------------
## For Developers: Implementation Logic (Pseudo-code)

// Step 1: Extract base file number for Tracking ID lookup
if (fileNumber.contains(" AND EXTENSION")) {
    lookupNumber = fileNumber.replace(" AND EXTENSION", "");
} else {
    lookupNumber = fileNumber;
}
trackingId = getTrackingId(lookupNumber);

// Step 2: Form Submission (Save to Database)
// Ensure the original 'fileNumber' variable (with " AND EXTENSION") is used in the INSERT/UPDATE query.
database.save(fileNumber); 
 

