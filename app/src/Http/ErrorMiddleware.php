<?php

declare(strict_types=1);

namespace App\Http;

use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;
use Slim\Psr7\Response;
use Throwable;

final class ErrorMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly View $view)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpException $exception) {
            return $this->render($request, $exception->getCode(), $exception->getMessage());
        } catch (NotFoundException $exception) {
            return $this->render($request, 404, $exception->getMessage());
        } catch (Throwable $exception) {
            error_log(sprintf('[%s] %s in %s:%d', $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine()));

            $message = env('APP_DEBUG') === '1'
                ? $exception->getMessage() . "\n\n" . $exception->getTraceAsString()
                : 'Something went wrong. Check the container logs for details.';

            return $this->render($request, 500, $message);
        }
    }

    private function render(ServerRequestInterface $request, int $status, string $message): ResponseInterface
    {
        $status = $status >= 400 && $status < 600 ? $status : 500;
        $response = new Response();

        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return json_out($response, ['error' => $message], $status);
        }

        return $this->view->respond($response, 'error', ['title' => 'Error', 'status' => $status, 'message' => $message], $status);
    }
}
