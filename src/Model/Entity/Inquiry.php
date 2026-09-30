<?php
declare(strict_types=1);

namespace TheMusicDev\Contact\Model\Entity;

use Cake\ORM\Entity;

/**
 * A contact-form submission (port of the monorepo `inquiries` table). Spam
 * flags live in `status` / `honeypot_value` / `submit_duration_ms`; see
 * Lib\ContactIntake for how they are set.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $subject
 * @property string $message
 * @property string $status
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $referrer
 * @property string|null $landing_page
 * @property string|null $locale
 * @property string|null $honeypot_value
 * @property int|null $submit_duration_ms
 * @property \Cake\I18n\DateTime $created
 */
final class Inquiry extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = ['*' => true];

    /** @var list<string> */
    public const STATUSES = ['new', 'read', 'spam', 'archived'];
}