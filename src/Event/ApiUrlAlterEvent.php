<?php

namespace Drupal\reqres_integration\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fired before the API request is sent.
 *
 * Subscribers may add, remove, or change query parameters via getParams() / setParams().
 */
class ApiUrlAlterEvent extends Event {

  const string EVENT_NAME = 'reqres_integration.api_url_alter';

  public function __construct(
    protected array $params,
  ) {}

  /**
   * Returns the current query parameters.
   */
  public function getParams(): array {
    return $this->params;
  }

  /**
   * Replaces the query parameters.
   */
  public function setParams(array $params): void {
    $this->params = $params;
  }

}
