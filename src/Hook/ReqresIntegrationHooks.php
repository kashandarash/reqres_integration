<?php

namespace Drupal\reqres_integration\Hook;

use Drupal\Core\Hook\Attribute\Hook;

class ReqresIntegrationHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'reqres_user_table' => [
        'variables' => [
          'items' => [],
          'label_email' => 'Email',
          'label_forename' => 'Forename',
          'label_surname' => 'Surname',
        ],
      ],
      // Add custom placeholder template for our lazy builder block.
      'big_pipe_interface_preview__reqres_integration_lazy_builder_build' => [],
    ];
  }

}
