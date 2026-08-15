<?php if ($warnings !== []): ?>
    <section class="card warn-card">
        <h2>Setup needed</h2>
        <ul class="warn-list">
            <?php foreach ($warnings as $warning): ?>
                <li><?= e($warning) ?></li>
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
        <h2>Upcoming</h2>
        <?php if ($upcoming === []): ?>
            <p class="empty">Nothing scheduled. Activate a template to start filling the calendar.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($upcoming as $post): ?>
                    <li>
                        <a class="list-main" href="/posts/<?= (int) $post['id'] ?>">
                            <span class="list-title"><?= e($post['template_name'] ?? 'Untitled template') ?></span>
                            <span class="list-sub"><?= e(mb_strimwidth((string) $post['caption'], 0, 90, '…')) ?></span>
                        </a>
                        <span class="list-meta">
                            <span class="pill pill-<?= e($post['status']) ?>"><?= e($post['status']) ?></span>
                            <time><?= e($post['scheduled_at']) ?> UTC</time>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Cost this month by provider</h2>
        <?php if ($costByProvider === []): ?>
            <p class="empty">No AI usage recorded this month.</p>
        <?php else: ?>
            <table class="data">
                <thead><tr><th>Provider</th><th>Kind</th><th>Calls</th><th>Cost</th></tr></thead>
                <tbody>
                <?php foreach ($costByProvider as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['kind']) ?></td>
                        <td><?= (int) $row['calls'] ?></td>
                        <td><?= e(money((float) $row['cost_usd'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Recent activity</h2>
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
                            <time><?= e((string) ($post['published_at'] ?? '')) ?></time>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
