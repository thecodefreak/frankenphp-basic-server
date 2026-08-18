<?php if ($warnings !== []): ?>
    <section class="card warn-card">
        <h2>Finish setting up</h2>
        <ul class="warn-list">
            <?php foreach ($warnings as $warning): ?>
                <li>
                    <span><?= e($warning['message']) ?></span>
                    <a href="<?= e($warning['href']) ?>"><?= e($warning['action']) ?> →</a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="stat-row">
    <div class="stat"><span class="stat-value"><?= (int) $counts['templates'] ?></span><span class="stat-label">Active templates</span></div>
    <div class="stat"><span class="stat-value"><?= (int) $counts['accounts'] ?></span><span class="stat-label">Connected accounts</span></div>
    <div class="stat"><span class="stat-value"><?= (int) $counts['providers'] ?></span><span class="stat-label">AI providers</span></div>
    <div class="stat"><span class="stat-value"><?= (int) $counts['published'] ?></span><span class="stat-label">Posts published</span></div>
    <div class="stat"><span class="stat-value"><?= e(money($costMonth)) ?></span><span class="stat-label">Spend this month</span></div>
</section>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <h2>Upcoming</h2>
            <a href="/calendar" class="btn btn-sm">Calendar</a>
        </div>
        <?php if ($upcoming === []): ?>
            <div class="empty-state">
                <p>Nothing scheduled yet. Activate a template to start filling the calendar.</p>
                <a class="btn btn-primary" href="/templates">Set up a template</a>
            </div>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($upcoming as $post): ?>
                    <li>
                        <a class="list-main" href="/posts/<?= (int) $post['id'] ?>">
                            <span class="list-title"><?= e($post['template_name'] ?? 'Untitled template') ?></span>
                            <span class="list-sub"><?= e(mb_strimwidth((string) $post['caption'], 0, 90, '…')) ?: 'Not generated yet' ?></span>
                        </a>
                        <span class="list-meta">
                            <span class="pill pill-<?= e($post['status']) ?>"><?= e($post['status']) ?></span>
                            <time datetime="<?= e($post['scheduled_at']) ?>"><?= e(substr((string) $post['scheduled_at'], 0, 16)) ?></time>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head">
            <h2>Cost this month</h2>
            <span class="pill"><?= e(money($costMonth)) ?> total</span>
        </div>
        <?php if ($costByProvider === []): ?>
            <p class="empty">No AI usage recorded this month.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Provider</th><th>Kind</th><th>Calls</th><th>Cost</th></tr></thead>
                    <tbody>
                    <?php foreach ($costByProvider as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td><span class="pill"><?= e($row['kind']) ?></span></td>
                            <td class="num"><?= (int) $row['calls'] ?></td>
                            <td class="num"><?= e(money((float) $row['cost_usd'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head">
            <h2>Recent activity</h2>
            <a href="/posts" class="btn btn-sm">All posts</a>
        </div>
        <?php if ($recent === []): ?>
            <p class="empty">No posts have run yet.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($recent as $post): ?>
                    <li>
                        <a class="list-main" href="/posts/<?= (int) $post['id'] ?>">
                            <span class="list-title"><?= e($post['template_name'] ?? 'Untitled template') ?></span>
                            <?php if ($post['last_error']): ?>
                                <span class="list-sub list-error"><?= e(mb_strimwidth((string) $post['last_error'], 0, 90, '…')) ?></span>
                            <?php endif; ?>
                        </a>
                        <span class="list-meta">
                            <span class="pill pill-<?= e($post['status']) ?>"><?= e($post['status']) ?></span>
                            <time><?= e(substr((string) ($post['published_at'] ?? ''), 0, 16)) ?></time>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
