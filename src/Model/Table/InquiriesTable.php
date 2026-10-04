<?php
declare(strict_types=1);

namespace TheMusicDev\Contact\Model\Table;

use Cake\ORM\Table;
use TheMusicDev\Contact\Model\Entity\Inquiry;

/**
 * Contact-form inquiries. Written by Lib\ContactIntake (the host's public
 * POST route calls it), listed and triaged in the host's admin.
 *
 * @extends \Cake\ORM\Table<array{Timestamp: \Cake\ORM\Behavior\TimestampBehavior}, \TheMusicDev\Contact\Model\Entity\Inquiry>
 * @method \Cake\Datasource\ResultSetInterface<int, \TheMusicDev\Contact\Model\Entity\Inquiry>|false saveMany(iterable<\TheMusicDev\Contact\Model\Entity\Inquiry> $entities, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \TheMusicDev\Contact\Model\Entity\Inquiry> saveManyOrFail(iterable<\TheMusicDev\Contact\Model\Entity\Inquiry> $entities, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \TheMusicDev\Contact\Model\Entity\Inquiry>|false deleteMany(iterable<\TheMusicDev\Contact\Model\Entity\Inquiry> $entities, array<string, mixed> $options = [])
 * @method \Cake\Datasource\ResultSetInterface<int, \TheMusicDev\Contact\Model\Entity\Inquiry> deleteManyOrFail(iterable<\TheMusicDev\Contact\Model\Entity\Inquiry> $entities, array<string, mixed> $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @method \TheMusicDev\Contact\Model\Entity\Inquiry newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 * @method \TheMusicDev\Contact\Model\Entity\Inquiry[] newEntities(array<array<string, mixed>> $data, array<string, mixed> $options = [])
 * @method \TheMusicDev\Contact\Model\Entity\Inquiry get(mixed $primaryKey, array<string, mixed>|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \TheMusicDev\Contact\Model\Entity\Inquiry findOrCreate(\Cake\ORM\Query\SelectQuery<\TheMusicDev\Contact\Model\Entity\Inquiry>|callable|array<string, mixed> $search, ?callable $callback = null, array<string, mixed> $options = [])
 * @method \TheMusicDev\Contact\Model\Entity\Inquiry patchEntity(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $data, array<string, mixed> $options = [])
 * @method \TheMusicDev\Contact\Model\Entity\Inquiry[] patchEntities(iterable<\TheMusicDev\Contact\Model\Entity\Inquiry> $entities, array<array<string, mixed>> $data, array<string, mixed> $options = [])
 */
final class InquiriesTable extends Table
{
    /** Table metadata + submission validation. */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('inquiries');
        $this->setPrimaryKey('id');
        $this->setEntityClass(Inquiry::class);
        // Note: no `modified` column in the schema — update events would
        // break saves. Only `created` is stamped.
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);

        // Field length caps as validation — oversized
        // payloads are a scraper signal and never become rows.
        $this->getValidator('default')
            ->requirePresence(['name', 'email', 'message'])
            ->notBlank('name', 'Name is required.')
            ->notBlank('email', 'Email is required.')
            ->notBlank('message', 'Message is required.')
            ->email('email', false, 'Not a valid email address.')
            // Optional fields must pass allowEmpty *and* length caps.
            ->allowEmptyString('phone')
            ->allowEmptyString('subject')
            ->maxLength('name', 100)
            ->maxLength('email', 100)
            ->maxLength('phone', 30)
            ->maxLength('subject', 200)
            ->maxLength('message', 5000);
    }
}
