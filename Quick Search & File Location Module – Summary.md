# Quick Search & File Location Module – Summary

## Objective

update on the log file mobile app ,
 Quick Search feature that allows designated archive user(file searcher) to instantly determine the current location and status of a file without relying on WhatsApp or manual inquiries.

## Process Flow

1. User enters a File Number.
2. System checks file location records.
3. System returns the current status and next action.

## Possible Results

### 1. In Transit

The file is currently assigned to an officer or department.

Display:

* Current Location
* Department/Officer Responsible

Action:

* Requester follows up with the responsible office.
Print KLAES FILE TRACKING REQUEST SHEET (already exist)
### 2. In Archive

The file is available in the archive.

Display:

* Archive Location (Rack/Shelf)

Action:

* file searcher retrieves the file.

### 3. In Pool Office

The file is located in the Pool Office.

Action:

*  file searcher  conducts a physical search.
* If found, issue the file.
* If not found, mark as File Not Found.

### 4. File Not Found

The file cannot be located.

Action:

* Generate a "File Not Found" KLAES FILE TRACKING REQUEST SHEET (already exist).
* Refer the case to Front Desk for investigation.

### 5. Refer to Original Registry

Investigation confirms the file was never transferred to the archive.

Action:

* Generate a "Refer to Original Registry" use KLAES FILE TRACKING REQUEST SHEET (already exist).

---

## User Responsibilities

###  file searcher 

* Search file locations.
* View file status.
* Print slips.
* Retrieve files from archive.

 

## Status Values

```text
IN_TRANSIT
IN_ARCHIVE
IN_POOL_OFFICE
FILE_NOT_FOUND
 

## Key Business Rule

Every search must return a clear outcome:

* In Transit
* In Archive
* In Pool Office
* File Not Found  , Refer to Original Registry
 


No search should end without a status and next action.
