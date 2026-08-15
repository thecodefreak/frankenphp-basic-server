<?php

declare(strict_types=1);

namespace App\Content;

use App\Ai\Pricing;
use App\Ai\ProviderException;
use App\Ai\ProviderFactory;
use App\Ai\ProviderRepository;
use App\Usage\UsageRepository;

final readonly class PostGenerator
{
    public function __construct(
        private ProviderFactory $factory,
        private ProviderRepository $providers,
        private ImageStore $images,
        private PostRepository $posts,
        private UsageRepository $usage,
    ) {
    }

    /** Generates a caption and images for $postId from $template, persisting content and token usage. */
    public function generate(array $template, int $postId): void
    {
        if ($template['text_provider_id'] === null || $template['image_provider_id'] === null) {
            throw new ProviderException('Template "' . $template['name'] . '" is missing a text or image provider.');
        }

        $textProvider = $this->providers->find((int) $template['text_provider_id'])
            ?? throw new ProviderException('Configured text provider no longer exists.');
        $imageProvider = $this->providers->find((int) $template['image_provider_id'])
            ?? throw new ProviderException('Configured image provider no longer exists.');

        $caption = $this->generateCaption($template, $textProvider, $postId);
        $imagePaths = $this->generateImages($template, $imageProvider, $postId);

        $this->posts->markGenerated($postId, $caption, $imagePaths);
    }

    private function generateCaption(array $template, array $provider, int $postId): string
    {
        $system = <<<PROMPT
            You write Instagram captions for an account about: {$template['subject']}.
            Context: {$template['description']}
            Formatting rules: {$template['caption_rules']}
            Reply with the caption text only, no preamble, no surrounding quotes.
            PROMPT;

        $result = $this->factory->text($provider)->generate($system, 'Write today\'s post.');

        $this->usage->record(
            $postId,
            (int) $provider['id'],
            'text',
            $result->model,
            $result->inputTokens,
            $result->outputTokens,
            0,
            Pricing::text($provider, $result->inputTokens, $result->outputTokens),
        );

        return $result->content;
    }

    /** @return string[] stored image filenames, in post order */
    private function generateImages(array $template, array $provider, int $postId): array
    {
        $prompt = trim(sprintf(
            "A social media image illustrating: %s\nTopic context: %s\nStyle: %s\nNo text, no watermark, no logos.",
            $template['subject'],
            $template['description'],
            $template['style_prompt'] !== '' ? $template['style_prompt'] : 'clean, modern, high quality',
        ));

        $count = max(1, (int) $template['image_count']);
        $result = $this->factory->image($provider)->generate($prompt, $count);

        $this->usage->record(
            $postId,
            (int) $provider['id'],
            'image',
            $result->model,
            $result->inputTokens,
            $result->outputTokens,
            count($result->images),
            Pricing::image($provider, $result->inputTokens, $result->outputTokens, count($result->images)),
        );

        return array_map(fn (string $bytes): string => $this->images->save($bytes), $result->images);
    }
}
