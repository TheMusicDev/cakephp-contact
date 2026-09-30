# TheMusicDev/Contact — plugin design

Contact-form intake for TheMusicDev sites: one `inquiries` table, one
`ContactIntake` seam, no routes, no UI. Port of the monorepo's
Cloudflare-Worker `contact-form` + its `inquiries` drizzle table, extracted
from the cakephp-tmd app (which first built it as app code, migration
20260929120000) into a reusable plugin — the same "domain owns the rules,
host owns the markup" split Recruiting established.

## Why a plugin

Every agency site wants a contact form that persists and triages. The rules
(honeypot, submit timing, length caps, provenance capture, spam-as-status)
are identical everywhere; only the markup, field labels, and JSON contract
shape change. So: plugin owns the rules + the table; the host owns the form
HTML/JS and the HTTP response.

## Not in scope

- **No emails.** Inquiries are triage-list only (read in the admin). If a
  site later wants notification mail, it is a host-side Queue.Mailer after
  the intake returns — the plugin returns the saved row; mail is the host's
  UX decision.
- **No trash behavior.** Inquiries are disposable triage material; a hard
  delete is what the list wants when the list is emptied. Recruiting's
  trash exists because applications carry files. (Revisit if a purge-happy
  site ever regrets it.)
- **No plugin-side admin UI.** Same rule as Recruiting: branding/markup
  stays in the app. App-side controller + template consume the table.
- **No rate limiting.** Same call as ApplyIntake: shared hosts have no good
  IP truth and batch senders are legitimate.

## Pieces

### `Model/Entity/Inquiry` + `Model/Table/InquiriesTable`

Moved 1:1 from the app. Table name stays `inquiries` (no data migration —
the table already exists on every deployed DB, see below). Entity keeps
`STATUSES` const (`new`, `read`, `spam`, `archived`) — the admin allowlist
source. Validator keeps the worker's field caps (name/email 100, phone 30,
subject 200, message 5000, email format) — oversized payloads are a scraper
signal and never become rows. TimestampBehavior writes `created` only (the
schema has no `modified`; `update` stamping would break status-flip saves).

### `Lib/ContactIntake` — the seam

Static `ContactIntake::intake(ServerRequest $request)` — one call, the
whole worker. Returns

```php
array{status: 'ok'|'spam'|'rejected'|'invalid',
      entity: Inquiry|null,
      errors: array<string, string>}
```

- `rejected` — required fields (name/email/message) missing or blank.
  Worker's 400 path; nothing saved.
- `spam` — honeypot filled or submit under `Contact.min_submit_ms`. Row IS
  saved (`status='spam'`, validated), caller answers success. Identical
  response either way — never tip bots off.
- `invalid` — validator failure (caps, email format). Worker's 400 path.
- `ok` — saved as `new`.

Also owns, ported from the app's `PagesController::contact()`:
- **Field whitelist 1:1 with the worker** — only recognized keys become row
  fields; anything else in the payload is ignored.
- **Submit clock** (`renderedAt`, ms since epoch): delta outside 0..1h is
  treated as missing (int32 column + clock-skew garbage), else recorded as
  `submit_duration_ms`.
- **Provenance server-side**: `ip_address` (X-Forwarded-For first hop — the
  shared host sits behind a CDN/proxy, the worker trusted
  CF-Connecting-IP; falls back to request clientIp), `user_agent`,
  `referrer`, `locale` (Accept-Language), `landing_page`.

Host side stays three lines of glue: call `intake()`, map the four statuses
to its own JSON contract (`{"ok":true}` / `{"error":...}`), keep POST on the
host route (CSRF middleware owns it).

### Config (IdentityBridge)

`config/app_default.php` contributes only:

```php
'Contact' => [
    'honeypot_field' => 'company', // per-site honeypot input name
    'min_submit_ms' => 500,        // worker's threshold
],
```

Nothing else earns a knob (ponytail). Hosts override the honeypot name in
their `config/app.php` if their form calls the field something else.

### Migration

`config/Migrations/20260929120000_CreateInquiries.php` — same filename and
schema as the app migration it replaces. **Ledger gotcha (build-time
correction):** `cake_migrations` keys rows by `(version, plugin)`, not by
name alone — the app copy was recorded with `plugin = NULL`, so after the
move the plugin migration shows `down` and the app row `MISSING`. Existing
DBs (dev + dbtest) need a one-time ledger fix:
`UPDATE cake_migrations SET plugin='TheMusicDev/Contact' WHERE version='20260929120000' AND migration_name='CreateInquiries' AND plugin IS NULL;`
(run on every DB that already ran the app version, incl. prod, BEFORE
`migrations migrate`). Fresh DBs just create the table. No `hasTable` guard —
it would mask real schema drift.

## Host wiring (cakephp-tmd as first consumer)

- `config/plugins.php`: `'TheMusicDev/Contact' => []`.
- `composer.json`: path repo `plugins/TheMusicDev/Contact` + require
  `themusicdev/cakephp-contact` — after `composer update` for the path repo.
- `PagesController::contact()` shrinks to render + intake + JSON mapping.
- Admin: `fetchTable('Inquiries')` → `fetchTable('TheMusicDev/Contact.Inquiries')`;
  `templates/Admin/inquiries.php` docblock/`Inquiry::STATUSES` refs go to
  the plugin FQCN.
- Tests: `AdminControllerTest` + `PagesControllerTest` keep their behaviors
  (they exercise the E2E surface), rebased on the plugin classes.

## Sequencing / deploy notes

1. Build plugin + rewire, `composer check` green.
2. `rm config/Migrations/20260929120000_CreateInquiries.php` only after the
   plugin file exists then run the ledger UPDATE above on each existing DB. Confirm with
   `bin/cake migrations status` and `-p TheMusicDev/Contact` (both clean).
3. On any other site that adopts the plugin: `bin/cake migrations migrate` —
   plugin migrations load from the plugin path automatically.