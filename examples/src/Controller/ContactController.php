<?php
declare(strict_types=1);

namespace App\Controller;

use TheMusicDev\Contact\Lib\ContactIntake;

/**
 * EXAMPLE (not autoloaded by the plugin): the public contact page.
 * GET renders the form; POST hands the request to the plugin and maps the result to JSON.
 */
class ContactController extends AppController
{
    /**
     * @return void
     */
    public function index(): void
    {
        if (!$this->getRequest()->is('post')) {
            return;
        }

        // The POST answers with JSON; do not render the template over it.
        $this->autoRender = false;

        $result = ContactIntake::intake($this->getRequest());

        // 'rejected' = a required field is missing; 'invalid' = a length cap or the email format failed.
        // Nothing was saved in either case.
        if ($result['status'] === 'rejected' || $result['status'] === 'invalid') {
            $message = $result['status'] === 'rejected' ? 'name, email, and message are required' : 'Invalid submission';
            $this->response = $this->response
                ->withType('application/json')
                ->withStatus(400)
                ->withStringBody((string)json_encode(['error' => $message, 'fields' => $result['errors']]));

            return;
        }

        // 'ok' and 'spam' get the SAME answer: a flagged submission was saved with status 'spam', and the bot
        // must not learn that it was caught.
        $this->response = $this->response->withType('application/json')->withStringBody('{"ok":true}');
    }
}
