 
#   KLAES Quick Technical Specs

A high-level list of the engines driving the Kano Land Administration Enterprise System.

 1. The Core (Laravel 9 Monolith)
- What: The main administrative brain and secure web portal.
- Role: Handles User Auth, Business Rules, Permissions (Spatie), and Registry Workflows.

 2. The Heavy Lifter (Python 3 Engine)
- What: Specialized automation scripts and background services.
- Role: Performs high-speed data cleaning, folder monitoring, and complex regex-based file normalization.

 3. The Search Engine (FastAPI)
- What: An asynchronous, high-performance Python web service.
- Role: Powers the Folder Search EDMS, indexing millions of files for sub-second search results.

 4. The Database (Enterprise SQL Server)
- What: Microsoft SQL Server (`sqlsrv`).
- Role: The secure, ACID-compliant "Source of Truth" for all land titles and transaction history.

 5. The UI (TALL Stack Hybrid)
- What: Tailwind CSS, Alpine.js, Laravel (Livewire), and Bootstrap 5.
- Role: Delivers a premium, reactive user experience with powerful DataTables for managing massive records.

 6. The "Prop ID" System
- What: A custom  unique identifier service.
- Role: Tracks every property's lifecycle across multiple tables, ensuring no record is ever lost or duplicated.

 7. The Automation Watcher
- What: A real-time folder monitoring service (Watchdog).
- Role: Automatically detects and renames incorrectly formatted folders on the server 24/7.

 