<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\ProviderRepository;
use App\Content\TemplateRepository;
use App\Http\NotFoundException;
use App\Scheduling\ScheduleRule;
use App\Support\Db;
use App\Support\Settings;
use App\View;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TemplateController
{
    public function __construct(
        private readonly View $view,
        private readonly TemplateRepository $templates,
        private readonly ProviderRepository $providers,
        private readonly Db $db,
        private readonly Settings $settings,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->respond($response, 'templates/index', [
            'title' => 'Templates',
            'templates' => $this->templates->all(),
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->respond($response, 'templates/form', [
            'title' => 'New Template',
            'template' => null,
            ...$this->formData(),
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$input, $errors] = $this->readInput($request);

        if ($errors !== []) {
            flash(implode(' ', $errors), 'error');

            return redirect($response, '/templates/new');
        }

        $id = $this->templates->create($input);
        flash('Template created.');

        return redirect($response, '/templates/' . $id . '/edit');
    }

    public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $template = $this->findOrFail((int) $args['id']);

        return $this->view->respond($response, 'templates/form', [
            'title' => 'Edit ' . $template['name'],
            'template' => $template,
            ...$this->formData(),
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) $args['id'];
        $this->findOrFail($id);

        [$input, $errors] = $this->readInput($request);

        if ($errors !== []) {
            flash(implode(' ', $errors), 'error');

            return redirect($response, '/templates/' . $id . '/edit');
        }

        $this->templates->update($id, $input);
        flash('Template saved.');

        return redirect($response, '/templates/' . $id . '/edit');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->templates->delete((int) $args['id']);
        flash('Template removed.');

        return redirect($response, '/templates');
    }

    public function previewSlots(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = (array) $request->getParsedBody();
        $timezone = (string) ($input['timezone'] ?? 'UTC');
        $rule = new ScheduleRule(
            times: self::parseTimes((string) ($input['times'] ?? '')),
            weekdays: array_map('intval', (array) ($input['weekdays'] ?? [])),
            timezone: in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC',
        );

        $slots = array_map(
            static fn ($slot) => [
                'utc' => utc_string($slot),
                'local' => $slot->setTimezone(tz($rule->timezone))->format('D, d M Y H:i'),
            ],
            $rule->nextSlots(now_utc(), 5)
        );

        return json_out($response, ['slots' => $slots]);
    }

    private function formData(): array
    {
        return [
            'textProviders' => $this->providers->ofKind('text'),
            'imageProviders' => $this->providers->ofKind('image'),
            'accounts' => $this->db->select('SELECT id, name FROM instagram_accounts ORDER BY name'),
            'timezones' => DateTimeZone::listIdentifiers(),
            'defaultTimezone' => $this->settings->timezone(),
        ];
    }

    private function readInput(ServerRequestInterface $request): array
    {
        $input = (array) $request->getParsedBody();
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        $subject = trim((string) ($input['subject'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $timezone = trim((string) ($input['timezone'] ?? 'UTC'));
        $imageCount = max(1, min(10, (int) ($input['image_count'] ?? 1)));

        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if ($subject === '') {
            $errors[] = 'Subject is required.';
        }
        if ($description === '') {
            $errors[] = 'Description is required.';
        }
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $errors[] = 'Unknown timezone.';
        }

        $rawTimes = (string) ($input['times'] ?? '');
        $times = self::parseTimes($rawTimes);
        if (trim($rawTimes) !== '' && count($times) !== count(array_filter(preg_split('/[,\s]+/', trim($rawTimes))))) {
            $errors[] = 'Times must be in HH:MM 24-hour format, separated by commas.';
        }
        $weekdays = array_values(array_unique(array_map('intval', (array) ($input['weekdays'] ?? []))));

        $isActive = !empty($input['is_active']);
        if ($isActive && ($times === [] || $weekdays === [])) {
            $errors[] = 'An active template needs at least one time and one weekday.';
        }

        $textProviderId = $input['text_provider_id'] !== '' ? (int) $input['text_provider_id'] : null;
        $imageProviderId = $input['image_provider_id'] !== '' ? (int) $input['image_provider_id'] : null;
        if ($isActive && ($textProviderId === null || $imageProviderId === null)) {
            $errors[] = 'An active template needs both a text and an image provider.';
        }

        return [[
            'name' => $name,
            'subject' => $subject,
            'description' => $description,
            'style_prompt' => trim((string) ($input['style_prompt'] ?? '')),
            'caption_rules' => trim((string) ($input['caption_rules'] ?? '')),
            'text_provider_id' => $textProviderId,
            'image_provider_id' => $imageProviderId,
            'image_count' => $imageCount,
            'instagram_account_id' => $input['instagram_account_id'] !== '' ? (int) $input['instagram_account_id'] : null,
            'timezone' => $timezone,
            'schedule_json' => json_encode(['times' => $times, 'weekdays' => $weekdays]),
            'is_active' => $isActive,
            'is_default' => !empty($input['is_default']),
        ], $errors];
    }

    private function findOrFail(int $id): array
    {
        return $this->templates->find($id) ?? throw new NotFoundException('Template #' . $id . ' not found.');
    }

    /** @return string[] valid, deduplicated "HH:MM" times parsed from a comma/space separated string */
    private static function parseTimes(string $raw): array
    {
        $times = [];

        foreach (preg_split('/[,\s]+/', trim($raw)) ?: [] as $candidate) {
            if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $candidate)) {
                $times[$candidate] = $candidate;
            }
        }

        $times = array_values($times);
        sort($times);

        return $times;
    }
}
