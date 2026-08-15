<?php
$editing = $template !== null;
$schedule = $editing ? (json_decode($template['schedule_json'], true) ?: ['times' => [], 'weekdays' => []]) : ['times' => [], 'weekdays' => [1, 2, 3, 4, 5]];
$weekdayLabels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
?>

<form method="post" action="<?= $editing ? '/templates/' . (int) $template['id'] : '/templates' ?>" class="card form" data-schedule-form>
    <h2>Content</h2>

    <label class="field">
        <span class="field-label">Name</span>
        <input type="text" name="name" required value="<?= e($template['name'] ?? '') ?>" placeholder="e.g. Couple Therapy">
    </label>

    <label class="field">
        <span class="field-label">Subject</span>
        <input type="text" name="subject" required value="<?= e($template['subject'] ?? '') ?>" placeholder="What this account is about">
    </label>

    <label class="field">
        <span class="field-label">Description</span>
        <textarea name="description" required placeholder="Guidance the AI uses to pick a fact/topic each time"><?= e($template['description'] ?? '') ?></textarea>
    </label>

    <label class="field">
        <span class="field-label">Image style prompt</span>
        <textarea name="style_prompt" placeholder="Visual style to apply to every generated image"><?= e($template['style_prompt'] ?? '') ?></textarea>
    </label>

    <label class="field">
        <span class="field-label">Caption rules</span>
        <textarea name="caption_rules" placeholder="Formatting rules: length, hashtags, tone…"><?= e($template['caption_rules'] ?? '') ?></textarea>
    </label>

    <h2>Generation</h2>

    <div class="field-row">
        <label class="field">
            <span class="field-label">Text provider</span>
            <select name="text_provider_id">
                <option value="">— none —</option>
                <?php foreach ($textProviders as $provider): ?>
                    <option value="<?= (int) $provider['id'] ?>" <?= (int) ($template['text_provider_id'] ?? 0) === (int) $provider['id'] ? 'selected' : '' ?>><?= e($provider['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span class="field-label">Image provider</span>
            <select name="image_provider_id">
                <option value="">— none —</option>
                <?php foreach ($imageProviders as $provider): ?>
                    <option value="<?= (int) $provider['id'] ?>" <?= (int) ($template['image_provider_id'] ?? 0) === (int) $provider['id'] ? 'selected' : '' ?>><?= e($provider['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span class="field-label">Images per post</span>
            <input type="number" name="image_count" min="1" max="10" value="<?= (int) ($template['image_count'] ?? 1) ?>">
            <span class="field-hint">1 = single image, 2+ = carousel</span>
        </label>
    </div>

    <label class="field">
        <span class="field-label">Instagram account</span>
        <select name="instagram_account_id">
            <option value="">— none —</option>
            <?php foreach ($accounts as $account): ?>
                <option value="<?= (int) $account['id'] ?>" <?= (int) ($template['instagram_account_id'] ?? 0) === (int) $account['id'] ? 'selected' : '' ?>><?= e($account['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($accounts === []): ?><span class="field-hint">No accounts connected yet — add one under Instagram.</span><?php endif; ?>
    </label>

    <h2>Schedule</h2>

    <label class="field">
        <span class="field-label">Timezone</span>
        <select name="timezone" data-schedule-field>
            <?php foreach ($timezones as $timezone): ?>
                <option value="<?= e($timezone) ?>" <?= ($template['timezone'] ?? $defaultTimezone) === $timezone ? 'selected' : '' ?>><?= e($timezone) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="field">
        <span class="field-label">Times (24h, comma-separated)</span>
        <input type="text" name="times" data-schedule-field value="<?= e(implode(', ', $schedule['times'])) ?>" placeholder="09:00, 18:30">
    </label>

    <fieldset class="field">
        <legend class="field-label">Days of week</legend>
        <div class="checkbox-row">
            <?php foreach ($weekdayLabels as $value => $label): ?>
                <label class="checkbox-field">
                    <input type="checkbox" name="weekdays[]" value="<?= $value ?>" data-schedule-field <?= in_array($value, $schedule['weekdays'], true) ? 'checked' : '' ?>>
                    <span><?= e($label) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div class="field">
        <span class="field-label">Next 5 occurrences</span>
        <ul class="preview-list" data-schedule-preview><li class="empty">Set a time and at least one day to preview.</li></ul>
    </div>

    <label class="field checkbox-field">
        <input type="checkbox" name="is_active" value="1" <?= !empty($template['is_active']) ? 'checked' : '' ?>>
        <span>Active (generates and publishes on schedule)</span>
    </label>

    <label class="field checkbox-field">
        <input type="checkbox" name="is_default" value="1" <?= !empty($template['is_default']) ? 'checked' : '' ?>>
        <span>Default template</span>
    </label>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Create template' ?></button>
        <a class="btn" href="/templates">Cancel</a>
    </div>
</form>
