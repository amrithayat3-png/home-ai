<?php

namespace App\Services;

use App\Support\Audit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ask HOME AI.
 *
 * The AI only chooses WHAT to look up: it turns the question into a small query plan.
 * It never sees the records and never writes the answer. The plan is run by
 * AskQueryEngine as exact database queries, so every figure in a reply comes from the
 * database and cannot be invented.
 */
class AskHomeAi
{
    private const INTENTS = [
        'list_matters',
        'count_matters',
        'get_matter',
        'briefing',
        'list_documents',
        'document_text',
        'unknown',
    ];

    public function __construct(private AskQueryEngine $engine)
    {
    }

    /**
     * @return array{answer: string, references: array<int, array<string, string>>, documents: array<int, array<string, mixed>>}
     */
    public function answer(string $question): array
    {
        $plan = $this->plan($question);

        Audit::record('ask.question', null, [
            'question' => Str::limit($question, 300),
            'intent' => $plan['intent'],
        ]);

        return $this->engine->run($plan);
    }

    /**
     * Ask the model to choose a query plan for the question. Only the question is sent, never any record.
     *
     * @return array<string, mixed>
     */
    private function plan(string $question): array
    {
        $key = config('services.gemini.key');

        if (blank($key)) {
            throw new RuntimeException('The Gemini API key is missing. Add GEMINI_API_KEY to the environment.');
        }

        $response = Http::timeout(30)
            ->withHeaders(['x-goog-api-key' => (string) $key])
            ->acceptJson()
            ->post($this->endpoint(), [
                'system_instruction' => [
                    'parts' => [['text' => $this->rules()]],
                ],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => "QUESTION\n".$question]],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0,
                ],
            ]);

        $response->throw();

        return $this->parsePlan((string) data_get($response->json(), 'candidates.0.content.parts.0.text'));
    }

    /**
     * Turns the model's reply into a safe plan. Anything unreadable becomes "unknown", which the engine
     * answers with "I can't answer that", never with a guess.
     *
     * @return array<string, mixed>
     */
    public function parsePlan(string $reply): array
    {
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($reply));
        $data = json_decode((string) $json, true);

        if (! is_array($data)) {
            return ['intent' => 'unknown'];
        }

        $intent = $data['intent'] ?? 'unknown';

        if (! is_string($intent) || ! in_array($intent, self::INTENTS, true)) {
            $intent = 'unknown';
        }

        return [
            'intent' => $intent,
            'filters' => is_array($data['filters'] ?? null) ? $data['filters'] : [],
            'group_by' => is_string($data['group_by'] ?? null) ? $data['group_by'] : null,
            'documents' => is_array($data['documents'] ?? null) ? $data['documents'] : [],
            'document_id' => is_numeric($data['document_id'] ?? null) ? (int) $data['document_id'] : null,
        ];
    }

    private function endpoint(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/'
            .config('services.gemini.model')
            .':generateContent';
    }

    private function rules(): string
    {
        return <<<'RULES'
You turn a question about government matters and documents into a JSON query plan.
You do not answer the question. You never see any record. Another program runs your plan against the database.
The question may be in English, Urdu or Roman Urdu.

Choose exactly one intent:
- list_matters: show or list matters ("show", "list", "which", "what is urgent", "pending matters in Police").
- count_matters: "how many ...". Use group_by when the question asks for a breakdown "by" something.
- get_matter: details of one matter, given its reference number HM-YYYY-NNN.
- briefing: a summary or briefing of what needs attention.
- list_documents: find or list filed documents.
- document_text: what one specific document says. Set document_id to the number after DOC-.
- unknown: anything else, or whenever you are not sure.

Filters (use null for anything the question does not mention. Never invent a value):
- status: Open, Pending, Closed, Active (Open and Pending) or Any. Leave null for active matters.
- section: the section name as written in the question (for example Police, Prison, Arms, Budget).
- priority: Critical, High, Normal, Low, or High_or_Critical for "high priority" or "important".
- district, officer, department, category: the text as written in the question.
- search: words to find in a matter's title or notes, only when the question asks to search for text.
- reference: a matter reference HM-YYYY-NNN.
- deadline: overdue, today, next_7_days, next_30_days, none, or overdue_or_next_7_days (for "urgent", "due soon", "needs attention").
- group_by (count_matters only): section, status, priority, district, officer, department or category.
Document filters go under "documents": category (correspondence, bills, invoices, challans, information, miscellaneous, letters), review_status (Filed or Needs review), matter_reference, unlinked (true for documents not linked to a matter), search.

Return JSON only, in exactly this shape:
{"intent": "unknown", "filters": {"status": null, "section": null, "priority": null, "district": null, "officer": null, "department": null, "category": null, "search": null, "reference": null, "deadline": null}, "group_by": null, "documents": {"category": null, "review_status": null, "matter_reference": null, "unlinked": null, "search": null}, "document_id": null}

Examples:
"How many pending matters are in Police?" gives intent count_matters, filters status Pending and section Police.
"What is urgent?" gives intent list_matters, filters deadline overdue_or_next_7_days.
"Show HM-2026-004" gives intent get_matter, filters reference HM-2026-004.
"Karachi ke overdue matters dikhao" gives intent list_matters, filters district Karachi and deadline overdue.
"Matters by section" gives intent count_matters, group_by section.
"Give me a briefing" gives intent briefing.
"What does DOC-5 say?" gives intent document_text, document_id 5.
"Show bills that need review" gives intent list_documents, documents category bills and review_status Needs review.
"What is the weather today?" gives intent unknown.
RULES;
    }
}
