<?php

declare(strict_types=1);

namespace App\Ai;

interface TextProvider
{
    public function generate(string $systemPrompt, string $userPrompt): TextResult;
}
