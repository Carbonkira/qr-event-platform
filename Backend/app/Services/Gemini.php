<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around Google's Generative Language API (Gemini) - there's
 * no official PHP SDK the way Anthropic ships one, so this is a plain REST
 * call via Laravel's Http client instead of a vendor package.
 */
class Gemini
{
    public static function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Sends a single-turn text prompt and returns the model's plain-text
     * reply. Callers are expected to check isConfigured() first (see
     * EventController::generateDescription / FeedbackSummaryController)
     * so a missing key surfaces as a clean 503, not this exception.
     */
    public static function generate(string $prompt, int $maxOutputTokens = 1024): string
    {
        $model = config('services.gemini.model', 'gemini-3.6-flash');
        $key = config('services.gemini.key');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $maxOutputTokens,
                // Flash models are hybrid-reasoning and otherwise burn
                // several hundred tokens "thinking" before writing anything
                // - on a small maxOutputTokens budget that eats the entire
                // budget and truncates the actual answer, which is exactly
                // what these short, single-turn writing tasks hit. The
                // older "-latest" alias allowed thinkingBudget=0 to switch
                // it off outright; gemini-3.6-flash rejects 0 with a 400
                // (confirmed live) and requires a non-zero minimum, so 1 is
                // the closest thing to "off" this model generation allows.
                'thinkingConfig' => ['thinkingBudget' => 1],
            ],
        ];

        // Google's shared flash capacity returns a 503 UNAVAILABLE
        // ("experiencing high demand... usually temporary") often enough in
        // practice that a bare pass-through breaks this feature for reasons
        // that have nothing to do with our own code, our API key, or our
        // billing tier - confirmed live, and confirmed this hits paid
        // accounts too, not just the free tier. A 503 means "no server has
        // room right now," not "something is wrong with this request," so
        // it's worth more than one retry - exponential backoff (1s, then
        // 3s) roughly matches how quickly this class of outage tends to
        // clear without holding the request open too long to be usable in
        // a request/response cycle.
        $response = Http::post($url, $payload);
        foreach ([1_000_000, 3_000_000] as $delayMicroseconds) {
            if ($response->status() !== 503) {
                break;
            }
            usleep($delayMicroseconds);
            $response = Http::post($url, $payload);
        }

        if ($response->failed()) {
            throw new RuntimeException('Gemini request failed: '.$response->body());
        }

        // A hybrid-reasoning model's answer isn't always one single part -
        // confirmed live, this can come back as two or more parts.text
        // entries that need concatenating. Reading only parts.0 (the
        // original approach) silently dropped the back half of real
        // answers rather than erroring, which is worse than a crash: it
        // looked like a short-but-valid summary instead of a truncated one.
        // Any part flagged "thought" is the model's internal reasoning
        // trace, not the actual answer, and is skipped.
        $parts = $response->json('candidates.0.content.parts', []);
        $text = collect($parts)
            ->reject(fn ($part) => $part['thought'] ?? false)
            ->pluck('text')
            ->filter(fn ($t) => is_string($t))
            ->implode('');

        if ($text === '') {
            throw new RuntimeException('Gemini returned no text content.');
        }

        return trim($text);
    }
}
