<?php

declare(strict_types=1);

namespace App\Instagram;

use App\Support\Http;
use App\Support\HttpResponse;
use App\Support\Secrets;

final readonly class InstagramClient
{
    private const GRAPH_VERSION = 'v21.0';
    private const POLL_INTERVAL_SECONDS = 5;
    private const POLL_TIMEOUT_SECONDS = 60;

    public function __construct(
        private Http $http,
        private Secrets $secrets,
    ) {
    }

    /** POST /<IG_ID>/media for a single (non-carousel) or carousel-child image. Returns the container id. */
    public function createImageContainer(array $account, string $imageUrl, ?string $caption = null, bool $carouselItem = false): string
    {
        $fields = [
            'image_url' => $imageUrl,
            'is_ai_generated' => 'true',
            'alt_text' => 'AI-generated image',
        ];

        if ($carouselItem) {
            $fields['is_carousel_item'] = 'true';
        } elseif ($caption !== null) {
            $fields['caption'] = $caption;
        }

        $id = $this->request($account, 'POST', $account['ig_user_id'] . '/media', $fields)->path('id');

        return is_string($id) || is_int($id) ? (string) $id : throw new InstagramException('Instagram did not return a container id.', 'retry');
    }

    /** POST /<IG_ID>/media with media_type=CAROUSEL, children=<child ids>. Returns the parent container id. */
    public function createCarouselContainer(array $account, array $childIds, string $caption): string
    {
        $fields = [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childIds),
            'caption' => $caption,
            'is_ai_generated' => 'true',
        ];

        $id = $this->request($account, 'POST', $account['ig_user_id'] . '/media', $fields)->path('id');

        return is_string($id) || is_int($id) ? (string) $id : throw new InstagramException('Instagram did not return a carousel container id.', 'retry');
    }

    /** Polls a container until FINISHED, throwing on ERROR/EXPIRED or timeout. */
    public function waitUntilFinished(array $account, string $containerId): void
    {
        $deadline = time() + self::POLL_TIMEOUT_SECONDS;

        while (true) {
            $status = $this->containerStatus($account, $containerId);

            if ($status === 'FINISHED' || $status === 'PUBLISHED') {
                return;
            }

            if ($status === 'ERROR') {
                throw new InstagramException('Instagram rejected the media container (status ERROR).', 'retry');
            }

            if ($status === 'EXPIRED') {
                throw new InstagramException('Media container expired before it could be published.', 'retry');
            }

            if (time() >= $deadline) {
                throw new InstagramException('Timed out waiting for media container to finish processing.', 'retry');
            }

            sleep(self::POLL_INTERVAL_SECONDS);
        }
    }

    public function containerStatus(array $account, string $containerId): string
    {
        $response = $this->request($account, 'GET', $containerId, ['fields' => 'status_code']);

        return (string) ($response->path('status_code') ?? 'ERROR');
    }

    /** POST /<IG_ID>/media_publish. Returns the published media id. */
    public function publish(array $account, string $containerId): string
    {
        $id = $this->request($account, 'POST', $account['ig_user_id'] . '/media_publish', ['creation_id' => $containerId])->path('id');

        return is_string($id) || is_int($id) ? (string) $id : throw new InstagramException('Instagram did not return a media id after publishing.', 'retry');
    }

    /** GET /<IG_ID>/content_publishing_limit — used to defer rather than fail when near the rolling 24h cap. */
    public function hasPublishingQuota(array $account): bool
    {
        $response = $this->request($account, 'GET', $account['ig_user_id'] . '/content_publishing_limit', ['fields' => 'config,quota_usage']);
        $used = (int) $response->path('data.0.quota_usage', 0);
        $total = (int) $response->path('data.0.config.quota_total', 100);

        return $used < $total;
    }

    /** GET /<IG_ID>/media?fields=id,timestamp&limit=5 — used to recover a media id after a crash between publish and confirmation. */
    public function recentMediaIds(array $account, int $limit = 5): array
    {
        $response = $this->request($account, 'GET', $account['ig_user_id'] . '/media', ['fields' => 'id,timestamp', 'limit' => (string) $limit]);

        return array_column((array) $response->path('data', []), 'id');
    }

    /** Instagram Login: refreshes the 60-day token. Facebook Login Page tokens don't expire, so this is a no-op there. */
    public function refreshTokenIfNeeded(array $account): ?array
    {
        if ($account['login_kind'] !== 'instagram') {
            return null;
        }

        $token = $this->secrets->decrypt($account['access_token_enc']);
        $response = $this->http->getJson('https://graph.instagram.com/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token,
        ]);

        if (!$response->ok()) {
            throw InstagramException::fromGraphError((array) $response->path('error', []));
        }

        $newToken = (string) $response->path('access_token');
        $expiresIn = (int) $response->path('expires_in', 5_184_000);

        return [
            'access_token' => $newToken,
            'expires_at' => utc_string(now_utc()->modify('+' . $expiresIn . ' seconds')),
        ];
    }

    private function baseUrl(array $account): string
    {
        $host = $account['login_kind'] === 'instagram' ? 'graph.instagram.com' : 'graph.facebook.com';

        return 'https://' . $host . '/' . self::GRAPH_VERSION;
    }

    private function request(array $account, string $method, string $path, array $fields): HttpResponse
    {
        $token = $this->secrets->decrypt($account['access_token_enc']);
        $url = $this->baseUrl($account) . '/' . ltrim($path, '/');
        $fields['access_token'] = $token;

        $response = $method === 'GET'
            ? $this->http->getJson($url, $fields)
            : $this->http->form('POST', $url, $fields);

        if (!$response->ok()) {
            throw InstagramException::fromGraphError((array) $response->path('error', []));
        }

        return $response;
    }
}
