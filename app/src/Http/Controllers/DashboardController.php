<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Db;
use App\Support\Settings;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DashboardController
{
    public function __construct(
        private readonly View $view,
        private readonly Db $db,
        private readonly Settings $settings,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->respond($response, 'dashboard', [
            'title' => 'Dashboard',
            'warnings' => $this->warnings(),
        ]);
    }

    private function warnings(): array
    {
        $warnings = [];

        if ($this->settings->publicBaseUrl() === '') {
            $warnings[] = 'No public base URL is configured. Instagram fetches images from your server, so publishing will fail until this is set.';
        } elseif (!str_starts_with($this->settings->publicBaseUrl(), 'https://')) {
            $warnings[] = 'The public base URL is not HTTPS. Instagram requires a publicly reachable HTTPS URL to fetch images.';
        }

        return $warnings;
    }
}
