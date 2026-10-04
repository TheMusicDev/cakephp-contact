<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use TheMusicDev\Contact\Model\Entity\Inquiry;

/**
 * EXAMPLE (not autoloaded by the plugin): admin triage for contact-form inquiries.
 *
 * Gate this behind your own authentication (a `beforeFilter` here or in an Admin base controller). The plugin ships
 * no admin screens on purpose: layout, branding and auth belong to the site.
 *
 * @property \TheMusicDev\Contact\Model\Table\InquiriesTable $Inquiries
 */
class InquiriesController extends AppController
{
    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Inquiries = $this->fetchTable('TheMusicDev/Contact.Inquiries');
    }

    /**
     * Newest first, 20 per page. Spam-flagged rows are listed too: triage decides what to keep.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('inquiries', $this->paginate($this->Inquiries->find(), [
            'order' => ['Inquiries.created' => 'DESC'],
            'sortableFields' => ['Inquiries.created'],
        ]));
    }

    /**
     * POST /admin/inquiries/{id}/status: flip the triage status.
     *
     * @param string $id Inquiry id
     * @return void
     */
    public function updateStatus(string $id): void
    {
        $this->getRequest()->allowMethod('post');
        $inquiry = $this->Inquiries->get($id);
        $status = (string)$this->getRequest()->getData('status');

        // Allowlist the value, and skip validation: the validator requires name/email/message to be present, which
        // a partial update does not send.
        if (in_array($status, Inquiry::STATUSES, true)) {
            $this->Inquiries->save($this->Inquiries->patchEntity($inquiry, ['status' => $status], ['validate' => false]));
            $this->Flash->success("Inquiry #{$inquiry->id} marked {$status}.");
        }

        $this->redirect(['action' => 'index']);
    }
}
