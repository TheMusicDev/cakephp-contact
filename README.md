# TheMusicDev/Contact

Contact-form intake for CakePHP 5 sites: an `inquiries` table and one seam, `ContactIntake::intake($request)`, that
validates a submission, flags spam (honeypot field and a too-fast-to-be-human timer), captures where it came from,
and saves it. The plugin owns the data and the rules; **your site owns the contact page, the response, and the admin
screens**.

Requires PHP 8.2+ and CakePHP 5.2+. Status: 1.0.0. Design and decisions: [`docs/contact-plugin-design.md`](docs/contact-plugin-design.md).

## Install

```bash
composer require themusicdev/contact
bin/cake plugin load TheMusicDev/Contact
bin/cake migrations migrate -p TheMusicDev/Contact
```

(`cakephp/migrations` runs the migration; it is a dev dependency of this repo, so require it in your app if you do
not already have it.)

## Use

In your controller action for the contact form's POST:

```php
use TheMusicDev\Contact\Lib\ContactIntake;

$result = ContactIntake::intake($this->getRequest());
// $result = ['status' => 'ok'|'spam'|'rejected'|'invalid', 'entity' => Inquiry|null, 'errors' => [field => message]]
```

| Status | Meaning | Saved? | Respond with |
|---|---|---|---|
| `ok` | a normal submission | yes, `status = new` | success |
| `spam` | honeypot filled, or submitted faster than `min_submit_ms` | yes, `status = spam` | **the same success** (never tip bots off) |
| `rejected` | `name`, `email` or `message` missing or blank | no | 400 |
| `invalid` | a length cap or the email format failed | no | 400, `errors` is a flat field => message map |

Request body fields (JSON or form): `name`, `email`, `message` (required); `phone`, `subject`, `landingPage`
(optional); `renderedAt` (ms since epoch when the form was rendered); the honeypot field. Anything else is ignored.
IP address, user agent, referrer and locale are taken from the request headers, never from the body.

The table alias is `TheMusicDev/Contact.Inquiries`; the triage statuses are `Inquiry::STATUSES`
(`new`, `read`, `spam`, `archived`).

## Examples: your site hosts its own pages

The plugin ships **no routes, no templates and no admin UI**. Layout, branding and authentication belong to the
site, so each site hosts its own contact page and its own admin screens. [`examples/`](examples/) has a contact
controller action, a form with the honeypot and the timer, and an admin triage list with a status flip. They are
examples to copy, clearly marked, not autoloaded, and not covered by the plugin's tests.

## Configure (host `config/app.php`, optional)

```php
'Contact' => [
    'honeypot_field' => 'company', // name of the hidden input bots fill
    'min_submit_ms' => 500,        // faster than this since renderedAt = spam
],
```

Host values win over the plugin's `config/app_default.php`.

## Gotchas

- **Send `renderedAt` and the honeypot input from your form** (see `examples/templates/Contact/index.php`). Without
  `renderedAt` the timing check is skipped; a value outside 0..1 hour old is treated as missing.
- **Both statuses `ok` and `spam` must get the same response**, or bots learn they were caught.
- **The client IP is the first `X-Forwarded-For` hop**, so it is only right behind a proxy/CDN you trust. Without
  the header it falls back to the request's own address.
- **No `modified` column**: only `created` is stamped. Update a status with `patchEntity(..., ['validate' => false])`
  because the validator requires name/email/message on every save.
- **No emails, no rate limiting** by design; send a notification from your own controller after `intake()` returns,
  and rate-limit at your proxy if you need it.
- **Adopting from an app-owned `inquiries` migration:** `cake_migrations` keys rows by `(version, plugin)`. Re-tag the
  existing row before migrating, or the plugin migration tries to create the table again:
  `UPDATE cake_migrations SET plugin='TheMusicDev/Contact' WHERE version='20260929120000' AND migration_name='CreateInquiries' AND plugin IS NULL;`
- After adding the plugin's migration to a running app, run `bin/cake schema_cache clear`.

## Development

```bash
docker compose up -d --wait dbtest   # MariaDB on port 3310, database contact_test
composer install
composer check                       # phpunit + phpcs + phpstan
```

Tests run on MariaDB, never sqlite. Override the connection with `DATABASE_TEST_URL`. Conventions for all
TheMusicDev plugins: `TheMusicDev/cakephp-conventions`. License: MIT.
