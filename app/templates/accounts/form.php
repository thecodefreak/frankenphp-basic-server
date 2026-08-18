<?php
$editing = $account !== null;
$loginKind = $account['login_kind'] ?? 'instagram';
$this->slot('back', ['/accounts', 'Instagram']);
?>

<form method="post" action="<?= $editing ? '/accounts/' . (int) $account['id'] : '/accounts' ?>" class="card form">
    <label class="field">
        <span class="field-label">Name <span class="req">*</span></span>
        <input type="text" name="name" required value="<?= e($account['name'] ?? '') ?>" placeholder="e.g. Main account">
        <span class="field-hint">Just a label for you — not the Instagram handle.</span>
    </label>

    <label class="field">
        <span class="field-label">Login type</span>
        <select name="login_kind">
            <option value="instagram" <?= $loginKind === 'instagram' ? 'selected' : '' ?>>Instagram Login — no Facebook Page, 60-day token</option>
            <option value="facebook" <?= $loginKind === 'facebook' ? 'selected' : '' ?>>Facebook Login — needs a Page, token doesn't expire</option>
        </select>
        <span class="field-hint">Both work. Instagram Login is simpler to set up; Facebook Login never needs re-authenticating. The README covers both.</span>
    </label>

    <label class="field">
        <span class="field-label">Instagram user ID <span class="req">*</span></span>
        <input type="text" name="ig_user_id" required value="<?= e($account['ig_user_id'] ?? '') ?>" placeholder="17841400000000000">
        <span class="field-hint">The numeric Business account ID, not your @handle.</span>
    </label>

    <label class="field">
        <span class="field-label">Facebook Page ID</span>
        <input type="text" name="page_id" value="<?= e($account['page_id'] ?? '') ?>" placeholder="Only needed for Facebook Login">
    </label>

    <div class="field">
        <span class="field-label">Access token<?= $editing ? '' : ' *' ?></span>
        <div class="input-group" data-reveal>
            <input type="password" name="access_token" autocomplete="off" placeholder="<?= $editing ? 'Leave blank to keep the current token' : 'Long-lived access token' ?>">
            <button type="button" class="btn btn-sm" data-no-busy>Show</button>
        </div>
        <span class="field-hint">Must be a <strong>long-lived</strong> token. Encrypted at rest; saving runs a live check against Instagram.</span>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary" data-busy="Verifying…"><?= $editing ? 'Save changes' : 'Connect account' ?></button>
        <a class="btn" href="/accounts">Cancel</a>
    </div>
</form>

<?php if ($editing): ?>
    <section class="card">
        <div class="card-head">
            <h2>Connection status</h2>
        </div>
        <?php if ($account['last_check_error']): ?>
            <p class="error-message"><?= e($account['last_check_error']) ?></p>
        <?php else: ?>
            <p class="empty">Last check passed<?= $account['last_refreshed_at'] ? ', token refreshed ' . e($account['last_refreshed_at']) . ' UTC' : '' ?>.</p>
        <?php endif; ?>
        <form method="post" action="/accounts/<?= (int) $account['id'] ?>/test" class="form-actions">
            <button type="submit" class="btn" data-busy="Checking…">Verify connection</button>
        </form>
    </section>
<?php endif; ?>
