<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\NotFoundException;
use App\Instagram\AccountRepository;
use App\Instagram\InstagramClient;
use App\Instagram\InstagramException;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AccountController
{
    public function __construct(
        private readonly View $view,
        private readonly AccountRepository $accounts,
        private readonly InstagramClient $instagram,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->respond($response, 'accounts/index', [
            'title' => 'Instagram Accounts',
            'accounts' => $this->accounts->all(),
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->respond($response, 'accounts/form', ['title' => 'Connect Instagram Account', 'account' => null]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->readInput($request);

        if ($input['access_token'] === '' || $input['ig_user_id'] === '') {
            flash('An access token and Instagram user ID are required.', 'error');

            return redirect($response, '/accounts/new');
        }

        $id = $this->accounts->create($input);
        $this->verify($id);

        return redirect($response, '/accounts/' . $id . '/edit');
    }

    public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $account = $this->findOrFail((int) $args['id']);

        return $this->view->respond($response, 'accounts/form', ['title' => 'Edit ' . $account['name'], 'account' => $account]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) $args['id'];
        $this->findOrFail($id);

        $this->accounts->update($id, $this->readInput($request));
        $this->verify($id);

        return redirect($response, '/accounts/' . $id . '/edit');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->accounts->delete((int) $args['id']);
        flash('Account removed.');

        return redirect($response, '/accounts');
    }

    public function test(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) $args['id'];
        $this->findOrFail($id);
        $this->verify($id);

        return redirect($response, '/accounts/' . $id . '/edit');
    }

    private function verify(int $id): void
    {
        $account = $this->findOrFail($id);

        try {
            $hasQuota = $this->instagram->hasPublishingQuota($account);
            $this->accounts->recordError($id, '');
            flash($hasQuota
                ? 'Connection verified. Publishing quota is available.'
                : 'Connection verified, but the 24-hour publishing quota is currently exhausted.');
        } catch (InstagramException $exception) {
            $this->accounts->recordError($id, $exception->getMessage());
            flash('Could not verify this account: ' . $exception->getMessage(), 'error');
        }
    }

    private function readInput(ServerRequestInterface $request): array
    {
        $input = (array) $request->getParsedBody();

        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'login_kind' => in_array($input['login_kind'] ?? '', ['instagram', 'facebook'], true) ? $input['login_kind'] : 'instagram',
            'ig_user_id' => trim((string) ($input['ig_user_id'] ?? '')),
            'page_id' => trim((string) ($input['page_id'] ?? '')),
            'access_token' => trim((string) ($input['access_token'] ?? '')),
        ];
    }

    private function findOrFail(int $id): array
    {
        return $this->accounts->find($id) ?? throw new NotFoundException('Account #' . $id . ' not found.');
    }
}
