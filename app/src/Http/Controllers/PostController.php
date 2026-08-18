<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Content\PostGenerator;
use App\Content\PostRepository;
use App\Content\PostStatus;
use App\Content\TemplateRepository;
use App\Http\NotFoundException;
use App\Usage\UsageRepository;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class PostController
{
    public function __construct(
        private readonly View $view,
        private readonly PostRepository $posts,
        private readonly TemplateRepository $templates,
        private readonly PostGenerator $generator,
        private readonly UsageRepository $usage,
    ) {
    }

    private const PER_PAGE = 25;

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $filters = [
            'status' => in_array($query['status'] ?? '', array_column(PostStatus::cases(), 'value'), true) ? $query['status'] : '',
            'template_id' => (int) ($query['template_id'] ?? 0),
        ];

        $total = $this->posts->countFiltered($filters);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) ($query['page'] ?? 1)));

        return $this->view->respond($response, 'posts/index', [
            'title' => 'Posts',
            'posts' => $this->posts->filtered($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'templates' => $this->templates->all(),
            'filters' => $filters,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $post = $this->findOrFail((int) $args['id']);

        return $this->view->respond($response, 'posts/show', [
            'title' => 'Post #' . $post['id'],
            'post' => $post,
            'usage' => $this->usage->forPost((int) $post['id']),
            'editable' => PostStatus::from($post['status'])->editable(),
        ]);
    }

    public function generateNow(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $templateId = (int) (($request->getParsedBody() ?? [])['template_id'] ?? 0);
        $template = $this->templates->find($templateId);

        if ($template === null) {
            flash('Template not found.', 'error');

            return redirect($response, '/posts');
        }

        $postId = $this->posts->createDraft($templateId);

        try {
            $this->generator->generate($template, $postId);
            flash('Post generated. Review it before scheduling.');
        } catch (Throwable $exception) {
            $this->posts->markFatal($postId, $exception->getMessage());
            flash('Generation failed: ' . $exception->getMessage(), 'error');
        }

        return redirect($response, '/posts/' . $postId);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $post = $this->findOrFail((int) $args['id']);

        if (!PostStatus::from($post['status'])->editable()) {
            flash('This post can no longer be edited.', 'error');

            return redirect($response, '/posts/' . $post['id']);
        }

        $input = (array) $request->getParsedBody();
        $caption = trim((string) ($input['caption'] ?? ''));

        if ($caption === '') {
            flash('Caption cannot be empty.', 'error');

            return redirect($response, '/posts/' . $post['id']);
        }

        $this->posts->updateContent((int) $post['id'], $caption, json_decode($post['images_json'], true) ?: []);
        flash('Post updated.');

        return redirect($response, '/posts/' . $post['id']);
    }

    public function retry(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $post = $this->findOrFail((int) $args['id']);
        $this->posts->retry((int) $post['id']);
        flash('Post queued for another attempt.');

        return redirect($response, '/posts/' . $post['id']);
    }

    public function publishNow(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $post = $this->findOrFail((int) $args['id']);

        if (!PostStatus::from($post['status'])->editable() || $post['caption'] === null) {
            flash('Generate content before publishing.', 'error');

            return redirect($response, '/posts/' . $post['id']);
        }

        $this->posts->publishNow((int) $post['id']);
        flash('Scheduled to publish within the next minute.');

        return redirect($response, '/posts/' . $post['id']);
    }

    public function cancel(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $post = $this->findOrFail((int) $args['id']);
        $this->posts->markCancelled((int) $post['id']);
        flash('Post cancelled.');

        return redirect($response, '/posts/' . $post['id']);
    }

    private function findOrFail(int $id): array
    {
        return $this->posts->find($id) ?? throw new NotFoundException('Post #' . $id . ' not found.');
    }
}
