# TheMusicDev/Contact — plugin design

Contact-form intake for CakePHP sites: one `inquiries` table, one
`ContactIntake` seam, no routes, no UI. It began as app code on the first
TheMusicDev site and was extracted into this reusable plugin — the "domain owns
the rules, host owns the markup" split.

**Status:** released as 1.0.0 (2026-10-03). Public API: `Lib\ContactIntake::intake()`,
the `TheMusicDev/Contact.Inquiries` table and `Inquiry::STATUSES`, the `Contact.*` config keys.

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

Table name is `inquiries`. Entity keeps
`STATUSES` const (`new`, `read`, `spam`, `archived`) — the admin allowlist
source. Validator has field caps (name/email 100, phone 30,
subject 200, message 5000, email format) — oversized payloads are a scraper
signal and never become rows. TimestampBehavior writes `created` only (the
schema has no `modified`; `update` stamping would break status-flip saves).

### `Lib/ContactIntake` — the seam

Static `ContactIntake::intake(ServerRequest $request)` — one call, the
whole intake. Returns

```php
array{status: 'ok'|'spam'|'rejected'|'invalid',
      entity: Inquiry|null,
      errors: array<string, string>}
```

- `rejected` — required fields (name/email/message) missing or blank.
  Answer 400; nothing saved.
- `spam` — honeypot filled or submit under `Contact.min_submit_ms`. Row IS
  saved (`status='spam'`, validated), caller answers success. Identical
  response either way — never tip bots off.
- `invalid` — validator failure (caps, email format). Answer 400.
- `ok` — saved as `new`.

Also owns:
- **Field whitelist** — only recognized keys become row
  fields; anything else in the payload is ignored.
- **Submit clock** (`renderedAt`, ms since epoch): delta outside 0..1h is
  treated as missing (int32 column + clock-skew garbage), else recorded as
  `submit_duration_ms`.
- **Provenance server-side**: `ip_address` (X-Forwarded-For first hop — a host
  behind a CDN/proxy only sees the proxy otherwise; falls back to request clientIp), `user_agent`,
  `referrer`, `locale` (Accept-Language), `landing_page`.

Host side stays three lines of glue: call `intake()`, map the four statuses
to its own JSON contract (`{"ok":true}` / `{"error":...}`), keep POST on the
host route (CSRF middleware owns it).

### Config (IdentityBridge)

`config/app_default.php` contributes only:

```php
'Contact' => [
    'honeypot_field' => 'company', // per-site honeypot input name
    'min_submit_ms' => 500,        // bot-signal floor
],
```

Nothing else earns a knob. Hosts override the honeypot name in
their `config/app.php` if their form calls the field something else.

### Migration

`config/Migrations/20260929120000_CreateInquiries.php` creates `inquiries`.
**Ledger gotcha:** `cake_migrations` keys rows by `(version, plugin)`, not by
name alone. A site that first ran an app-owned copy of this migration has a
ledger row with `plugin = NULL`; after adopting the plugin the plugin migration
shows `down` and the app row `MISSING`. Re-tag it on every such DB BEFORE
`migrations migrate`:
`UPDATE cake_migrations SET plugin='TheMusicDev/Contact' WHERE version='20260929120000' AND migration_name='CreateInquiries' AND plugin IS NULL;`
Fresh DBs just create the table. No `hasTable` guard — it would mask real
schema drift.

## Host side

See `examples/` for a contact action, form, and admin triage list/status flip
to copy. The host owns the route, the response contract, the admin screens
and their auth.
