<?php

namespace Drupal\reqres_integration\Service;

use Drupal\Core\State\StateInterface;
use Drupal\reqres_integration\Event\ApiUrlAlterEvent;
use Drupal\reqres_integration\Form\ApiSettingsForm;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ApiClient {

  /**
   * ApiClient construct.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   */
  public function __construct(
    protected ClientInterface          $httpClient,
    protected LoggerInterface          $logger,
    protected StateInterface           $state,
    protected EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * Fetches a page of items from the configured API endpoint.
   *
   * @param int $page
   *   Zero-based page index.
   * @param int $limit
   *   Number of items per page.
   *
   * @return array
   *   Associative array with keys 'items' and 'total'.
   */
  public function fetch(int $page, int $limit): array {
    $url = $this->state->get(ApiSettingsForm::STATE_API_URL, '');
    $api_key = $this->state->get(ApiSettingsForm::STATE_API_KEY, '');

    // Add an event so other modules can alter the query.
    // The users filtering should be in this stage, and not by removing some rows after the api call.
    // If we really have to filter users, and for example remove "John Dou", we have two correct options:
    // 1. Update the API and add support for it.
    // 2. Cache users in Drupal, for example on cron and that make extended select from this scope.
    //
    // P.S. Maybe by writing "Before presenting the list of users, an extension point should be exposed to allow a consumer of
    // this module to further filter the list of users themselves to remove anyone they do not want to
    // list." you wanted recursion, and additional api calls to fill the gaps in the paginated pages, but it is a dead end.
    // Or maybe broken pagination is acceptable, but I don't think it is a good solutions. Thanks!
    $event = new ApiUrlAlterEvent(['page' => $page + 1, 'per_page' => $limit]);
    $this->eventDispatcher->dispatch($event, ApiUrlAlterEvent::EVENT_NAME);
    $full_url = $url . '?' . http_build_query($event->getParams());

    try {
      $response = $this->httpClient->get($full_url, [
        'headers' => ['x-api-key' => $api_key],
      ]);
      $data = json_decode($response->getBody(), TRUE);

      return [
        'items' => $data['data'] ?? [],
        'total' => $data['total'] ?? NULL,
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('API request failed: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

}