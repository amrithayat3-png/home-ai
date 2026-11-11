<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filed Documents | HOME AI</title>
    <link rel="icon" href="{{ asset('images/centre-seal-64.png') }}" type="image/png">
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
        .bulk-card { align-items: center; background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(16,42,67,.08); display: flex; flex-wrap: wrap; gap: 14px; justify-content: space-between; margin-bottom: 20px; padding: 18px 20px; }
        .bulk-card p { margin: 4px 0 0; color: #667085; font-size: 13px; }
        .bulk-card button { background: #027a48; border: 0; border-radius: 8px; color: white; cursor: pointer; font: inherit; font-weight: bold; padding: 12px 18px; }
        .tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .tabs a { white-space: nowrap; }
        .tabs a { background: white; border-radius: 999px; color: #344054; font-size: 14px; padding: 8px 16px; text-decoration: none; box-shadow: 0 1px 4px rgba(16,42,67,.08); }
        .tabs a.active { background: #175cd3; color: white; }
        .category-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); margin-bottom: 18px; }
        .category-card { background: white; border-radius: 12px; box-shadow: 0 1px 6px rgba(16,42,67,.1); color: #1d2939; display: flex; flex-direction: column; gap: 4px; padding: 18px; text-decoration: none; }
        .category-card.current { outline: 2px solid #175cd3; }
        .category-card .cat-name { font-weight: bold; font-size: 15px; }
        .category-card .cat-count { color: #175cd3; font-size: 30px; font-weight: bold; line-height: 1.1; }
        .category-card .cat-note { color: #667085; font-size: 13px; }
        .cat-filter { color: #475467; font-size: 14px; margin: 0 0 14px; }
        .cat-filter a { color: #175cd3; font-weight: bold; margin-left: 8px; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/home-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/home-ui-dark.css') }}">
</head>
<body class="theme-dark">
    @include('partials.quicknav')
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

        @if (auth()->user()->canManageWork() && $waiting > 0)
            <div class="bulk-card">
                <div>
                    <strong>{{ $waiting }} {{ \Illuminate\Support\Str::plural('document', $waiting) }} waiting for an officer to confirm.</strong>
                    <p>
                        @if ($sure > 0)
                            The scanner is very sure ({{ $thresholdPercent }}% or more) about {{ $sure }} of them. Confirming files them under the category the scanner chose. You can still change any document later.
                        @else
                            None reaches {{ $thresholdPercent }}% confidence, so each one needs an officer to open it.
                        @endif
                    </p>
                </div>
                @if ($sure > 0)
                    <form method="POST" action="{{ route('documents.confirm-bulk') }}"
                          onsubmit="return confirm('Confirm and file {{ $sure }} {{ \Illuminate\Support\Str::plural('document', $sure) }}? You can still change any of them later.')">
                        @csrf
                        <button type="submit">Confirm {{ $sure }} the scanner is sure about</button>
                    </form>
                @endif
            </div>
        @endif

        <div class="tabs">
            <a href="{{ route('documents.index') }}" @class(['active' => ! $onlyReview && ! $showCategories])>All documents</a>
            <a href="{{ route('documents.index', ['status' => 'review']) }}" @class(['active' => $onlyReview])>Needs review ({{ $waiting }})</a>
            <a href="{{ route('documents.index', ['view' => 'categories']) }}" @class(['active' => $showCategories])>Categories</a>
        </div>

        @if ($showCategories)
            <div class="category-grid" id="categories">
                @foreach ($categoryCards as $card)
                    <a class="category-card @if ($category === $card['key']) current @endif" href="{{ route('documents.index', ['category' => $card['key']]) }}">
                        <span class="cat-name">{{ $card['label'] }}</span>
                        <span class="cat-count">{{ $card['total'] }}</span>
                        <span class="cat-note">
                            {{ \Illuminate\Support\Str::plural('document', $card['total']) }}
                            @if ($card['waiting'] > 0)
                                &middot; {{ $card['waiting'] }} waiting for review
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>

            @if ($category)
                <p class="cat-filter">
                    Showing <strong>{{ str($category)->headline() }}</strong> only.
                    <a href="{{ route('documents.index', ['view' => 'categories']) }}">Show all categories</a>
                </p>
            @endif
        @endif

        <div class="table-card">
            @if ($documents->isEmpty())
                <div class="empty">{{ $category ? 'No documents in this category yet.' : ($onlyReview ? 'Nothing is waiting for review.' : 'No documents have been uploaded yet.') }}</div>
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
                                <td><a class="doc-link" href="{{ route('documents.index', ['category' => $document->category]) }}">{{ str($document->category)->headline() }}</a></td>
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
