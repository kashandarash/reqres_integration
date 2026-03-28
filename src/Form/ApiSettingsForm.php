<?php

namespace Drupal\reqres_integration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin settings form for the ReqRes API connection.
 */
class ApiSettingsForm extends FormBase {

  const STATE_API_URL = 'reqres_integration.api_url';

  const STATE_API_URL_DEFAULT = 'https://reqres.in/api/users';

  const STATE_API_KEY = 'reqres_integration.api_key';

  /**
   * ApiSettingsForm construct.
   */
  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('state'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'reqres_integration_api_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['api_url'] = [
      '#type' => 'url',
      '#title' => $this->t('API URL'),
      '#default_value' => $this->state->get(self::STATE_API_URL, self::STATE_API_URL_DEFAULT),
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('API Key'),
      '#description' => $this->state->get(self::STATE_API_KEY, '') ? $this->t('Leave blank to keep the existing key.') : '',
      '#required' => !$this->state->get(self::STATE_API_KEY, ''),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Save config to the state, to make it unique peer env.
    // It is easier than add config ignore.
    // But I prefer to add variables like api key in .env variables, so they can be secured/changed on file system level.
    $this->state->set(self::STATE_API_URL, $form_state->getValue('api_url'));
    $api_key = $form_state->getValue('api_key');
    if (!empty($api_key)) {
      $this->state->set(self::STATE_API_KEY, $api_key);
    }

    $this->messenger()->addStatus($this->t('API settings saved.'));
  }

}