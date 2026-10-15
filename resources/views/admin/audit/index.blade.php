<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log | HOME AI</title>
    <style>
        body { margin: 0; background: #f3f6fb; color: #1d2939; font-family: Arial, sans-serif; }
        .topbar { align-items: center; background: #102a43; color: white; display: flex; justify-content: space-between; padding: 22px 45px; }
        .topbar h1 { font-size: 24px; margin: 0; }
        .topbar a { background: #2563eb; border-radius: 6px; color: white; padding: 10px 16px; text-decoration: none; }
        .container { margin: 35px auto; max-width: 1300px; padding: 0 25px; }
        .filters { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
        .filters input, .filters select { border: 1px solid #d0d5dd; border-radius: 6px; font: inherit; padding: 9px 12px; }
        .filters button { background: #2563eb; border: 0; border-radius: 6px; color: white; cursor: pointer; font: inherit; padding: 9px 18px; }
        .table-card { background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(16,42,67,.08); overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #eaf1fb; color: #344054; font-size: 13px; padding: 13px; text-align: left; white-space: nowrap; }
        td { border-top: 1px solid #eaecf0; font-size: 13px; padding: 12px 13px; vertical-align: top; }
        .action { background: #eaf1fb; border-radius: 6px; color: #175cd3; font-size: 12px; font-weight: bold; padding: 3px 8px; white-space: nowrap; }
        .details { color: #667085; font-family: monospace; font-size: 12px; max-width: 420px; overflow-wrap: anywhere; white-space: pre-wrap; }
        .pager { display: flex; gap: 12px; margin-top: 18px; }
        .pager a { color: #175cd3; font-weight: bold; text-decoration: none; }
        .note { color: #667085; font-size: 13px; margin-top: 18px; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/home-ui.css') }}">
</head>
<body>
    <div class="topbar">
        <h1>HOME AI: Audit Log</h1>
        <a href="{{ route('admin.users.index') }}">Back to Users</a>
    </div>

    <main class="container">
        <form class="filters" method="GET" action="{{ route('admin.audit.index') }}">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search person, record or action">
            <select name="action">
                <option value="">All actions</option>
                @foreach ($actions as $actionName)
                    <option value="{{ $actionName }}" @selected(request('action') === $actionName)>{{ $actionName }}</option>
                @endforeach
            </select>
            <button type="submit">Filter</button>
        </form>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Who</th>
                        <th>Action</th>
                        <th>Record</th>
                        <th>Details</th>
                        <th>Address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->created_at?->timezone(config('reminders.timezone', 'Asia/Karachi'))->format('d M Y, H:i:s') }}</td>
                            <td>{{ $log->user_label ?? 'Not signed in' }}</td>
                            <td><span class="action">{{ $log->action }}</span></td>
                            <td>{{ $log->subject_label ?? ($log->subject_type ? $log->subject_type.' #'.$log->subject_id : '') }}</td>
                            <td class="details">{{ $log->details ? json_encode($log->details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '' }}</td>
                            <td>{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pager">
            @if ($logs->previousPageUrl())
                <a href="{{ $logs->previousPageUrl() }}">Newer</a>
            @endif
            @if ($logs->nextPageUrl())
                <a href="{{ $logs->nextPageUrl() }}">Older</a>
            @endif
        </div>

        <div class="note">Entries are written by the system and cannot be edited or deleted from HOME AI.</div>
    </main>
</body>
</html>
