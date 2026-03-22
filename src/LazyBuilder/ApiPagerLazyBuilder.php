<?php

namespace Drupal\reqres_integration\LazyBuilder;

use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\reqres_integration\Service\ApiClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ApiPagerLazyBuilder implements TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['build'];
  }

  /**
   * Constructs a new ApiPagerLazyBuilder.
   */
  public function __construct(
    protected ApiClient             $apiClient,
    protected PagerManagerInterface $pagerManager,
    protected LoggerInterface       $logger,
    protected RequestStack          $requestStack,
  ) {}

  /**
   * Builds the paged item list render array.
   *
   * @param int $items_per_page
   *   The number of items per page.
   * @param string $label_email
   *   Label for the email field.
   * @param string $label_forename
   *   Label for the forename field.
   * @param string $label_surname
   *   Label for the surname field.
   *
   * @return array
   *   A render array with the item list and pager.
   */
  public function build(int $items_per_page, string $label_email, string $label_forename, string $label_surname): array {
    try {
      $current_page = (int) $this->requestStack->getCurrentRequest()->query->get('page', 0);

      $result = $this->apiClient->fetch($current_page, $items_per_page);

      $items = $result['items'];
      $total = $result['total'] ?? (($current_page + 1) * $items_per_page + 1);

      $this->pagerManager->createPager($total, $items_per_page);
    }
    catch (\Exception $e) {
      $this->logger->error('Lazy builder failed: @message', ['@message' => $e->getMessage()]);
      return [
        '#markup' => $this->t('API error.'),
      ];
    }

    return [
      'table' => [
        '#theme' => 'reqres_user_table',
        '#items' => $items,
        '#label_email' => $label_email,
        '#label_forename' => $label_forename,
        '#label_surname' => $label_surname,
      ],
      'pager' => ['#type' => 'pager'],
      '#cache' => [
        // Let's cache it by query param and keep for 5 min.
        // We are not using cache tags here because there is no Drupal entity.
        // We could use tags (like ENTITY:ID) if, for example, this content is generated per entity.
        'contexts' => ['url.query_args:page'],
        'max-age' => 300,
      ],
    ];
  }

}
