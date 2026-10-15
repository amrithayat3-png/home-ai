<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Matter;
use App\Models\Reminder;
use App\Services\MatterExtractor;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'section' => ['required', Rule::in($this->allowedSections())],
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

        Audit::record('matter.created', $matter, [
            'title' => $matter->title,
            'section' => $matter->section,
            'deadline' => (string) $data['deadline'],
            'from_document' => $documentId,
        ], $matter->matter_reference);

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

    /**
     * Close a matter: it leaves the active lists, the dashboard and the reminders, but nothing is deleted.
     */
    public function close(Matter $matter)
    {
        $matter->forceFill(['status' => 'Closed'])->save();

        Audit::record('matter.closed', $matter, [], $matter->matter_reference);

        // Unread reminders for a closed matter are no longer useful.
        Reminder::where('matter_id', $matter->id)->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()
            ->route('matters.show', $matter)
            ->with('success', "{$matter->matter_reference} is now closed.");
    }

    public function reopen(Matter $matter)
    {
        $matter->forceFill(['status' => 'Open'])->save();

        Audit::record('matter.reopened', $matter, [], $matter->matter_reference);

        return redirect()
            ->route('matters.show', $matter)
            ->with('success', "{$matter->matter_reference} is open again.");
    }

    /**
     * Administrators only. Allowed when the matter is Closed or has no documents.
     * Linked documents are kept and go back to "Needs matter".
     */
    public function destroy(Matter $matter)
    {
        if ($matter->status !== 'Closed' && $matter->documents()->exists()) {
            return redirect()
                ->route('matters.show', $matter)
                ->with('error', 'Close this matter first. A matter with documents can only be deleted once it is closed.');
        }

        $reference = $matter->matter_reference;

        // A copy of the record goes to the audit trail before it is removed.
        Audit::record('matter.deleted', $matter, [
            'snapshot' => $matter->only([
                'matter_reference', 'title', 'section', 'category', 'priority', 'status', 'district',
                'assigned_officer', 'related_department', 'received_date', 'deadline',
                'summary', 'last_action', 'next_action',
            ]),
            'documents_kept' => $matter->documents()->count(),
        ], $reference);

        DB::transaction(function () use ($matter) {
            Document::where('matter_id', $matter->id)->update([
                'matter_id' => null,
                'matter_link_source' => null,
            ]);

            Document::where('suggested_matter_id', $matter->id)->update([
                'suggested_matter_id' => null,
            ]);

            Reminder::where('matter_id', $matter->id)->delete();

            $matter->delete();
        });

        return redirect()
            ->route('matters')
            ->with('success', "{$reference} was deleted. Its documents are kept and marked Needs matter.");
    }

    private function form(array $prefill, array $missing, ?Document $document, ?string $notice)
    {
        return view('matters.create', [
            'prefill' => $prefill,
            'missing' => $missing,
            'document' => $document,
            'notice' => $notice,
            'sections' => $this->allowedSections(),
            'priorities' => MatterExtractor::PRIORITIES,
            'categories' => Matter::query()->orderBy('category')->distinct()->pluck('category'),
        ]);
    }

    /**
     * The sections this account may create matters in: all of them, or only the ones it is limited to.
     *
     * @return array<int, string>
     */
    private function allowedSections(): array
    {
        return Matter::restrictedSections() ?? MatterExtractor::SECTIONS;
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
