<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search | HOME AI</title>
    <link rel="icon" href="{{ asset('images/centre-seal-64.png') }}" type="image/png">
    <style>
        body { margin: 0; background: #f3f6fb; color: #1d2939; font-family: Arial, sans-serif; }
        .topbar { align-items: center; background: #102a43; color: white; display: flex; justify-content: space-between; padding: 22px 45px; }
        .topbar h1 { font-size: 24px; margin: 0; }
        .container { margin: 35px auto; max-width: 1200px; padding: 0 25px; }
        .table-card { background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(16,42,67,.08); margin-bottom: 28px; overflow: hidden; }
        .table-card h2 { font-size: 17px; margin: 0; padding: 18px 20px 4px; }
        .table-card .count { color: #667085; font-size: 13px; padding: 0 20px 12px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #eaf1fb; color: #344054; font-size: 13px; padding: 13px 15px; text-align: left; }
        td { border-top: 1px solid #eaecf0; font-size: 14px; padding: 13px 15px; vertical-align: top; }
        .doc-link { color: #175cd3; font-weight: bold; text-decoration: none; }
        .doc-link:hover { text-decoration: underline; }
        .filed { color: #027a48; font-weight: bold; }
        .review { color: #b54708; font-weight: bold; }
        .snippet { color: #475467; font-size: 13px; line-height: 1.55; margin-top: 6px; }
        .snippet mark { background: #fef0c7; border-radius: 3px; color: inherit; padding: 0 2px; }
        .empty { color: #667085; padding: 28px; text-align: center; }
        .hint { background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(16,42,67,.08); color: #475467; line-height: 1.7; padding: 26px; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/home-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/home-ui-dark.css') }}">
</head>
<body class="theme-dark">
    @include('partials.quicknav')

    <div class="topbar">
        <h1>HOME AI: Search</h1>
    </div>

    <main class="container">
        @if ($query === '')
            <div class="hint">
                Type a word or two in the box above. HOME AI looks through matters (reference, title, officer, notes)
                and through the words inside every scanned document. Every word you type must appear.
            </div>
        @elseif ($tooShort)
            <div class="hint">Please type at least 2 letters.</div>
        @else
            <p class="note">Results for <strong>{{ $query }}</strong></p>

            <div class="table-card">
                <h2>Matters</h2>
                <div class="count">
                    {{ $mattersTotal }} {{ \Illuminate\Support\Str::plural('match', $mattersTotal) }}
                    @if ($mattersTotal > $limit) (showing the first {{ $limit }}. Add another word to narrow it down.) @endif
                </div>
                @if ($matters->isEmpty())
                    <div class="empty">No matters found.</div>
                @else
                    <table>
                        <thead>
                            <tr><th>Reference</th><th>Title</th><th>Section</th><th>Officer</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($matters as $matter)
                                <tr>
                                    <td><a class="doc-link" href="{{ route('matters.show', $matter) }}">{{ $matter->matter_reference }}</a></td>
                                    <td>{{ $matter->title }}</td>
                                    <td>{{ $matter->section }}</td>
                                    <td>{{ $matter->assigned_officer }}</td>
                                    <td>{{ $matter->status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="table-card">
                <h2>Documents</h2>
                <div class="count">
                    {{ $documentsTotal }} {{ \Illuminate\Support\Str::plural('match', $documentsTotal) }}
                    @if ($documentsTotal > $limit) (showing the first {{ $limit }}. Add another word to narrow it down.) @endif
                </div>
                @if ($documents->isEmpty())
                    <div class="empty">No documents found.</div>
                @else
                    <table>
                        <thead>
                            <tr><th>Document</th><th>Category</th><th>Matter</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($documents as $document)
                                <tr>
                                    <td>
                                        <a class="doc-link" href="{{ route('documents.show', $document) }}">{{ $document->original_name }}</a>
                                        @if ($document->snippet)
                                            <div class="snippet">{{ $document->snippet }}</div>
                                        @endif
                                    </td>
                                    <td>{{ str($document->category)->headline() }}</td>
                                    <td>
                                        @if ($document->matter)
                                            <a class="doc-link" href="{{ route('matters.show', $document->matter) }}">{{ $document->matter->matter_reference }}</a>
                                        @else
                                            <span class="review">Not linked</span>
                                        @endif
                                    </td>
                                    <td class="{{ $document->review_status === 'Filed' ? 'filed' : 'review' }}">{{ $document->review_status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </main>
</body>
</html>
