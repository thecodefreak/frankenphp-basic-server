<?php $actions = '<a class="btn btn-primary" href="/templates/new">New template</a>'; ?>

<?php if ($templates === []): ?>
    <section class="card">
        <p class="empty">No templates yet.</p>
    </section>
<?php else: ?>
    <section class="card">
        <table class="data">
            <thead><tr><th>Name</th><th>Subject</th><th>Providers</th><th>Schedule</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($templates as $template): ?>
                <?php $schedule = json_decode($template['schedule_json'], true) ?: ['times' => [], 'weekdays' => []]; ?>
                <tr>
                    <td>
                        <a href="/templates/<?= (int) $template['id'] ?>/edit"><?= e($template['name']) ?></a>
                        <?php if ($template['is_default']): ?><span class="pill">default</span><?php endif; ?>
                    </td>
                    <td><?= e(mb_strimwidth($template['subject'], 0, 40, '…')) ?></td>
                    <td><?= e($template['text_provider_name'] ?? '—') ?> / <?= e($template['image_provider_name'] ?? '—') ?></td>
                    <td><?= e(implode(', ', $schedule['times'])) ?: '—' ?></td>
                    <td><span class="pill <?= $template['is_active'] ? 'pill-published' : '' ?>"><?= $template['is_active'] ? 'active' : 'inactive' ?></span></td>
                    <td class="row-actions">
                        <a href="/templates/<?= (int) $template['id'] ?>/edit">Edit</a>
                        <form method="post" action="/templates/<?= (int) $template['id'] ?>/delete" data-confirm="Remove template &quot;<?= e($template['name']) ?>&quot;? Scheduled posts for it will remain in history but stop generating.">
                            <button type="submit" class="btn-link">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>
