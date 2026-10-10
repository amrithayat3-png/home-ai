<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matters & Files | HOME AI</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1d2939;
        }

        .topbar {
            background: #102a43;
            color: white;
            padding: 22px 45px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar h1 {
            margin: 0;
            font-size: 24px;
        }

        .topbar-actions {
            display: flex;
            gap: 10px;
        }

        .topbar a {
            color: white;
            text-decoration: none;
            background: #2563eb;
            padding: 10px 16px;
            border-radius: 6px;
        }

        .container {
            max-width: 1250px;
            margin: 35px auto;
            padding: 0 25px;
        }

        .summary {
            color: #667085;
            margin-bottom: 22px;
        }

        .filters {
            background: white;
            border-radius: 10px;
            display: grid;
            gap: 14px;
            grid-template-columns: 2fr repeat(3, 1fr) auto auto;
            margin-bottom: 22px;
            padding: 18px;
            box-shadow: 0 2px 12px rgba(16, 42, 67, 0.08);
        }

        .filters input,
        .filters select,
        .filters button,
        .clear-filters {
            border: 1px solid #d0d5dd;
            border-radius: 6px;
            box-sizing: border-box;
            font: inherit;
            min-height: 40px;
            padding: 9px 11px;
        }

        .filters button {
            background: #2563eb;
            border-color: #2563eb;
            color: white;
            cursor: pointer;
        }

        .clear-filters {
            color: #344054;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .table-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(16, 42, 67, 0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #eaf1fb;
            color: #344054;
            text-align: left;
            padding: 15px;
            font-size: 13px;
        }

        td {
            padding: 15px;
            border-top: 1px solid #eaecf0;
            font-size: 14px;
        }

        .priority-high {
            color: #b42318;
            font-weight: bold;
        }

        .priority-normal {
            color: #175cd3;
            font-weight: bold;
        }

        .priority-low {
            color: #667085;
            font-weight: bold;
        }

        .status-open {
            color: #027a48;
            font-weight: bold;
        }

        .status-pending {
            color: #b54708;
            font-weight: bold;
        }

        .matter-link {
            color: #175cd3;
            font-weight: bold;
            text-decoration: none;
        }

        .matter-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 900px) {
            .filters {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            .filters {
                grid-template-columns: 1fr;
            }
        }

        .dl-badge {
            display: inline-block;
            margin-left: 6px;
            padding: 2px 7px;
            border-radius: 6px;
            font-size: 11px;
            white-space: nowrap;
            background: rgba(255,255,255,.06);
        }

        .dl-overdue { background: rgba(255,107,122,.16); color: #ff6b7a; }
        .dl-today   { background: rgba(255,107,122,.10); color: #ff9aa5; }
        .dl-soon    { background: rgba(245,184,91,.14); color: #f5b85b; }
        .dl-week    { background: rgba(92,169,255,.14); color: #5ca9ff; }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>HOME AI — Matters & Files</h1>
        <div class="topbar-actions">
            @if (auth()->user()->canManageWork())
                <a href="{{ route('matters.create') }}">New Matter</a>
                <a href="{{ route('documents.create') }}">Upload & Classify</a>
            @endif
            <a href="{{ route('home') }}">Back to Dashboard</a>
        </div>
    </div>

    <div class="container">
        <h2>Active Matters</h2>
        <p class="summary">{{ $matters->count() }} Open and Pending matters currently match your filters.</p>

        <form class="filters" method="GET" action="{{ route('matters') }}">
            <input
                type="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search by reference, title, or section"
            >

            <select name="status">
                <option value="">All active statuses</option>
                <option value="Open" @selected(request('status') === 'Open')>Open</option>
                <option value="Pending" @selected(request('status') === 'Pending')>Pending</option>
            </select>

            <select name="section">
                <option value="">All sections</option>
                @foreach ($sections as $section)
                    <option value="{{ $section }}" @selected(request('section') === $section)>
                        {{ $section }}
                    </option>
                @endforeach
            </select>

            <select name="priority">
                <option value="">All priorities</option>
                <option value="High" @selected(request('priority') === 'High')>High</option>
                <option value="Normal" @selected(request('priority') === 'Normal')>Normal</option>
                <option value="Low" @selected(request('priority') === 'Low')>Low</option>
            </select>

            <button type="submit">Apply Filters</button>
            <a class="clear-filters" href="{{ route('matters') }}">Clear</a>
        </form>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Matter</th>
                        <th>Section</th>
                        <th>Priority</th>
                        <th>Deadline</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($matters as $matter)
                        <tr>
                            <td>{{ $matter->matter_reference }}</td>
                            <td>
                                <a class="matter-link" href="{{ route('matters.show', $matter) }}">
                                    {{ $matter->title }}
                                </a>
                            </td>
                            <td>{{ $matter->section }}</td>
                            <td class="priority-{{ strtolower($matter->priority) }}">
                                {{ $matter->priority }}
                            </td>
                            <td>
                                {{ \Illuminate\Support\Str::of($matter->deadline)->substr(0, 10) }}
                                @php $badge = $matter->deadlineBadge(); @endphp
                                @if ($badge)
                                    <span class="dl-badge dl-{{ $badge['level'] }}">{{ $badge['label'] }}</span>
                                @endif
                            </td>
                            <td class="status-{{ strtolower($matter->status) }}">
                                {{ $matter->status }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No active matters found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
