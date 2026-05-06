# Cross-Registry File Request Workflow

This document outlines the workflows and UI requirements for inter-departmental file requests between Land and Kangis.

## 1. KANGIS Workflow (Kangis Requesting from Lands)
* **Requester:** KANGIS
* **Supply (Origin):** Land Registry
* **Approval Chain:** 
  1. Land Registry -> *Recommendation*
  2. Director of Lands -> *Approval*
* **Result:** File is routed to KANGIS.

## 2. LAND Workflow (Lands Requesting from Kangis)
* **Requester:** Land Department
* **Supply (Origin):** Kangis Registry
* **Approval Chain:**
  1. Kangis Registry -> *Recommendation*
  2. DG Kangis -> *Approval*
* **Result:** File is routed to the Land Department.

## 3. UI & Implementation Requirements
* **Remove Auto-Triggers:** The specific alert (*"File Request Confirmation: You are about to send a file request to Land Registry."*) should **no longer trigger automatically** just by selecting a Registry (Origin) and Destination Office (Department).
* **Explicit 'Request File' Button:** A dedicated button must be added to manually trigger the cross-registry file request.
* **Workflow UI Adjustments:** The frontend UI must be adjusted to explicitly support and indicate the structured approval chains (Recommendation → Director/DG Approval) based on the direction of the request. 

 