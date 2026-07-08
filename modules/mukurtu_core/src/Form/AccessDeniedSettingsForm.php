<?php

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure the title and message shown on the custom access denied page.
 */
class AccessDeniedSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_core.access_denied';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_core_access_denied_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $config->get('title'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $message = $config->get('message') ?? [];
    $form['message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Message'),
      '#default_value' => $message['value'] ?? '',
      '#format' => $message['format'] ?? 'full_html',
      '#allowed_formats' => ['basic_html', 'full_html'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('title', $form_state->getValue('title'))
      ->set('message', $form_state->getValue('message'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
