<?php $actions = '<a class="btn btn-primary" href="/accounts/new">Connect account</a>'; ?>

<?php if ($accounts === []): ?>
    <section class="card">
        <p class="empty">No Instagram accounts connected yet.</p>
    </section>
<?php else: ?>
    <section class="card">
        <table class="data">
            <thead><tr><th>Name</th><th>Login</th><th>IG user ID</th><th>Token expires</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($accounts as $account): ?>
                <tr>
                    <td><a href="/accounts/<?= (int) $account['id'] ?>/edit"><?= e($account['name']) ?></a></td>
                    <td><?= e($account['login_kind']) ?></td>
                    <td><?= e($account['ig_user_id']) ?></td>
                    <td><?= e($account['token_expires_at'] ?? 'never') ?></td>
                    <td><?= $account['last_check_error'] ? '<span class="pill pill-failed">error</span>' : '<span class="pill pill-published">ok</span>' ?></td>
                    <td class="row-actions">
                        <a href="/accounts/<?= (int) $account['id'] ?>/edit">Edit</a>
                        <form method="post" action="/accounts/<?= (int) $account['id'] ?>/delete" data-confirm="Remove account &quot;<?= e($account['name']) ?>&quot;? Templates using it will need a new account.">
                            <button type="submit" class="btn-link">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>
