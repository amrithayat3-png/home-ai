<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => null, 'roles' => User::ROLES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ]);

        $user = new User();
        $user->forceFill([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Account created for '.$user->name.' ('.$user->roleLabel().').');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['user' => $user, 'roles' => User::ROLES]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $isActive = $request->boolean('is_active');
        $isSelf = $user->is($request->user());

        if ($isSelf && ($data['role'] !== $user->role || ! $isActive)) {
            return back()
                ->withInput()
                ->withErrors(['role' => 'You cannot change your own role or deactivate your own account.']);
        }

        $losesAdmin = $user->isAdmin() && $user->is_active && ($data['role'] !== User::ROLE_ADMIN || ! $isActive);

        if ($losesAdmin && User::where('role', User::ROLE_ADMIN)->where('is_active', true)->count() <= 1) {
            return back()
                ->withInput()
                ->withErrors(['role' => 'This is the only active administrator. Create another administrator first.']);
        }

        $user->forceFill([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'role' => $data['role'],
            'is_active' => $isActive,
        ])->save();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Account updated for '.$user->name.'.');
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'new_password' => ['required', 'confirmed', $this->passwordRule()],
        ]);

        $user->forceFill([
            'password' => $data['new_password'],
            'remember_token' => Str::random(60),
        ])->save();

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'The password for '.$user->name.' was changed.');
    }

    private function passwordRule(): Password
    {
        return Password::min(10)->letters()->numbers();
    }
}
