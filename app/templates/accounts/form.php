<?php $editing = $account !== null; ?>

<form method="post" action="<?= $editing ? '/accounts/' . (int) $account['id'] : '/accounts' ?>" class="card form">
    <label class="field">
        <span class="field-label">Name</span>
        <input type="text" name="name" required value="<?= e($account['name'] ?? '') ?>" placeholder="e.g. Main account">
    </label>

    <label class="field">
        <span class="field-label">Login type</span>
        <select name="login_kind">
            <option value="instagram" <?= ($account['login_kind'] ?? 'instagram') === 'instagram' ? 'selected' : '' ?>>Instagram Login (no Facebook Page, 60-day token)</option>
            <option value="facebook" <?= ($account['login_kind'] ?? '') === 'facebook' ? 'selected' : '' ?>>Facebook Login (needs a linked Page, token doesn't expire)</option>
        </select>
        <span class="field-hint">See the README for how to obtain each. Both are supported equally — pick whichever you already set up in Meta's developer console.</span>
    </label>

    <label class="field">
        <span class="field-label">Instagram user ID</span>
        <input type="text" name="ig_user_id" required value="<?= e($account['ig_user_id'] ?? '') ?>" placeholder="1789...">
    </label>

    <label class="field">
        <span class="field-label">Facebook Page ID</span>
        <input type="text" name="page_id" value="<?= e($account['page_id'] ?? '') ?>" placeholder="Only needed for Facebook Login">
    </label>

    <label class="field">
        <span class="field-label">Access token<?= $editing ? ' (leave blank to keep the current token)' : '' ?></span>
        <input type="password" name="access_token" autocomplete="off" placeholder="<?= $editing ? '••••••••' : '' ?>">
    </label>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Connect account' ?></button>
        <a class="btn" href="/accounts">Cancel</a>
    </div>
</form>

<?php if ($editing): ?>
    <form method="post" action="/accounts/<?= (int) $account['id'] ?>/test" class="form-actions" style="max-width:640px">
        <button type="submit" class="btn">Verify connection</button>
    </form>
    <?php if ($account['last_check_error']): ?>
        <p class="error-message" style="max-width:640px"><?= e($account['last_check_error']) ?></p>
    <?php endif; ?>
<?php endif; ?>
