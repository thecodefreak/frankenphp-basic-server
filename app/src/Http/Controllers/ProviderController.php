<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Pricing;
use App\Ai\ProviderException;
use App\Ai\ProviderFactory;
use App\Ai\ProviderRepository;
use App\Http\NotFoundException;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProviderController
{
    private const TYPES = [
        'text' => ['openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'openai_compatible' => 'OpenAI-compatible (custom base URL)'],
        'image' => ['openai' => 'OpenAI', 'openai_compatible' => 'OpenAI-compatible (custom base URL)'],
    ];

    public function __construct(
        private readonly View $view,
        private readonly ProviderRepository $providers,
        private readonly ProviderFactory $factory,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->respond($response, 'providers/index', [
            'title' => 'AI Providers',
            'providers' => $this->providers->all(),
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->respond($response, 'providers/form', [
            'title' => 'Add AI Provider',
            'provider' => null,
            'types' => self::TYPES,
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->readInput($request);

        if ($input['api_key'] === '') {
            flash('An API key is required.', 'error');

            return redirect($response, '/providers/new');
        }

        $id = $this->providers->create($input);
        flash('Provider added.');

        return redirect($response, '/providers/' . $id . '/edit');
    }

    public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $provider = $this->findOrFail((int) $args['id']);

        return $this->view->respond($response, 'providers/form', [
            'title' => 'Edit ' . $provider['name'],
            'provider' => $provider,
            'types' => self::TYPES,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) $args['id'];
        $this->findOrFail($id);

        $this->providers->update($id, $this->readInput($request));
        flash('Provider updated.');

        return redirect($response, '/providers/' . $id . '/edit');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->providers->delete((int) $args['id']);
        flash('Provider removed.');

        return redirect($response, '/providers');
    }

    public function test(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $provider = $this->findOrFail((int) $args['id']);

        try {
            if ($provider['kind'] === 'text') {
                $result = $this->factory->text($provider)->generate(
                    'Reply with exactly one short word to confirm the connection works.',
                    'Say hello.'
                );
                $pricing = Pricing::text($provider, $result->inputTokens, $result->outputTokens);
                flash(sprintf(
                    'Connection OK. Model replied "%s" (%d in / %d out tokens, %s).',
                    mb_strimwidth($result->content, 0, 40, '…'),
                    $result->inputTokens,
                    $result->outputTokens,
                    money($pricing['cost_usd'])
                ));
            } else {
                $result = $this->factory->image($provider)->generate('A simple test image of a blue circle on white background.', 1);
                $pricing = Pricing::image($provider, $result->inputTokens, $result->outputTokens, count($result->images));
                flash(sprintf(
                    'Connection OK. Generated %d image(s), %s (%s).',
                    count($result->images),
                    money($pricing['cost_usd']),
                    $pricing['estimated'] ? 'estimated from per-image price' : 'from token usage'
                ));
            }
        } catch (ProviderException $exception) {
            flash('Test failed: ' . $exception->getMessage(), 'error');
        }

        return redirect($response, '/providers/' . $provider['id'] . '/edit');
    }

    private function readInput(ServerRequestInterface $request): array
    {
        $input = (array) $request->getParsedBody();

        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'kind' => in_array($input['kind'] ?? '', ['text', 'image'], true) ? $input['kind'] : 'text',
            'type' => (string) ($input['type'] ?? 'openai'),
            'base_url' => trim((string) ($input['base_url'] ?? '')),
            'api_key' => trim((string) ($input['api_key'] ?? '')),
            'model' => trim((string) ($input['model'] ?? '')),
            'price_input_per_mtok' => (float) ($input['price_input_per_mtok'] ?? 0),
            'price_output_per_mtok' => (float) ($input['price_output_per_mtok'] ?? 0),
            'price_per_image' => (float) ($input['price_per_image'] ?? 0),
            'is_default' => !empty($input['is_default']),
        ];
    }

    private function findOrFail(int $id): array
    {
        return $this->providers->find($id) ?? throw new NotFoundException('Provider #' . $id . ' not found.');
    }
}
