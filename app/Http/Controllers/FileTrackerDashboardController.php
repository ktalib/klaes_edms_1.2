<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;

class FileTrackerDashboardController extends Controller
{
    /**
     * Display the File Tracker dashboard placeholder.
     */
    public function index(\Illuminate\Http\Request $request)
    {
        $pageTitle = 'File Tracker Dashboard';
        $module = $request->get('url', '');

        Log::info('File Tracker Dashboard viewed', [
            'user_id' => auth()->id(),
        ]);

        return view('file_tracker_dashboard.index', [
            'PageTitle' => $pageTitle,
            'module'    => $module,
        ]);
    }
}
