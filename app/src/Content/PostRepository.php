<?php

declare(strict_types=1);

namespace App\Content;

use App\Support\Db;

final readonly class PostRepository
{
    private const RECLAIM_AFTER_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    public function __construct(private Db $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->first(
            "SELECT p.*, t.name AS template_name FROM posts p LEFT JOIN templates t ON t.id = p.template_id WHERE p.id = ?",
            [$id]
        );
    }

    public function paginated(int $limit = 50, int $offset = 0): array
    {
        return $this->db->select(
            "SELECT p.*, t.name AS template_name FROM posts p LEFT JOIN templates t ON t.id = p.template_id
             ORDER BY COALESCE(p.scheduled_at, p.created_at) DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /** @param array{status?: string, template_id?: int} $filters */
    public function filtered(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->filterClause($filters);

        return $this->db->select(
            "SELECT p.*, t.name AS template_name, COALESCE(SUM(u.cost_usd), 0) AS cost_usd
             FROM posts p
             LEFT JOIN templates t ON t.id = p.template_id
             LEFT JOIN token_usage u ON u.post_id = p.id
             {$where}
             GROUP BY p.id
             ORDER BY COALESCE(p.scheduled_at, p.created_at) DESC
             LIMIT ? OFFSET ?",
            [...$params, $limit, $offset]
        );
    }

    public function countFiltered(array $filters): int
    {
        [$where, $params] = $this->filterClause($filters);

        return (int) $this->db->value("SELECT COUNT(*) FROM posts p {$where}", $params);
    }

    /** Posts in a UTC window with their total AI cost, for the calendar. */
    public function betweenUtc(string $fromUtc, string $untilUtc): array
    {
        return $this->db->select(
            "SELECT p.*, t.name AS template_name, COALESCE(SUM(u.cost_usd), 0) AS cost_usd
             FROM posts p
             LEFT JOIN templates t ON t.id = p.template_id
             LEFT JOIN token_usage u ON u.post_id = p.id
             WHERE p.scheduled_at >= ? AND p.scheduled_at < ?
             GROUP BY p.id
             ORDER BY p.scheduled_at",
            [$fromUtc, $untilUtc]
        );
    }

    private function filterClause(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'p.status = ?';
            $params[] = $filters['status'];
        }

        if (($filters['template_id'] ?? 0) > 0) {
            $conditions[] = 'p.template_id = ?';
            $params[] = $filters['template_id'];
        }

        return [$conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions), $params];
    }

    /** Creates a pending slot; INSERT OR IGNORE makes materializing idempotent against the (template_id, scheduled_at) unique index. */
    public function materializeSlot(int $templateId, string $scheduledAtUtc): void
    {
        $now = utc_string(now_utc());

        $this->db->execute(
            "INSERT OR IGNORE INTO posts (template_id, status, scheduled_at, images_json, created_at, updated_at)
             VALUES (?, ?, ?, '[]', ?, ?)",
            [$templateId, PostStatus::Pending->value, $scheduledAtUtc, $now, $now]
        );
    }

    public function createDraft(int $templateId): int
    {
        $now = utc_string(now_utc());

        return $this->db->insert('posts', [
            'template_id' => $templateId,
            'status' => PostStatus::Draft->value,
            'images_json' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array[] posts claimed for generation, each already flipped to 'generating' */
    public function claimForGeneration(string $cutoffUtc, int $limit = 5): array
    {
        return $this->claimBatch(
            "SELECT id FROM posts WHERE status = ? AND scheduled_at <= ?
             AND (next_attempt_at IS NULL OR next_attempt_at <= ?) LIMIT ?",
            [PostStatus::Pending->value, $cutoffUtc, utc_string(now_utc()), $limit],
            PostStatus::Pending,
            PostStatus::Generating,
        );
    }

    /** @return array[] posts claimed for publishing, each already flipped to 'publishing' */
    public function claimForPublishing(int $limit = 5): array
    {
        $now = utc_string(now_utc());

        return $this->claimBatch(
            "SELECT id FROM posts WHERE status = ? AND scheduled_at <= ?
             AND (next_attempt_at IS NULL OR next_attempt_at <= ?) LIMIT ?",
            [PostStatus::Ready->value, $now, $now, $limit],
            PostStatus::Ready,
            PostStatus::Publishing,
        );
    }

    public function claimById(int $id, PostStatus $from, PostStatus $to): ?array
    {
        $claimed = $this->claimBatch(
            'SELECT id FROM posts WHERE id = ? AND status = ? LIMIT 1',
            [$id, $from->value],
            $from,
            $to,
        );

        return $claimed[0] ?? null;
    }

    public function dueForNotification(int $leadMinutes): array
    {
        $threshold = utc_string(now_utc()->modify('+' . $leadMinutes . ' minutes'));

        return $this->db->select(
            "SELECT * FROM posts WHERE status = ? AND scheduled_at IS NOT NULL
             AND scheduled_at <= ? AND notified_at IS NULL",
            [PostStatus::Ready->value, $threshold]
        );
    }

    public function markNotified(int $id): void
    {
        $this->db->update('posts', $id, ['notified_at' => utc_string(now_utc())]);
    }

    public function overdue(int $graceMinutes): array
    {
        $threshold = utc_string(now_utc()->modify('-' . $graceMinutes . ' minutes'));

        return $this->db->select(
            "SELECT * FROM posts WHERE status IN (?, ?) AND scheduled_at IS NOT NULL AND scheduled_at < ?",
            [PostStatus::Pending->value, PostStatus::Ready->value, $threshold]
        );
    }

    public function markGenerated(int $id, string $caption, array $imagePaths): void
    {
        $this->save($id, [
            'status' => PostStatus::Ready->value,
            'caption' => $caption,
            'images_json' => json_encode($imagePaths),
            'last_error' => null,
        ]);
    }

    public function markPublished(int $id, string $mediaId): void
    {
        $this->save($id, [
            'status' => PostStatus::Published->value,
            'ig_media_id' => $mediaId,
            'published_at' => utc_string(now_utc()),
            'last_error' => null,
        ]);
    }

    public function markSkipped(int $id, string $reason): void
    {
        $this->save($id, ['status' => PostStatus::Skipped->value, 'last_error' => $reason]);
    }

    public function markCancelled(int $id): void
    {
        $this->save($id, ['status' => PostStatus::Cancelled->value]);
    }

    public function storeContainer(int $id, ?string $containerId, ?array $children = null): void
    {
        $data = ['ig_container_id' => $containerId];
        if ($children !== null) {
            $data['ig_children_json'] = json_encode($children);
        }
        $this->save($id, $data);
    }

    /** Retryable failure: exponential backoff, terminal after MAX_ATTEMPTS. Returns back to $retryStatus meanwhile. */
    public function recordRetry(int $id, PostStatus $retryStatus, string $error): void
    {
        $row = $this->find($id);
        $attempts = ((int) ($row['attempts'] ?? 0)) + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->save($id, ['status' => PostStatus::Failed->value, 'attempts' => $attempts, 'last_error' => $error]);

            return;
        }

        $delayMinutes = min(2 ** $attempts * 2, 30);

        $this->save($id, [
            'status' => $retryStatus->value,
            'attempts' => $attempts,
            'next_attempt_at' => utc_string(now_utc()->modify('+' . $delayMinutes . ' minutes')),
            'last_error' => $error,
        ]);
    }

    /** Deferred failure (e.g. rate limit): retried later without counting against the attempt budget. */
    public function recordDeferral(int $id, PostStatus $retryStatus, string $error, int $delayMinutes = 60): void
    {
        $this->save($id, [
            'status' => $retryStatus->value,
            'next_attempt_at' => utc_string(now_utc()->modify('+' . $delayMinutes . ' minutes')),
            'last_error' => $error,
        ]);
    }

    public function markFatal(int $id, string $error): void
    {
        $this->save($id, ['status' => PostStatus::Failed->value, 'last_error' => $error]);
    }

    /** Resets a failed/skipped post back for another attempt, reusing existing content when present. */
    public function retry(int $id): void
    {
        $post = $this->find($id);
        if ($post === null) {
            return;
        }

        $hasContent = trim((string) $post['caption']) !== '' && $post['images_json'] !== '[]';
        $scheduledAt = $post['scheduled_at'] ?? utc_string(now_utc());

        $this->save($id, [
            'status' => ($hasContent ? PostStatus::Ready : PostStatus::Pending)->value,
            'attempts' => 0,
            'next_attempt_at' => null,
            'last_error' => null,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function publishNow(int $id): void
    {
        $this->save($id, ['scheduled_at' => utc_string(now_utc()), 'next_attempt_at' => null]);
    }

    public function updateContent(int $id, string $caption, array $imagePaths): void
    {
        $this->save($id, [
            'caption' => $caption,
            'images_json' => json_encode($imagePaths),
            'ig_container_id' => null,
            'ig_children_json' => null,
            'attempts' => 0,
        ]);
    }

    /** Finished posts still holding image files past the retention threshold. */
    public function finishedOlderThan(string $thresholdUtc): array
    {
        return $this->db->select(
            "SELECT * FROM posts WHERE status IN (?, ?, ?, ?) AND images_json != '[]' AND updated_at < ?",
            [PostStatus::Published->value, PostStatus::Failed->value, PostStatus::Skipped->value, PostStatus::Cancelled->value, $thresholdUtc]
        );
    }

    public function clearImages(int $id): void
    {
        $this->save($id, ['images_json' => '[]']);
    }

    /** Reclaims posts stuck mid-flight (crashed worker) after the lock timeout. */
    public function reclaimStale(): int
    {
        $threshold = utc_string(now_utc()->modify('-' . self::RECLAIM_AFTER_MINUTES . ' minutes'));

        $reclaimed = $this->db->execute(
            "UPDATE posts SET status = ?, locked_at = NULL WHERE status = ? AND locked_at < ?",
            [PostStatus::Pending->value, PostStatus::Generating->value, $threshold]
        );

        $reclaimed += $this->db->execute(
            "UPDATE posts SET status = ?, locked_at = NULL WHERE status = ? AND locked_at < ?",
            [PostStatus::Ready->value, PostStatus::Publishing->value, $threshold]
        );

        return $reclaimed;
    }

    private function claimBatch(string $selectSql, array $selectParams, PostStatus $from, PostStatus $to): array
    {
        $candidates = $this->db->select($selectSql, $selectParams);
        $claimed = [];

        foreach ($candidates as $candidate) {
            $rows = $this->db->execute(
                'UPDATE posts SET status = ?, locked_at = ?, updated_at = ? WHERE id = ? AND status = ?',
                [$to->value, utc_string(now_utc()), utc_string(now_utc()), $candidate['id'], $from->value]
            );

            if ($rows === 1) {
                $claimed[] = $this->find((int) $candidate['id']);
            }
        }

        return $claimed;
    }

    private function save(int $id, array $data): void
    {
        $this->db->update('posts', $id, [...$data, 'updated_at' => utc_string(now_utc())]);
    }
}
