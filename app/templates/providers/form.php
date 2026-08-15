<?php $editing = $provider !== null; ?>

<form method="post" action="<?= $editing ? '/providers/' . (int) $provider['id'] : '/providers' ?>" class="card form">
    <label class="field">
        <span class="field-label">Name</span>
        <input type="text" name="name" required value="<?= e($provider['name'] ?? '') ?>" placeholder="e.g. OpenAI GPT-5">
    </label>

    <div class="field-row">
        <label class="field">
            <span class="field-label">Kind</span>
            <select name="kind" <?= $editing ? 'disabled' : '' ?> data-kind-select data-types="<?= e(json_encode($types, JSON_UNESCAPED_SLASHES)) ?>">
                <option value="text" <?= ($provider['kind'] ?? 'text') === 'text' ? 'selected' : '' ?>>Text (captions)</option>
                <option value="image" <?= ($provider['kind'] ?? '') === 'image' ? 'selected' : '' ?>>Image</option>
            </select>
            <?php if ($editing): ?><input type="hidden" name="kind" value="<?= e($provider['kind']) ?>"><?php endif; ?>
        </label>

        <label class="field">
            <span class="field-label">Type</span>
            <select name="type" id="type">
                <?php foreach ($types[$provider['kind'] ?? 'text'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($provider['type'] ?? 'openai') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <label class="field">
        <span class="field-label">Model</span>
        <input type="text" name="model" required value="<?= e($provider['model'] ?? '') ?>" placeholder="gpt-5, claude-opus-5, gpt-image-1…">
    </label>

    <label class="field">
        <span class="field-label">Base URL override</span>
        <input type="text" name="base_url" value="<?= e($provider['base_url'] ?? '') ?>" placeholder="Leave blank to use the provider's default endpoint">
        <span class="field-hint">Only needed for a self-hosted or OpenAI-compatible endpoint.</span>
    </label>

    <label class="field">
        <span class="field-label">API key<?= $editing ? ' (leave blank to keep the current key)' : '' ?></span>
        <input type="password" name="api_key" autocomplete="off" placeholder="<?= $editing ? '••••••••' : '' ?>">
    </label>

    <h2>Pricing (USD, used to estimate cost — not read from the provider)</h2>
    <div class="field-row">
        <label class="field">
            <span class="field-label">Input $ / 1M tokens</span>
            <input type="number" step="0.000001" min="0" name="price_input_per_mtok" value="<?= e((string) ($provider['price_input_per_mtok'] ?? '0')) ?>">
        </label>
        <label class="field">
            <span class="field-label">Output $ / 1M tokens</span>
            <input type="number" step="0.000001" min="0" name="price_output_per_mtok" value="<?= e((string) ($provider['price_output_per_mtok'] ?? '0')) ?>">
        </label>
        <label class="field">
            <span class="field-label">$ / image (fallback)</span>
            <input type="number" step="0.000001" min="0" name="price_per_image" value="<?= e((string) ($provider['price_per_image'] ?? '0')) ?>">
        </label>
    </div>
    <p class="field-hint">Image cost uses token pricing when the provider reports usage (e.g. gpt-image-1); otherwise it falls back to the per-image price and is labelled "estimated".</p>

    <label class="field checkbox-field">
        <input type="checkbox" name="is_default" value="1" <?= !empty($provider['is_default']) ? 'checked' : '' ?>>
        <span>Use as the default <span id="kind-label"><?= e($provider['kind'] ?? 'text') ?></span> provider for new templates</span>
    </label>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add provider' ?></button>
        <a class="btn" href="/providers">Cancel</a>
    </div>
</form>

<?php if ($editing): ?>
    <form method="post" action="/providers/<?= (int) $provider['id'] ?>/test" class="form-actions" style="max-width:640px">
        <button type="submit" class="btn">Test connection</button>
    </form>
<?php endif; ?>
