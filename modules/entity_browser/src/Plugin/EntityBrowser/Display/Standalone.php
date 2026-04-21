<?php

namespace Drupal\entity_browser\Plugin\EntityBrowser\Display;

use Drupal\entity_browser\DisplayBase;
use Drupal\entity_browser\DisplayRouterInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Presents entity browser as a standalone form.
 *
 * @EntityBrowserDisplay(
 *   id = "standalone",
 *   label = @Translation("Standalone form"),
 *   description = @Translation("Displays the entity browser as a standalone form. Only intended for testing or very specific use cases."),
 *   uses_route = TRUE
 * )
 */
class Standalone extends DisplayBase implements DisplayRouterInterface {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#required' => TRUE,
      '#description' => $this->t('The path at which the browser will be accessible. Must begin with a forward slash.'),
      '#default_value' => $this->configuration['path'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'path' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function selectionCompleted(array $entities) {
    // @todo Implement it.
  }

  /**
   * {@inheritdoc}
   */
  public function path() {
    return $this->configuration['path'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $path = $form_state->getValue('path');

    if ($path[0] != '/') {
      $form_state->setError($form['path'], $this->t('The Path field must begin with a forward slash.'));
    }
  }

}
