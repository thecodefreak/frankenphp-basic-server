<?php

declare(strict_types=1);

namespace App\Ai;

final readonly class Pricing
{
    /** @return array{cost_usd: float, unit_price: array, estimated: bool} */
    public static function text(array $provider, int $inputTokens, int $outputTokens): array
    {
        $inputPrice = (float) $provider['price_input_per_mtok'];
        $outputPrice = (float) $provider['price_output_per_mtok'];

        $cost = ($inputTokens / 1_000_000 * $inputPrice) + ($outputTokens / 1_000_000 * $outputPrice);

        return [
            'cost_usd' => round($cost, 6),
            'unit_price' => ['price_input_per_mtok' => $inputPrice, 'price_output_per_mtok' => $outputPrice],
            'estimated' => false,
        ];
    }

    /** @return array{cost_usd: float, unit_price: array, estimated: bool} */
    public static function image(array $provider, int $inputTokens, int $outputTokens, int $imageCount): array
    {
        if ($inputTokens > 0 || $outputTokens > 0) {
            $inputPrice = (float) $provider['price_input_per_mtok'];
            $outputPrice = (float) $provider['price_output_per_mtok'];
            $cost = ($inputTokens / 1_000_000 * $inputPrice) + ($outputTokens / 1_000_000 * $outputPrice);

            return [
                'cost_usd' => round($cost, 6),
                'unit_price' => ['price_input_per_mtok' => $inputPrice, 'price_output_per_mtok' => $outputPrice],
                'estimated' => false,
            ];
        }

        $perImage = (float) $provider['price_per_image'];

        return [
            'cost_usd' => round($perImage * $imageCount, 6),
            'unit_price' => ['price_per_image' => $perImage],
            'estimated' => true,
        ];
    }
}
