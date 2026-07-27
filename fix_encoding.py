import os

html_path = r"C:\wamp64\www\klaes\resources\views\create_file_tracker_page\File-Tracker-Commissioner-Dashboard.html"
blade_path = r"C:\wamp64\www\klaes\resources\views\file_tracker_dashboard\partials\commissioner_dashboard.blade.php"
js_path = r"C:\wamp64\www\klaes\public\js\commissioner-dashboard.js"

with open(html_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Extract styles (lines 11-402, index 10-401) and body (lines 420-822, index 419-821)
styles = lines[10:402]
body = lines[419:822]

with open(blade_path, 'w', encoding='utf-8') as f:
    f.writelines(styles)
    f.writelines(body)

# Extract JS (lines 824-2351, index 823-2350)
js_original = lines[823:2351]

js_content = "".join(js_original)
js_modified = js_content

fetch_logic = """
        let fileTrackers = [];
        let officeData = {};
        let isLoading = true;

        async function fetchDashboardData() {
            try {
                const response = await fetch('/api/file-tracker-dashboard/overview-commissioner');
                const result = await response.json();
                if (result.success) {
                    fileTrackers = result.data.trackers;
                    
                    fileTrackers.forEach(t => {
                        if (t.currentOfficeId) {
                            officeData[t.currentOfficeId] = {
                                name: t.currentOffice,
                                code: t.currentOfficeId,
                                department: t.currentOfficeDepartment || t.department
                            };
                        }
                    });
                    
                    isLoading = false;
                    updateDashboardStats();
                    updateFilesTable();
                    updateIdleStats();
                    updateIdleTable();
                    updateRequestedTable();
                    if (typeof chartsVisible !== 'undefined' && chartsVisible) updateCharts();
                }
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
                isLoading = false;
            }
        }
"""

split_marker = "let currentDetailsTracker = null;"
if split_marker in js_modified:
    parts = js_modified.split(split_marker, 1)
    js_modified = fetch_logic + split_marker + parts[1]

init_marker = "document.addEventListener('DOMContentLoaded', initializeDashboard);"
new_init = """document.addEventListener('DOMContentLoaded', () => {
            initializeDashboard();
            fetchDashboardData();
        });"""
js_modified = js_modified.replace(init_marker, new_init)

with open(js_path, 'w', encoding='utf-8') as f:
    f.write(js_modified)

print("Extraction and patching complete!")
