<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;

/**
 * Cron builds a minimal environment and does not inherit Docker's env vars,
 * so the .env file is the primary source and getenv() only a fallback.
 */
function env(string $key, ?string $default = null): ?string
{
    static $file = null;

    if ($file === null) {
        $file = [];
        $path = dirname(__DIR__) . '/.env';
        if (is_readable($path)) {
            $file = parse_ini_file($path, false, INI_SCANNER_RAW) ?: [];
        }
    }

    $value = $file[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return trim((string) $value);
}

function app_path(string $relative = ''): string
{
    $base = dirname(__DIR__);

    return $relative === '' ? $base : $base . '/' . ltrim($relative, '/');
}

function now_utc(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

function to_utc(DateTimeImmutable $date): DateTimeImmutable
{
    return $date->setTimezone(new DateTimeZone('UTC'));
}

function utc_string(DateTimeImmutable $date): string
{
    return to_utc($date)->format('Y-m-d H:i:s');
}

function parse_utc(?string $value): ?DateTimeImmutable
{
    if ($value === null || $value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

    return $date instanceof DateTimeImmutable ? $date : null;
}

function tz(string $identifier): DateTimeZone
{
    try {
        return new DateTimeZone($identifier);
    } catch (Exception) {
        return new DateTimeZone('UTC');
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(?float $usd): string
{
    if ($usd === null) {
        return '—';
    }

    return $usd > 0 && $usd < 0.01 ? '$' . number_format($usd, 4) : '$' . number_format($usd, 2);
}

function flash(string $message, string $kind = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'kind' => $kind];
}

function redirect(ResponseInterface $response, string $to): ResponseInterface
{
    return $response->withHeader('Location', $to)->withStatus(303);
}

function json_out(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
{
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
}
