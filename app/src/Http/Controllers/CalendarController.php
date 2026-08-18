<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Content\PostRepository;
use App\View;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CalendarController
{
    private const COUNTED_AS_POSTED = ['published'];

    public function __construct(
        private readonly View $view,
        private readonly PostRepository $posts,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->respond($response, 'calendar', ['title' => 'Calendar']);
    }

    public function data(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $month = (string) ($request->getQueryParams()['month'] ?? now_utc()->format('Y-m'));
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $month . '-01 00:00:00', tz('UTC'));

        if ($start === false) {
            $start = now_utc()->modify('first day of this month')->setTime(0, 0);
        }

        $end = $start->modify('first day of next month');

        $days = [];
        $totals = ['posts' => 0, 'published' => 0, 'failed' => 0, 'cost_usd' => 0.0];

        foreach ($this->posts->betweenUtc(utc_string($start), utc_string($end)) as $post) {
            $day = substr((string) $post['scheduled_at'], 0, 10);
            $cost = (float) $post['cost_usd'];
            $status = (string) $post['status'];

            $days[$day] ??= ['posts' => [], 'count' => 0, 'published' => 0, 'cost_usd' => 0.0];
            $days[$day]['posts'][] = [
                'id' => (int) $post['id'],
                'status' => $status,
                'time' => substr((string) $post['scheduled_at'], 11, 5),
                'template_name' => $post['template_name'],
                'caption' => $post['caption'] !== null ? mb_strimwidth((string) $post['caption'], 0, 80, '…') : null,
                'cost_usd' => round($cost, 4),
            ];
            $days[$day]['count']++;
            $days[$day]['cost_usd'] = round($days[$day]['cost_usd'] + $cost, 4);

            $isPosted = in_array($status, self::COUNTED_AS_POSTED, true);
            $days[$day]['published'] += $isPosted ? 1 : 0;

            $totals['posts']++;
            $totals['published'] += $isPosted ? 1 : 0;
            $totals['failed'] += in_array($status, ['failed', 'skipped'], true) ? 1 : 0;
            $totals['cost_usd'] = round($totals['cost_usd'] + $cost, 4);
        }

        return json_out($response, ['month' => $start->format('Y-m'), 'days' => $days, 'totals' => $totals]);
    }
}
