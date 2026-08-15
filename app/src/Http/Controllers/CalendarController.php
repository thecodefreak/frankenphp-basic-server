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
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01', tz('UTC'));

        if ($start === false) {
            $start = now_utc()->modify('first day of this month')->setTime(0, 0);
        }

        $end = $start->modify('first day of next month');

        $days = [];
        foreach ($this->posts->betweenUtc(utc_string($start), utc_string($end)) as $post) {
            $day = substr((string) $post['scheduled_at'], 0, 10);
            $days[$day][] = [
                'id' => (int) $post['id'],
                'status' => $post['status'],
                'time' => substr((string) $post['scheduled_at'], 11, 5),
                'template_name' => $post['template_name'],
                'caption' => $post['caption'] !== null ? mb_strimwidth((string) $post['caption'], 0, 60, '…') : null,
            ];
        }

        return json_out($response, ['month' => $start->format('Y-m'), 'days' => $days]);
    }
}
