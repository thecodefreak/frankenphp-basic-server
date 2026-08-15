<?php

declare(strict_types=1);

namespace App\Usage;

use App\Support\Db;

final readonly class UsageRepository
{
    public function __construct(private Db $db)
    {
    }

    /** @param array{cost_usd: float, unit_price: array, estimated: bool} $pricing */
    public function record(
        ?int $postId,
        int $providerId,
        string $kind,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $imageCount,
        array $pricing,
    ): void {
        $this->db->insert('token_usage', [
            'post_id' => $postId,
            'provider_id' => $providerId,
            'kind' => $kind,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'image_count' => $imageCount,
            'unit_price_json' => json_encode($pricing['unit_price']),
            'cost_usd' => $pricing['cost_usd'],
            'estimated' => $pricing['estimated'] ? 1 : 0,
            'created_at' => utc_string(now_utc()),
        ]);
    }

    public function totalSince(string $sinceUtc): float
    {
        return (float) $this->db->value('SELECT COALESCE(SUM(cost_usd), 0) FROM token_usage WHERE created_at >= ?', [$sinceUtc]);
    }

    public function byProviderSince(string $sinceUtc): array
    {
        return $this->db->select(
            "SELECT p.name, u.kind, SUM(u.cost_usd) AS cost_usd, COUNT(*) AS calls
             FROM token_usage u JOIN ai_providers p ON p.id = u.provider_id
             WHERE u.created_at >= ? GROUP BY p.id ORDER BY cost_usd DESC",
            [$sinceUtc]
        );
    }

    public function forPost(int $postId): array
    {
        return $this->db->select('SELECT * FROM token_usage WHERE post_id = ? ORDER BY id', [$postId]);
    }
}
