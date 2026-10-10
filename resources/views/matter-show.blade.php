<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $matter->matter_reference }} | HOME AI</title>

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

        .topbar a {
            color: white;
            text-decoration: none;
            background: #2563eb;
            padding: 10px 16px;
            border-radius: 6px;
        }

        .container {
            max-width: 1000px;
            margin: 35px auto;
            padding: 0 25px;
        }

        .reference {
            color: #667085;
            font-size: 14px;
            margin-bottom: 8px;
        }

        h2 {
            margin-top: 0;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 28px;
            margin-top: 22px;
            box-shadow: 0 2px 12px rgba(16, 42, 67, 0.08);
        }

        .details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .label {
            color: #667085;
            display: block;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .value {
            font-size: 16px;
        }

        .section-title {
            font-size: 16px;
            margin: 26px 0 8px;
        }

        .content {
            background: #f8fafc;
            border-left: 4px solid #2563eb;
            line-height: 1.6;
            padding: 16px;
        }

        .flash {
            background: #ecfdf3;
            color: #027a48;
            margin-bottom: 18px;
            padding: 14px;
        }

        .doc-row {
            border-bottom: 1px solid #eaecf0;
            padding: 12px 0;
        }

        .doc-name {
            color: #175cd3;
            font-weight: bold;
            text-decoration: none;
        }

        .doc-name:hover {
            text-decoration: underline;
        }

        .doc-meta {
            color: #667085;
            display: block;
            font-size: 13px;
            margin-top: 4px;
        }

        @media (max-width: 650px) {
            .topbar {
                padding: 18px 22px;
            }

            .details {
                grid-template-columns: 1fr;
            }
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 14px 0 6px;
        }

        .actions button {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,.18);
            background: transparent;
            color: inherit;
            cursor: pointer;
        }

        .actions .danger {
            border-color: rgba(255,107,122,.5);
            color: #ff6b7a;
        }

        .flash-error {
            border-color: rgba(255,107,122,.5);
            color: #ff6b7a;
        }

        .hint {
            opacity: .7;
            font-size: 13px;
            align-self: center;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>HOME AI — Matter Details</h1>
        <a href="{{ route('matters') }}">Back to Matters</a>
    </div>

    <main class="container">
        @if (session('success'))
            <div class="flash">{{ session('success') }}</div>
        @endif

        <p class="reference">{{ $matter->matter_reference }}</p>
        <h2>{{ $matter->title }}</h2>

        @if (session('error'))
            <div class="flash flash-error">{{ session('error') }}</div>
        @endif

        @if (auth()->user()->canManageWork() || auth()->user()->isAdmin())
            <div class="actions">
                @if (auth()->user()->canManageWork())
                    @if ($matter->status === 'Closed')
                        <form method="POST" action="{{ route('matters.reopen', $matter) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit">Reopen matter</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('matters.close', $matter) }}"
                              onsubmit="return confirm('Close this matter? It will leave the active lists and reminders.');">
                            @csrf
                            @method('PATCH')
                            <button type="submit">Close matter</button>
                        </form>
                    @endif
                @endif

                @if (auth()->user()->isAdmin())
                    @if ($matter->status === 'Closed' || $matter->documents->isEmpty())
                        <form method="POST" action="{{ route('matters.destroy', $matter) }}"
                              onsubmit="return confirm('Delete {{ $matter->matter_reference }} permanently? Linked documents are kept and marked Needs matter. This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="danger">Delete matter</button>
                        </form>
                    @else
                        <span class="hint">Close this matter before deleting it.</span>
                    @endif
                @endif
            </div>
        @endif

        <section class="card">
            <div class="details">
                <div>
                    <span class="label">Status</span>
                    <span class="value">{{ $matter->status }}</span>
                </div>
                <div>
                    <span class="label">Priority</span>
                    <span class="value">{{ $matter->priority }}</span>
                </div>
                <div>
                    <span class="label">Section</span>
                    <span class="value">{{ $matter->section }}</span>
                </div>
                <div>
                    <span class="label">Category</span>
                    <span class="value">{{ $matter->category }}</span>
                </div>
                <div>
                    <span class="label">District</span>
                    <span class="value">{{ $matter->district }}</span>
                </div>
                <div>
                    <span class="label">Deadline</span>
                    <span class="value">{{ $matter->deadline }}</span>
                </div>
                <div>
                    <span class="label">Assigned Officer</span>
                    <span class="value">{{ $matter->assigned_officer }}</span>
                </div>
                <div>
                    <span class="label">Related Department</span>
                    <span class="value">{{ $matter->related_department }}</span>
                </div>
            </div>

            <h3 class="section-title">Summary</h3>
            <div class="content">{{ $matter->summary }}</div>

            <h3 class="section-title">Last Action</h3>
            <div class="content">{{ $matter->last_action }}</div>

            <h3 class="section-title">Next Action</h3>
            <div class="content">{{ $matter->next_action }}</div>

            <h3 class="section-title">Linked Documents ({{ $matter->documents->count() }})</h3>
            @forelse ($matter->documents as $document)
                <div class="doc-row">
                    <a class="doc-name" href="{{ route('documents.show', $document) }}">{{ $document->original_name }}</a>
                    <span class="doc-meta">
                        {{ str($document->category)->headline() }}
                        | {{ $document->review_status }}
                        | {{ $document->classified_at?->format('d M Y, H:i') }}
                    </span>
                </div>
            @empty
                <div class="content">No documents are linked to this matter yet.</div>
            @endforelse
        </section>
    </main>
</body>
</html>
