<?php
$editing = $template !== null;
$schedule = $editing ? (json_decode($template['schedule_json'], true) ?: ['times' => [], 'weekdays' => []]) : ['times' => [], 'weekdays' => [1, 2, 3, 4, 5]];
$weekdayLabels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
$this->slot('back', ['/templates', 'Templates']);
?>

<form method="post" action="<?= $editing ? '/templates/' . (int) $template['id'] : '/templates' ?>" class="card form" data-schedule-form>
    <h2>Content</h2>

    <label class="field">
        <span class="field-label">Name <span class="req">*</span></span>
        <input type="text" name="name" required value="<?= e($template['name'] ?? '') ?>" placeholder="e.g. Couple Therapy">
    </label>

    <label class="field">
        <span class="field-label">Subject <span class="req">*</span></span>
        <input type="text" name="subject" required value="<?= e($template['subject'] ?? '') ?>" placeholder="What this account is about">
        <span class="field-hint">One line describing the account's theme. Used in every caption and image prompt.</span>
    </label>

    <label class="field">
        <span class="field-label">Description <span class="req">*</span></span>
        <textarea name="description" required placeholder="Guidance the AI uses to pick a topic each time"><?= e($template['description'] ?? '') ?></textarea>
        <span class="field-hint">The more specific this is, the less repetitive your posts will be.</span>
    </label>

    <label class="field">
        <span class="field-label">Image style prompt</span>
        <textarea name="style_prompt" placeholder="Visual style applied to every generated image"><?= e($template['style_prompt'] ?? '') ?></textarea>
        <span class="field-hint">Tip: ask for no text in the image — AI-rendered text usually looks wrong.</span>
    </label>

    <label class="field">
        <span class="field-label">Caption rules</span>
        <textarea name="caption_rules" placeholder="Length, hashtags, tone…"><?= e($template['caption_rules'] ?? '') ?></textarea>
        <span class="field-hint">Instagram allows 2,200 characters and 30 hashtags per caption.</span>
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
            <?php if ($textProviders === []): ?><span class="field-hint"><a href="/providers/new">Add a text provider</a> first.</span><?php endif; ?>
        </label>

        <label class="field">
            <span class="field-label">Image provider</span>
            <select name="image_provider_id">
                <option value="">— none —</option>
                <?php foreach ($imageProviders as $provider): ?>
                    <option value="<?= (int) $provider['id'] ?>" <?= (int) ($template['image_provider_id'] ?? 0) === (int) $provider['id'] ? 'selected' : '' ?>><?= e($provider['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($imageProviders === []): ?><span class="field-hint"><a href="/providers/new">Add an image provider</a> first.</span><?php endif; ?>
        </label>

        <label class="field" style="flex: 0 1 150px">
            <span class="field-label">Images per post</span>
            <input type="number" name="image_count" min="1" max="10" value="<?= (int) ($template['image_count'] ?? 1) ?>">
            <span class="field-hint">2+ becomes a carousel.</span>
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
        <?php if ($accounts === []): ?><span class="field-hint">No accounts connected — <a href="/accounts/new">connect one</a>.</span><?php endif; ?>
    </label>

    <h2>Schedule</h2>

    <label class="field">
        <span class="field-label">Timezone</span>
        <input type="text" name="timezone" list="timezone-list" data-schedule-field
               value="<?= e($template['timezone'] ?? $defaultTimezone) ?>" placeholder="Start typing, e.g. Europe/London">
        <datalist id="timezone-list">
            <?php foreach ($timezones as $timezone): ?>
                <option value="<?= e($timezone) ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <span class="field-hint">Times below are local to this zone and follow daylight saving automatically.</span>
    </label>

    <div class="field" data-times>
        <span class="field-label">Posting times</span>
        <input type="hidden" name="times" value="<?= e(implode(',', $schedule['times'])) ?>" data-schedule-field>
        <div class="chip-list" data-times-list></div>
        <div class="input-group">
            <input type="time" aria-label="New posting time">
            <button type="button" class="btn btn-sm" data-times-add data-no-busy>Add time</button>
        </div>
    </div>

    <fieldset class="field">
        <legend class="field-label">Days of week</legend>
        <div class="toggle-row">
            <?php foreach ($weekdayLabels as $value => $label): ?>
                <label class="toggle-pill">
                    <input type="checkbox" name="weekdays[]" value="<?= $value ?>" <?= in_array($value, $schedule['weekdays'], true) ? 'checked' : '' ?>>
                    <span><?= e($label) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="preset-row">
            <button type="button" class="btn btn-sm" data-weekday-preset="1,2,3,4,5" data-no-busy>Weekdays</button>
            <button type="button" class="btn btn-sm" data-weekday-preset="6,7" data-no-busy>Weekend</button>
            <button type="button" class="btn btn-sm" data-weekday-preset="1,2,3,4,5,6,7" data-no-busy>Every day</button>
            <button type="button" class="btn btn-sm" data-weekday-preset="" data-no-busy>Clear</button>
        </div>
    </fieldset>

    <div class="field">
        <span class="field-label">Next 5 occurrences</span>
        <ul class="preview-list" data-schedule-preview><li>Loading…</li></ul>
    </div>

    <h2>Status</h2>

    <label class="field checkbox-field">
        <input type="checkbox" name="is_active" value="1" <?= !empty($template['is_active']) ? 'checked' : '' ?>>
        <span><strong>Active</strong> — generate and publish on this schedule. Needs both providers, an account, a time and a day.</span>
    </label>

    <label class="field checkbox-field">
        <input type="checkbox" name="is_default" value="1" <?= !empty($template['is_default']) ? 'checked' : '' ?>>
        <span><strong>Default template</strong> — preselected when generating a post manually.</span>
    </label>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary" data-busy="Saving…"><?= $editing ? 'Save changes' : 'Create template' ?></button>
        <a class="btn" href="/templates">Cancel</a>
    </div>
</form>
