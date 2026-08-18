<?php $this->slot('actions', '<a class="btn btn-primary" href="/accounts/new">Connect account</a>'); ?>

<?php if ($accounts === []): ?>
    <section class="card">
        <div class="empty-state">
            <p>Connect an Instagram Business or Creator account to publish to. See the README for how to get a token.</p>
            <a class="btn btn-primary" href="/accounts/new">Connect an account</a>
        </div>
    </section>
<?php else: ?>
    <section class="card">
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Name</th><th>Login</th><th>IG user ID</th><th>Token expires</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td><a href="/accounts/<?= (int) $account['id'] ?>/edit"><?= e($account['name']) ?></a></td>
                        <td><?= $account['login_kind'] === 'instagram' ? 'Instagram Login' : 'Facebook Login' ?></td>
                        <td class="num"><?= e($account['ig_user_id']) ?></td>
                        <td class="num"><?= e(substr((string) ($account['token_expires_at'] ?? ''), 0, 10)) ?: 'never' ?></td>
                        <td>
                            <?php if ($account['last_check_error']): ?>
                                <span class="pill pill-failed">error</span>
                            <?php else: ?>
                                <span class="pill pill-published">ok</span>
                            <?php endif; ?>
                        </td>
                        <td class="row-actions">
                            <a href="/accounts/<?= (int) $account['id'] ?>/edit">Edit</a>
                            <form method="post" action="/accounts/<?= (int) $account['id'] ?>/delete" data-confirm="Remove account &quot;<?= e($account['name']) ?>&quot;? Templates using it will need a new account.">
                                <button type="submit" class="btn-link" data-no-busy>Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
