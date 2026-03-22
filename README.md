# ReqRes Integration

Displays a paginated table of users from the ReqRes API via a configurable block.
API credentials are stored in Drupal **State** (environment-specific, never exported to config).

---

## Installation via Composer (GitHub)

1. Add the repository to your root `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/YOUR_GITHUB_USERNAME/reqres_integration"
    }
  ]
}
```

2. Require the module:

```bash
composer require yourname/reqres_integration:dev-main
```

3. Enable the module and rebuild caches:

```bash
drush en reqres_integration
drush cr
```

---

## Configuration

### 1. Set the API URL and key

Go to **Administration → Configuration → Web services → ReqRes API Settings**
(`/admin/config/services/reqres-integration`) and enter:

- **API URL** — base endpoint, e.g. `https://reqres.in/api/users`
- **API Key** — your environment-specific API key

These values are saved to Drupal State and are **not** exported by `drush config:export`,
so each environment (local, staging, production) holds its own credentials.

### 2. Place the block

1. Go to **Administration → Structure → Block layout**.
2. Click **Place block** in the desired region.
3. Find **ReqRes Integration Block** and click **Place block**.
4. Configure the block:
   - **Items per page** — number of rows shown per page (default: 6).
   - **Forename field label** — column header for the first name (default: `Forename`).
   - **Surname field label** — column header for the last name (default: `Surname`).
   - **Email field label** — column header for the email address (default: `Email`).
5. Save the block. A paginated table will appear in the chosen region.

---

## Extending: altering API query parameters

Before each API request the module dispatches `ApiUrlAlterEvent`, which exposes the
query parameter array. Any module can subscribe to add, remove, or override parameters.

### 1. Create the subscriber class

```php
// my_module/src/EventSubscriber/ReqresApiSubscriber.php

namespace Drupal\my_module\EventSubscriber;

use Drupal\reqres_integration\Event\ApiUrlAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReqresApiSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      ApiUrlAlterEvent::EVENT_NAME => 'onApiUrlAlter',
    ];
  }

  public function onApiUrlAlter(ApiUrlAlterEvent $event): void {
    $params = $event->getParams();
    // Example: add a custom filter parameter.
    $params['filter'] = 'active';
    $event->setParams($params);
  }

}
```

### 2. Register the subscriber as a tagged service

```yaml
# my_module/my_module.services.yml

services:
  my_module.reqres_api_subscriber:
    class: Drupal\my_module\EventSubscriber\ReqresApiSubscriber
    tags:
      - { name: event_subscriber }
```

### 3. Rebuild caches

```bash
drush cr
```

The subscriber will now run on every API call made by this module.

---

## Available event

| Constant | Value |
|---|---|
| `ApiUrlAlterEvent::EVENT_NAME` | `reqres_integration.api_url_alter` |

**Methods on the event object:**

| Method | Description |
|---|---|
| `getParams(): array` | Returns the current query parameters |
| `setParams(array $params): void` | Replaces the query parameters |
