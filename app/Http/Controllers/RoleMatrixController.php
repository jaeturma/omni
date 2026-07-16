<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleMatrixController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): View
    {
        Gate::authorize('roles.view');

        return view('roles.index', ['roles' => Role::query()->with('permissions')->orderBy('name')->get(), 'permissions' => Permission::query()->orderBy('name')->get()]);
    }
}
