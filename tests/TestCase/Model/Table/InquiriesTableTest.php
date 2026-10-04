<?php
declare(strict_types=1);

namespace TheMusicDev\Contact\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use TheMusicDev\Contact\Model\Entity\Inquiry;
use TheMusicDev\Contact\Model\Table\InquiriesTable;

/**
 * The inquiries table: validation caps, the created-only timestamp, the status list.
 */
final class InquiriesTableTest extends TestCase
{
    private InquiriesTable $inquiries;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();
        /** @var \TheMusicDev\Contact\Model\Table\InquiriesTable $table */
        $table = TableRegistry::getTableLocator()->get('TheMusicDev/Contact.Inquiries');
        $this->inquiries = $table;
        $this->inquiries->deleteAll([]);
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

    public function testTheStatusListIsTheTriageAllowlist(): void
    {
        $this->assertSame(['new', 'read', 'spam', 'archived'], Inquiry::STATUSES);
    }

    public function testRequiredFieldsAreValidated(): void
    {
        $entity = $this->inquiries->newEntity([]);

        $this->assertArrayHasKey('name', $entity->getErrors());
        $this->assertArrayHasKey('email', $entity->getErrors());
        $this->assertArrayHasKey('message', $entity->getErrors());
    }

    public function testOptionalFieldsMayBeEmpty(): void
    {
        $entity = $this->inquiries->newEntity([
            'name' => 'A', 'email' => 'a@example.com', 'message' => 'hi', 'phone' => '', 'subject' => '',
        ]);

        $this->assertSame([], $entity->getErrors());
    }

    public function testASavedRowGetsCreatedAndTheNewStatusByDefault(): void
    {
        $entity = $this->inquiries->saveOrFail($this->inquiries->newEntity([
            'name' => 'A', 'email' => 'a@example.com', 'message' => 'hi',
        ]));

        $fresh = $this->inquiries->get($entity->id);
        $this->assertNotNull($fresh->created);
        $this->assertSame('new', $fresh->status);
    }

    public function testAStatusFlipSavesWithoutAModifiedColumn(): void
    {
        $entity = $this->inquiries->saveOrFail($this->inquiries->newEntity([
            'name' => 'A', 'email' => 'a@example.com', 'message' => 'hi',
        ]));

        $this->inquiries->saveOrFail(
            $this->inquiries->patchEntity($entity, ['status' => 'read'], ['validate' => false]),
        );

        $this->assertSame('read', $this->inquiries->get($entity->id)->status);
    }
}
