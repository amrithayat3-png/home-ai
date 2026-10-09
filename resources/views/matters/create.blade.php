@php
    $required = ['title', 'section', 'category', 'priority', 'deadline'];
    $isMissing = fn ($field) => $document && in_array($field, $missing, true);
    $value = fn ($field) => old($field, $prefill[$field] ?? '');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Matter | HOME AI</title>
    <style>
        body { margin: 0; background: #f3f6fb; color: #1d2939; font-family: Arial, sans-serif; }
        .topbar { align-items: center; background: #102a43; color: white; display: flex; justify-content: space-between; padding: 22px 45px; }
        .topbar h1 { font-size: 24px; margin: 0; }
        .topbar a { background: #2563eb; border-radius: 6px; color: white; padding: 10px 16px; text-decoration: none; }
        .container { margin: 35px auto; max-width: 900px; padding: 0 25px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(16,42,67,.08); padding: 28px; }
        .grid { display: grid; gap: 20px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .full { grid-column: 1 / -1; }
        label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px; }
        .req { color: #b42318; }
        input, select, textarea { border: 1px solid #d0d5dd; border-radius: 6px; box-sizing: border-box; font: inherit; padding: 10px 12px; width: 100%; }
        textarea { min-height: 90px; resize: vertical; }
        .missing input, .missing select, .missing textarea { background: #fffaeb; border-color: #f79009; }
        .missing.is-required input, .missing.is-required select, .missing.is-required textarea { background: #fef3f2; border-color: #f04438; }
        .hint { color: #b54708; font-size: 12px; margin-top: 5px; }
        .is-required .hint { color: #b42318; }
        .field-error { color: #b42318; font-size: 12px; margin-top: 5px; }
        .banner { border-radius: 6px; line-height: 1.5; margin-bottom: 20px; padding: 14px; }
        .banner.info { background: #eff8ff; color: #175cd3; }
        .banner.warn { background: #fffaeb; color: #b54708; }
        .banner.error { background: #fef3f2; color: #b42318; }
        .actions { display: flex; gap: 10px; margin-top: 26px; }
        .btn { background: #2563eb; border: 0; border-radius: 6px; color: white; cursor: pointer; font: inherit; padding: 12px 20px; text-decoration: none; }
        .btn-light { background: #eaf1fb; color: #175cd3; }
        @media (max-width: 650px) {
            .topbar { padding: 18px 22px; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>HOME AI: New Matter</h1>
        <a href="{{ $document ? route('documents.show', $document) : route('matters') }}">Cancel</a>
    </div>

    <main class="container">
        <section class="card">
            @if ($document)
                <div class="banner info">
                    Creating a matter from <strong>{{ $document->original_name }}</strong>.
                    HOME AI filled in what the document states. Fields marked in amber or red were not found in the document, so please enter them yourself. Nothing is saved until you press Create Matter.
                </div>
            @endif

            @if ($notice)
                <div class="banner warn">{{ $notice }}</div>
            @endif

            @if ($errors->any())
                <div class="banner error">Please fix the highlighted fields and try again.</div>
            @endif

            <form method="POST" action="{{ route('matters.store') }}">
                @csrf

                @if ($document)
                    <input type="hidden" name="document_id" value="{{ $document->id }}">
                @endif

                <div class="grid">
                    <div class="full {{ $isMissing('title') ? 'missing is-required' : '' }}">
                        <label for="title">Matter title <span class="req">*</span></label>
                        <input id="title" name="title" type="text" value="{{ $value('title') }}" required>
                        @if ($isMissing('title')) <div class="hint">Required. Not found in the document.</div> @endif
                        @error('title') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="{{ $isMissing('section') ? 'missing is-required' : '' }}">
                        <label for="section">Section <span class="req">*</span></label>
                        <select id="section" name="section" required>
                            <option value="">Select a section</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section }}" @selected($value('section') === $section)>{{ $section }}</option>
                            @endforeach
                        </select>
                        @if ($isMissing('section')) <div class="hint">Required. Could not be determined from the document.</div> @endif
                        @error('section') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="{{ $isMissing('category') ? 'missing is-required' : '' }}">
                        <label for="category">Category <span class="req">*</span></label>
                        <input id="category" name="category" type="text" list="category-list" value="{{ $value('category') }}" required>
                        <datalist id="category-list">
                            @foreach ($categories as $category)
                                <option value="{{ $category }}"></option>
                            @endforeach
                        </datalist>
                        @if ($isMissing('category')) <div class="hint">Required. Not found in the document.</div> @endif
                        @error('category') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="{{ $isMissing('priority') ? 'missing is-required' : '' }}">
                        <label for="priority">Priority <span class="req">*</span></label>
                        <select id="priority" name="priority" required>
                            <option value="">Select a priority</option>
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority }}" @selected($value('priority') === $priority)>{{ $priority }}</option>
                            @endforeach
                        </select>
                        @if ($isMissing('priority')) <div class="hint">Required. The document does not state its urgency.</div> @endif
                        @error('priority') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="{{ $isMissing('deadline') ? 'missing is-required' : '' }}">
                        <label for="deadline">Deadline <span class="req">*</span></label>
                        <input id="deadline" name="deadline" type="date" value="{{ $value('deadline') }}" required>
                        @if ($isMissing('deadline')) <div class="hint">Required. No due date found in the document.</div> @endif
                        @error('deadline') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="{{ $isMissing('received_date') ? 'missing' : '' }}">
                        <label for="received_date">Received date</label>
                        <input id="received_date" name="received_date" type="date" value="{{ $value('received_date') }}">
                        @if ($isMissing('received_date')) <div class="hint">Not found in the document (optional).</div> @endif
                        @error('received_date') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="{{ $isMissing('district') ? 'missing' : '' }}">
                        <label for="district">District</label>
                        <input id="district" name="district" type="text" value="{{ $value('district') }}">
                        @if ($isMissing('district')) <div class="hint">Not found in the document (optional).</div> @endif
                        @error('district') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="{{ $isMissing('assigned_officer') ? 'missing' : '' }}">
                        <label for="assigned_officer">Assigned officer</label>
                        <input id="assigned_officer" name="assigned_officer" type="text" value="{{ $value('assigned_officer') }}">
                        @if ($isMissing('assigned_officer')) <div class="hint">Not found in the document (optional).</div> @endif
                        @error('assigned_officer') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="full {{ $isMissing('related_department') ? 'missing' : '' }}">
                        <label for="related_department">Related department</label>
                        <input id="related_department" name="related_department" type="text" value="{{ $value('related_department') }}">
                        @if ($isMissing('related_department')) <div class="hint">Not found in the document (optional).</div> @endif
                        @error('related_department') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="full {{ $isMissing('summary') ? 'missing' : '' }}">
                        <label for="summary">Summary</label>
                        <textarea id="summary" name="summary">{{ $value('summary') }}</textarea>
                        @if ($isMissing('summary')) <div class="hint">Not found in the document (optional).</div> @endif
                        @error('summary') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="full">
                        <label for="last_action">Last action</label>
                        <textarea id="last_action" name="last_action">{{ $value('last_action') }}</textarea>
                        @error('last_action') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="full {{ $isMissing('next_action') ? 'missing' : '' }}">
                        <label for="next_action">Next action</label>
                        <textarea id="next_action" name="next_action">{{ $value('next_action') }}</textarea>
                        @if ($isMissing('next_action')) <div class="hint">Not found in the document (optional).</div> @endif
                        @error('next_action') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Create Matter</button>
                    <a class="btn btn-light" href="{{ $document ? route('documents.show', $document) : route('matters') }}">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
