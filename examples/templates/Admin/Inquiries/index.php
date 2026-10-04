<?php
/**
 * EXAMPLE (not autoloaded by the plugin): the inquiries triage list. Restyle it to match your admin.
 *
 * @var \Cake\View\View $this
 * @var \Cake\Datasource\Paging\PaginatedInterface $inquiries
 */
$csrf = (string)$this->getRequest()->getAttribute('csrfToken');
?>
<h1>Inquiries</h1>
<?= $this->Flash->render() ?>

<?php if ($inquiries->count() === 0) : ?>
    <p>No inquiries yet.</p>
<?php endif; ?>

<?php foreach ($inquiries as $inquiry) : ?>
    <article>
        <header>
            <strong><?= h($inquiry->name) ?></strong> &lt;<?= h($inquiry->email) ?>&gt;
            <span class="status status-<?= h($inquiry->status) ?>"><?= h($inquiry->status) ?></span>
        </header>
        <?php if ((string)$inquiry->subject !== '') : ?>
            <p><em><?= h($inquiry->subject) ?></em></p>
        <?php endif; ?>
        <p><?= nl2br(h($inquiry->message)) ?></p>

        <details>
            <summary>Details</summary>
            <dl>
                <dt>IP address</dt><dd><?= h($inquiry->ip_address ?? '-') ?></dd>
                <dt>User agent</dt><dd><?= h($inquiry->user_agent ?? '-') ?></dd>
                <dt>Referrer</dt><dd><?= h($inquiry->referrer ?? '-') ?></dd>
                <dt>Landing page</dt><dd><?= h($inquiry->landing_page ?? '-') ?></dd>
                <dt>Submit duration</dt>
                <dd><?= $inquiry->submit_duration_ms !== null ? number_format($inquiry->submit_duration_ms / 1000, 1) . 's' : '-' ?></dd>
                <dt>Honeypot</dt><dd><?= h($inquiry->honeypot_value ?? '-') ?></dd>
            </dl>
        </details>

        <?php foreach (\TheMusicDev\Contact\Model\Entity\Inquiry::STATUSES as $next) : ?>
            <?php if ($next === $inquiry->status) {
                continue;
            } ?>
            <form method="post" style="display:inline"
                action="<?= $this->Url->build(['action' => 'updateStatus', $inquiry->id]) ?>">
                <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>">
                <input type="hidden" name="status" value="<?= h($next) ?>">
                <button type="submit">Mark <?= h($next) ?></button>
            </form>
        <?php endforeach; ?>
    </article>
<?php endforeach; ?>

<?php if ($inquiries->count() > 0) : ?>
    <?= $this->Paginator->numbers() ?>
<?php endif; ?>
