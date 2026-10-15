<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Matter;
use App\Services\DocumentClassifier;
use App\Services\MatterMatcher;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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

    /**
     * The disk that holds uploaded documents: the server disk in development, the bucket in production.
     */
    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('documents.disk'));
    }

    public function index()
    {
        $documents = Document::with(['matter', 'suggestedMatter'])->latest()->get();

        return view('documents.index', compact('documents'));
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

            if (! $disk->move($document->stored_path, $newPath)) {
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

    public function create()
    {
        $matters = Matter::whereIn('status', ['Open', 'Pending'])
            ->orderBy('matter_reference')
            ->get(['id', 'matter_reference', 'title']);

        return view('documents.create', compact('matters'));
    }

    public function store(Request $request, DocumentClassifier $classifier, MatterMatcher $matcher)
    {
        $file = $request->allFiles()['document'] ?? null;

        if (! $file) {
            return back()->withErrors(['document' => 'No file reached HOME AI. Please select the file again and retry.']);
        }

        if (! $file->isValid()) {
            $message = match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the PHP upload limit.',
                UPLOAD_ERR_PARTIAL => 'The file upload was interrupted before it finished. Please try again.',
                UPLOAD_ERR_NO_FILE => 'No file was selected.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server could not find a temporary upload folder.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the file to its temporary upload folder.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped this file upload.',
                default => 'The file upload failed with error code '.$file->getError().'.',
            };

            return back()->withErrors(['document' => $message]);
        }

        $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,docx', 'max:10240'],
            'matter_id' => ['nullable', 'integer', 'exists:matters,id'],
        ]);

        // A matter chosen at upload must be one this account is allowed to see.
        if ($request->filled('matter_id') && ! Matter::whereKey($request->input('matter_id'))->exists()) {
            return back()->withInput()->withErrors(['matter_id' => 'That matter does not exist or is not available to your account.']);
        }

        // The same file is never filed twice. This also saves a scan.
        $hash = hash_file('sha256', $file->getRealPath());
        $existing = Document::withoutGlobalScopes()->where('file_hash', $hash)->first();

        if ($existing) {
            Audit::record('document.duplicate_blocked', $existing, [
                'uploaded_name' => $file->getClientOriginalName(),
            ], $file->getClientOriginalName());

            // Only describe the existing document to an account that is allowed to see it.
            $visible = Document::whereKey($existing->id)->first();
            $detail = $visible ? ' It is already filed as document '.$visible->id.' ('.$visible->original_name.').' : '';

            return back()
                ->withInput()
                ->withErrors(['document' => 'This exact file has already been uploaded.'.$detail.' It was not uploaded again.']);
        }

        $disk = $this->disk();
        $extension = $this->safeExtension($file);
        $workingCopy = sys_get_temp_dir().'/homeai-'.Str::uuid().'.'.$extension;
        $destination = null;
        $analysis = null;
        $matter = null;
        $suggestedMatterId = null;

        try {
            if (! copy($file->getRealPath(), $workingCopy)) {
                throw new RuntimeException('The uploaded file could not be read.');
            }

            $analysis = $classifier->classify($workingCopy, (string) $file->getMimeType());

            $destination = 'documents/'.$analysis['category'].'/'.Str::uuid().'.'.$extension;

            $stream = fopen($workingCopy, 'rb');
            $saved = $disk->put($destination, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $saved) {
                $destination = null;

                throw new RuntimeException('The file could not be saved to storage.');
            }

            $linkSource = null;

            if ($request->filled('matter_id')) {
                $matter = Matter::find($request->input('matter_id'));
                $linkSource = $matter ? 'manual' : null;
            }

            if (! $matter) {
                $matter = $matcher->findByReference(
                    $analysis['matter_reference'] ?? null,
                    $analysis['extracted_text'] ?? null,
                    $file->getClientOriginalName()
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
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $destination,
                'mime_type' => (string) $file->getMimeType(),
                'file_size' => $file->getSize(),
                'file_hash' => $hash,
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

            return back()
                ->withInput()
                ->withErrors(['document' => 'This exact file has already been uploaded. It was not uploaded again.']);
        } catch (Throwable $exception) {
            $this->discard($disk, $destination);

            return back()
                ->withInput()
                ->withErrors(['document' => 'HOME AI could not classify this file. '.$exception->getMessage()]);
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

        if ($matter) {
            $message .= ' It is linked to '.$matter->matter_reference.'.';
        } elseif ($suggestedMatterId) {
            $message .= ' A matching matter was suggested. Please confirm it on the document page.';
        } else {
            $message .= ' No matching matter was found. You can link it manually.';
        }

        return redirect()
            ->route('documents.index')
            ->with('success', $message);
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
