<?php

declare(strict_types=1);

namespace App\Ai;

interface ImageProvider
{
    /** Always requests square 1024x1024 output — the one size guaranteed inside Instagram's 4:5..1.91:1 window. */
    public function generate(string $prompt, int $count): ImageResult;
}
