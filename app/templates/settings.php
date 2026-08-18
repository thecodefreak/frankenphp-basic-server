<form method="post" action="/settings" class="card form">
    <h2>Publishing</h2>

    <label class="field">
        <span class="field-label">Public base URL</span>
        <input type="url" name="public_base_url" value="<?= e($settings['public_base_url'] ?? '') ?>" placeholder="https://posts.example.com">
        <span class="field-hint">Instagram downloads your images from its own servers, so this must be a publicly reachable HTTPS address that serves this app. Without it, publishing fails with error 2207003.</span>
    </label>

    <label class="field">
        <span class="field-label">Default timezone</span>
        <input type="text" name="default_timezone" list="timezone-list" value="<?= e($settings['default_timezone'] ?? 'UTC') ?>" placeholder="Start typing, e.g. Europe/London">
        <datalist id="timezone-list">
            <?php foreach ($timezones as $timezone): ?>
                <option value="<?= e($timezone) ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <span class="field-hint">Used as the default when creating a template. Each template keeps its own timezone.</span>
    </label>

    <h2>Timing</h2>

    <div class="field-row">
        <label class="field">
            <span class="field-label">Generate ahead (minutes)</span>
            <input type="number" name="generate_lead_minutes" min="5" max="10080" value="<?= (int) ($settings['generate_lead_minutes'] ?? 90) ?>">
            <span class="field-hint">How early a post is generated before its slot, leaving room for retries.</span>
        </label>

        <label class="field">
            <span class="field-label">Missed slot grace (minutes)</span>
            <input type="number" name="missed_slot_grace_minutes" min="0" max="10080" value="<?= (int) ($settings['missed_slot_grace_minutes'] ?? 60) ?>">
            <span class="field-hint">Past this delay a slot is skipped rather than published late, so downtime never dumps a backlog onto your feed.</span>
        </label>
    </div>

    <div class="field-row">
        <label class="field">
            <span class="field-label">Image retention (days)</span>
            <input type="number" name="image_retention_days" min="1" max="3650" value="<?= (int) ($settings['image_retention_days'] ?? 30) ?>">
            <span class="field-hint">Generated images are deleted this long after the post finishes.</span>
        </label>

        <label class="field">
            <span class="field-label">Notify before posting (minutes)</span>
            <input type="number" name="webhook_lead_minutes" min="0" max="10080" value="<?= (int) ($settings['webhook_lead_minutes'] ?? 30) ?>">
            <span class="field-hint">How far ahead of a slot the pre-post webhook fires.</span>
        </label>
    </div>

    <h2>Notifications</h2>

    <label class="field">
        <span class="field-label">Webhook URL</span>
        <input type="url" name="webhook_url" value="<?= e($settings['webhook_url'] ?? '') ?>" placeholder="https://discord.com/api/webhooks/...">
        <span class="field-hint">A JSON payload is POSTed here for upcoming, published and failed posts. Discord and Slack webhook URLs are detected automatically.</span>
    </label>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary" data-busy="Saving…">Save settings</button>
        <?php if (($settings['webhook_url'] ?? '') !== ''): ?>
            <button type="submit" formaction="/settings/webhook-test" class="btn" data-busy="Sending…">Send test notification</button>
        <?php endif; ?>
    </div>
</form>
