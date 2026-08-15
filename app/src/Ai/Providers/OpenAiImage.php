<?php

declare(strict_types=1);

namespace App\Ai\Providers;

use App\Ai\ImageProvider;
use App\Ai\ImageResult;
use App\Ai\ProviderException;
use App\Support\Http;

final readonly class OpenAiImage implements ImageProvider
{
    public function __construct(
        private Http $http,
        private string $apiKey,
        private string $model,
        private string $baseUrl = 'https://api.openai.com/v1',
    ) {
    }

    public function generate(string $prompt, int $count): ImageResult
    {
        $response = $this->http->json('POST', rtrim($this->baseUrl, '/') . '/images/generations', [
            'Authorization: Bearer ' . $this->apiKey,
        ], [
            'model' => $this->model,
            'prompt' => $prompt,
            'n' => $count,
            'size' => '1024x1024',
            'output_format' => 'jpeg',
        ]);

        if (!$response->ok()) {
            throw new ProviderException('OpenAI image generation failed: ' . $response->errorMessage());
        }

        $items = $response->path('data', []);
        if (!is_array($items) || $items === []) {
            throw new ProviderException('OpenAI returned no images.');
        }

        $images = [];
        foreach ($items as $item) {
            $b64 = $item['b64_json'] ?? null;
            if (!is_string($b64) || $b64 === '') {
                throw new ProviderException('OpenAI image response missing b64_json.');
            }
            $decoded = base64_decode($b64, true);
            if ($decoded === false) {
                throw new ProviderException('OpenAI image response could not be decoded.');
            }
            $images[] = $decoded;
        }

        return new ImageResult(
            images: $images,
            inputTokens: (int) $response->path('usage.input_tokens', 0),
            outputTokens: (int) $response->path('usage.output_tokens', 0),
            model: $this->model,
        );
    }
}
