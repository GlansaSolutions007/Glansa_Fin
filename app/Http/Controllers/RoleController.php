<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\RoleHasPermission;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;


class RoleController extends Controller
{
    public function index()
    {
        $companyId = auth()->user()->role_id === 1
        ? cache()->get('superadmin_company_' . auth()->id(), 0)
        : auth()->user()->company_id;
        return response()->json(
        Role::with('permissions')
            ->where('id', '!=', 1)
            ->where('company_id', $companyId)
            ->get()
    );
    }

    public function store(Request $request)
    {
        $companyId = auth()->user()->role_id === 1
            ? cache()->get('superadmin_company_' . auth()->id(), 0)
            : auth()->user()->company_id;

        $request->validate([
            'name' => [
                'required',
                \Illuminate\Validation\Rule::unique('roles')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                }),
            ],
        ]);

        Role::create(['name' => $request->name, 'company_id' => $companyId]);

        return response()->json(['success' => true, 'message' => 'Role created successfully.']);
    }

     public function update(Request $request)
     {
         $request->validate(['name' => 'required']);
        Role::where('id', $request->id)->update(['name' => $request->name]);
        return response()->json(['success' => true, 'message' => 'Role Updated successfully.']);
     }

    // public function assignPermissions(Request $request, Role $role)
    // {
    //     $role->syncPermissions($request->permissions);
    //     return response()->json(['message' => 'Permissions updated.']);
    // }

    public function assignPermissions(Request $request, $roleId)
    {
        $companyId1 = auth()->user()->role_id === 1
        ? cache()->get('superadmin_company_' . auth()->id(), 0)
        : auth()->user()->company_id;
        
        $permissions = $request->permissions ?? [];

    if (!is_array($permissions)) {
        return response()->json(['error' => 'Invalid permissions format'], 422);
    }

    $permissionIds = Permission::whereIn('name', $permissions)->pluck('id')->toArray();

    // Delete old permissions
    RoleHasPermission::where('role_id', $roleId)
        ->where('company_id', $companyId1)
        ->delete();

    // Only insert if there are new permissions
    if (count($permissionIds) > 0) {
        $insertData = array_map(function($pid) use ($roleId) {
        $companyId = auth()->user()->role_id === 1
        ? cache()->get('superadmin_company_' . auth()->id(), 0)
        : auth()->user()->company_id;
            return [
                'permission_id' => $pid,
                'role_id' => $roleId,
                'company_id' => $companyId
            ];
        }, $permissionIds);

        RoleHasPermission::insert($insertData);
    }

        return response()->json(['message' => 'Permissions updated manually.']);
    }

    public function getRolePermissions($roleId)
    {
        $permissionNames = DB::table('role_has_permissions')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->where('role_has_permissions.role_id', $roleId)
            ->pluck('permissions.name');

        return response()->json($permissionNames);
    }

}
