<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class View
{
    public function __construct(private readonly string $directory)
    {
    }

    public function render(string $view, array $data = []): string
    {
        $content = $this->capture($view, $data);

        return $this->capture('layout', $data + ['content' => $content]);
    }

    public function partial(string $view, array $data = []): string
    {
        return $this->capture($view, $data);
    }

    public function respond(ResponseInterface $response, string $view, array $data = [], int $status = 200): ResponseInterface
    {
        $response->getBody()->write($this->render($view, $data));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus($status);
    }

    private function capture(string $__view, array $__data): string
    {
        $__path = $this->directory . '/' . $__view . '.php';

        if (!is_file($__path)) {
            throw new RuntimeException('View not found: ' . $__view);
        }

        extract($__data, EXTR_SKIP);
        ob_start();
        require $__path;

        return (string) ob_get_clean();
    }
}
