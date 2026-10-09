<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filed Documents | HOME AI</title>
    <style>
        body { margin: 0; background: #f3f6fb; color: #1d2939; font-family: Arial, sans-serif; }
        .topbar { align-items: center; background: #102a43; color: white; display: flex; justify-content: space-between; padding: 22px 45px; }
        .topbar h1 { font-size: 24px; margin: 0; }
        .topbar a { background: #2563eb; border-radius: 6px; color: white; padding: 10px 16px; text-decoration: none; }
        .container { margin: 35px auto; max-width: 1200px; padding: 0 25px; }
        .success { background: #ecfdf3; color: #027a48; margin-bottom: 20px; padding: 14px; }
        .table-card { background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(16,42,67,.08); overflow: hidden; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #eaf1fb; color: #344054; font-size: 13px; padding: 15px; text-align: left; }
        td { border-top: 1px solid #eaecf0; font-size: 14px; padding: 15px; vertical-align: top; }
        .filed { color: #027a48; font-weight: bold; }
        .review { color: #b54708; font-weight: bold; }
        .empty { color: #667085; padding: 28px; text-align: center; }
        .doc-link { color: #175cd3; font-weight: bold; text-decoration: none; }
        .doc-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>HOME AI — Filed Documents</h1>
        @if (auth()->user()->canManageWork())
            <a href="{{ route('documents.create') }}">Upload a Document</a>
        @endif
    </div>

    <main class="container">
        @if (session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <div class="table-card">
            @if ($documents->isEmpty())
                <div class="empty">No documents have been uploaded yet.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Category</th>
                            <th>Matter</th>
                            <th>Confidence</th>
                            <th>Reason</th>
                            <th>Filing Status</th>
                            <th>Scanned</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $document)
                            <tr>
                                <td>
                                    <a class="doc-link" href="{{ route('documents.show', $document) }}">{{ $document->original_name }}</a>
                                </td>
                                <td>{{ str($document->category)->headline() }}</td>
                                <td>
                                    @if ($document->matter)
                                        <a class="doc-link" href="{{ route('matters.show', $document->matter) }}">{{ $document->matter->matter_reference }}</a>
                                    @elseif ($document->suggestedMatter)
                                        <span class="review">Suggested: {{ $document->suggestedMatter->matter_reference }}</span>
                                    @else
                                        <span class="review">Needs matter</span>
                                    @endif
                                </td>
                                <td>{{ number_format($document->confidence * 100) }}%</td>
                                <td>{{ $document->classification_reason }}</td>
                                <td class="{{ $document->review_status === 'Filed' ? 'filed' : 'review' }}">
                                    {{ $document->review_status }}
                                </td>
                                <td>{{ $document->classified_at?->format('d M Y, H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </main>
</body>
</html>
