<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Matter;
use App\Services\DocumentClassifier;
use App\Services\MatterMatcher;
use App\Support\Audit;
use App\Support\UploadRejected;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;

class DocumentController extends Controller
{
    public const CATEGORIES = [
        'correspondence',
        'bills',
        'invoices',
        'challans',
        'information',
        'miscellaneous',
        'letters',
    ];

    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'docx'];

    private const MAX_BYTES = 10 * 1024 * 1024;

    private const MIME_BY_EXTENSION = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** The shape of a storage key issued by directUploadUrl(): nothing else is accepted back. */
    private const DIRECT_KEY_PATTERN = '#^incoming/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(pdf|jpg|jpeg|png|webp|docx)$#';

    /**
     * The disk that holds uploaded documents: the server disk in development, the bucket in production.
     */
    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('documents.disk'));
    }

    public function index(Request $request)
    {
        $threshold = (float) config('documents.bulk_confirm_threshold');
        $onlyReview = $request->query('status') === 'review';

        $category = $request->query('category');
        $category = is_string($category) && in_array($category, self::CATEGORIES, true) ? $category : null;
        $showCategories = $category !== null || $request->query('view') === 'categories';

        $documents = Document::with(['matter', 'suggestedMatter'])
            ->when($onlyReview, fn ($query) => $query->where('review_status', 'Needs review'))
            ->when($category, fn ($query) => $query->where('category', $category))
            ->latest()
            ->get();

        $waiting = Document::where('review_status', 'Needs review')->count();
        $sure = Document::where('review_status', 'Needs review')->where('confidence', '>=', $threshold)->count();

        // How many documents sit in each category (only what this account may see), and how many of those still wait for review.
        $totals = Document::query()->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');
        $waitingByCategory = Document::query()->where('review_status', 'Needs review')
            ->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');

        $categoryCards = collect(self::CATEGORIES)->map(fn (string $name) => [
            'key' => $name,
            'label' => str($name)->headline()->toString(),
            'total' => (int) ($totals[$name] ?? 0),
            'waiting' => (int) ($waitingByCategory[$name] ?? 0),
        ]);

        return view('documents.index', [
            'documents' => $documents,
            'waiting' => $waiting,
            'sure' => $sure,
            'thresholdPercent' => (int) round($threshold * 100),
            'onlyReview' => $onlyReview,
            'category' => $category,
            'showCategories' => $showCategories,
            'categoryCards' => $categoryCards,
        ]);
    }

    public function show(Document $document)
    {
        $document->load(['matter', 'suggestedMatter']);

        $fileExists = $this->disk()->exists($document->stored_path);
        $categories = self::CATEGORIES;
        $matters = Matter::orderBy('matter_reference')->get(['id', 'matter_reference', 'title', 'status']);

        return view('documents.show', compact('document', 'fileExists', 'categories', 'matters'));
    }

    public function linkMatter(Request $request, Document $document)
    {
        $data = $request->validate([
            'matter_id' => ['nullable', 'integer', 'exists:matters,id'],
        ]);

        if (empty($data['matter_id'])) {
            $document->matter_id = null;
            $document->matter_link_source = null;
            $document->save();

            Audit::record('document.matter_unlinked', $document, [], $document->original_name);

            return redirect()
                ->route('documents.show', $document)
                ->with('success', 'The document is no longer linked to a matter.');
        }

        // The matter lookup respects the account's section limits, so a hidden matter cannot be linked by guessing its id.
        $matter = Matter::find($data['matter_id']);

        if (! $matter) {
            return back()->withErrors(['matter_id' => 'That matter does not exist or is not available to your account.']);
        }

        $acceptedSuggestion = (int) $data['matter_id'] === (int) $document->suggested_matter_id;

        $document->matter_id = $matter->id;
        $document->matter_link_source = $acceptedSuggestion ? 'confirmed' : 'manual';
        $document->suggested_matter_id = null;
        $document->save();

        Audit::record('document.matter_linked', $document, [
            'matter' => $matter->matter_reference,
            'source' => $document->matter_link_source,
        ], $document->original_name);

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'The document is linked to '.$matter->matter_reference.'.');
    }

    public function file(Request $request, Document $document)
    {
        $disk = $this->disk();

        abort_unless($disk->exists($document->stored_path), 404, 'The stored file could not be found.');

        $headers = ['X-Content-Type-Options' => 'nosniff'];

        if ($request->boolean('download')) {
            Audit::record('document.downloaded', $document, [], $document->original_name);

            return $disk->download($document->stored_path, $document->original_name, $headers);
        }

        Audit::record('document.opened', $document, [], $document->original_name);

        return $disk->response(
            $document->stored_path,
            $document->original_name,
            $headers + ['Content-Type' => $document->mime_type],
            'inline'
        );
    }

    /**
     * Removes a wrong or duplicate upload: the stored file first, then the record.
     * A copy of the record's details stays in the audit trail. If the file cannot be removed, nothing is deleted.
     */
    public function destroy(Document $document)
    {
        $disk = $this->disk();
        $name = $document->original_name;

        try {
            if ($disk->exists($document->stored_path)) {
                $disk->delete($document->stored_path);
            }
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['document' => 'HOME AI could not remove the stored file, so nothing was deleted. Please try again.']);
        }

        Audit::record('document.deleted', $document, [
            'snapshot' => $document->only([
                'original_name', 'stored_path', 'mime_type', 'file_size', 'category', 'confidence',
                'review_status', 'matter_id', 'file_hash', 'uploaded_by',
            ]),
        ], $name);

        $matterId = $document->matter_id;
        $document->delete();

        $message = '"'.$name.'" was deleted.';

        return $matterId && Matter::whereKey($matterId)->exists()
            ? redirect()->route('matters.show', $matterId)->with('success', $message)
            : redirect()->route('documents.index')->with('success', $message);
    }

    public function updateCategory(Request $request, Document $document)
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', self::CATEGORIES)],
        ]);

        $newCategory = $data['category'];
        $oldCategory = $document->category;

        if ($newCategory !== $document->category) {
            $disk = $this->disk();

            if (! $disk->exists($document->stored_path)) {
                return back()->withErrors(['category' => 'The stored file could not be found, so it was not moved.']);
            }

            $newPath = 'documents/'.$newCategory.'/'.basename($document->stored_path);

            $disk->makeDirectory('documents/'.$newCategory);

            if (! $this->moveStored($disk, $document->stored_path, $newPath)) {
                return back()->withErrors(['category' => 'The file could not be moved to the new category folder.']);
            }

            $document->stored_path = $newPath;
            $document->category = $newCategory;
        }

        $document->review_status = 'Filed';
        $document->save();

        Audit::record('document.confirmed', $document, [
            'category_before' => $oldCategory,
            'category_after' => $newCategory,
        ], $document->original_name);

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'The document is filed under '.str($newCategory)->headline().'.');
    }

    public function create(Request $request)
    {
        $matters = Matter::whereIn('status', ['Open', 'Pending'])
            ->orderBy('matter_reference')
            ->get(['id', 'matter_reference', 'title']);

        // Arriving from a matter page: that matter is already chosen.
        $selectedMatterId = $matters->contains('id', $request->integer('matter')) ? $request->integer('matter') : null;

        return view('documents.create', compact('matters', 'selectedMatterId'));
    }

    /**
     * Step 1 of a large upload: hands the browser a short-lived link so it can send the file
     * straight to storage. Vercel refuses request bodies over about 4.5 MB, so large files
     * cannot travel through the app itself.
     */
    public function directUploadUrl(Request $request)
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_BYTES],
        ], [
            'size.max' => 'The file is larger than 10 MB.',
        ]);

        $extension = strtolower(pathinfo($data['filename'], PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return response()->json(['message' => 'Only PDF, JPG, JPEG, PNG, WEBP and DOCX files can be uploaded.'], 422);
        }

        // A server disk (development) has no browser upload links. The page then uses the normal form.
        if (config('filesystems.disks.'.config('documents.disk').'.driver') !== 's3') {
            return response()->json(['direct' => false]);
        }

        $key = 'incoming/'.Str::uuid().'.'.$extension;

        try {
            $signed = $this->disk()->temporaryUploadUrl($key, now()->addMinutes(15), [
                'ContentType' => self::MIME_BY_EXTENSION[$extension],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'HOME AI could not prepare the upload link. Please try again.'], 500);
        }

        // Only links issued to this session can be turned into a document later.
        $pending = array_slice((array) $request->session()->get('direct_uploads', []), -20);
        $pending[] = $key;
        $request->session()->put('direct_uploads', $pending);

        $headers = [];

        foreach ($signed['headers'] as $name => $value) {
            if (in_array(strtolower($name), ['host', 'content-length'], true)) {
                continue;
            }

            $headers[$name] = is_array($value) ? implode(',', $value) : (string) $value;
        }

        return response()->json([
            'direct' => true,
            'url' => $signed['url'],
            'headers' => $headers,
            'key' => $key,
        ]);
    }

    public function store(Request $request, DocumentClassifier $classifier, MatterMatcher $matcher)
    {
        try {
            $result = $this->ingest($request, $classifier, $matcher);
        } catch (UploadRejected $rejected) {
            return back()->withInput()->withErrors([$rejected->field => $rejected->getMessage()]);
        }

        return redirect()
            ->route('documents.index')
            ->with('success', $result['message']);
    }

    /**
     * One file per request, answered as JSON, so the upload page can send many files in a row
     * and show the result of each one on its own line.
     */
    public function storeItem(Request $request, DocumentClassifier $classifier, MatterMatcher $matcher)
    {
        try {
            $result = $this->ingest($request, $classifier, $matcher);
        } catch (UploadRejected $rejected) {
            return response()->json(['ok' => false, 'message' => $rejected->getMessage()], 422);
        } catch (ValidationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => collect($exception->errors())->flatten()->first() ?? 'The file was not accepted.',
            ], 422);
        }

        $document = $result['document'];

        return response()->json([
            'ok' => true,
            'id' => $document->id,
            'name' => $document->original_name,
            'category' => str($document->category)->headline()->toString(),
            'confidence' => (float) $document->confidence,
            'review_status' => $document->review_status,
            'matter' => $result['matter']?->matter_reference,
            'suggested_matter' => $result['suggested']?->matter_reference,
            'url' => route('documents.show', $document),
            'message' => $result['message'],
        ]);
    }

    /**
     * Confirms, in one go, every document still waiting for review that the scanner is very sure about.
     * An officer presses the button, so each of these is still a person's confirmation, recorded in the audit trail.
     * "ids" limits it to the documents of one upload batch. Without it, every eligible document is confirmed.
     */
    public function confirmBulk(Request $request)
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $threshold = (float) config('documents.bulk_confirm_threshold');

        $query = Document::where('review_status', 'Needs review')
            ->where('confidence', '>=', $threshold);

        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        }

        $confirmed = 0;

        foreach ($query->get() as $document) {
            $document->review_status = 'Filed';
            $document->save();

            Audit::record('document.confirmed', $document, [
                'category_before' => $document->category,
                'category_after' => $document->category,
                'bulk' => true,
                'confidence' => (float) $document->confidence,
            ], $document->original_name);

            $confirmed++;
        }

        $remaining = Document::where('review_status', 'Needs review')->count();

        $message = $confirmed === 0
            ? 'No documents met the confirm-all rule. Open each document to confirm it.'
            : $confirmed.' '.Str::plural('document', $confirmed).' confirmed and filed.'
                .($remaining > 0 ? ' '.$remaining.' still '.($remaining === 1 ? 'needs' : 'need').' an officer to look at them.' : '');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'confirmed' => $confirmed, 'remaining' => $remaining, 'message' => $message]);
        }

        return redirect()->route('documents.index')->with('success', $message);
    }

    /**
     * Checks one file (from the form, or already sent to storage), scans it, files it and records it.
     * Throws UploadRejected, with a readable message, when the file is not accepted.
     *
     * @return array{document: Document, matter: ?Matter, suggested: ?Matter, message: string}
     */
    private function ingest(Request $request, DocumentClassifier $classifier, MatterMatcher $matcher): array
    {
        $disk = $this->disk();

        $prepared = $request->filled('uploaded_key')
            ? $this->prepareDirectUpload($request, $disk)
            : $this->prepareFormUpload($request);

        $workingCopy = $prepared['working_copy'];
        $directKey = $prepared['direct_key'];
        $destination = null;
        $analysis = null;
        $matter = null;
        $suggestedMatterId = null;

        try {
            if ($prepared['copy_from'] !== null && ! copy($prepared['copy_from'], $workingCopy)) {
                throw new RuntimeException('The uploaded file could not be read.');
            }

            $analysis = $classifier->classify($workingCopy, $prepared['mime']);

            $destination = 'documents/'.$analysis['category'].'/'.Str::uuid().'.'.$prepared['extension'];

            $stream = fopen($workingCopy, 'rb');

            if ($stream === false) {
                throw new RuntimeException('The uploaded file could not be read.');
            }

            $saved = $disk->put($destination, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $saved) {
                $destination = null;

                throw new RuntimeException('The file could not be saved to storage.');
            }

            // A large file came in through the holding folder: the filed copy is saved, so remove the holding copy.
            $this->discard($disk, $directKey);

            $linkSource = null;

            if ($request->filled('matter_id')) {
                $matter = Matter::find((int) $request->input('matter_id'));
                $linkSource = $matter ? 'manual' : null;
            }

            if (! $matter) {
                $matter = $matcher->findByReference(
                    $analysis['matter_reference'] ?? null,
                    $analysis['extracted_text'] ?? null,
                    $prepared['name']
                );
                $linkSource = $matter ? 'reference' : null;
            }

            if (! $matter) {
                $suggestedMatterId = $matcher->suggestByTitle(
                    ($analysis['subject'] ?? '').' '.($analysis['extracted_text'] ?? '')
                )?->id;
            }

            $autoFile = config('documents.auto_file') && $analysis['confidence'] >= config('documents.auto_file_threshold');

            $document = Document::create([
                'original_name' => $prepared['name'],
                'stored_path' => $destination,
                'mime_type' => $prepared['mime'],
                'file_size' => $prepared['size'],
                'file_hash' => $prepared['hash'],
                'uploaded_by' => auth()->id(),
                'category' => $analysis['category'],
                'confidence' => $analysis['confidence'],
                'classification_reason' => $analysis['reason'],
                'extracted_text' => $analysis['extracted_text'],
                'review_status' => $autoFile ? 'Filed' : 'Needs review',
                'classified_at' => now(),
                'matter_id' => $matter?->id,
                'suggested_matter_id' => $suggestedMatterId,
                'matter_link_source' => $linkSource,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $this->discard($disk, $destination);
            $this->discard($disk, $directKey);

            throw new UploadRejected('This exact file has already been uploaded. It was not uploaded again.');
        } catch (Throwable $exception) {
            $this->discard($disk, $destination);
            $this->discard($disk, $directKey);

            throw new UploadRejected('HOME AI could not classify this file. '.$exception->getMessage());
        } finally {
            if (is_file($workingCopy)) {
                @unlink($workingCopy);
            }
        }

        Audit::record('document.uploaded', $document, [
            'category' => $analysis['category'],
            'confidence' => $analysis['confidence'],
            'review_status' => $document->review_status,
            'matter' => $matter?->matter_reference,
        ], $document->original_name);

        $message = $document->review_status === 'Filed'
            ? 'The document was scanned and filed in '.str($analysis['category'])->headline().'.'
            : 'The document was scanned and sorted into '.str($analysis['category'])->headline().'. An officer must confirm it on the document page before it counts as filed.';

        $suggested = null;

        if ($matter) {
            $message .= ' It is linked to '.$matter->matter_reference.'.';
        } elseif ($suggestedMatterId) {
            $suggested = Matter::find($suggestedMatterId);
            $message .= ' A matching matter was suggested. Please confirm it on the document page.';
        } else {
            $message .= ' No matching matter was found. You can link it manually.';
        }

        return ['document' => $document, 'matter' => $matter, 'suggested' => $suggested, 'message' => $message];
    }

    /**
     * A file that came through the form (small files). Checks it and describes it for ingest().
     *
     * @return array<string, mixed>
     */
    private function prepareFormUpload(Request $request): array
    {
        $file = $request->file('document');

        if (! $file instanceof UploadedFile) {
            throw new UploadRejected('No file reached HOME AI. Please select the file again and retry.');
        }

        if (! $file->isValid()) {
            throw new UploadRejected(match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the PHP upload limit.',
                UPLOAD_ERR_PARTIAL => 'The file upload was interrupted before it finished. Please try again.',
                UPLOAD_ERR_NO_FILE => 'No file was selected.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server could not find a temporary upload folder.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the file to its temporary upload folder.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped this file upload.',
                default => 'The file upload failed with error code '.$file->getError().'.',
            });
        }

        $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,docx', 'max:10240'],
            'matter_id' => ['nullable', 'integer', 'exists:matters,id'],
        ]);

        // A matter chosen at upload must be one this account is allowed to see.
        if ($request->filled('matter_id') && ! Matter::whereKey($request->input('matter_id'))->exists()) {
            throw new UploadRejected('That matter does not exist or is not available to your account.', 'matter_id');
        }

        // The same file is never filed twice. This also saves a scan.
        $hash = hash_file('sha256', $file->getRealPath());

        $this->assertNotDuplicate($hash, $file->getClientOriginalName());

        $extension = $this->safeExtension($file);

        return [
            'name' => $file->getClientOriginalName(),
            'mime' => (string) $file->getMimeType(),
            'size' => $file->getSize(),
            'hash' => $hash,
            'extension' => $extension,
            'working_copy' => sys_get_temp_dir().'/homeai-'.Str::uuid().'.'.$extension,
            'copy_from' => $file->getRealPath(),
            'direct_key' => null,
        ];
    }

    /**
     * A large file the browser already sent to storage (step 2). The file never passed through
     * the validation above, so everything is checked again here from the stored bytes.
     *
     * @return array<string, mixed>
     */
    private function prepareDirectUpload(Request $request, FilesystemAdapter $disk): array
    {
        $data = $request->validate([
            'uploaded_key' => ['required', 'string', 'regex:'.self::DIRECT_KEY_PATTERN],
            'uploaded_name' => ['required', 'string', 'max:255'],
            'matter_id' => ['nullable', 'integer', 'exists:matters,id'],
        ]);

        $key = $data['uploaded_key'];
        $pending = (array) $request->session()->get('direct_uploads', []);

        // Only a link this session was given can be used, and only once.
        if (! in_array($key, $pending, true)) {
            throw new UploadRejected('This upload is no longer valid. Please select the file again.');
        }

        $request->session()->put('direct_uploads', array_values(array_diff($pending, [$key])));

        $fail = function (string $message) use ($disk, $key): never {
            $this->discard($disk, $key);

            throw new UploadRejected($message);
        };

        if ($request->filled('matter_id') && ! Matter::whereKey($request->input('matter_id'))->exists()) {
            $this->discard($disk, $key);

            throw new UploadRejected('That matter does not exist or is not available to your account.', 'matter_id');
        }

        $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $workingCopy = sys_get_temp_dir().'/homeai-'.Str::uuid().'.'.$extension;

        try {
            if (! $disk->exists($key)) {
                $fail('The file did not reach storage. Please select it again and retry.');
            }

            $size = (int) $disk->size($key);

            if ($size < 1 || $size > self::MAX_BYTES) {
                $fail('The file is empty or larger than 10 MB.');
            }

            $in = $disk->readStream($key);
            $out = fopen($workingCopy, 'wb');

            if (! is_resource($in) || ! is_resource($out) || stream_copy_to_stream($in, $out) === false) {
                throw new RuntimeException('The stored file could not be read.');
            }

            fclose($in);
            fclose($out);
        } catch (UploadRejected $rejected) {
            @unlink($workingCopy);

            throw $rejected;
        } catch (Throwable $exception) {
            report($exception);
            @unlink($workingCopy);

            $fail('The uploaded file could not be read from storage. '.$exception->getMessage());
        }

        $mime = $this->verifiedMime($workingCopy, $extension);

        if ($mime === null) {
            @unlink($workingCopy);

            $fail('The file content does not match its type. Only real PDF, JPG, PNG, WEBP and DOCX files are accepted.');
        }

        $hash = hash_file('sha256', $workingCopy);
        $name = Str::limit(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', basename(str_replace('\\', '/', $data['uploaded_name']))), 200, '');
        $name = $name !== '' ? $name : 'document.'.$extension;

        try {
            $this->assertNotDuplicate($hash, $name);
        } catch (UploadRejected $rejected) {
            @unlink($workingCopy);
            $this->discard($disk, $key);

            throw $rejected;
        }

        return [
            'name' => $name,
            'mime' => $mime,
            'size' => $size,
            'hash' => $hash,
            'extension' => $extension,
            'working_copy' => $workingCopy,
            'copy_from' => null,
            'direct_key' => $key,
        ];
    }

    /**
     * The same file is never filed twice. Throws when this one is a repeat.
     */
    private function assertNotDuplicate(string $hash, string $uploadedName): void
    {
        $existing = Document::withoutGlobalScopes()->where('file_hash', $hash)->first();

        if (! $existing) {
            return;
        }

        Audit::record('document.duplicate_blocked', $existing, [
            'uploaded_name' => $uploadedName,
        ], $uploadedName);

        // Only describe the existing document to an account that is allowed to see it.
        $visible = Document::whereKey($existing->id)->first();
        $detail = $visible ? ' It is already filed as document '.$visible->id.' ('.$visible->original_name.').' : '';

        throw new UploadRejected('This exact file has already been uploaded.'.$detail.' It was not uploaded again.');
    }

    /**
     * The real type of a stored file, read from its bytes and not from its name. Null when it is not an accepted type.
     */
    private function verifiedMime(string $path, string $extension): ?string
    {
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';

        $accepted = match ($extension) {
            'pdf' => ['application/pdf'],
            'jpg', 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'docx' => [self::MIME_BY_EXTENSION['docx'], 'application/zip', 'application/octet-stream'],
            default => [],
        };

        if (! in_array($detected, $accepted, true)) {
            return null;
        }

        if ($extension === 'docx') {
            $zip = new ZipArchive;

            if ($zip->open($path) !== true || $zip->locateName('word/document.xml') === false) {
                return null;
            }

            $zip->close();
        }

        return self::MIME_BY_EXTENSION[$extension];
    }

    /**
     * A file extension taken from a short list, never copied blindly from the browser.
     */
    private function safeExtension(UploadedFile $file): string
    {
        $client = strtolower($file->getClientOriginalExtension());

        if (in_array($client, self::ALLOWED_EXTENSIONS, true)) {
            return $client;
        }

        $guessed = strtolower((string) $file->guessExtension());

        return in_array($guessed, self::ALLOWED_EXTENSIONS, true) ? $guessed : 'bin';
    }

    /**
     * Moves a stored file by saving the new copy and then removing the old one.
     * The bucket's own "copy" command is not used because Supabase Storage does not support
     * the permission lookup that Laravel's move() performs first.
     */
    private function moveStored(FilesystemAdapter $disk, string $from, string $to): bool
    {
        $stream = $disk->readStream($from);

        if (! is_resource($stream)) {
            return false;
        }

        try {
            $saved = $disk->writeStream($to, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $saved) {
            return false;
        }

        return $disk->delete($from);
    }

    /**
     * Removes a file that was saved before something later in the upload failed, so no orphan is left behind.
     */
    private function discard(FilesystemAdapter $disk, ?string $path): void
    {
        if ($path === null) {
            return;
        }

        try {
            $disk->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
