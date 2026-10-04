<?php
declare(strict_types=1);

namespace TheMusicDev\Contact\Test\TestCase\Lib;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use TheMusicDev\Contact\Lib\ContactIntake;
use TheMusicDev\Contact\Model\Entity\Inquiry;

/**
 * The intake rules: honeypot, submit timing, validation caps, provenance, and what is saved for each status.
 */
final class ContactIntakeTest extends TestCase
{
    private Table $inquiries;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->inquiries = TableRegistry::getTableLocator()->get('TheMusicDev/Contact.Inquiries');
        $this->inquiries->deleteAll([]);
        Configure::write('Contact', ['honeypot_field' => 'company', 'min_submit_ms' => 500]);
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): void
    {
        $this->inquiries->deleteAll([]);
        TableRegistry::getTableLocator()->clear();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private function request(array $body, array $headers = []): ServerRequest
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return new ServerRequest(['post' => $body, 'environment' => $server]);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function human(array $extra = []): array
    {
        return $extra + [
            'name' => 'Angela Test',
            'email' => 'angela@example.com',
            'message' => 'Need a site for our studio.',
            // A minute before submit clears the 500 ms bot threshold.
            'renderedAt' => (int)(microtime(true) * 1000) - 60_000,
        ];
    }

    private function row(): Inquiry
    {
        /** @var \TheMusicDev\Contact\Model\Entity\Inquiry|null $row */
        $row = $this->inquiries->find()->first();
        $this->assertNotNull($row, 'a row should exist');

        return $row;
    }

    public function testAHumanSubmissionIsSavedAsNew(): void
    {
        $result = ContactIntake::intake($this->request($this->human([
            'phone' => '+1 555',
            'subject' => 'Studio site',
            'landingPage' => '/contact',
        ])));

        $this->assertSame('ok', $result['status']);
        $this->assertInstanceOf(Inquiry::class, $result['entity']);
        $this->assertSame([], $result['errors']);
        $row = $this->row();
        $this->assertSame('new', $row->status);
        $this->assertSame('Angela Test', $row->name);
        $this->assertSame('+1 555', $row->phone);
        $this->assertSame('Studio site', $row->subject);
        $this->assertSame('/contact', $row->landing_page);
        $this->assertNull($row->honeypot_value);
        $this->assertNotNull($row->submit_duration_ms);
        $this->assertNotNull($row->created);
    }

    public function testAFilledHoneypotIsSavedAsSpam(): void
    {
        $result = ContactIntake::intake($this->request($this->human(['company' => 'Acme Bot Co'])));

        $this->assertSame('spam', $result['status']);
        $row = $this->row();
        $this->assertSame('spam', $row->status);
        $this->assertSame('Acme Bot Co', $row->honeypot_value);
    }

    public function testTheHoneypotFieldNameComesFromConfig(): void
    {
        Configure::write('Contact.honeypot_field', 'website');

        // The default name is now just an unknown field and is ignored.
        $this->assertSame('ok', ContactIntake::intake($this->request($this->human(['company' => 'x'])))['status']);
        $this->inquiries->deleteAll([]);

        $this->assertSame('spam', ContactIntake::intake($this->request($this->human(['website' => 'x'])))['status']);
        $this->assertSame('x', $this->row()->honeypot_value);
    }

    public function testAFastSubmitIsSavedAsSpam(): void
    {
        $result = ContactIntake::intake($this->request($this->human([
            'renderedAt' => (int)(microtime(true) * 1000) - 100,
        ])));

        $this->assertSame('spam', $result['status']);
        $row = $this->row();
        $this->assertSame('spam', $row->status);
        $this->assertNotNull($row->submit_duration_ms);
        $this->assertLessThan(500, $row->submit_duration_ms);
    }

    public function testTheMinimumSubmitTimeComesFromConfig(): void
    {
        Configure::write('Contact.min_submit_ms', 0);

        $result = ContactIntake::intake($this->request($this->human([
            'renderedAt' => (int)(microtime(true) * 1000) - 100,
        ])));

        $this->assertSame('ok', $result['status']);
    }

    public function testAMissingOrNonsenseClockIsStoredAsNullAndNotSpam(): void
    {
        $tooOld = (int)(microtime(true) * 1000) - 2 * 3_600_000;
        $inFuture = (int)(microtime(true) * 1000) + 60_000;
        foreach ([null, '', 'abc', 0, -5, $tooOld, $inFuture] as $clock) {
            $body = $this->human();
            unset($body['renderedAt']);
            if ($clock !== null) {
                $body['renderedAt'] = $clock;
            }
            $this->inquiries->deleteAll([]);

            $result = ContactIntake::intake($this->request($body));

            $this->assertSame('ok', $result['status'], 'clock: ' . var_export($clock, true));
            $this->assertNull($this->row()->submit_duration_ms, 'clock: ' . var_export($clock, true));
        }
    }

    public function testMissingRequiredFieldsAreRejectedAndNothingIsSaved(): void
    {
        foreach (['name', 'email', 'message'] as $missing) {
            $body = $this->human();
            unset($body[$missing]);
            $result = ContactIntake::intake($this->request($body));
            $this->assertSame('rejected', $result['status'], $missing);
            $this->assertNull($result['entity']);
        }
        // Blank and non-string values count as missing too.
        $this->assertSame('rejected', ContactIntake::intake($this->request($this->human(['name' => '   '])))['status']);
        $this->assertSame('rejected', ContactIntake::intake($this->request($this->human(['email' => ['a']])))['status']);
        $this->assertSame(0, $this->inquiries->find()->count());
    }

    public function testBadEmailAndOversizedFieldsAreInvalidAndNothingIsSaved(): void
    {
        $cases = [
            'email' => ['email' => 'not-an-email'],
            'message' => ['message' => str_repeat('x', 5001)],
            'name' => ['name' => str_repeat('n', 101)],
            'phone' => ['phone' => str_repeat('1', 31)],
            'subject' => ['subject' => str_repeat('s', 201)],
        ];
        foreach ($cases as $field => $override) {
            $result = ContactIntake::intake($this->request($this->human($override)));
            $this->assertSame('invalid', $result['status'], $field);
            $this->assertNull($result['entity']);
            $this->assertArrayHasKey($field, $result['errors']);
        }
        $this->assertSame(0, $this->inquiries->find()->count());
    }

    public function testASpamRowStillHasToPassValidation(): void
    {
        $result = ContactIntake::intake($this->request($this->human([
            'company' => 'Bot',
            'message' => str_repeat('x', 5001),
        ])));

        $this->assertSame('invalid', $result['status']);
        $this->assertSame(0, $this->inquiries->find()->count());
    }

    public function testMaximumLengthsAreAccepted(): void
    {
        $result = ContactIntake::intake($this->request($this->human([
            'name' => str_repeat('n', 100),
            'message' => str_repeat('x', 5000),
            'phone' => str_repeat('1', 30),
            'subject' => str_repeat('s', 200),
        ])));

        $this->assertSame('ok', $result['status']);
    }

    public function testInputIsTrimmedAndUnknownFieldsAreIgnored(): void
    {
        $result = ContactIntake::intake($this->request($this->human([
            'name' => '  Angela  ',
            'email' => ' angela@example.com ',
            'status' => 'archived',
            'id' => 99,
            'ip_address' => '6.6.6.6',
        ])));

        $this->assertSame('ok', $result['status']);
        $row = $this->row();
        $this->assertSame('Angela', $row->name);
        $this->assertSame('angela@example.com', $row->email);
        $this->assertSame('new', $row->status, 'status is set server-side');
        $this->assertNotSame(99, $row->id);
        $this->assertNotSame('6.6.6.6', $row->ip_address, 'provenance is never taken from the form');
    }

    public function testProvenanceIsCapturedFromTheRequest(): void
    {
        ContactIntake::intake($this->request($this->human(), [
            'X-Forwarded-For' => '203.0.113.9, 10.0.0.1',
            'User-Agent' => 'TestAgent/1.0',
            'Referer' => 'https://example.com/from',
            'Accept-Language' => 'en-US,en;q=0.9',
        ]));

        $row = $this->row();
        $this->assertSame('203.0.113.9', $row->ip_address);
        $this->assertSame('TestAgent/1.0', $row->user_agent);
        $this->assertSame('https://example.com/from', $row->referrer);
        $this->assertSame('en-US,en;q=0.9', $row->locale);
    }

    public function testNoProxyHeaderMeansNoForwardedIp(): void
    {
        ContactIntake::intake($this->request($this->human()));

        $this->assertNull($this->row()->ip_address);
    }
}
