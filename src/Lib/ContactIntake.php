<?php
declare(strict_types=1);

namespace TheMusicDev\Contact\Lib;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;
use TheMusicDev\Contact\Model\Entity\Inquiry;

/**
 * Contact intake — the plugin's domain seam for the public contact form
 * (host route, usually POST /contact, JSON or form body). Owns every rule
 * the monorepo's Cloudflare Worker pinned:
 *
 * - field whitelist 1:1 — only recognized keys become row fields; anything
 *   else in the payload is ignored.
 * - honeypot (field name from `Contact.honeypot_field`, default `company`)
 *   filled → row SAVED as 'spam', caller answers success. Same response
 *   whether flagged or not — never tip bots off.
 * - submit clock (`renderedAt`, ms since epoch): delta outside 0..1h is
 *   treated as missing (int32 column + clock-skew garbage); deltas under
 *   `Contact.min_submit_ms` flag the row spam, still saved.
 * - provenance captured server-side, never from the form: ip (XFF first
 *   hop — the shared host sits behind a CDN/proxy), user agent, referrer,
 *   locale, landing page.
 *
 * Unlike ApplyIntake (rejects bot clocks outright) the contact form keeps
 * triage material: spam rows persist with status='spam'.
 */
final class ContactIntake
{
    /** Ceiling on the submit clock — 1 hour */
    private const WINDOW_MS = 3_600_000;

    /**
     * Process one submission.
     *
     * @return array{status: string, entity: Inquiry|null, errors: array<string, string>}
     *     status: 'ok' (saved, new) | 'spam' (saved as spam) | 'rejected'
     *     (required fields missing, nothing saved) | 'invalid' (validation
     *     failure, nothing saved)
     */
    public static function intake(ServerRequest $request): array
    {
        $data = (array)$request->getParsedBody();
        $isNonEmptyString = static fn (mixed $value): bool => is_string($value) && trim($value) !== '';

        $honeypotField = self::honeypotField();
        $invalid = static fn (array $errors): array =>
            ['status' => 'invalid', 'entity' => null, 'errors' => $errors];

        // Whitelist shape first, like the worker — required trio present.
        if (
            !$isNonEmptyString($data['name'] ?? null)
            || !$isNonEmptyString($data['email'] ?? null)
            || !$isNonEmptyString($data['message'] ?? null)
        ) {
            return ['status' => 'rejected', 'entity' => null, 'errors' => []];
        }

        $honeypotValue = $isNonEmptyString($data[$honeypotField] ?? null)
            ? trim((string)$data[$honeypotField]) : null;
        $durationMs = static::submitDurationMs((string)($data['renderedAt'] ?? ''));
        $isSpam = $honeypotValue !== null
            || ($durationMs !== null && $durationMs < self::minSubmitMs());

        $inquiries = TableRegistry::getTableLocator()->get('TheMusicDev/Contact.Inquiries');
        $entity = $inquiries->newEntity([
            'name' => trim((string)$data['name']),
            'email' => trim((string)$data['email']),
            'phone' => $isNonEmptyString($data['phone'] ?? null) ? trim((string)$data['phone']) : null,
            'subject' => $isNonEmptyString($data['subject'] ?? null) ? trim((string)$data['subject']) : null,
            'message' => (string)$data['message'],
            'landing_page' => $isNonEmptyString($data['landingPage'] ?? null) ? (string)$data['landingPage'] : null,
            'ip_address' => static::clientIp($request),
            'user_agent' => mb_substr($request->getHeaderLine('User-Agent'), 0, 255) ?: null,
            'referrer' => mb_substr($request->getHeaderLine('Referer'), 0, 255) ?: null,
            'locale' => mb_substr($request->getHeaderLine('Accept-Language'), 0, 100) ?: null,
            'honeypot_value' => $honeypotValue,
            'submit_duration_ms' => $durationMs,
        ]);

        // Spam rows keep the same field validation — bot garbage with valid
        // fields is triage material, bot garbage that fails a cap is 400.
        if ($entity->getErrors() !== []) {
            return $invalid(static::flattenErrors($entity));
        }

        $entity->status = $isSpam ? 'spam' : 'new';
        $saved = $inquiries->save($entity); // never false: errors are checked above

        return [
            'status' => $isSpam ? 'spam' : 'ok',
            'entity' => $entity,
            'errors' => [],
        ];
    }

    /** honeypot field name (per-site, default 'company'). */
    private static function honeypotField(): string
    {
        return (string)Configure::read('Contact.honeypot_field', 'company');
    }

    /** bot-signal floor on the submit clock (worker's 500ms). */
    private static function minSubmitMs(): int
    {
        return (int)Configure::read('Contact.min_submit_ms', 500);
    }

    /**
     * The worker trusted any renderedAt; the DB column is int32, so deltas
     * outside 0..1h store null instead of overflowing.
     */
    private static function submitDurationMs(string $raw): ?int
    {
        if (!is_numeric($raw) || (int)$raw <= 0) {
            return null;
        }
        $delta = (int)(microtime(true) * 1000) - (int)$raw;

        return ($delta >= 0 && $delta <= self::WINDOW_MS) ? $delta : null;
    }

    /**
     * Visitor IP: first X-Forwarded-For hop, else the request's own client
     * address (null when tests/CLI have neither).
     */
    private static function clientIp(ServerRequest $request): ?string
    {
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);

            return $first !== '' ? $first : null;
        }

        return (string)$request->clientIp() ?: null;
    }

    /**
     * Entity errors → flat field→message map for the caller's response.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @return array<string, string>
     */
    private static function flattenErrors(\Cake\Datasource\EntityInterface $entity): array
    {
        $flat = [];
        foreach ($entity->getErrors() as $field => $messages) {
            foreach (array_filter($messages) as $message) {
                $flat[(string)$field] = (string)$message;
                break;
            }
        }

        return $flat;
    }
}