# Instagram AI Auto-Poster

A self-hosted PHP app that generates Instagram posts (caption + image(s)) with AI on a
schedule and publishes them automatically via the official Instagram Graph API. Content
is driven by **templates** — a subject, a description, an image style, and a posting
schedule — so one account can run several distinct content styles, with one marked default
(a "couple therapy" relationship-facts template ships as an inactive example).

## Requirements

- Docker and Docker Compose
- An Instagram **Business or Creator** account
- A Meta developer app with either Instagram Login or Facebook Login configured (see below)
- API keys for whichever AI providers you want to use (OpenAI and/or Anthropic out of the box)
- **A public HTTPS URL that reaches this app.** Instagram's servers download generated
  images directly from your server before publishing — `docker compose up` on `localhost`
  alone is enough for generating/previewing posts, but publishing requires a real domain
  behind HTTPS (a reverse proxy, tunnel, or your own DNS + TLS). This is the single most
  common setup mistake; the dashboard will warn you if it's missing.

## Getting started

```bash
cp .env.example .env
docker run --rm dunglas/frankenphp php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
# paste the output as APP_KEY= in .env — this encrypts provider keys and IG tokens at rest
docker compose up --build -d
```

Open `http://localhost:8087/`, then in order:

1. **Settings** — set your public base URL (blank is fine until you're ready to publish),
   default timezone, and (optionally) a webhook URL for notifications.
2. **AI Providers** — add at least one Text provider and one Image provider, with API keys
   and per-token/per-image prices (used only for the app's own cost dashboard, not read
   from the provider). Use "Test connection" to confirm each one works.
3. **Instagram** — connect an account (see below for how to get a token).
4. **Templates** — edit the seeded "Couple Therapy" template or add your own: subject,
   description, image style, caption rules, providers, Instagram account, schedule
   (times + weekdays + timezone), then mark it Active.
5. **Posts** — "Generate now" to preview a post before it's ever scheduled; edit the
   caption, then "Publish now" or let the schedule pick it up.

A cron job inside the container runs `bin/scheduler.php` every minute, which materializes
upcoming slots from active templates, generates content ahead of time, sends the
pre-post webhook, and publishes posts once their scheduled time arrives.

## Connecting Instagram

Two Meta login paths work equally well — the app is host-agnostic per account. Pick
whichever you already have configured in the [Meta developer console](https://developers.facebook.com/apps).

| | Instagram Login | Facebook Login |
|---|---|---|
| Requires a linked Facebook Page | No | Yes |
| Token lifetime | 60 days, auto-refreshed by the scheduler | Does not expire |
| Scopes | `instagram_business_basic`, `instagram_business_content_publish` | `instagram_basic`, `instagram_content_publish`, `pages_read_engagement` |
| API host | `graph.instagram.com` | `graph.facebook.com` |
| Best for | Simpler setup, no Page needed | Set-and-forget — no re-auth risk if the box is offline for a while |

For either path you need: the account's **Instagram user ID** and a **long-lived access
token** with the scopes above. Meta's [Instagram API with Instagram
Login](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login)
and [Instagram API with Facebook
Login](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-facebook-login)
guides walk through generating both — the short-lived token from the standard OAuth flow
must be exchanged for a long-lived one before pasting it into **Instagram → Connect
account** here. For Facebook Login, also record the linked Page ID.

The scheduler refreshes Instagram Login tokens automatically starting 7 days before
expiry; if a container is stopped for more than ~60 days, an Instagram Login connection
will need to be re-authenticated by hand. Facebook Login connections don't have this
concern.

## AI providers

Text and image generation are configured independently, so you can mix providers (e.g.
Anthropic for captions, OpenAI for images). Built-in provider types:

- **OpenAI** — text via `/chat/completions`, images via `/images/generations`
  (`gpt-image-1`, requested at `1024x1024`/JPEG — the one size guaranteed inside
  Instagram's 4:5–1.91:1 aspect window, so images are never cropped)
- **Anthropic** — text via `/messages`
- **OpenAI-compatible** — either type with a custom base URL, for self-hosted or
  third-party OpenAI-API-compatible endpoints

Generated images are normalized (JPEG, sRGB, ≤1440px wide, ≤8MB) regardless of what the
provider returns, and rejected with a clear error if the aspect ratio is out of range
rather than being silently cropped.

Cost is computed locally from the price fields on each provider (no provider returns
dollar amounts), snapshotted per usage row so past costs don't move if you edit prices
later. Providers without token usage in their API response (most image providers other
than `gpt-image-1`) fall back to a flat per-image price, shown as "estimated" in the UI.

## Scheduling behavior

- Slots are materialized up to 48 hours ahead from each active template's schedule, then
  generated `generate_lead_minutes` (default 90) before they're due, leaving room for
  retries.
- Failed generation/publishing retries with exponential backoff (up to 5 attempts) for
  transient errors; permission/token errors fail immediately without retrying.
- If a post's scheduled time passes by more than `missed_slot_grace_minutes` (default 60)
  before it publishes — e.g. after container downtime — it's marked **skipped** rather
  than published late, so an outage never dumps a burst of stale posts onto the account.
- The unique `(template_id, scheduled_at)` slot key makes re-running the scheduler safe;
  it never creates duplicate posts for the same slot.

## Environment variables

| Variable | Purpose |
|---|---|
| `APP_KEY` | Base64-encoded 32-byte key encrypting provider keys and IG tokens at rest. Required. |
| `APP_DEBUG` | `1` shows full error details in the browser; leave `0` in production. |
| `DB_PATH` | SQLite database path inside the container (default `/data/app.sqlite`). |

## Data & backups

Everything persists in two Docker volumes: `insta_data` (the SQLite database, at
`/data/app.sqlite`) and `insta_images` (generated images). Back up `insta_data` — it holds
your templates, post history, encrypted provider keys, and encrypted Instagram tokens; a
missing `APP_KEY` makes those unrecoverable, so back it up alongside `.env`.

## Development

`compose.override.yml` bind-mounts source directories (`src/`, `templates/`, `bin/`,
`migrations/`, `public/assets`) for live editing without a rebuild; `vendor/` stays baked
into the image (a plain `./app:/app` mount would shadow it — this is deliberate).
Run `docker compose up --build` after changing `composer.json`.

```bash
docker compose logs -f php          # app + cron output
docker compose exec php php bin/scheduler.php   # run one scheduler tick manually
docker compose exec php php bin/migrate.php     # apply new migrations
```

## Architecture

```
app/
  public/            Front controller, static assets, generated images (storage/images)
  src/
    Ai/              Text/image provider interfaces, OpenAI & Anthropic clients, pricing
    Content/         Templates, posts, the generation pipeline, image normalization
    Instagram/       Graph API client (containers, carousels, publish, token refresh)
    Scheduling/       DST-safe schedule rules, the per-minute scheduler tick
    Notify/          Webhook notifications
    Usage/           Token/cost tracking
    Http/Controllers/ Route handlers
    Support/         DB, HTTP client, encryption, settings — shared helpers
  templates/         Plain PHP views (no template engine dependency)
  migrations/        Hand-rolled SQL migrations
  bin/               CLI entrypoints (scheduler, migrate, key generation)
docker/              crontab, container entrypoint
```

No ORM, no JS build step, no Node in the runtime image — Slim Framework is the only
framework dependency, views are plain PHP includes, and the calendar/forms use
hand-written vanilla JS.
