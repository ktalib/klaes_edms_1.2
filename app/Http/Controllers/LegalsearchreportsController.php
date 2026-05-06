<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LegalsearchreportsController extends Controller
{ 
    public function index() {
        $PageTitle = 'Legal Search Reports';
        $PageDescription = '';
        //Log::info('File Tracker accessed', ['user_id' => auth()->id()]);
        return view('legalsearchreports.index', compact('PageTitle', 'PageDescription'));
    }
}
