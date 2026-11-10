<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload & Classify | HOME AI</title>
    <style>
        body { margin: 0; background: #f3f6fb; color: #1d2939; font-family: Arial, sans-serif; }
        .topbar { align-items: center; background: #102a43; color: white; display: flex; justify-content: space-between; padding: 22px 45px; }
        .topbar h1 { font-size: 24px; margin: 0; }
        .topbar a { background: #2563eb; border-radius: 6px; color: white; padding: 10px 16px; text-decoration: none; }
        .container { margin: 40px auto; max-width: 900px; padding: 0 25px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(16,42,67,.08); padding: 30px; }
        .steps { color: #667085; line-height: 1.6; }
        .upload-box { background: #f8fafc; border: 2px dashed #98a2b3; border-radius: 8px; display: block; margin: 24px 0; padding: 30px; text-align: center; }
        .upload-box.over { background: #e8f0fe; border-color: #2563eb; }
        input[type=file] { max-width: 100%; }
        button { background: #2563eb; border: 0; border-radius: 6px; color: white; cursor: pointer; font: inherit; padding: 12px 18px; }
        button:disabled { background: #98a2b3; cursor: not-allowed; }
        button.secondary { background: #eaf1fb; color: #175cd3; }
        button.small { font-size: 13px; padding: 6px 10px; }
        .error { background: #fef3f2; color: #b42318; margin-bottom: 18px; padding: 14px; }
        .banner { background: #e8f0fe; border-radius: 8px; color: #1849a9; line-height: 1.5; margin-bottom: 18px; padding: 14px; }
        .note { color: #667085; font-size: 13px; line-height: 1.5; }
        .field-label { display: block; font-weight: bold; margin-bottom: 6px; }
        select { border: 1px solid #d0d5dd; border-radius: 6px; box-sizing: border-box; font: inherit; margin-bottom: 8px; max-width: 100%; min-height: 40px; padding: 9px 11px; width: 100%; }
        .queue { border-collapse: collapse; margin-top: 22px; width: 100%; }
        .queue td { border-top: 1px solid #eaecf0; font-size: 14px; padding: 11px 8px; vertical-align: top; }
        .queue .name { font-weight: bold; overflow-wrap: anywhere; }
        .queue .size { color: #667085; white-space: nowrap; }
        .st-queued { color: #667085; }
        .st-working { color: #175cd3; }
        .st-done { color: #027a48; }
        .st-failed { color: #b42318; }
        .st-working::before { animation: spin 1s linear infinite; border: 2px solid #b2ccff; border-radius: 50%; border-top-color: #175cd3; content: ""; display: inline-block; height: 12px; margin-right: 7px; vertical-align: -1px; width: 12px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .summary { background: #f8fafc; border-radius: 8px; display: none; line-height: 1.6; margin-top: 22px; padding: 18px; }
        .summary.show { display: block; }
        .summary .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
        .summary a.btn { background: #eaf1fb; border-radius: 6px; color: #175cd3; font-weight: bold; padding: 12px 18px; text-decoration: none; }
        .doc-link { color: #175cd3; font-weight: bold; text-decoration: none; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/home-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/home-ui-dark.css') }}">
</head>
<body class="theme-dark">
    @include('partials.quicknav')

    <div class="topbar">
        <h1>HOME AI — Upload & Classify</h1>
        <a href="{{ route('documents.index') }}">View Filed Documents</a>
    </div>

    <main class="container">
        <section class="card">
            <h2>Upload documents</h2>
            <p class="steps">Add one file or many. HOME AI reads each one, suggests its category, and links it to a matter when it can. Each document then waits for an officer to confirm it, one by one or all at once.</p>

            @if ($errors->any())
                <div class="error">{{ $errors->first('document') ?: $errors->first() }}</div>
            @endif

            @php($selectedMatter = $selectedMatterId ? $matters->firstWhere('id', $selectedMatterId) : null)
            @if ($selectedMatter)
                <div class="banner">
                    These documents will be linked to <strong>{{ $selectedMatter->matter_reference }}</strong>: {{ $selectedMatter->title }}.
                </div>
            @endif

            <label class="upload-box" id="dropzone" for="files">
                <strong>Drag files here</strong> or choose them:
                <br><br>
                <input type="file" id="files" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.docx">
                <p class="note">Accepted: PDF, JPG, JPEG, PNG, WEBP, and DOCX. Maximum size: 10 MB each. You can add as many files as you like.</p>
            </label>

            <label class="field-label" for="matter_id">Link all of these to a matter (optional)</label>
            <select id="matter_id" name="matter_id">
                <option value="">Detect automatically</option>
                @foreach ($matters as $matter)
                    <option value="{{ $matter->id }}" @selected((string) old('matter_id', $selectedMatterId) === (string) $matter->id)>
                        {{ $matter->matter_reference }} | {{ $matter->title }}
                    </option>
                @endforeach
            </select>
            <p class="note">Leave on automatic and HOME AI will link each document if it finds a matter reference, or suggest a match for you to confirm.</p>

            <table class="queue" id="queue" hidden>
                <tbody id="queueBody"></tbody>
            </table>

            <p>
                <button type="button" id="startButton" disabled>Scan and File Documents</button>
                <span class="note" id="startNote"></span>
            </p>

            <div class="summary" id="summary" aria-live="polite">
                <div id="summaryText"></div>
                <div class="actions">
                    <button type="button" id="confirmButton" hidden>Confirm the ones the scanner is sure about</button>
                    <a class="btn" id="reviewLink" href="{{ route('documents.index', ['status' => 'review']) }}">Review these documents</a>
                    @if ($selectedMatter)
                        <a class="btn" href="{{ route('matters.show', $selectedMatter) }}">Back to {{ $selectedMatter->matter_reference }}</a>
                    @endif
                    <button type="button" class="secondary" id="moreButton">Upload more</button>
                </div>
                <p class="note" id="confirmNote"></p>
            </div>

            <p class="note">Use synthetic or non-sensitive files while using the free Gemini tier.</p>
        </section>
    </main>

    <script>
        // Each file is sent on its own and answered on its own line, so one bad file never stops the rest.
        // Files over 4 MB cannot pass through the site itself (the host refuses requests that big),
        // so those go straight to storage first and the site then only reads them from there.
        (function () {
            var MAX = 10 * 1024 * 1024;
            var LIMIT = 4 * 1024 * 1024;
            var PARALLEL = 2;
            var ALLOWED = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'docx'];
            var csrf = @json(csrf_token());
            var urls = {
                item: @json(route('documents.upload-item')),
                direct: @json(route('documents.direct-url')),
                confirm: @json(route('documents.confirm-bulk'))
            };

            var input = document.getElementById('files');
            var drop = document.getElementById('dropzone');
            var queue = document.getElementById('queue');
            var body = document.getElementById('queueBody');
            var startButton = document.getElementById('startButton');
            var startNote = document.getElementById('startNote');
            var matter = document.getElementById('matter_id');
            var summary = document.getElementById('summary');
            var summaryText = document.getElementById('summaryText');
            var confirmButton = document.getElementById('confirmButton');
            var confirmNote = document.getElementById('confirmNote');
            var moreButton = document.getElementById('moreButton');

            var items = [];
            var active = 0;
            var running = false;

            function sizeText(bytes) {
                return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
            }

            function setStatus(item, state, text) {
                item.state = state;
                item.status.className = 'st-' + state;
                item.status.textContent = text;
                item.actions.textContent = '';

                if (state === 'failed' || state === 'queued') {
                    if (state === 'failed' && item.retryable) {
                        item.actions.appendChild(button('Retry', function () { item.retryable = false; setStatus(item, 'queued', 'Waiting'); refresh(); pump(); }));
                    }
                    item.actions.appendChild(button('Remove', function () { remove(item); }));
                }

                if (state === 'done' && item.result) {
                    var link = document.createElement('a');
                    link.className = 'doc-link';
                    link.href = item.result.url;
                    link.textContent = 'Open';
                    item.actions.appendChild(link);
                }
            }

            function button(label, handler) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'secondary small';
                b.textContent = label;
                b.addEventListener('click', handler);
                return b;
            }

            function remove(item) {
                items.splice(items.indexOf(item), 1);
                item.row.remove();
                refresh();
            }

            function addFiles(list) {
                Array.prototype.forEach.call(list, function (file) {
                    var duplicate = items.some(function (i) {
                        return i.file.name === file.name && i.file.size === file.size && i.file.lastModified === file.lastModified;
                    });
                    if (duplicate) { return; }

                    var row = document.createElement('tr');
                    var name = document.createElement('td');
                    var size = document.createElement('td');
                    var status = document.createElement('td');
                    var actions = document.createElement('td');
                    name.className = 'name';
                    name.textContent = file.name;
                    size.className = 'size';
                    size.textContent = sizeText(file.size);
                    row.appendChild(name);
                    row.appendChild(size);
                    row.appendChild(status);
                    row.appendChild(actions);
                    body.appendChild(row);

                    var item = { file: file, row: row, status: status, actions: actions, state: 'queued', retryable: false, result: null };
                    items.push(item);

                    var ext = (file.name.split('.').pop() || '').toLowerCase();
                    if (ALLOWED.indexOf(ext) === -1) {
                        setStatus(item, 'failed', 'Not accepted. Only PDF, JPG, PNG, WEBP and DOCX files can be uploaded.');
                    } else if (file.size > MAX) {
                        setStatus(item, 'failed', 'Larger than 10 MB.');
                    } else if (file.size < 1) {
                        setStatus(item, 'failed', 'The file is empty.');
                    } else {
                        setStatus(item, 'queued', 'Waiting');
                    }
                });

                input.value = '';
                refresh();
            }

            function refresh() {
                var waiting = items.filter(function (i) { return i.state === 'queued'; }).length;
                queue.hidden = items.length === 0;
                startButton.disabled = waiting === 0 || running;
                startButton.textContent = waiting > 1 ? 'Scan and file ' + waiting + ' documents' : 'Scan and file the document';
                startNote.textContent = '';
            }

            function friendly(status) {
                if (status === 413) { return 'The file is too large for the server. Press Retry once more or try a smaller file.'; }
                if (status === 429) { return 'Too many files at once. Wait a minute, then press Retry.'; }
                if (status === 403) { return 'Your account is not allowed to upload documents.'; }
                if (status >= 500) { return 'The server had a problem with this file. Press Retry.'; }
                return 'The file was not accepted.';
            }

            function post(item, fields) {
                var data = new FormData();
                Object.keys(fields).forEach(function (key) { data.append(key, fields[key]); });
                if (matter.value) { data.append('matter_id', matter.value); }

                return fetch(urls.item, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: data
                }).then(function (response) {
                    if (response.status === 419) {
                        throw new Error('Your session expired. Reload this page and sign in again.');
                    }
                    return response.json().catch(function () { return {}; }).then(function (json) {
                        if (!response.ok || !json.ok) {
                            var error = new Error(json.message || friendly(response.status));
                            error.retry = response.status >= 429 || !json.message;
                            throw error;
                        }
                        return json;
                    });
                });
            }

            function send(item) {
                return new Promise(function (resolve, reject) {
                    fetch(urls.direct, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ filename: item.file.name, size: item.file.size })
                    }).then(function (response) {
                        return response.json().catch(function () { return {}; }).then(function (json) {
                            if (!response.ok) { throw new Error(json.message || friendly(response.status)); }
                            if (!json.direct) { resolve(null); return; }

                            var xhr = new XMLHttpRequest();
                            xhr.open('PUT', json.url);
                            Object.keys(json.headers || {}).forEach(function (name) { xhr.setRequestHeader(name, json.headers[name]); });
                            xhr.upload.onprogress = function (event) {
                                if (event.lengthComputable) {
                                    setStatus(item, 'working', 'Uploading ' + Math.round(event.loaded / event.total * 100) + '%');
                                }
                            };
                            xhr.onload = function () {
                                if (xhr.status >= 200 && xhr.status < 300) { resolve(json.key); } else {
                                    var error = new Error('Storage refused the file (error ' + xhr.status + '). Press Retry.');
                                    error.retry = true;
                                    reject(error);
                                }
                            };
                            xhr.onerror = function () {
                                var error = new Error('The file could not be sent to storage. Check the connection, then press Retry.');
                                error.retry = true;
                                reject(error);
                            };
                            xhr.send(item.file);
                        });
                    }).catch(reject);
                });
            }

            function process(item) {
                setStatus(item, 'working', 'Uploading...');

                var work;
                if (item.file.size > LIMIT) {
                    work = send(item).then(function (key) {
                        setStatus(item, 'working', 'Scanning the document...');
                        return key
                            ? post(item, { uploaded_key: key, uploaded_name: item.file.name })
                            : post(item, { document: item.file });
                    });
                } else {
                    work = post(item, { document: item.file });
                }

                return work.then(function (result) {
                    item.result = result;
                    var text = result.category + ', ' + Math.round(result.confidence * 100) + '% sure';
                    text += result.matter ? '. Linked to ' + result.matter + '.' : (result.suggested_matter ? '. Suggested matter: ' + result.suggested_matter + '.' : '. No matter found.');
                    setStatus(item, 'done', text);
                }, function (error) {
                    item.retryable = error.retry !== false;
                    setStatus(item, 'failed', error.message || 'The upload failed.');
                });
            }

            function pump() {
                running = true;
                startButton.disabled = true;

                var next;
                while (active < PARALLEL && (next = items.filter(function (i) { return i.state === 'queued'; })[0])) {
                    active++;
                    (function (item) {
                        process(item).then(function () { active--; pump(); });
                    })(next);
                    next.state = 'working';
                }

                if (active === 0) { finish(); }
            }

            function finish() {
                running = false;
                refresh();

                var done = items.filter(function (i) { return i.state === 'done'; });
                var failed = items.filter(function (i) { return i.state === 'failed'; });
                var text = done.length + (done.length === 1 ? ' document' : ' documents') + ' scanned and sorted.';
                if (failed.length) { text += ' ' + failed.length + (failed.length === 1 ? ' file' : ' files') + ' could not be added. See the red lines above.'; }

                var sure = done.filter(function (i) { return i.result.confidence >= {{ (float) config('documents.bulk_confirm_threshold') }}; });
                summaryText.textContent = done.length
                    ? text + ' They now wait for an officer to confirm them.'
                    : text;
                confirmButton.hidden = sure.length === 0;
                confirmButton.disabled = false;
                confirmButton.textContent = 'Confirm the ' + sure.length + ' the scanner is sure about';
                confirmNote.textContent = '';
                document.getElementById('reviewLink').hidden = done.length === 0;
                summary.classList.add('show');
            }

            confirmButton.addEventListener('click', function () {
                var ids = items.filter(function (i) {
                    return i.state === 'done' && i.result.confidence >= {{ (float) config('documents.bulk_confirm_threshold') }} && i.result.review_status === 'Needs review';
                }).map(function (i) { return i.result.id; });

                confirmButton.disabled = true;
                fetch(urls.confirm, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ ids: ids })
                }).then(function (response) { return response.json(); }).then(function (json) {
                    confirmNote.textContent = json.message || 'Done.';
                }).catch(function () {
                    confirmButton.disabled = false;
                    confirmNote.textContent = 'That did not work. Please try again.';
                });
            });

            moreButton.addEventListener('click', function () {
                items = items.filter(function (i) { if (i.state === 'done' || i.state === 'failed') { i.row.remove(); return false; } return true; });
                summary.classList.remove('show');
                refresh();
            });

            startButton.addEventListener('click', pump);
            input.addEventListener('change', function () { addFiles(input.files); });

            ['dragenter', 'dragover'].forEach(function (name) {
                drop.addEventListener(name, function (event) { event.preventDefault(); drop.classList.add('over'); });
            });
            ['dragleave', 'drop'].forEach(function (name) {
                drop.addEventListener(name, function (event) { event.preventDefault(); drop.classList.remove('over'); });
            });
            drop.addEventListener('drop', function (event) { addFiles(event.dataTransfer.files); });

            window.addEventListener('beforeunload', function (event) {
                if (running) { event.preventDefault(); event.returnValue = ''; }
            });
        })();
    </script>
</body>
</html>
