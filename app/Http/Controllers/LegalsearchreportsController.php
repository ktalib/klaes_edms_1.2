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
        return view('legalsearchreports.index', compact('PageTitle', 'PageDescription'));
    }

    public function data(Request $request) {
        $query = \App\Models\LegalSearchLog::with('user');
        
        return \Yajra\DataTables\Facades\DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('date', function ($log) {
                return $log->created_at ? $log->created_at->format('d M, Y h:i A') : '-';
            })
            ->addColumn('staff', function ($log) {
                return $log->user ? trim($log->user->first_name . ' ' . $log->user->last_name) : '-';
            })
            ->addColumn('result_display', function ($log) {
                $color = $log->result_status == 'Found' ? 'success' : 'danger';
                return '<span class="badge badge-light-'.$color.'">'.$log->result_status.' ('.$log->results_count.')</span>';
            })
            ->addColumn('printed_status', function ($log) {
                $color = $log->printed ? 'success' : 'secondary';
                $text = $log->printed ? 'Printed' : 'Not Printed';
                return '<span class="badge badge-light-'.$color.'">'.$text.'</span>';
            })
            ->addColumn('action', function ($log) {
                if ($log->direct_link) {
                    return '<a href="'.$log->direct_link.'" target="_blank" class="btn btn-sm btn-primary">Open Search</a>';
                }
                return '-';
            })
            ->rawColumns(['result_display', 'printed_status', 'action'])
            ->make(true);
    }
}
