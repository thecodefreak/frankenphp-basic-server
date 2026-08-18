<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Db;
use App\Support\Settings;
use App\Usage\UsageRepository;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DashboardController
{
    public function __construct(
        private readonly View $view,
        private readonly Db $db,
        private readonly Settings $settings,
        private readonly UsageRepository $usage,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $monthStart = now_utc()->modify('first day of this month')->format('Y-m-01 00:00:00');

        return $this->view->respond($response, 'dashboard', [
            'title' => 'Dashboard',
            'counts' => [
                'templates' => (int) $this->db->value('SELECT COUNT(*) FROM templates WHERE is_active = 1'),
                'accounts' => (int) $this->db->value('SELECT COUNT(*) FROM instagram_accounts'),
                'providers' => (int) $this->db->value('SELECT COUNT(*) FROM ai_providers'),
                'published' => (int) $this->db->value("SELECT COUNT(*) FROM posts WHERE status = 'published'"),
            ],
            'costMonth' => $this->usage->totalSince($monthStart),
            'costByProvider' => $this->usage->byProviderSince($monthStart),
            'upcoming' => $this->db->select(
                "SELECT p.id, p.status, p.scheduled_at, p.caption, t.name AS template_name
                 FROM posts p LEFT JOIN templates t ON t.id = p.template_id
                 WHERE p.status IN ('pending', 'generating', 'ready') AND p.scheduled_at IS NOT NULL
                 ORDER BY p.scheduled_at LIMIT 8"
            ),
            'recent' => $this->db->select(
                "SELECT p.id, p.status, p.published_at, p.last_error, t.name AS template_name
                 FROM posts p LEFT JOIN templates t ON t.id = p.template_id
                 WHERE p.status IN ('published', 'failed', 'skipped')
                 ORDER BY COALESCE(p.published_at, p.updated_at) DESC LIMIT 8"
            ),
            'warnings' => $this->warnings(),
        ]);
    }

    /** @return array<array{message: string, action: string, href: string}> */
    private function warnings(): array
    {
        $warnings = [];

        if ($this->settings->publicBaseUrl() === '') {
            $warnings[] = ['message' => 'No public base URL set. Instagram downloads images from your server, so publishing will fail without it.', 'action' => 'Set URL', 'href' => '/settings'];
        } elseif (!str_starts_with($this->settings->publicBaseUrl(), 'https://')) {
            $warnings[] = ['message' => 'The public base URL is not HTTPS. Instagram only fetches images over HTTPS.', 'action' => 'Fix URL', 'href' => '/settings'];
        }

        if ((int) $this->db->value("SELECT COUNT(*) FROM ai_providers WHERE kind = 'text'") === 0) {
            $warnings[] = ['message' => 'No text provider configured — captions cannot be generated.', 'action' => 'Add provider', 'href' => '/providers/new'];
        }

        if ((int) $this->db->value("SELECT COUNT(*) FROM ai_providers WHERE kind = 'image'") === 0) {
            $warnings[] = ['message' => 'No image provider configured — post images cannot be generated.', 'action' => 'Add provider', 'href' => '/providers/new'];
        }

        if ((int) $this->db->value('SELECT COUNT(*) FROM instagram_accounts') === 0) {
            $warnings[] = ['message' => 'No Instagram account connected yet.', 'action' => 'Connect', 'href' => '/accounts/new'];
        }

        if ((int) $this->db->value('SELECT COUNT(*) FROM templates WHERE is_active = 1') === 0) {
            $warnings[] = ['message' => 'No active template — nothing is scheduled to post.', 'action' => 'Activate', 'href' => '/templates'];
        }

        return $warnings;
    }
}
