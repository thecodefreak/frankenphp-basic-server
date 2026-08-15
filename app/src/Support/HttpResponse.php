<?php

declare(strict_types=1);

namespace App\Support;

final readonly class HttpResponse
{
    public function __construct(
        public int $status,
        public string $body,
        public array $json = [],
        public ?string $transportError = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->transportError === null && $this->status >= 200 && $this->status < 300;
    }

    public function serverError(): bool
    {
        return $this->status >= 500 || $this->transportError !== null;
    }

    public function path(string $dotted, mixed $default = null): mixed
    {
        $value = $this->json;

        foreach (explode('.', $dotted) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function errorMessage(): string
    {
        if ($this->transportError !== null) {
            return $this->transportError;
        }

        foreach (['error.message', 'error.error.message', 'message', 'detail'] as $candidate) {
            $message = $this->path($candidate);
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return 'HTTP ' . $this->status . ': ' . mb_substr($this->body, 0, 500);
    }
}
