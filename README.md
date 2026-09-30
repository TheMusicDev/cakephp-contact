# TheMusicDev/Contact

Contact-form intake for TheMusicDev CakePHP sites: the `inquiries` table and
one seam, `Lib\ContactIntake::intake($request)`, that owns the worker-ported
spam rules. Plugin = domain; the public route, form markup, JSON response and
admin triage UI live in the host. Rationale: `docs/contact-plugin-design.md`.

## Install

1. composer path repo + `require themusicdev/cakephp-contact ^1.0`
2. `config/plugins.php`: `'TheMusicDev/Contact' => []`
3. `bin/cake migrations migrate -p TheMusicDev/Contact`

## Use

```php
$result = \TheMusicDev\Contact\Lib\ContactIntake::intake($this->getRequest());
// $result['status']: 'ok' | 'spam' | 'rejected' | 'invalid'
```

- `ok` / `spam` → answer the same success body (never tip bots off). Spam rows
  are saved with `status = 'spam'` for triage.
- `rejected` (name/email/message missing) / `invalid` (validator caps, email
  format) → 400, nothing saved; `$result['errors']` is a flat field → message map.

Table alias: `TheMusicDev/Contact.Inquiries`. Statuses:
`Inquiry::STATUSES` (`new`, `read`, `spam`, `archived`).

## Configure (host `config/app.php`, optional)

```php
'Contact' => ['honeypot_field' => 'company', 'min_submit_ms' => 500],
```

Host values win over `config/app_default.php`.

## Gotchas

- **Adopting from an app-owned `inquiries` migration:** `cake_migrations` keys
  rows by `(version, plugin)`. Re-tag the existing row before migrating:
  `UPDATE cake_migrations SET plugin='TheMusicDev/Contact' WHERE version='20260929120000' AND migration_name='CreateInquiries' AND plugin IS NULL;`
- The form's JS must send `renderedAt` (ms since epoch) and the honeypot field;
  deltas outside 0..1h are stored as null.
- IP comes from the first `X-Forwarded-For` hop — only correct behind a proxy/CDN you trust.
- No `modified` column: the Timestamp behavior stamps `created` only.
- Admin updates via `patchEntity` need `['validate' => false]` (requirePresence trips on partial data).
