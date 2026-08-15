<?php

declare(strict_types=1);

namespace App\Ai;

final readonly class ImageResult
{
    /** @param string[] $images raw binary image data */
    public function __construct(
        public array $images,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
    ) {
    }
}
