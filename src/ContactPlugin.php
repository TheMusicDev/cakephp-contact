<?php
declare(strict_types=1);

namespace TheMusicDev\Contact;

use Cake\Core\BasePlugin;

/**
 * Contact plugin = domain only (inquiries table + intake logic + config).
 * No shipped routes and no admin UI: the public form and the admin triage
 * screen are host-side (docs/contact-plugin-design.md — plugin = domain,
 * markup = host).
 */
final class ContactPlugin extends BasePlugin
{
}