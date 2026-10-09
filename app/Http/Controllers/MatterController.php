<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Matter;
use App\Services\MatterExtractor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class MatterController extends Controller
{
    public function create()
    {
        return $this->form(prefill: [], missing: [], document: null, notice: null);
    }

    public function createFromDocument(Document $document, MatterExtractor $extractor)
    {
        if ($document->matter_id) {
            return redirect()
                ->route('documents.show', $document)
                ->withErrors(['matter_id' => 'This document is already linked to a matter.']);
        }

        $prefill = [];
        $notice = null;

        try {
            $prefill = $extractor->fromDocument($document);
        } catch (Throwable $exception) {
            $notice = 'HOME AI could not read this document automatically ('
                .Str::limit($exception->getMessage(), 160)
                .'). Please enter the details yourself.';
        }

        $prefill['last_action'] = 'Document received and filed.';

        $missing = collect(MatterExtractor::FIELDS)
            ->filter(fn ($field) => blank($prefill[$field] ?? null))
            ->values()
            ->all();

        return $this->form($prefill, $missing, $document, $notice);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'section' => ['required', Rule::in(MatterExtractor::SECTIONS)],
            'category' => ['required', 'string', 'max:100'],
            'priority' => ['required', Rule::in(MatterExtractor::PRIORITIES)],
            'district' => ['nullable', 'string', 'max:100'],
            'assigned_officer' => ['nullable', 'string', 'max:150'],
            'related_department' => ['nullable', 'string', 'max:150'],
            'received_date' => ['nullable', 'date'],
            'deadline' => ['required', 'date'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'last_action' => ['nullable', 'string', 'max:2000'],
            'next_action' => ['nullable', 'string', 'max:2000'],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
        ]);

        $documentId = $data['document_id'] ?? null;
        unset($data['document_id']);

        $matter = $this->createMatter($data);

        $message = 'Matter '.$matter->matter_reference.' was created.';

        if ($documentId) {
            $document = Document::find($documentId);

            if ($document && ! $document->matter_id) {
                $document->matter_id = $matter->id;
                $document->matter_link_source = 'created';
                $document->suggested_matter_id = null;
                $document->save();

                $message .= ' The document is linked to it.';
            }
        }

        return redirect()
            ->route('matters.show', $matter)
            ->with('success', $message);
    }

    private function form(array $prefill, array $missing, ?Document $document, ?string $notice)
    {
        return view('matters.create', [
            'prefill' => $prefill,
            'missing' => $missing,
            'document' => $document,
            'notice' => $notice,
            'sections' => MatterExtractor::SECTIONS,
            'priorities' => MatterExtractor::PRIORITIES,
            'categories' => Matter::query()->orderBy('category')->distinct()->pluck('category'),
        ]);
    }

    private function createMatter(array $data): Matter
    {
        $attempts = 0;

        while (true) {
            try {
                $matter = new Matter();
                $matter->forceFill($data + [
                    'matter_reference' => $this->nextReference(),
                    'status' => 'Open',
                ])->save();

                return $matter;
            } catch (UniqueConstraintViolationException $exception) {
                if (++$attempts >= 3) {
                    throw $exception;
                }
            }
        }
    }

    private function nextReference(): string
    {
        $prefix = 'HM-'.now()->year.'-';

        $last = Matter::where('matter_reference', 'like', $prefix.'%')
            ->pluck('matter_reference')
            ->map(fn ($reference) => (int) substr($reference, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }
}
