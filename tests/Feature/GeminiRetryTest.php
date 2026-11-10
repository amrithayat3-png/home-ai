<?php

namespace Tests\Feature;

use App\Services\DocumentClassifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class GeminiRetryTest extends TestCase
{
    private function reply(): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'category' => 'letters',
                    'confidence' => 0.9,
                    'reason' => 'A letter.',
                    'matter_reference' => null,
                    'subject' => 'Hello',
                    'extracted_text' => 'Hello.',
                ])]]],
            ]],
        ];
    }

    private function sampleFile(): string
    {
        $path = sys_get_temp_dir().'/gemini-retry-'.uniqid().'.pdf';
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        return $path;
    }

    public function test_a_busy_answer_from_gemini_is_retried_and_then_succeeds(): void
    {
        Sleep::fake();
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['code' => 503, 'message' => 'This model is currently experiencing high demand.']], 503)
                ->push(['error' => ['code' => 429, 'message' => 'Too many requests.']], 429)
                ->push($this->reply(), 200),
        ]);

        $path = $this->sampleFile();
        $result = app(DocumentClassifier::class)->classify($path, 'application/pdf');
        @unlink($path);

        $this->assertSame('letters', $result['category']);
        Http::assertSentCount(3);
    }

    public function test_it_gives_up_after_three_busy_answers(): void
    {
        Sleep::fake();
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 503]], 503),
        ]);

        $path = $this->sampleFile();

        try {
            app(DocumentClassifier::class)->classify($path, 'application/pdf');
            $this->fail('Expected the classifier to give up.');
        } catch (\Illuminate\Http\Client\RequestException $exception) {
            $this->assertSame(503, $exception->response->status());
        } finally {
            @unlink($path);
        }

        Http::assertSentCount(3);
    }

    public function test_other_errors_are_not_retried(): void
    {
        Sleep::fake();
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('bad key', 403),
        ]);

        $path = $this->sampleFile();

        try {
            app(DocumentClassifier::class)->classify($path, 'application/pdf');
            $this->fail('Expected an error.');
        } catch (\Illuminate\Http\Client\RequestException) {
            // expected
        } finally {
            @unlink($path);
        }

        Http::assertSentCount(1);
    }
}
