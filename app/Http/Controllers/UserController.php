<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UserSessionManager;
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

    public function store(StoreUserRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $data = $request->safe()->except(['roles', 'password_confirmation']);
            $user = User::query()->create($data);
            $user->syncRoles($request->validated('roles'));

            return $user;
        });
        $audit->log('user.roles_changed', $user, after: ['roles' => $request->validated('roles')]);

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', ['managedUser' => $user->load('roles'), 'roles' => Role::query()->orderBy('name')->get()]);
    }

    public function update(UpdateUserRequest $request, User $user, UserSessionManager $sessions, AuditLogger $audit): RedirectResponse
    {
        $beforeRoles = $user->roles()->pluck('name')->all();
        $invalidateSessions = $request->filled('password') || ! $request->boolean('active');
        DB::transaction(function () use ($request, $user): void {
            $data = $request->safe()->except(['roles', 'password_confirmation']);
            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }
            $user->update($data);
            $user->syncRoles($request->validated('roles'));
        });
        if ($invalidateSessions) {
            $sessions->invalidate($user);
        }
        $afterRoles = $user->roles()->pluck('name')->all();
        if ($beforeRoles !== $afterRoles) {
            $audit->log('user.roles_changed', $user, ['roles' => $beforeRoles], ['roles' => $afterRoles]);
        }

        return redirect()->route('users.index')->with('success', 'User updated.');
    }
}
