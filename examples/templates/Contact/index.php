<?php
/**
 * EXAMPLE (not autoloaded by the plugin): the contact form.
 *
 * @var \Cake\View\View $this
 */
// Cake's CSRF cookie is httponly, so JavaScript cannot read it: hand the token over in the markup.
$csrf = (string)$this->getRequest()->getAttribute('csrfToken');
?>
<form id="contact-form" data-action="<?= $this->Url->build(['controller' => 'Contact', 'action' => 'index']) ?>"
    data-csrf-token="<?= h($csrf) ?>">
    <label>Name <input name="name" required maxlength="100"></label>
    <label>Email <input name="email" type="email" required maxlength="100"></label>
    <label>Phone <input name="phone" maxlength="30"></label>
    <label>Subject <input name="subject" maxlength="200"></label>
    <label>Message <textarea name="message" required maxlength="5000"></textarea></label>

    <!-- Honeypot: hidden from people, filled by bots. The name must match Contact.honeypot_field. -->
    <div style="position:absolute;left:-9999px" aria-hidden="true">
        <label>Company <input name="company" tabindex="-1" autocomplete="off"></label>
    </div>

    <button type="submit">Send</button>
    <p id="contact-status" role="status"></p>
</form>

<script>
const form = document.getElementById('contact-form');
// ms since epoch when the form was rendered: the plugin flags submissions that arrive faster than a person can type.
const renderedAt = Date.now();

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const body = Object.fromEntries(new FormData(form));
    body.renderedAt = renderedAt;
    body.landingPage = location.pathname;

    const response = await fetch(form.dataset.action, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': form.dataset.csrfToken},
        body: JSON.stringify(body),
    });
    document.getElementById('contact-status').textContent = response.ok ? 'Thanks, we got your message.' : 'Please check the form.';
});
</script>
