 
# Blind Scanning Workflow — blind_scans.blade.php

## Location
blind_scans.blade.php

## Purpose (one line)
A browser UI for packaging a locally-prepared scanned parent folder (with A4/A3 subfolders) into a ZIP and migrating it to the server, plus tools to browse server storage and view migration logs.

## Key features
- Manual local folder selection using the File System Access API (showDirectoryPicker).
<!-- - Automatic analysis of folder structure (detects A4/A3 subfolders, counts files).
- Preview of files grouped by A4 / A3 / root. -->
- Creates ZIP archive client-side (JSZip) and POSTs it to the server migration endpoint.
- Server browser to navigate /public_html/storage, preview files, and open files.
- Migration logs displayed from server (_migrations.json or API).
- Explorer for local C:\edms roots, with helpers to analyze and migrate parent folders directly.
- Progress modal, toasts, and status UI.

## Expected local layout
- Root: C:\edms
- For each file number create a folder named exactly as the file number:
  - C:\edms\<FileNo>\A4\
  - C:\edms\<FileNo>\A3\
- Files placed in the appropriate A4/A3 subfolders (or root files for flat structures).

## How to use (high level)
1. Select a File No in the included file_number_info component.
2. Click "Browse to Folder" and pick the local parent folder (the folder named for the file number).
3. Click "Preview Files" to analyze and view A4/A3/root contents.
4. Click "Migrate to Server" to ZIP client-side and upload to server.
5. Use Server Browser tab to inspect server storage or Logs tab to view migration history.

## Browser & permission requirements
- Requires a modern Chromium-based browser that implements showDirectoryPicker (Chrome/Edge).
- Must be served via HTTPS or localhost for File System Access API to work.
- The site will prompt for directory read (or readwrite) permission when accessing local folders.

## Client-side behavior & important functions
- analyzeFolderStructure / generatePreviewData: inspect handles and count files.
- zipDirectoryToBlob: recursively builds ZIP via JSZip and returns a Blob.
- migrateToServer / migrateParent: create FormData (includes _token CSRF), POST ZIP to route MIGRATE_ENDPOINT.
- fetchServerList, fetchLogs: call server API endpoints (API_LIST, API_LOGS) to display server contents and logs.

## Laravel integration points (from blade)
- MIGRATE_ENDPOINT = route('blind_scan.migrate')
- API_LIST = route('blind_scan.api.list')
- API_LOGS = route('blind_scan.api.logs')
- CREATE_FOLDER_ENDPOINT = route('blind-scanning.create-folder')
- CSRF token appended to uploads: '{{ csrf_token() }}'
- availableFiles injected via @json($availableFiles)

## UI sections
- Quick Create: choose file number, browse folder, preview.
- File Preview & Upload: show analyzed contents and migrate.
- Server Storage & Logs: server file browser, preview pane, migration logs.
- Explorer: initialize and inspect C:\edms root, analyze children, migrate parent.
- Progress Modal: shows ZIP/upload progress.

## Notes & troubleshooting
- If showDirectoryPicker is unavailable, the UI warns: "Use Chrome/Edge over HTTPS or localhost".
- Large folders will create large ZIPs client-side — monitor memory and network limits.
- Server must implement the listed API endpoints and accept multipart/form-data with zip and folderName fields; responses should be JSON { ok: true } (or error).
- If server API isn't configured the client silently logs a warning and keeps local functionality usable.
- Ensure CSRF token is valid for migration POSTs.

## Security & operational tips
- Validate and sanitize folderName and file_no server-side before extracting ZIP.
- Enforce maximum upload size on server.
- Persist migration logs with timestamps and server paths for auditing.

 EDMS\BLIND_SCAN\COM-2025-4554\ A3 and A4 folder


blind-scanning:3982 Uncaught TypeError: Cannot read properties of null (reading 'classList')
    at HTMLDocument.<anonymous> (blind-scanning:3982:62)
    at HTMLInputElement.updateMlsFileNumberPreview (blind-scanning:2908:20)
(anonymous)	@	blind-scanning:3982
updateMlsFileNumberPreview	@	blind-scanning:2908

 the  Browse to Folder should pick the COM-2025-4554 folder which  will  have A3 and A4 folder
 inside,, and inside those folders they  might be files inside as well, 

 EDMS\BLIND_SCAN\COM-2025-4554\ A3 and A4 folder (from the client pc)


the Preview Files button will send the  files to the File Preview & Upload
Preview your folder contents and migrate to server section
and the Migrate to Server will now send the files to Server Storage  and db
SELECT TOP (1000) [id]
      ,[temp_file_id]
      ,[original_filename]
      ,[document_path]
      ,[paper_size]
      ,[document_type]
      ,[notes]
      ,[status]
      ,[uploaded_by]
      ,[file_indexing_id]
      ,[converted_at]
      ,[created_at]
      ,[updated_at]
  FROM [klas].[dbo].[blind_scannings]





 Server Storage & Logs
Browse /public_html/storage, preview files, and review migration logs (persisted in _migrations.json).

the correct  Server Storage  path is 
storage\app\public\EDMS\BLIND_SCAN\{COM-2025-455} {A4} {A3}


almost good, 


this is the 
C:.
└───BLIND_SCAN
    └───RES-2025-3344
        ├───A3
        └───A4

RES-2025-3344
Partial structure - missing A4 or A3 subfolder
Total files: 2
A4 Files (2)
WhatsApp Image 2025-08-21 at 11.09.36 AM.jpeg
28.3 KB
WhatsApp Image 2025-08-21 at 1.06.49 PM.jpeg
101.9 KB




C:\Users\Administrator\Documents\app\storage\app\public\EDMS\BLIND_SCAN\RES-2025-3344\RES-2025-3344_1757340872.zp