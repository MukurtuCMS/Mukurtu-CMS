<?php

namespace Drupal\dashboards\Layouts;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Add layout settings.
 *
 * Layout plugin settings class.
 */
class SectionLayout extends LayoutDefault implements PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'reverse' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();
    $form['reverse'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reverse columns'),
      '#default_value' => $configuration['reverse'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['reverse'] = $form_state->getValue('reverse');
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $regions) {
    $configuration = $this->getConfiguration();
    $build = parent::build($regions);
    if ($configuration['reverse']) {
      $build['#attributes']['class'][] = 'dashboard-reverse';
    }
    return $build;
  }

}
