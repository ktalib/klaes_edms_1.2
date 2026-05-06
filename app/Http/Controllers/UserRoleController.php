<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserRoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (Auth::user()->type != 'super admin' && !Auth::user()->can('manage user')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        
        $PageTitle = 'User Roles';
        $PageDescription = 'List of all user roles';
        $userRoles = UserRole::with('department')->orderBy('name', 'asc')->get();
        return view('user_role.index', compact('userRoles', 'PageTitle', 'PageDescription'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (Auth::user()->type != 'super admin' && !Auth::user()->can('create user')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $departments = Department::where('is_active', 1)->pluck('name', 'id');
        return view('user_role.create', compact('departments'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (Auth::user()->type != 'super admin' && !Auth::user()->can('create user')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
           
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        UserRole::create([
            'name' => $request->name,
            'guard_name' => 'web',
            'department_id' => $request->department_id,
            'description' => $request->description,
            'level' => $request->level,
            'user_type' => $request->user_type,
            'is_active' => $request->has('is_active') ? 1 : 0
        ]);

        return redirect()->route('user-roles.index')->with('success', __('User role created successfully'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (Auth::user()->type != 'super admin' && !Auth::user()->can('edit user')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $userRole = UserRole::findOrFail($id);
        $departments = Department::where('is_active', 1)->pluck('name', 'id');
        
        return view('user_role.edit', compact('userRole', 'departments'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (Auth::user()->type != 'super admin' && !Auth::user()->can('edit user')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
 
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $userRole = UserRole::findOrFail($id);
        $userRole->update([
            'name' => $request->name,
            'department_id' => $request->department_id,
            'description' => $request->description,
            'level' => $request->level,
            'user_type' => $request->user_type,
            'is_active' => $request->has('is_active') ? 1 : 0
        ]);

        return redirect()->route('user-roles.index')->with('success', __('User role updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (Auth::user()->type != 'super admin' && !Auth::user()->can('delete user')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        
        $userRole = UserRole::findOrFail($id);
        
        // Check if any users have this role
        $usersWithRole = \App\Models\User::whereRaw("',' + CAST(assign_role AS VARCHAR(MAX)) + ',' LIKE '%," . (int)$id . ",%'")->count();
        
        if ($usersWithRole > 0) {
            return redirect()->back()->with('error', __('Cannot delete role that is assigned to users'));
        }
        
        $userRole->delete();
        return redirect()->route('user-roles.index')->with('success', __('User role deleted successfully'));
    }

    /**
     * Remove multiple resources from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkDelete(Request $request)
    {
        if (Auth::user()->type != 'super admin' && !Auth::user()->can('delete user')) {
            return response()->json(['success' => false, 'message' => __('Permission Denied.')], 403);
        }

        $ids = $request->ids;
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => __('No roles selected.')]);
        }

        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($ids as $id) {
            $userRole = UserRole::find($id);
            if ($userRole) {
                // Check if any users have this role
                $usersWithRole = \App\Models\User::whereRaw("',' + CAST(assign_role AS VARCHAR(MAX)) + ',' LIKE '%," . (int)$id . ",%'")->count();
                
                if ($usersWithRole > 0) {
                    $skippedCount++;
                    continue;
                }
                
                $userRole->delete();
                $deletedCount++;
            }
        }

        $message = __(':deletedCount roles deleted successfully.', ['deletedCount' => $deletedCount]);
        if ($skippedCount > 0) {
            $message .= ' ' . __(':skippedCount roles skipped because they are assigned to users.', ['skippedCount' => $skippedCount]);
        }

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }

    /**
     * Get user roles by department
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getByDepartment(Request $request)
    {
        try {
            $departmentId = $request->department_id;
            
            // Get roles for the specific department
            $departmentRoles = UserRole::where('department_id', $departmentId)
                             ->where('is_active', 1)
                             ->get(['id', 'name', 'description']);
            
            // Also include general roles that don't have a specific department
            $generalRoles = UserRole::whereNull('department_id')
                          ->where('is_active', 1)
                          ->get(['id', 'name', 'description']);
            
            // Merge and return all roles
            $allRoles = $departmentRoles->merge($generalRoles);
            
            return response()->json($allRoles);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load roles: ' . $e->getMessage()], 500);
        }
    }
}
