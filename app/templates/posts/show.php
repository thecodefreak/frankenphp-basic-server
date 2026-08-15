<?php $images = json_decode($post['images_json'] ?? '[]', true) ?: []; ?>

<div class="grid-2">
    <section class="card">
        <h2>Content</h2>
        <p class="field-hint">Status: <span class="pill pill-<?= e($post['status']) ?>"><?= e($post['status']) ?></span>
            <?php if ($post['scheduled_at']): ?> · Scheduled <?= e($post['scheduled_at']) ?> UTC<?php endif; ?>
            <?php if ($post['published_at']): ?> · Published <?= e($post['published_at']) ?> UTC<?php endif; ?>
        </p>

        <?php if ($post['last_error']): ?>
            <p class="error-message"><?= e($post['last_error']) ?></p>
        <?php endif; ?>

        <?php if ($images !== []): ?>
            <div class="image-grid">
                <?php foreach ($images as $filename): ?>
                    <img src="/storage/images/<?= e($filename) ?>" alt="" loading="lazy">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($editable): ?>
            <form method="post" action="/posts/<?= (int) $post['id'] ?>" class="form">
                <label class="field">
                    <span class="field-label">Caption</span>
                    <textarea name="caption" rows="6"><?= e($post['caption'] ?? '') ?></textarea>
                </label>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save caption</button>
                    <?php if ($post['caption']): ?>
                        <button type="submit" formaction="/posts/<?= (int) $post['id'] ?>/publish-now" class="btn">Publish now</button>
                    <?php endif; ?>
                    <?php if ($post['scheduled_at']): ?>
                        <button type="submit" formaction="/posts/<?= (int) $post['id'] ?>/cancel" class="btn btn-danger" data-confirm="Cancel this post?">Cancel</button>
                    <?php endif; ?>
                </div>
            </form>
        <?php elseif (in_array($post['status'], ['failed', 'skipped'], true)): ?>
            <form method="post" action="/posts/<?= (int) $post['id'] ?>/retry" class="form-actions">
                <button type="submit" class="btn btn-primary">Retry</button>
            </form>
        <?php else: ?>
            <p class="empty">Caption cannot be changed while a post is <?= e($post['status']) ?>.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>AI usage &amp; cost</h2>
        <?php if ($usage === []): ?>
            <p class="empty">No usage recorded yet.</p>
        <?php else: ?>
            <table class="data">
                <thead><tr><th>Kind</th><th>Model</th><th>Tokens</th><th>Images</th><th>Cost</th></tr></thead>
                <tbody>
                <?php $total = 0.0; ?>
                <?php foreach ($usage as $row): $total += (float) $row['cost_usd']; ?>
                    <tr>
                        <td><?= e($row['kind']) ?></td>
                        <td><?= e((string) $row['model']) ?></td>
                        <td><?= (int) $row['input_tokens'] ?> in / <?= (int) $row['output_tokens'] ?> out</td>
                        <td><?= (int) $row['image_count'] ?></td>
                        <td><?= e(money((float) $row['cost_usd'])) ?><?= $row['estimated'] ? ' (est.)' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="4" style="text-align:right">Total</td><td><?= e(money($total)) ?></td></tr></tfoot>
            </table>
        <?php endif; ?>
    </section>
</div>
