<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $user ? 'Edit User' : 'Add User' }} | HOME AI</title>
    <style>
        body { margin: 0; background: #f3f6fb; color: #1d2939; font-family: Arial, sans-serif; }
        .topbar { align-items: center; background: #102a43; color: white; display: flex; justify-content: space-between; padding: 22px 45px; }
        .topbar h1 { font-size: 24px; margin: 0; }
        .topbar a { background: #2563eb; border-radius: 6px; color: white; padding: 10px 16px; text-decoration: none; }
        .container { margin: 35px auto; max-width: 720px; padding: 0 25px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(16,42,67,.08); margin-bottom: 22px; padding: 28px; }
        h2 { margin-top: 0; }
        label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px; }
        input[type=text], input[type=email], input[type=password], select { border: 1px solid #d0d5dd; border-radius: 6px; box-sizing: border-box; font: inherit; padding: 10px 12px; width: 100%; }
        .field { margin-bottom: 18px; }
        .check { align-items: center; display: flex; gap: 8px; }
        .check label { margin: 0; }
        .field-error { color: #b42318; font-size: 12px; margin-top: 5px; }
        .success { background: #ecfdf3; color: #027a48; margin-bottom: 20px; padding: 14px; }
        .hint { color: #667085; font-size: 12px; margin-top: 5px; }
        .btn { background: #2563eb; border: 0; border-radius: 6px; color: white; cursor: pointer; font: inherit; padding: 12px 20px; }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>HOME AI: {{ $user ? 'Edit User' : 'Add User' }}</h1>
        <a href="{{ route('admin.users.index') }}">Back to Users</a>
    </div>

    <main class="container">
        @if (session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <section class="card">
            <h2>{{ $user ? 'Account details' : 'New account' }}</h2>

            <form method="POST" action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}">
                @csrf
                @if ($user)
                    @method('PATCH')
                @endif

                <div class="field">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user?->name) }}" required>
                    @error('name') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="email">Email (used to sign in)</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" required>
                    @error('email') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        @foreach ($roles as $key => $label)
                            <option value="{{ $key }}" @selected(old('role', $user?->role ?? 'officer') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                @if ($user)
                    <div class="field check">
                        <input type="hidden" name="is_active" value="0">
                        <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $user->is_active))>
                        <label for="is_active">Account is active</label>
                    </div>
                    @error('is_active') <div class="field-error">{{ $message }}</div> @enderror
                @else
                    <div class="field">
                        <label for="password">Temporary password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required>
                        <div class="hint">At least 10 characters, with letters and numbers. Share it with the person privately.</div>
                        @error('password') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                @endif

                <button class="btn" type="submit">{{ $user ? 'Save Changes' : 'Create Account' }}</button>
            </form>
        </section>

        @if ($user)
            <section class="card">
                <h2>Reset password</h2>

                <form method="POST" action="{{ route('admin.users.password', $user) }}">
                    @csrf
                    @method('PATCH')

                    <div class="field">
                        <label for="new_password">New password</label>
                        <input id="new_password" name="new_password" type="password" autocomplete="new-password" required>
                        <div class="hint">At least 10 characters, with letters and numbers.</div>
                        @error('new_password') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="new_password_confirmation">Confirm new password</label>
                        <input id="new_password_confirmation" name="new_password_confirmation" type="password" autocomplete="new-password" required>
                    </div>

                    <button class="btn" type="submit">Change Password</button>
                </form>
            </section>
        @endif
    </main>
</body>
</html>
