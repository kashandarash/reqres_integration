<?php

namespace Drupal\reqres_integration\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @Block(
 *   id = "reqres_integration_block",
 *   admin_label = @Translation("ReqRes Integration Block")
 * )
 */
class ApiPagerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'items_per_page' => 6,
      'label_email' => 'Email',
      'label_forename' => 'Forename',
      'label_surname' => 'Surname',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['items_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Items per page'),
      '#default_value' => $this->configuration['items_per_page'],
      '#min' => 1,
    ];

    $form['label_forename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Forename field label'),
      '#default_value' => $this->configuration['label_forename'],
      '#required' => TRUE,
    ];

    $form['label_surname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Surname field label'),
      '#default_value' => $this->configuration['label_surname'],
      '#required' => TRUE,
    ];

    $form['label_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email field label'),
      '#default_value' => $this->configuration['label_email'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['items_per_page'] = (int) $form_state->getValue('items_per_page');
    $this->configuration['label_email'] = $form_state->getValue('label_email');
    $this->configuration['label_forename'] = $form_state->getValue('label_forename');
    $this->configuration['label_surname'] = $form_state->getValue('label_surname');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {

    return [
      '#lazy_builder' => [
        'reqres_integration.lazy_builder:build',
        [
          $this->configuration['items_per_page'],
          $this->configuration['label_email'],
          $this->configuration['label_forename'],
          $this->configuration['label_surname'],
        ],
      ],
      '#create_placeholder' => TRUE,
    ];
  }

}