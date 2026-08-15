INSERT INTO settings (key, value) VALUES
    ('public_base_url', ''),
    ('default_timezone', 'UTC'),
    ('generate_lead_minutes', '90'),
    ('missed_slot_grace_minutes', '60'),
    ('image_retention_days', '30'),
    ('webhook_url', ''),
    ('webhook_lead_minutes', '30');

INSERT INTO templates (
    name, subject, description, style_prompt, caption_rules,
    image_count, timezone, schedule_json, is_default, is_active, created_at
) VALUES (
    'Couple Therapy',
    'Couple therapy and relationship psychology',
    'Short, evidence-informed facts about healthy relationships: communication patterns, conflict repair, attachment styles, and emotional regulation. Warm and non-judgemental, never prescriptive or clinical.',
    'Calm minimalist illustration, soft muted palette, generous negative space, no text rendered in the image, no logos, no watermarks.',
    'Open with a single hooking sentence. Keep under 600 characters. End with one gentle reflective question. Use at most 8 relevant hashtags on the last line.',
    1,
    'UTC',
    '{"times":["09:00"],"weekdays":[1,2,3,4,5]}',
    1,
    0,
    datetime('now')
);
