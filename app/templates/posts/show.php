<?php
$images = json_decode($post['images_json'] ?? '[]', true) ?: [];
$totalCost = array_sum(array_map(static fn ($u) => (float) $u['cost_usd'], $usage));
$this->slot('back', ['/posts', 'Posts']);
?>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <h2>Content</h2>
            <span class="pill pill-<?= e($post['status']) ?>"><?= e($post['status']) ?></span>
        </div>

        <ul class="list" style="margin-bottom: 14px">
            <li>
                <span class="list-sub">Template</span>
                <span class="list-meta"><?= e($post['template_name'] ?? 'Untitled') ?></span>
            </li>
            <?php if ($post['scheduled_at']): ?>
                <li><span class="list-sub">Scheduled</span><span class="list-meta"><?= e($post['scheduled_at']) ?> UTC</span></li>
            <?php endif; ?>
            <?php if ($post['published_at']): ?>
                <li><span class="list-sub">Published</span><span class="list-meta"><?= e($post['published_at']) ?> UTC</span></li>
            <?php endif; ?>
            <?php if ((int) $post['attempts'] > 0): ?>
                <li><span class="list-sub">Attempts</span><span class="list-meta"><?= (int) $post['attempts'] ?> of 5</span></li>
            <?php endif; ?>
            <?php if ($post['ig_media_id']): ?>
                <li><span class="list-sub">Instagram media ID</span><span class="list-meta"><?= e($post['ig_media_id']) ?></span></li>
            <?php endif; ?>
        </ul>

        <?php if ($post['last_error']): ?>
            <p class="error-message"><?= e($post['last_error']) ?></p>
        <?php endif; ?>

        <?php if ($images !== []): ?>
            <div class="image-grid" data-lightbox>
                <?php foreach ($images as $filename): ?>
                    <button type="button" data-full="/storage/images/<?= e($filename) ?>" data-no-busy aria-label="View full size">
                        <img src="/storage/images/<?= e($filename) ?>" alt="" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($editable): ?>
            <form method="post" action="/posts/<?= (int) $post['id'] ?>" class="form form-wide">
                <div class="field" data-counter="2200">
                    <span class="field-label">Caption</span>
                    <textarea name="caption" rows="8"><?= e($post['caption'] ?? '') ?></textarea>
                    <span class="counter" data-counter-output></span>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" data-busy="Saving…">Save caption</button>
                    <?php if ($post['caption']): ?>
                        <button type="submit" formaction="/posts/<?= (int) $post['id'] ?>/publish-now" class="btn" data-busy="Queuing…">Publish now</button>
                    <?php endif; ?>
                    <?php if ($post['scheduled_at']): ?>
                        <button type="submit" formaction="/posts/<?= (int) $post['id'] ?>/cancel" class="btn btn-danger" data-confirm="Cancel this post? The slot stays taken so it won't be recreated." data-no-busy>Cancel post</button>
                    <?php endif; ?>
                </div>
            </form>
        <?php else: ?>
            <?php if ($post['caption']): ?>
                <p class="field-label">Caption</p>
                <p style="white-space: pre-wrap"><?= e($post['caption']) ?></p>
            <?php endif; ?>
            <?php if (in_array($post['status'], ['failed', 'skipped'], true)): ?>
                <form method="post" action="/posts/<?= (int) $post['id'] ?>/retry" class="form-actions">
                    <button type="submit" class="btn btn-primary" data-busy="Queuing…">Retry this post</button>
                </form>
            <?php else: ?>
                <p class="empty">This post is <?= e($post['status']) ?> and can no longer be edited.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head">
            <h2>AI usage &amp; cost</h2>
            <span class="pill"><?= e(money($totalCost)) ?></span>
        </div>
        <?php if ($usage === []): ?>
            <p class="empty">No usage recorded — this post hasn't been generated yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Kind</th><th>Model</th><th>Tokens</th><th>Images</th><th>Cost</th></tr></thead>
                    <tbody>
                    <?php foreach ($usage as $row): ?>
                        <tr>
                            <td><span class="pill"><?= e($row['kind']) ?></span></td>
                            <td><code><?= e((string) $row['model']) ?></code></td>
                            <td class="num"><?= (int) $row['input_tokens'] ?> in / <?= (int) $row['output_tokens'] ?> out</td>
                            <td class="num"><?= (int) $row['image_count'] ?></td>
                            <td class="num">
                                <?= e(money((float) $row['cost_usd'])) ?>
                                <?php if ($row['estimated']): ?><br><span class="field-hint">estimated</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
