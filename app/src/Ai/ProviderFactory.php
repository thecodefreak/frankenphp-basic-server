<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Providers\AnthropicText;
use App\Ai\Providers\OpenAiImage;
use App\Ai\Providers\OpenAiText;
use App\Support\Http;
use App\Support\Secrets;
use RuntimeException;

final readonly class ProviderFactory
{
    public function __construct(
        private Http $http,
        private Secrets $secrets,
    ) {
    }

    public function text(array $provider): TextProvider
    {
        $this->assertKind($provider, 'text');

        $apiKey = $this->secrets->decrypt($provider['api_key_enc']);
        $model = (string) $provider['model'];
        $baseUrl = $this->baseUrl($provider);

        return match ($provider['type']) {
            'anthropic' => $baseUrl === null
                ? new AnthropicText($this->http, $apiKey, $model)
                : new AnthropicText($this->http, $apiKey, $model, $baseUrl),
            'openai', 'openai_compatible' => $baseUrl === null
                ? new OpenAiText($this->http, $apiKey, $model)
                : new OpenAiText($this->http, $apiKey, $model, $baseUrl),
            default => throw new RuntimeException('Unsupported text provider type: ' . $provider['type']),
        };
    }

    public function image(array $provider): ImageProvider
    {
        $this->assertKind($provider, 'image');

        $apiKey = $this->secrets->decrypt($provider['api_key_enc']);
        $model = (string) $provider['model'];
        $baseUrl = $this->baseUrl($provider);

        return match ($provider['type']) {
            'openai', 'openai_compatible' => $baseUrl === null
                ? new OpenAiImage($this->http, $apiKey, $model)
                : new OpenAiImage($this->http, $apiKey, $model, $baseUrl),
            default => throw new RuntimeException('Unsupported image provider type: ' . $provider['type']),
        };
    }

    private function baseUrl(array $provider): ?string
    {
        $baseUrl = trim((string) ($provider['base_url'] ?? ''));

        return $baseUrl === '' ? null : $baseUrl;
    }

    private function assertKind(array $provider, string $kind): void
    {
        if ($provider['kind'] !== $kind) {
            throw new RuntimeException(sprintf('Provider #%d is a %s provider, not %s.', $provider['id'], $provider['kind'], $kind));
        }
    }
}
