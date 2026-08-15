<?php

declare(strict_types=1);

namespace App\Scheduling;

use App\Ai\ProviderException;
use App\Content\ImageStore;
use App\Content\PostGenerator;
use App\Content\PostRepository;
use App\Content\PostStatus;
use App\Content\TemplateRepository;
use App\Instagram\AccountRepository;
use App\Instagram\InstagramClient;
use App\Instagram\InstagramException;
use App\Notify\Webhook;
use App\Support\Settings;
use Generator;

final readonly class Scheduler
{
    private const MATERIALIZE_HOURS_AHEAD = 48;
    private const TOKEN_REFRESH_WITHIN_DAYS = 7;

    public function __construct(
        private TemplateRepository $templates,
        private PostRepository $posts,
        private PostGenerator $generator,
        private AccountRepository $accounts,
        private InstagramClient $instagram,
        private Webhook $webhook,
        private Settings $settings,
        private ImageStore $images,
    ) {
    }

    /** @return Generator<string> human-readable log lines, one per action taken */
    public function tick(): Generator
    {
        yield from $this->materialize();
        yield from $this->generate();
        yield from $this->notify();
        yield from $this->publish();
        yield from $this->maintain();
    }

    private function materialize(): Generator
    {
        $from = now_utc();
        $until = $from->modify('+' . self::MATERIALIZE_HOURS_AHEAD . ' hours');

        foreach ($this->templates->active() as $template) {
            $rule = ScheduleRule::fromJson($template['schedule_json'], $template['timezone']);

            foreach ($rule->slotsBetween($from, $until) as $slot) {
                $this->posts->materializeSlot((int) $template['id'], utc_string($slot));
            }
        }

        yield 'materialize: ok';
    }

    private function generate(): Generator
    {
        $cutoff = utc_string(now_utc()->modify('+' . $this->settings->int('generate_lead_minutes', 90) . ' minutes'));

        foreach ($this->posts->claimForGeneration($cutoff) as $post) {
            $template = $this->templates->find((int) $post['template_id']);

            if ($template === null || !$template['is_active']) {
                $this->posts->markSkipped((int) $post['id'], 'Template was deleted or deactivated.');
                yield sprintf('generate: post #%d skipped, template gone', $post['id']);
                continue;
            }

            try {
                $this->generator->generate($template, (int) $post['id']);
                yield sprintf('generate: post #%d ready', $post['id']);
            } catch (ProviderException $exception) {
                $this->posts->recordRetry((int) $post['id'], PostStatus::Pending, $exception->getMessage());
                $this->notifyIfFailed((int) $post['id']);
                yield sprintf('generate: post #%d failed attempt (%s)', $post['id'], $exception->getMessage());
            }
        }
    }

    private function notify(): Generator
    {
        $leadMinutes = $this->settings->int('webhook_lead_minutes', 30);

        foreach ($this->posts->dueForNotification($leadMinutes) as $post) {
            $this->posts->markNotified((int) $post['id']);
            $this->webhook->prePost($this->posts->find((int) $post['id']));
            yield sprintf('notify: post #%d', $post['id']);
        }
    }

    private function publish(): Generator
    {
        $graceMinutes = $this->settings->int('missed_slot_grace_minutes', 60);

        foreach ($this->posts->claimForPublishing() as $post) {
            $threshold = now_utc()->modify('-' . $graceMinutes . ' minutes');

            if (parse_utc($post['scheduled_at']) < $threshold) {
                $this->posts->markSkipped((int) $post['id'], 'Missed its scheduled time by more than the grace window.');
                $this->webhook->skipped($this->posts->find((int) $post['id']));
                yield sprintf('publish: post #%d skipped (overdue)', $post['id']);
                continue;
            }

            yield sprintf('publish: post #%d %s', $post['id'], $this->publishOne($post));
        }
    }

    private function publishOne(array $post): string
    {
        $template = $this->templates->find((int) $post['template_id']);
        $account = $template !== null && $template['instagram_account_id'] !== null
            ? $this->accounts->find((int) $template['instagram_account_id'])
            : null;

        if ($account === null) {
            $this->posts->markFatal((int) $post['id'], 'No Instagram account is configured for this template.');
            $this->notifyIfFailed((int) $post['id']);

            return 'failed (no account)';
        }

        try {
            if (!$this->instagram->hasPublishingQuota($account)) {
                $this->posts->recordDeferral((int) $post['id'], PostStatus::Ready, 'Instagram publishing quota (100/24h) is exhausted.');

                return 'deferred (quota)';
            }

            $mediaId = $this->runPublishSequence($account, $post);
            $this->posts->markPublished((int) $post['id'], $mediaId);
            $this->webhook->published($this->posts->find((int) $post['id']));

            return 'published';
        } catch (InstagramException $exception) {
            return $this->handlePublishFailure((int) $post['id'], $account, $exception);
        }
    }

    private function handlePublishFailure(int $postId, array $account, InstagramException $exception): string
    {
        match ($exception->classification) {
            'defer' => $this->posts->recordDeferral($postId, PostStatus::Ready, $exception->getMessage()),
            'fatal' => $this->posts->markFatal($postId, $exception->getMessage()),
            default => $this->posts->recordRetry($postId, PostStatus::Ready, $exception->getMessage()),
        };

        if ($exception->classification === 'fatal' && str_contains($exception->getMessage(), '190')) {
            $this->accounts->recordError((int) $account['id'], $exception->getMessage());
            $this->webhook->tokenExpiring($account);
        }

        $this->notifyIfFailed($postId);

        return 'failed (' . $exception->classification . '): ' . $exception->getMessage();
    }

    /** @return string the published media id */
    private function runPublishSequence(array $account, array $post): string
    {
        $images = json_decode((string) $post['images_json'], true) ?: [];
        $urls = array_map(fn (string $file): string => $this->images->publicUrl($file), $images);

        if (count($urls) <= 1) {
            return $this->publishSingle($account, $post, $urls[0] ?? throw new InstagramException('Post has no images.', 'fatal'));
        }

        return $this->publishCarousel($account, $post, $urls);
    }

    private function publishSingle(array $account, array $post, string $imageUrl): string
    {
        $containerId = $post['ig_container_id'];

        if ($containerId !== null) {
            $resumed = $this->resumeContainer($account, $post, $containerId);
            if ($resumed !== null) {
                return $resumed;
            }
        }

        $containerId = $this->instagram->createImageContainer($account, $imageUrl, (string) $post['caption']);
        $this->posts->storeContainer((int) $post['id'], $containerId);

        $this->instagram->waitUntilFinished($account, $containerId);

        return $this->instagram->publish($account, $containerId);
    }

    private function publishCarousel(array $account, array $post, array $urls): string
    {
        $children = json_decode((string) ($post['ig_children_json'] ?? '[]'), true) ?: [];

        for ($i = count($children); $i < count($urls); $i++) {
            $childId = $this->instagram->createImageContainer($account, $urls[$i], null, carouselItem: true);
            $children[] = $childId;
            $this->posts->storeContainer((int) $post['id'], $post['ig_container_id'], $children);
        }

        foreach ($children as $childId) {
            $this->instagram->waitUntilFinished($account, $childId);
        }

        $containerId = $post['ig_container_id'];

        if ($containerId !== null) {
            $resumed = $this->resumeContainer($account, $post, $containerId);
            if ($resumed !== null) {
                return $resumed;
            }
        }

        $containerId = $this->instagram->createCarouselContainer($account, $children, (string) $post['caption']);
        $this->posts->storeContainer((int) $post['id'], $containerId, $children);

        $this->instagram->waitUntilFinished($account, $containerId);

        return $this->instagram->publish($account, $containerId);
    }

    /**
     * Resumes a container left over from a crash between creation and publish.
     * Returns the media id if Instagram shows it already published, null if the caller should proceed to publish it.
     */
    private function resumeContainer(array $account, array $post, string $containerId): ?string
    {
        $status = $this->instagram->containerStatus($account, $containerId);

        if ($status === 'PUBLISHED') {
            $recent = $this->instagram->recentMediaIds($account, 5);

            return $recent[0] ?? $containerId;
        }

        if ($status === 'FINISHED') {
            return $this->instagram->publish($account, $containerId);
        }

        // EXPIRED or ERROR: fall through so the caller creates a fresh container.
        $this->posts->storeContainer((int) $post['id'], null);

        return null;
    }

    private function maintain(): Generator
    {
        foreach ($this->accounts->expiringWithin(self::TOKEN_REFRESH_WITHIN_DAYS) as $account) {
            try {
                $refreshed = $this->instagram->refreshTokenIfNeeded($account);
                if ($refreshed !== null) {
                    $this->accounts->saveRefreshedToken((int) $account['id'], $refreshed['access_token'], $refreshed['expires_at']);
                    yield sprintf('maintain: refreshed token for account #%d', $account['id']);
                }
            } catch (InstagramException $exception) {
                $this->accounts->recordError((int) $account['id'], $exception->getMessage());
                $this->webhook->tokenExpiring($account);
                yield sprintf('maintain: token refresh failed for account #%d (%s)', $account['id'], $exception->getMessage());
            }
        }

        foreach ($this->posts->overdue($this->settings->int('missed_slot_grace_minutes', 60)) as $post) {
            $this->posts->markSkipped((int) $post['id'], 'Missed its scheduled time while pending.');
            $this->webhook->skipped($this->posts->find((int) $post['id']));
            yield sprintf('maintain: post #%d skipped (overdue while pending)', $post['id']);
        }

        $reclaimed = $this->posts->reclaimStale();
        if ($reclaimed > 0) {
            yield sprintf('maintain: reclaimed %d stuck post(s)', $reclaimed);
        }

        $this->pruneOldImages();
    }

    private function pruneOldImages(): void
    {
        // Image files for finished posts are removed after image_retention_days;
        // the post row (and its caption/history) is kept indefinitely.
        $retentionDays = $this->settings->int('image_retention_days', 30);
        $threshold = utc_string(now_utc()->modify('-' . $retentionDays . ' days'));

        foreach ($this->posts->finishedOlderThan($threshold) as $post) {
            foreach (json_decode((string) $post['images_json'], true) ?: [] as $file) {
                $this->images->delete($file);
            }
            $this->posts->clearImages((int) $post['id']);
        }
    }

    private function notifyIfFailed(int $postId): void
    {
        $post = $this->posts->find($postId);
        if ($post !== null && $post['status'] === PostStatus::Failed->value) {
            $this->webhook->failed($post);
        }
    }
}
