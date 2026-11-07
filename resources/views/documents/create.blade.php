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
        .container { margin: 40px auto; max-width: 760px; padding: 0 25px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(16,42,67,.08); padding: 30px; }
        .steps { color: #667085; line-height: 1.6; }
        .upload-box { background: #f8fafc; border: 2px dashed #98a2b3; border-radius: 8px; margin: 24px 0; padding: 28px; text-align: center; }
        input[type=file] { max-width: 100%; }
        button { background: #2563eb; border: 0; border-radius: 6px; color: white; cursor: pointer; font: inherit; padding: 12px 18px; }
        .error { background: #fef3f2; color: #b42318; margin-bottom: 18px; padding: 14px; }
        .note { color: #667085; font-size: 13px; line-height: 1.5; }
        .field-label { display: block; font-weight: bold; margin-bottom: 6px; }
        select { border: 1px solid #d0d5dd; border-radius: 6px; box-sizing: border-box; font: inherit; margin-bottom: 8px; max-width: 100%; min-height: 40px; padding: 9px 11px; width: 100%; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/home-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/home-ui-dark.css') }}">
</head>
<body class="theme-dark">
    <div class="topbar">
        <h1>HOME AI — Upload & Classify</h1>
        <a href="{{ route('documents.index') }}">View Filed Documents</a>
    </div>

    <main class="container">
        <section class="card">
            <h2>Upload a document</h2>
            <p class="steps">HOME AI will read the file, suggest its category, and move it into the relevant protected folder. Files with low-confidence results are marked for review.</p>

            @if ($errors->any())
                <div class="error">{{ $errors->first('document') ?: $errors->first() }}</div>
            @endif

            <form id="upload-form" method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="upload-box">
                    <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp,.docx" required>
                    <p class="note">Accepted: PDF, JPG, JPEG, PNG, WEBP, and DOCX. Maximum size: 10 MB.</p>
                </div>
                <label class="field-label" for="matter_id">Link to a matter (optional)</label>
                <select id="matter_id" name="matter_id">
                    <option value="">Detect automatically</option>
                    @foreach ($matters as $matter)
                        <option value="{{ $matter->id }}" @selected(old('matter_id') == $matter->id)>
                            {{ $matter->matter_reference }} | {{ $matter->title }}
                        </option>
                    @endforeach
                </select>
                <p class="note">Leave on automatic and HOME AI will link the document if it finds a matter reference, or suggest a match for you to confirm.</p>

                <button type="submit">Scan and File Document</button>
                <p class="note" id="upload-status" role="status" hidden></p>
            </form>

            <p class="note">Use synthetic or non-sensitive files while using the free Gemini tier.</p>
        </section>
    </main>
    <script>
        // Files over 4 MB cannot pass through the site itself (the host refuses requests that big),
        // so the browser sends them straight to storage and the form then only names the stored file.
        (function () {
            var form = document.getElementById('upload-form');
            var input = form.querySelector('input[type=file]');
            var button = form.querySelector('button[type=submit]');
            var status = document.getElementById('upload-status');
            var LIMIT = 4 * 1024 * 1024;
            var busy = false;

            function say(text, isError) {
                status.hidden = false;
                status.textContent = text;
                status.style.color = isError ? '#b42318' : '';
            }

            function failed(error) {
                busy = false;
                button.disabled = false;
                say(error.message || 'The upload failed. Please try again.', true);
            }

            function send(plan, file) {
                return new Promise(function (resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('PUT', plan.url);
                    Object.keys(plan.headers || {}).forEach(function (name) {
                        xhr.setRequestHeader(name, plan.headers[name]);
                    });
                    xhr.upload.onprogress = function (event) {
                        if (event.lengthComputable) {
                            say('Uploading the file: ' + Math.round(event.loaded / event.total * 100) + '%');
                        }
                    };
                    xhr.onload = function () {
                        if (xhr.status >= 200 && xhr.status < 300) { resolve(); } else {
                            reject(new Error('Storage refused the file (error ' + xhr.status + '). Please try again.'));
                        }
                    };
                    xhr.onerror = function () {
                        reject(new Error('The file could not be sent to storage. Check the connection and try again.'));
                    };
                    xhr.send(file);
                });
            }

            function hidden(name, value) {
                var field = document.createElement('input');
                field.type = 'hidden';
                field.name = name;
                field.value = value;
                form.appendChild(field);
            }

            form.addEventListener('submit', function (event) {
                var file = input.files && input.files[0];

                if (!file || file.size <= LIMIT) { return; }

                event.preventDefault();
                if (busy) { return; }
                busy = true;
                button.disabled = true;
                say('Preparing the upload...');

                fetch(@json(route('documents.direct-url')), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token())
                    },
                    body: JSON.stringify({ filename: file.name, size: file.size })
                }).then(function (response) {
                    return response.json().then(function (body) { return { ok: response.ok, body: body }; });
                }).then(function (result) {
                    if (!result.ok) {
                        var first = result.body.errors ? Object.values(result.body.errors)[0][0] : null;
                        throw new Error(result.body.message && !result.body.errors ? result.body.message : (first || 'The upload could not be prepared.'));
                    }

                    if (!result.body.direct) {
                        form.submit();
                        return null;
                    }

                    return send(result.body, file).then(function () {
                        hidden('uploaded_key', result.body.key);
                        hidden('uploaded_name', file.name);
                        input.disabled = true;
                        say('Scanning and filing the document. This can take a minute...');
                        form.submit();
                    });
                }).catch(failed);
            });
        })();
    </script>
</body>
</html>
