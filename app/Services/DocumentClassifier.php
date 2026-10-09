<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class DocumentClassifier
{
    private const CATEGORIES = [
        'correspondence',
        'bills',
        'invoices',
        'challans',
        'information',
        'miscellaneous',
        'letters',
    ];

    public function classify(string $filePath, string $mimeType): array
    {
        return $this->parseResponse($this->askAboutFile($this->prompt(), $filePath, $mimeType));
    }

    /**
     * Send any prompt together with a document (PDF, image, or DOCX) to Gemini and return the raw reply.
     */
    public function askAboutFile(string $prompt, string $filePath, string $mimeType): string
    {
        $key = config('services.gemini.key');

        if (blank($key)) {
            throw new RuntimeException('The Gemini API key is missing. Add GEMINI_API_KEY to the .env file.');
        }

        if (str_ends_with(strtolower($filePath), '.docx')) {
            return $this->classifyText($prompt, $this->extractDocxText($filePath));
        }

        return $this->classifyFile($prompt, $filePath, $mimeType);
    }

    private function classifyFile(string $prompt, string $filePath, string $mimeType): string
    {
        $response = Http::timeout(90)
            ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->acceptJson()
            ->post($this->endpoint(), [
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => base64_encode(file_get_contents($filePath)),
                            ],
                        ],
                    ],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0,
                ],
            ]);

        $response->throw();

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text');
    }

    private function classifyText(string $prompt, string $text): string
    {
        $response = Http::timeout(90)
            ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->acceptJson()
            ->post($this->endpoint(), [
                'contents' => [[
                    'parts' => [[
                        'text' => $prompt."\n\nDocument text:\n".Str::limit($text, 20000),
                    ]],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0,
                ],
            ]);

        $response->throw();

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text');
    }

    private function endpoint(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/'
            .config('services.gemini.model')
            .':generateContent';
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
Read this document and return JSON only. Choose exactly one category from:
correspondence, bills, invoices, challans, information, miscellaneous, letters.

Also look for a matter reference number printed on the document. The format is HM-YYYY-NNN, for example HM-2026-004. If none is printed on the document, use null.

Return this exact JSON structure:
{
  "category": "one category from the list",
  "confidence": 0.0,
  "reason": "short reason for the category",
  "matter_reference": "the HM-YYYY-NNN reference exactly as printed, or null",
  "subject": "the subject line or main topic of the document, under 120 characters",
  "extracted_text": "a concise transcription or summary of important text, maximum 5000 characters"
}
PROMPT;
    }

    private function parseResponse(string $response): array
    {
        $json = trim($response);
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $json);
        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['category'])) {
            throw new RuntimeException('The document reader returned an invalid classification.');
        }

        $category = strtolower((string) $data['category']);

        if (! in_array($category, self::CATEGORIES, true)) {
            $category = 'miscellaneous';
        }

        $reference = $data['matter_reference'] ?? null;
        $reference = is_string($reference) && strtolower(trim($reference)) !== 'null' && trim($reference) !== ''
            ? Str::limit(trim($reference), 50, '')
            : null;

        return [
            'category' => $category,
            'confidence' => min(1, max(0, (float) ($data['confidence'] ?? 0))),
            'reason' => Str::limit((string) ($data['reason'] ?? 'No reason was provided.'), 500),
            'matter_reference' => $reference,
            'subject' => Str::limit((string) ($data['subject'] ?? ''), 255, ''),
            'extracted_text' => Str::limit((string) ($data['extracted_text'] ?? ''), 5000),
        ];
    }

    private function extractDocxText(string $filePath): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Word document scanning requires the PHP ZIP extension.');
        }

        $archive = new ZipArchive();

        if ($archive->open($filePath) !== true) {
            throw new RuntimeException('This Word document could not be opened.');
        }

        $xml = $archive->getFromName('word/document.xml');
        $archive->close();

        if ($xml === false) {
            throw new RuntimeException('This Word document does not contain readable text.');
        }

        return trim(preg_replace('/\s+/', ' ', strip_tags($xml)));
    }
}
