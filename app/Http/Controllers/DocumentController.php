<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Matter;
use App\Services\DocumentClassifier;
use App\Services\MatterMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function index()
    {
        $documents = Document::with(['matter', 'suggestedMatter'])->latest()->get();

        return view('documents.index', compact('documents'));
    }

    public function show(Document $document)
    {
        $document->load(['matter', 'suggestedMatter']);

        $fileExists = Storage::disk('local')->exists($document->stored_path);
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

            return redirect()
                ->route('documents.show', $document)
                ->with('success', 'The document is no longer linked to a matter.');
        }

        $acceptedSuggestion = (int) $data['matter_id'] === (int) $document->suggested_matter_id;

        $document->matter_id = (int) $data['matter_id'];
        $document->matter_link_source = $acceptedSuggestion ? 'confirmed' : 'manual';
        $document->suggested_matter_id = null;
        $document->save();

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'The document is linked to '.Matter::find($document->matter_id)?->matter_reference.'.');
    }

    public function file(Request $request, Document $document)
    {
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404, 'The stored file could not be found.');

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($document->stored_path, $document->original_name);
        }

        return Storage::disk('local')->response(
            $document->stored_path,
            $document->original_name,
            ['Content-Type' => $document->mime_type],
            'inline'
        );
    }

    public function updateCategory(Request $request, Document $document)
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', self::CATEGORIES)],
        ]);

        $newCategory = $data['category'];

        if ($newCategory !== $document->category) {
            $disk = Storage::disk('local');

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
                UPLOAD_ERR_NO_TMP_DIR => 'Windows could not find a temporary upload folder.',
                UPLOAD_ERR_CANT_WRITE => 'Windows could not write the file to its temporary upload folder.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped this file upload.',
                default => 'The file upload failed with error code '.$file->getError().'.',
            };

            return back()->withErrors(['document' => $message]);
        }

        $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,docx', 'max:10240'],
            'matter_id' => ['nullable', 'integer', 'exists:matters,id'],
        ]);

        $inboxPath = $file->store('documents/inbox', 'local');

        try {
            $analysis = $classifier->classify(
                Storage::disk('local')->path($inboxPath),
                $file->getMimeType()
            );

            $extension = $file->getClientOriginalExtension();
            $destination = 'documents/'.$analysis['category'].'/'.Str::uuid().'.'.$extension;

            Storage::disk('local')->makeDirectory('documents/'.$analysis['category']);
            Storage::disk('local')->move($inboxPath, $destination);

            $matter = null;
            $linkSource = null;
            $suggestedMatterId = null;

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

            Document::create([
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $destination,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'category' => $analysis['category'],
                'confidence' => $analysis['confidence'],
                'classification_reason' => $analysis['reason'],
                'extracted_text' => $analysis['extracted_text'],
                'review_status' => $analysis['confidence'] >= 0.80 ? 'Filed' : 'Needs review',
                'classified_at' => now(),
                'matter_id' => $matter?->id,
                'suggested_matter_id' => $suggestedMatterId,
                'matter_link_source' => $linkSource,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($inboxPath);

            return back()
                ->withInput()
                ->withErrors(['document' => 'HOME AI could not classify this file. '.$exception->getMessage()]);
        }

        $message = 'The document was scanned and filed in '.str($analysis['category'])->headline().'.';

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
}
