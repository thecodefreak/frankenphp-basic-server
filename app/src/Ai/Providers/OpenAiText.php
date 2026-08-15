<?php

declare(strict_types=1);

namespace App\Ai\Providers;

use App\Ai\ProviderException;
use App\Ai\TextProvider;
use App\Ai\TextResult;
use App\Support\Http;

/** Also handles any OpenAI-compatible /chat/completions endpoint via a custom base_url. */
final readonly class OpenAiText implements TextProvider
{
    public function __construct(
        private Http $http,
        private string $apiKey,
        private string $model,
        private string $baseUrl = 'https://api.openai.com/v1',
    ) {
    }

    public function generate(string $systemPrompt, string $userPrompt): TextResult
    {
        $response = $this->http->json('POST', rtrim($this->baseUrl, '/') . '/chat/completions', [
            'Authorization: Bearer ' . $this->apiKey,
        ], [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (!$response->ok()) {
            throw new ProviderException('OpenAI text generation failed: ' . $response->errorMessage());
        }

        $content = $response->path('choices.0.message.content');
        if (!is_string($content) || $content === '') {
            throw new ProviderException('OpenAI returned an empty completion.');
        }

        return new TextResult(
            content: trim($content),
            inputTokens: (int) $response->path('usage.prompt_tokens', 0),
            outputTokens: (int) $response->path('usage.completion_tokens', 0),
            model: (string) $response->path('model', $this->model),
        );
    }
}
