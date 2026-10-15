<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MatterExtractor;
use App\Support\Audit;
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
        return view('admin.users.form', ['user' => null, 'roles' => User::ROLES, 'sections' => MatterExtractor::SECTIONS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string', Rule::in(MatterExtractor::SECTIONS)],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ]);

        $user = new User();
        $user->forceFill([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'password' => $data['password'],
            'role' => $data['role'],
            'sections' => $this->sectionsFrom($data),
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        Audit::record('user.created', $user, [
            'role' => $user->role,
            'sections' => $user->sections,
        ], $user->email);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Account created for '.$user->name.' ('.$user->roleLabel().').');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['user' => $user, 'roles' => User::ROLES, 'sections' => MatterExtractor::SECTIONS]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string', Rule::in(MatterExtractor::SECTIONS)],
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

        $before = $user->only(['name', 'email', 'role', 'sections', 'is_active']);

        $user->forceFill([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'role' => $data['role'],
            'sections' => $this->sectionsFrom($data),
            'is_active' => $isActive,
        ])->save();

        Audit::record('user.updated', $user, [
            'before' => $before,
            'after' => $user->only(['name', 'email', 'role', 'sections', 'is_active']),
        ], $user->email);

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

        Audit::record('user.password_reset', $user, [], $user->email);

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'The password for '.$user->name.' was changed.');
    }

    /**
     * A list of sections to limit the account to, or null for every section.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>|null
     */
    private function sectionsFrom(array $data): ?array
    {
        $sections = array_values(array_unique($data['sections'] ?? []));

        return $sections === [] ? null : $sections;
    }

    private function passwordRule(): Password
    {
        return Password::min(10)->letters()->numbers();
    }
}
