<?php
declare(strict_types=1);

/**
 * TheMusicDev/Contact ship-with defaults. Merged UNDER host values by the
 * plugin's config/bootstrap.php — hosts win, per the IdentityBridge
 * convention (cakephp-conventions: plugins.md).
 *
 * Only two knobs exist because real sites vary them: the honeypot input
 * name and the bot-signal submit floor. Nothing else is per-site.
 */
return [
    'Contact' => [
        'honeypot_field' => 'company',
        'min_submit_ms' => 500,
    ],
];