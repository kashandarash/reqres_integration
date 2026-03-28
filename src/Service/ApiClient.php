<?php

namespace Drupal\reqres_integration\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\reqres_integration\Event\ApiUrlAlterEvent;
use Drupal\reqres_integration\Form\ApiSettingsForm;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * API client class.
 */
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
    protected ModuleHandlerInterface   $moduleHandler,
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
    $url = $this->state->get(ApiSettingsForm::STATE_API_URL, ApiSettingsForm::STATE_API_URL_DEFAULT);
    $api_key = $this->state->get(ApiSettingsForm::STATE_API_KEY, '');

    if (empty($api_key)) {
      $this->logger->error('The API key is not set');
      return [
        'items' => [],
        'total' => NULL,
      ];
    }

    // Add an event so other modules can alter the query.
    // The users filtering should be in this stage, and not by removing some rows after the api call.
    // If we really have to filter users, and for example remove "John Dou", we have two correct options:
    // 1. Update the API and add support for it.
    // 2. Cache users in Drupal, for example on cron and that make extended select from this scope.
    $event = new ApiUrlAlterEvent(['page' => $page + 1, 'per_page' => $limit]);
    $this->eventDispatcher->dispatch($event, ApiUrlAlterEvent::EVENT_NAME);
    $full_url = $url . '?' . http_build_query($event->getParams());

    try {
      $response = $this->httpClient->get($full_url, [
        'headers' => ['x-api-key' => $api_key],
      ]);
      $data = json_decode($response->getBody(), TRUE);

      // Alter list of items.
      $this->moduleHandler->alter('reqres_integration_api_data', $data);

      return [
        'items' => $data['data'] ?? [],
         'total' => $data['total'] ?? 0,
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('API request failed: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

}