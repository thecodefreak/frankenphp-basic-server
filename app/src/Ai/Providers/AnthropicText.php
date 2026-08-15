<?php

declare(strict_types=1);

namespace App\Ai\Providers;

use App\Ai\ProviderException;
use App\Ai\TextProvider;
use App\Ai\TextResult;
use App\Support\Http;

final readonly class AnthropicText implements TextProvider
{
    public function __construct(
        private Http $http,
        private string $apiKey,
        private string $model,
        private string $baseUrl = 'https://api.anthropic.com/v1',
    ) {
    }

    public function generate(string $systemPrompt, string $userPrompt): TextResult
    {
        $response = $this->http->json('POST', rtrim($this->baseUrl, '/') . '/messages', [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
        ], [
            'model' => $this->model,
            'max_tokens' => 1024,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (!$response->ok()) {
            throw new ProviderException('Anthropic text generation failed: ' . $response->errorMessage());
        }

        $content = $response->path('content.0.text');
        if (!is_string($content) || $content === '') {
            throw new ProviderException('Anthropic returned an empty completion.');
        }

        return new TextResult(
            content: trim($content),
            inputTokens: (int) $response->path('usage.input_tokens', 0),
            outputTokens: (int) $response->path('usage.output_tokens', 0),
            model: (string) $response->path('model', $this->model),
        );
    }
}
