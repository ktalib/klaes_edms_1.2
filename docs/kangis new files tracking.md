 
## **KANGIS Tracking For New FIles Overview**

 
### **1. Tracking Categories**
 
* **Exsiting Files:**  Already Done (the currrent kangis file tracker workflow which is ok).
 

### **2. Workflow for "New Files (New KANGIS)"**
The process originates from an Indexing tracking sheet and moves through the following stages:
if the New KANGIS is selected and they havent indexed theere should be yes or no cofirmation, yes will open the  Create New File Index page in a new tab and the user wil index file , 

once the Customer Service scan the qr , will log the file in their department

1.  **Origin:** Indexing Tracking Sheet
2.  **Customer Service:**  
3.  **Vetting Committee:**  
4.  **Geometry (GIS):**  
5.  **Production (GIS):**  
6.  **Collection (DG):**  
7.  **Registry:** Final recording and filing.
  

each of these steps , will send sms to the applicaint (cus they will catpture the Phone Number in Create New File Index)  each time the time the file is log in to a new Destination Office (Departments) */Receiving Office * . (there is no sms live api for now but the resquest should go through but with a sweetlart that will display somthing like tihis, succuccfull but sms did not send no valid api key)
 
so when indexing the new (which is the first step)
the user will select the following file number 

MLS
KANGIS and
New KANGIS

 





and onces the he file has been indexed using the normal indexing page but for KANGIS Registry the user can select MLS
KANGIS and New KANGIS is the master file nuber the will go the file_number, and oin the indexing custome rand entitie tables  add   MLS
KANGIS and New KANGIS,
  so we no need to update the "Create File Index
Create a new file index record" ui a bit and also enable the New KANGIS

the grouping table table for this new kn_grouping, and the 
resources\views\fileindexing\batch-tracking-sheet.blade.php to kangis_tracking-sheet.blade.php  and all the 3 file nuber s should
displayed 

thee tracking sheet willbe used  to log to the following  Destination Office (Departments) */Receiving Office *  

2.  **Customer Service:**  logout
3.  **Vetting Committee:**  logout
4.  **Geometry (GIS):**   logout
5.  **Production (GIS):**  logout
6.  **Collection (DG):**   logout
7.  **Registry:**  Login back to the registry
 by just scan the qr code which have  the tracking id using the "Search File Trackers" modal

## **KANGIS Tracking Diagram for New FIles**

| **Existing Files** | **New Files** |
| :--- | :--- |
| *(Blank/Unspecified)* | **Indexing Tracking Sheet (Origin)** |
| | $\downarrow$ |
| | **Customer Service** |
| | $\downarrow$ |
| | **Vetting (Committee)** |
| | $\downarrow$ |
| | **Geometry (GIS)** |
| | $\downarrow$ |
| | **Production (GIS)** |
| | $\downarrow$ |
| | **Collection (DG)** |
| | $\downarrow$ |
| | **Registry** |





