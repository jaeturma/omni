<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('users.index', ['users' => User::query()->with('roles')->latest()->paginate(15)]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', ['roles' => Role::query()->orderBy('name')->get()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->safe()->except(['roles', 'password_confirmation']);
            $user = User::query()->create($data);
            $user->syncRoles($request->validated('roles'));
        });

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', ['managedUser' => $user->load('roles'), 'roles' => Role::query()->orderBy('name')->get()]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $data = $request->safe()->except(['roles', 'password_confirmation']);
            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }
            $user->update($data);
            $user->syncRoles($request->validated('roles'));
        });

        return redirect()->route('users.index')->with('success', 'User updated.');
    }
}
