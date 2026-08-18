<?php
ob_start(); ?>
<form method="post" action="/posts/generate" class="input-group">
    <label class="visually-hidden" for="gen-template">Template</label>
    <select name="template_id" id="gen-template" required>
        <option value="">Generate from…</option>
        <?php foreach ($templates as $template): ?>
            <option value="<?= (int) $template['id'] ?>" <?= $template['is_default'] ? 'selected' : '' ?>><?= e($template['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary" data-busy="Generating…">Generate now</button>
</form>
<?php $this->slot('actions', ob_get_clean()); ?>

<section class="card">
    <form method="get" action="/posts" class="filter-bar">
        <label class="field">
            <span class="field-label">Status</span>
            <select name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach (['draft', 'pending', 'generating', 'ready', 'publishing', 'published', 'failed', 'skipped', 'cancelled'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span class="field-label">Template</span>
            <select name="template_id" onchange="this.form.submit()">
                <option value="0">All templates</option>
                <?php foreach ($templates as $template): ?>
                    <option value="<?= (int) $template['id'] ?>" <?= $filters['template_id'] === (int) $template['id'] ? 'selected' : '' ?>><?= e($template['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn" data-no-busy>Apply</button>
        <?php if ($filters['status'] !== '' || $filters['template_id'] > 0): ?>
            <a class="btn btn-sm" href="/posts">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($posts === []): ?>
        <div class="empty-state">
            <p><?= $total === 0 && $filters['status'] === '' && $filters['template_id'] === 0
                ? 'No posts yet. Generate one manually, or activate a template and let the schedule create them.'
                : 'No posts match these filters.' ?></p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Scheduled</th><th>Template</th><th>Status</th><th>Caption</th><th>Cost</th></tr></thead>
                <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td class="num"><?= e(substr((string) ($post['scheduled_at'] ?? ''), 0, 16)) ?: '<span class="empty">—</span>' ?></td>
                        <td><a href="/posts/<?= (int) $post['id'] ?>"><?= e($post['template_name'] ?? 'Untitled') ?></a></td>
                        <td><span class="pill pill-<?= e($post['status']) ?>"><?= e($post['status']) ?></span></td>
                        <td class="wrap">
                            <?= e(mb_strimwidth((string) $post['caption'], 0, 70, '…')) ?>
                            <?php if ($post['last_error']): ?>
                                <br><span class="field-hint list-error"><?= e(mb_strimwidth((string) $post['last_error'], 0, 70, '…')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= e(money((float) $post['cost_usd'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <?php $query = static fn (int $p): string => '/posts?' . http_build_query(['status' => $filters['status'], 'template_id' => $filters['template_id'], 'page' => $p]); ?>
            <div class="pager">
                <span class="pager-info">Page <?= $page ?> of <?= $pages ?> · <?= $total ?> post<?= $total === 1 ? '' : 's' ?></span>
                <span class="pager-controls">
                    <?php if ($page > 1): ?><a class="btn btn-sm" href="<?= e($query($page - 1)) ?>">‹ Previous</a><?php endif; ?>
                    <?php if ($page < $pages): ?><a class="btn btn-sm" href="<?= e($query($page + 1)) ?>">Next ›</a><?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
