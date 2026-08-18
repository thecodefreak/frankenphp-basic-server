<?php
$editing = $provider !== null;
$kind = $provider['kind'] ?? 'text';
$this->slot('back', ['/providers', 'AI Providers']);
?>

<form method="post" action="<?= $editing ? '/providers/' . (int) $provider['id'] : '/providers' ?>" class="card form">
    <h2>Connection</h2>

    <label class="field">
        <span class="field-label">Name <span class="req">*</span></span>
        <input type="text" name="name" required value="<?= e($provider['name'] ?? '') ?>" placeholder="e.g. OpenAI captions">
    </label>

    <div class="field-row">
        <label class="field">
            <span class="field-label">Kind</span>
            <select name="kind" <?= $editing ? 'disabled' : '' ?> data-kind-select data-types="<?= e(json_encode($types, JSON_UNESCAPED_SLASHES)) ?>">
                <option value="text" <?= $kind === 'text' ? 'selected' : '' ?>>Text — writes captions</option>
                <option value="image" <?= $kind === 'image' ? 'selected' : '' ?>>Image — generates pictures</option>
            </select>
            <?php if ($editing): ?>
                <input type="hidden" name="kind" value="<?= e($kind) ?>">
                <span class="field-hint">Kind can't change after creation.</span>
            <?php endif; ?>
        </label>

        <label class="field">
            <span class="field-label">Type</span>
            <select name="type" id="type">
                <?php foreach ($types[$kind] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($provider['type'] ?? 'openai') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <label class="field">
        <span class="field-label">Model <span class="req">*</span></span>
        <input type="text" name="model" required value="<?= e($provider['model'] ?? '') ?>" placeholder="gpt-5-mini, claude-opus-5, gpt-image-1…">
        <span class="field-hint">Use the exact model ID from your provider's docs.</span>
    </label>

    <label class="field">
        <span class="field-label">Base URL override</span>
        <input type="url" name="base_url" value="<?= e($provider['base_url'] ?? '') ?>" placeholder="Leave blank for the provider's default endpoint">
        <span class="field-hint">Only needed for self-hosted or OpenAI-compatible endpoints.</span>
    </label>

    <div class="field">
        <span class="field-label">API key<?= $editing ? '' : ' *' ?></span>
        <div class="input-group" data-reveal>
            <input type="password" name="api_key" autocomplete="off" placeholder="<?= $editing ? 'Leave blank to keep the current key' : 'sk-…' ?>">
            <button type="button" class="btn btn-sm" data-no-busy>Show</button>
        </div>
        <span class="field-hint">Encrypted at rest with your <code>APP_KEY</code>. Never shown again after saving.</span>
    </div>

    <h2>Pricing</h2>
    <p class="field-hint" style="margin: -6px 0 0">Providers don't report costs, so these rates drive the spend figures on your dashboard. Check your provider's pricing page.</p>

    <div class="field-row" data-price-for="text" <?= $kind === 'text' ? '' : 'hidden' ?>>
        <label class="field">
            <span class="field-label">Input $ / 1M tokens</span>
            <input type="number" step="0.000001" min="0" name="price_input_per_mtok" value="<?= e((string) ($provider['price_input_per_mtok'] ?? '0')) ?>">
        </label>
        <label class="field">
            <span class="field-label">Output $ / 1M tokens</span>
            <input type="number" step="0.000001" min="0" name="price_output_per_mtok" value="<?= e((string) ($provider['price_output_per_mtok'] ?? '0')) ?>">
        </label>
    </div>

    <div class="field" data-price-for="image" <?= $kind === 'image' ? '' : 'hidden' ?>>
        <span class="field-label">$ per image</span>
        <input type="number" step="0.000001" min="0" name="price_per_image" value="<?= e((string) ($provider['price_per_image'] ?? '0')) ?>">
        <span class="field-hint">Used when the provider reports no token usage. Costs from it are labelled "estimated".</span>
    </div>

    <label class="field checkbox-field">
        <input type="checkbox" name="is_default" value="1" <?= !empty($provider['is_default']) ? 'checked' : '' ?>>
        <span>Use as the default provider of this kind for new templates</span>
    </label>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary" data-busy="Saving…"><?= $editing ? 'Save changes' : 'Add provider' ?></button>
        <a class="btn" href="/providers">Cancel</a>
    </div>
</form>

<?php if ($editing): ?>
    <section class="card">
        <div class="card-head">
            <h2>Test connection</h2>
        </div>
        <p class="field-hint">Sends a tiny real request to confirm the key, model and endpoint work. Costs a fraction of a cent.</p>
        <form method="post" action="/providers/<?= (int) $provider['id'] ?>/test" class="form-actions">
            <button type="submit" class="btn" data-busy="Testing…">Run test</button>
        </form>
    </section>
<?php endif; ?>
