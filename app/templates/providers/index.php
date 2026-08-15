<?php $actions = '<a class="btn btn-primary" href="/providers/new">Add provider</a>'; ?>

<?php if ($providers === []): ?>
    <section class="card">
        <p class="empty">No AI providers yet. Add a text provider and an image provider to start generating posts.</p>
    </section>
<?php else: ?>
    <?php foreach (['text' => 'Text providers', 'image' => 'Image providers'] as $kind => $label): ?>
        <?php $rows = array_filter($providers, static fn ($p) => $p['kind'] === $kind); ?>
        <section class="card">
            <h2><?= e($label) ?></h2>
            <?php if ($rows === []): ?>
                <p class="empty">None configured.</p>
            <?php else: ?>
                <table class="data">
                    <thead><tr><th>Name</th><th>Type</th><th>Model</th><th>Default</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $provider): ?>
                        <tr>
                            <td><a href="/providers/<?= (int) $provider['id'] ?>/edit"><?= e($provider['name']) ?></a></td>
                            <td><?= e($provider['type']) ?></td>
                            <td><?= e($provider['model']) ?></td>
                            <td><?= $provider['is_default'] ? '✓' : '' ?></td>
                            <td class="row-actions">
                                <a href="/providers/<?= (int) $provider['id'] ?>/edit">Edit</a>
                                <form method="post" action="/providers/<?= (int) $provider['id'] ?>/delete" data-confirm="Remove provider &quot;<?= e($provider['name']) ?>&quot;?">
                                    <button type="submit" class="btn-link">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
