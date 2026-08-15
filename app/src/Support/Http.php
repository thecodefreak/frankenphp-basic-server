<?php

declare(strict_types=1);

namespace App\Support;

use CurlHandle;
use RuntimeException;

final class Http
{
    public function __construct(
        private readonly int $timeout = 180,
        private readonly int $connectTimeout = 15,
    ) {
    }

    public function json(string $method, string $url, array $headers = [], ?array $body = null): HttpResponse
    {
        $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers[] = 'Content-Type: application/json';

        return $this->send($method, $url, $headers, $payload);
    }

    public function form(string $method, string $url, array $fields, array $headers = []): HttpResponse
    {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';

        return $this->send($method, $url, $headers, http_build_query($fields));
    }

    public function getJson(string $url, array $query = [], array $headers = []): HttpResponse
    {
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $this->send('GET', $url, $headers, null);
    }

    /** Downloads a remote asset, returning its raw bytes. */
    public function download(string $url, int $maxBytes = 25_000_000): string
    {
        $handle = $this->handle('GET', $url, [], null);
        curl_setopt($handle, CURLOPT_PROGRESSFUNCTION, static function ($_, $downloaded) use ($maxBytes): int {
            return $downloaded > $maxBytes ? 1 : 0;
        });
        curl_setopt($handle, CURLOPT_NOPROGRESS, false);

        $body = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);

        if ($body === false) {
            throw new RuntimeException('Download failed: ' . ($error !== '' ? $error : 'exceeded ' . $maxBytes . ' bytes'));
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Download failed with HTTP ' . $status);
        }

        return (string) $body;
    }

    private function send(string $method, string $url, array $headers, ?string $payload): HttpResponse
    {
        $attempt = 0;

        while (true) {
            $response = $this->once($method, $url, $headers, $payload);

            if (!$response->serverError() || ++$attempt >= 3) {
                return $response;
            }

            usleep(($attempt ** 2) * 500_000);
        }
    }

    private function once(string $method, string $url, array $headers, ?string $payload): HttpResponse
    {
        $handle = $this->handle($method, $url, $headers, $payload);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);

        if ($body === false) {
            return new HttpResponse(0, '', [], $error !== '' ? $error : 'Request failed');
        }

        $decoded = json_decode((string) $body, true);

        return new HttpResponse($status, (string) $body, is_array($decoded) ? $decoded : []);
    }

    private function handle(string $method, string $url, array $headers, ?string $payload): CurlHandle
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('Unable to initialise curl for ' . $url);
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'insta-autoposter/1.0',
        ]);

        if ($payload !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
        }

        return $handle;
    }
}
