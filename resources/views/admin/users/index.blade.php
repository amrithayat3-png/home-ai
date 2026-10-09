<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | HOME AI</title>
    <style>
        body { margin: 0; background: #f3f6fb; color: #1d2939; font-family: Arial, sans-serif; }
        .topbar { align-items: center; background: #102a43; color: white; display: flex; justify-content: space-between; padding: 22px 45px; }
        .topbar h1 { font-size: 24px; margin: 0; }
        .topbar-actions { display: flex; gap: 10px; }
        .topbar a { background: #2563eb; border-radius: 6px; color: white; padding: 10px 16px; text-decoration: none; }
        .container { margin: 35px auto; max-width: 1100px; padding: 0 25px; }
        .success { background: #ecfdf3; color: #027a48; margin-bottom: 20px; padding: 14px; }
        .table-card { background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(16,42,67,.08); overflow: hidden; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #eaf1fb; color: #344054; font-size: 13px; padding: 15px; text-align: left; }
        td { border-top: 1px solid #eaecf0; font-size: 14px; padding: 15px; }
        .active { color: #027a48; font-weight: bold; }
        .inactive { color: #b42318; font-weight: bold; }
        .role { background: #eaf1fb; border-radius: 6px; color: #175cd3; font-size: 12px; font-weight: bold; padding: 4px 8px; }
        .edit-link { color: #175cd3; font-weight: bold; text-decoration: none; }
        .notes { color: #667085; font-size: 13px; line-height: 1.6; margin-top: 22px; }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>HOME AI: Users</h1>
        <div class="topbar-actions">
            <a href="{{ route('admin.users.create') }}">Add User</a>
            <a href="{{ route('home') }}">Back to Dashboard</a>
        </div>
    </div>

    <main class="container">
        @if (session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $listedUser)
                        <tr>
                            <td>{{ $listedUser->name }}</td>
                            <td>{{ $listedUser->email }}</td>
                            <td><span class="role">{{ $listedUser->roleLabel() }}</span></td>
                            <td class="{{ $listedUser->is_active ? 'active' : 'inactive' }}">
                                {{ $listedUser->is_active ? 'Active' : 'Deactivated' }}
                            </td>
                            <td><a class="edit-link" href="{{ route('admin.users.edit', $listedUser) }}">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="notes">
            <strong>Administrator:</strong> everything, including managing accounts.<br>
            <strong>Officer:</strong> creates matters, uploads and files documents, links documents to matters, and uses Ask HOME AI.<br>
            <strong>Executive:</strong> views the dashboard, matters and documents, and uses Ask HOME AI. Cannot change anything.
        </div>
    </main>
</body>
</html>
