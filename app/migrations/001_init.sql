CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);

CREATE TABLE ai_providers (
    id                     INTEGER PRIMARY KEY AUTOINCREMENT,
    name                   TEXT NOT NULL,
    kind                   TEXT NOT NULL CHECK (kind IN ('text', 'image')),
    type                   TEXT NOT NULL CHECK (type IN ('openai', 'anthropic', 'openai_compatible')),
    base_url               TEXT,
    api_key_enc            TEXT NOT NULL,
    model                  TEXT NOT NULL,
    options_json           TEXT NOT NULL DEFAULT '{}',
    price_input_per_mtok   REAL NOT NULL DEFAULT 0,
    price_output_per_mtok  REAL NOT NULL DEFAULT 0,
    price_per_image        REAL NOT NULL DEFAULT 0,
    is_default             INTEGER NOT NULL DEFAULT 0,
    created_at             TEXT NOT NULL
);

CREATE INDEX idx_providers_kind ON ai_providers (kind, is_default);

CREATE TABLE instagram_accounts (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    name              TEXT NOT NULL,
    login_kind        TEXT NOT NULL CHECK (login_kind IN ('instagram', 'facebook')),
    ig_user_id        TEXT NOT NULL,
    page_id           TEXT,
    access_token_enc  TEXT NOT NULL,
    token_expires_at  TEXT,
    last_refreshed_at TEXT,
    last_check_error  TEXT,
    created_at        TEXT NOT NULL
);

CREATE TABLE templates (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    name                  TEXT NOT NULL,
    subject               TEXT NOT NULL,
    description           TEXT NOT NULL,
    style_prompt          TEXT,
    caption_rules         TEXT,
    text_provider_id      INTEGER REFERENCES ai_providers (id) ON DELETE SET NULL,
    image_provider_id     INTEGER REFERENCES ai_providers (id) ON DELETE SET NULL,
    image_count           INTEGER NOT NULL DEFAULT 1 CHECK (image_count BETWEEN 1 AND 10),
    instagram_account_id  INTEGER REFERENCES instagram_accounts (id) ON DELETE SET NULL,
    timezone              TEXT NOT NULL DEFAULT 'UTC',
    schedule_json         TEXT NOT NULL DEFAULT '{"times":[],"weekdays":[]}',
    is_default            INTEGER NOT NULL DEFAULT 0,
    is_active             INTEGER NOT NULL DEFAULT 0,
    created_at            TEXT NOT NULL
);

CREATE TABLE posts (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id          INTEGER REFERENCES templates (id) ON DELETE CASCADE,
    instagram_account_id INTEGER REFERENCES instagram_accounts (id) ON DELETE SET NULL,
    status               TEXT NOT NULL,
    scheduled_at         TEXT,
    caption              TEXT,
    images_json          TEXT NOT NULL DEFAULT '[]',
    ig_container_id      TEXT,
    ig_children_json     TEXT,
    ig_media_id          TEXT,
    attempts             INTEGER NOT NULL DEFAULT 0,
    next_attempt_at      TEXT,
    locked_at            TEXT,
    last_error           TEXT,
    notified_at          TEXT,
    published_at         TEXT,
    created_at           TEXT NOT NULL,
    updated_at           TEXT NOT NULL
);

CREATE UNIQUE INDEX idx_posts_slot ON posts (template_id, scheduled_at);
CREATE INDEX idx_posts_due ON posts (status, next_attempt_at);
CREATE INDEX idx_posts_scheduled ON posts (scheduled_at);

CREATE TABLE token_usage (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id         INTEGER REFERENCES posts (id) ON DELETE CASCADE,
    provider_id     INTEGER REFERENCES ai_providers (id) ON DELETE SET NULL,
    kind            TEXT NOT NULL,
    model           TEXT,
    input_tokens    INTEGER NOT NULL DEFAULT 0,
    output_tokens   INTEGER NOT NULL DEFAULT 0,
    image_count     INTEGER NOT NULL DEFAULT 0,
    unit_price_json TEXT NOT NULL DEFAULT '{}',
    cost_usd        REAL NOT NULL DEFAULT 0,
    estimated       INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT NOT NULL
);

CREATE INDEX idx_usage_created ON token_usage (created_at);
