<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Contact-form inquiries — port of the monorepo's `inquiries` drizzle table
 * (packages/database/src/schema.ts) + the Cloudflare Worker's write shape.
 * Status is set server-side: 'new' for humans, 'spam' when the honeypot or
 * submit-timing check flags the request.
 *
 * Moved here from the app (config/Migrations) verbatim. DBs that already ran
 * the app version must re-tag the ledger row (plugin column) — see
 * docs/contact-plugin-design.md; fresh DBs create the table.
 */
final class CreateInquiries extends BaseMigration
{
    public function change(): void
    {
        $this->table('inquiries')
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('phone', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('subject', 'string', ['limit' => 200, 'null' => true])
            ->addColumn('message', 'text', ['null' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'null' => false, 'default' => 'new'])
            ->addColumn('ip_address', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('user_agent', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('referrer', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('landing_page', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('locale', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('honeypot_value', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('submit_duration_ms', 'integer', ['null' => true])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addIndex(['status'])
            ->addIndex(['created'])
            ->create();
    }
}