# Examples (not autoloaded)

Copy these into your own app and adapt them. They are **examples**: nothing here is autoloaded or loaded by the
plugin, and they are not tested by the plugin's suite. The plugin owns the data and the spam rules; **your site owns
the contact page, the JSON/HTML response, and the admin screens** (branding, layout and auth belong to the site).

| File | What it shows |
|---|---|
| `src/Controller/ContactController.php` | The public action: call `ContactIntake::intake()`, map the four statuses to a response |
| `templates/Contact/index.php` | A form with the honeypot field, the render timestamp and a `fetch()` submit |
| `src/Controller/Admin/InquiriesController.php` | An admin list (paginated, newest first) and the status flip |
| `templates/Admin/Inquiries/index.php` | The triage list with a status button per row and an empty state |

Routes to add to `config/routes.php` (mount the admin ones behind your own auth):

```php
$routes->connect('/contact', ['controller' => 'Contact', 'action' => 'index'], ['_name' => 'contact']);
$routes->prefix('Admin', function (RouteBuilder $routes): void {
    $routes->connect('/inquiries', ['controller' => 'Inquiries', 'action' => 'index'], ['_name' => 'admin.inquiries']);
    $routes->connect('/inquiries/{id}/status', ['controller' => 'Inquiries', 'action' => 'updateStatus'], ['_name' => 'admin.inquiries.status'])
        ->setPass(['id'])->setMethods(['POST']);
});
```
