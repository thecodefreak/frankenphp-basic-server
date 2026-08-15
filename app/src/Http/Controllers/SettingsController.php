<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Settings;
use App\View;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SettingsController
{
    private const INT_FIELDS = [
        'generate_lead_minutes' => [5, 10080],
        'missed_slot_grace_minutes' => [0, 10080],
        'image_retention_days' => [1, 3650],
        'webhook_lead_minutes' => [0, 10080],
    ];

    public function __construct(
        private readonly View $view,
        private readonly Settings $settings,
    ) {
    }

    public function edit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->respond($response, 'settings', [
            'title' => 'Settings',
            'settings' => $this->settings->all(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = (array) $request->getParsedBody();
        $errors = [];

        $baseUrl = rtrim(trim((string) ($input['public_base_url'] ?? '')), '/');
        if ($baseUrl !== '' && !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Public base URL is not a valid URL.';
        }

        $timezone = trim((string) ($input['default_timezone'] ?? 'UTC'));
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $errors[] = 'Unknown timezone.';
        }

        $webhookUrl = trim((string) ($input['webhook_url'] ?? ''));
        if ($webhookUrl !== '' && !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Webhook URL is not a valid URL.';
        }

        $values = ['public_base_url' => $baseUrl, 'default_timezone' => $timezone, 'webhook_url' => $webhookUrl];

        foreach (self::INT_FIELDS as $field => [$min, $max]) {
            $value = (int) ($input[$field] ?? 0);
            if ($value < $min || $value > $max) {
                $errors[] = sprintf('%s must be between %d and %d.', str_replace('_', ' ', $field), $min, $max);
                continue;
            }
            $values[$field] = (string) $value;
        }

        if ($errors !== []) {
            flash(implode(' ', $errors), 'error');

            return redirect($response, '/settings');
        }

        $this->settings->setMany($values);
        flash('Settings saved.');

        return redirect($response, '/settings');
    }
}
