<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document->original_name }} | HOME AI</title>
    <style>
        body { margin: 0; background: #f3f6fb; color: #1d2939; font-family: Arial, sans-serif; }
        .topbar { align-items: center; background: #102a43; color: white; display: flex; justify-content: space-between; padding: 22px 45px; }
        .topbar h1 { font-size: 24px; margin: 0; }
        .topbar a { background: #2563eb; border-radius: 6px; color: white; padding: 10px 16px; text-decoration: none; }
        .container { margin: 35px auto; max-width: 1000px; padding: 0 25px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(16,42,67,.08); margin-top: 22px; padding: 28px; }
        .details { display: grid; gap: 22px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .label { color: #667085; display: block; font-size: 12px; font-weight: bold; margin-bottom: 6px; text-transform: uppercase; }
        .value { font-size: 16px; word-break: break-word; }
        .filed { color: #027a48; font-weight: bold; }
        .review { color: #b54708; font-weight: bold; }
        .section-title { font-size: 16px; margin: 26px 0 8px; }
        .content { background: #f8fafc; border-left: 4px solid #2563eb; line-height: 1.6; padding: 16px; white-space: pre-wrap; word-break: break-word; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
        .btn { background: #2563eb; border: 0; border-radius: 6px; color: white; cursor: pointer; font: inherit; padding: 10px 16px; text-decoration: none; }
        .btn-light { background: #eaf1fb; color: #175cd3; }
        .success { background: #ecfdf3; color: #027a48; padding: 14px; }
        .error { background: #fef3f2; color: #b42318; padding: 14px; }
        .warn { background: #fffaeb; color: #b54708; padding: 14px; }
        select { border: 1px solid #d0d5dd; border-radius: 6px; font: inherit; min-height: 40px; padding: 9px 11px; }
        .move-form { align-items: center; display: flex; flex-wrap: wrap; gap: 10px; }
        @media (max-width: 650px) {
            .topbar { padding: 18px 22px; }
            .details { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>HOME AI: Document Details</h1>
        <a href="{{ route('documents.index') }}">Back to Documents</a>
    </div>

    <main class="container">
        @if (session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <h2>{{ $document->original_name }}</h2>

        <section class="card">
            <div class="details">
                <div>
                    <span class="label">Category</span>
                    <span class="value">{{ str($document->category)->headline() }}</span>
                </div>
                <div>
                    <span class="label">Filing Status</span>
                    <span class="value {{ $document->review_status === 'Filed' ? 'filed' : 'review' }}">{{ $document->review_status }}</span>
                </div>
                <div>
                    <span class="label">Confidence</span>
                    <span class="value">{{ number_format($document->confidence * 100) }}%</span>
                </div>
                <div>
                    <span class="label">Scanned</span>
                    <span class="value">{{ $document->classified_at?->format('d M Y, H:i') }}</span>
                </div>
                <div>
                    <span class="label">File Type</span>
                    <span class="value">{{ $document->mime_type }}</span>
                </div>
                <div>
                    <span class="label">File Size</span>
                    <span class="value">{{ number_format($document->file_size / 1024, 1) }} KB</span>
                </div>
            </div>

            <h3 class="section-title">Why this category</h3>
            <div class="content">{{ $document->classification_reason }}</div>

            <h3 class="section-title">Linked Matter</h3>
            @if ($document->matter)
                <div class="content">
                    <a href="{{ route('matters.show', $document->matter) }}">{{ $document->matter->matter_reference }}</a>
                    | {{ $document->matter->title }}
                    ({{ match ($document->matter_link_source) {
                        'reference' => 'linked automatically from the reference number',
                        'confirmed' => 'suggestion confirmed by you',
                        'created' => 'matter created from this document',
                        default => 'linked by you',
                    } }})
                </div>
            @elseif ($document->suggestedMatter)
                <div class="warn">
                    Suggested matter: <strong>{{ $document->suggestedMatter->matter_reference }}</strong> | {{ $document->suggestedMatter->title }}
                    @if (auth()->user()->canManageWork())
                    <form method="POST" action="{{ route('documents.matter', $document) }}" style="margin-top: 10px;">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="matter_id" value="{{ $document->suggestedMatter->id }}">
                        <button class="btn" type="submit">Confirm Link</button>
                    </form>
                    @endif
                </div>
            @else
                <div class="warn">This document is not linked to any matter yet.</div>
            @endif

            @if (auth()->user()->canManageWork())
            @unless ($document->matter)
                <div class="actions" style="margin-top: 12px;">
                    <a class="btn" href="{{ route('documents.matter.create', $document) }}">Create Matter from this Document</a>
                </div>
            @endunless

            <form class="move-form" style="margin-top: 12px;" method="POST" action="{{ route('documents.matter', $document) }}">
                @csrf
                @method('PATCH')
                <select name="matter_id">
                    <option value="">No matter (unlink)</option>
                    @foreach ($matters as $matter)
                        <option value="{{ $matter->id }}" @selected($document->matter_id == $matter->id)>
                            {{ $matter->matter_reference }} | {{ $matter->title }}
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-light" type="submit">Save Link</button>
            </form>
            @endif

            <h3 class="section-title">Original File</h3>
            @if ($fileExists)
                <div class="actions">
                    <a class="btn" href="{{ route('documents.file', $document) }}" target="_blank" rel="noopener">Open Original</a>
                    <a class="btn btn-light" href="{{ route('documents.file', ['document' => $document, 'download' => 1]) }}">Download</a>
                </div>
            @else
                <div class="warn">The stored file could not be found on disk.</div>
            @endif

            <h3 class="section-title">Scanned Content</h3>
            <div class="content">{{ $document->extracted_text ?: 'No text was extracted from this document.' }}</div>

            @if (auth()->user()->canManageWork())
            <h3 class="section-title">Review and Re-file</h3>
            <form class="move-form" method="POST" action="{{ route('documents.category', $document) }}">
                @csrf
                @method('PATCH')
                <select name="category">
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected($document->category === $category)>
                            {{ str($category)->headline() }}
                        </option>
                    @endforeach
                </select>
                <button class="btn" type="submit">
                    {{ $document->review_status === 'Filed' ? 'Move to Category' : 'Confirm and File' }}
                </button>
            </form>
            @endif
        </section>
    </main>
</body>
</html>
