<?php

namespace App\Services;

use App\Models\Document;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MatterExtractor
{
    public const SECTIONS = [
        'Administration',
        'Arms',
        'Budget',
        'Civil Defence',
        'Foreigner',
        'Internal Security',
        'Monitoring',
        'Police',
        'Prison',
    ];

    public const PRIORITIES = ['High', 'Normal', 'Low'];

    public const FIELDS = [
        'title',
        'section',
        'category',
        'priority',
        'district',
        'assigned_officer',
        'related_department',
        'received_date',
        'deadline',
        'summary',
        'next_action',
    ];

    public function __construct(private DocumentClassifier $classifier)
    {
    }

    /**
     * Read a filed document and return matter fields. A field the document does not state is null.
     */
    public function fromDocument(Document $document): array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($document->stored_path)) {
            throw new RuntimeException('The stored file could not be found.');
        }

        $reply = $this->classifier->askAboutFile(
            $this->prompt(),
            $disk->path($document->stored_path),
            $document->mime_type
        );

        return $this->normalise($this->decode($reply));
    }

    private function prompt(): string
    {
        $sections = implode(', ', self::SECTIONS);
        $today = now()->toDateString();

        return <<<PROMPT
You are reading an official document received by a government home department. Extract the details needed to open a new matter (case file) for it.

Rules:
- Use only what the document actually states. If a detail is not clearly stated, return null for it. Do not guess and do not invent.
- "section" must be exactly one of: {$sections}. Return null if none clearly fits.
- "priority" must be exactly High, Normal, or Low, and only if the document indicates urgency (for example "urgent", "immediate", "most important"). Otherwise null.
- "received_date" and "deadline" must be in YYYY-MM-DD format. Only fill "deadline" if the document states a due date or a response time you can convert to a date. Today's date is {$today}.
- "title" is a short matter title of at most 12 words.
- "summary" is two to four sentences describing what the document asks for or reports.
- "next_action" is the action the document asks the department to take, if stated.

Return JSON only, with exactly these keys:
{
  "title": null,
  "section": null,
  "category": null,
  "priority": null,
  "district": null,
  "assigned_officer": null,
  "related_department": null,
  "received_date": null,
  "deadline": null,
  "summary": null,
  "next_action": null
}
PROMPT;
    }

    private function decode(string $reply): array
    {
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($reply));
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new RuntimeException('The document reader returned an invalid reply.');
        }

        return $data;
    }

    private function normalise(array $data): array
    {
        $clean = [];

        foreach (self::FIELDS as $field) {
            $value = $data[$field] ?? null;
            $value = is_string($value) ? trim($value) : null;

            if ($value === null || $value === '' || strtolower($value) === 'null') {
                $clean[$field] = null;

                continue;
            }

            $clean[$field] = $value;
        }

        $clean['section'] = $this->matchOption($clean['section'], self::SECTIONS);
        $clean['priority'] = $this->matchOption($clean['priority'], self::PRIORITIES);
        $clean['received_date'] = $this->date($clean['received_date']);
        $clean['deadline'] = $this->date($clean['deadline']);
        $clean['title'] = $clean['title'] ? Str::limit($clean['title'], 255, '') : null;

        return $clean;
    }

    private function matchOption(?string $value, array $options): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach ($options as $option) {
            if (strcasecmp($option, $value) === 0) {
                return $option;
            }
        }

        return null;
    }

    private function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
