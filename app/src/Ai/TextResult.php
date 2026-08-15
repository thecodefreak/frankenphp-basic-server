<?php

declare(strict_types=1);

namespace App\Ai;

final readonly class TextResult
{
    public function __construct(
        public string $content,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
    ) {
    }
}
