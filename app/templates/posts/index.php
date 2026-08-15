<?php
$activeTemplates = array_filter($templates, static fn ($t) => $t['is_active']);
ob_start();
?>
<form method="post" action="/posts/generate" style="display:inline-flex; gap:8px;">
    <select name="template_id" required>
        <option value="">Generate from…</option>
        <?php foreach ($templates as $template): ?>
            <option value="<?= (int) $template['id'] ?>"><?= e($template['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Generate now</button>
</form>
<?php $actions = ob_get_clean(); ?>

<?php if ($posts === []): ?>
    <section class="card">
        <p class="empty">No posts yet. Generate one from a template, or wait for the schedule to create them.</p>
    </section>
<?php else: ?>
    <section class="card">
        <table class="data">
            <thead><tr><th>Template</th><th>Status</th><th>Scheduled</th><th>Caption</th></tr></thead>
            <tbody>
            <?php foreach ($posts as $post): ?>
                <tr>
                    <td><a href="/posts/<?= (int) $post['id'] ?>"><?= e($post['template_name'] ?? 'Untitled') ?></a></td>
                    <td><span class="pill pill-<?= e($post['status']) ?>"><?= e($post['status']) ?></span></td>
                    <td><?= e((string) ($post['scheduled_at'] ?? '—')) ?></td>
                    <td><?= e(mb_strimwidth((string) $post['caption'], 0, 70, '…')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>
