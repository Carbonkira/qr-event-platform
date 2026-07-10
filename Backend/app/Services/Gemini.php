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
        $model = config('services.gemini.model', 'gemini-flash-latest');
        $key = config('services.gemini.key');

        $response = Http::post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
            [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'maxOutputTokens' => $maxOutputTokens,
                    // The "-latest" flash alias is a hybrid-reasoning model that
                    // otherwise burns several hundred tokens "thinking" before
                    // writing anything - on a small maxOutputTokens budget that
                    // eats the entire budget and truncates the actual answer.
                    // These are short, single-turn writing tasks with nothing
                    // to reason about, so thinking is switched off outright.
                    'thinkingConfig' => ['thinkingBudget' => 0],
                ],
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException('Gemini request failed: '.$response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            throw new RuntimeException('Gemini returned no text content.');
        }

        return trim($text);
    }
}
