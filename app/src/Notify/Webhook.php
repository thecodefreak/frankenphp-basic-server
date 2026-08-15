<?php

declare(strict_types=1);

namespace App\Notify;

use App\Support\Http;
use App\Support\Settings;

final readonly class Webhook
{
    public function __construct(
        private Http $http,
        private Settings $settings,
    ) {
    }

    public function prePost(array $post): void
    {
        $this->send('pre_post', sprintf(
            'Upcoming post "%s" is scheduled for %s UTC.',
            $post['template_name'] ?? ('post #' . $post['id']),
            $post['scheduled_at']
        ), $post);
    }

    public function published(array $post): void
    {
        $this->send('published', sprintf(
            'Post "%s" was published to Instagram.',
            $post['template_name'] ?? ('post #' . $post['id'])
        ), $post);
    }

    public function failed(array $post): void
    {
        $this->send('failed', sprintf(
            'Post "%s" failed after %d attempts: %s',
            $post['template_name'] ?? ('post #' . $post['id']),
            $post['attempts'] ?? 0,
            $post['last_error'] ?? 'unknown error'
        ), $post);
    }

    public function skipped(array $post): void
    {
        $this->send('skipped', sprintf(
            'Post "%s" was skipped — its scheduled time passed while generation or publishing fell behind.',
            $post['template_name'] ?? ('post #' . $post['id'])
        ), $post);
    }

    public function tokenExpiring(array $account): void
    {
        $this->send('token_expiring', sprintf(
            'Instagram account "%s" needs re-authentication (token expired or refresh failed).',
            $account['name']
        ), $account);
    }

    public function test(): void
    {
        $this->send('test', 'This is a test notification from your Instagram auto-poster.', []);
    }

    private function send(string $event, string $message, array $data): void
    {
        $url = $this->settings->get('webhook_url');
        if ($url === '') {
            return;
        }

        $body = match (true) {
            str_contains($url, 'discord.com/api/webhooks') => ['content' => $message],
            str_contains($url, 'hooks.slack.com') => ['text' => $message],
            default => ['event' => $event, 'message' => $message, 'data' => $data, 'timestamp' => utc_string(now_utc())],
        };

        try {
            $this->http->json('POST', $url, [], $body);
        } catch (\Throwable) {
            // Notification delivery is best-effort; a broken webhook must never block posting.
        }
    }
}
