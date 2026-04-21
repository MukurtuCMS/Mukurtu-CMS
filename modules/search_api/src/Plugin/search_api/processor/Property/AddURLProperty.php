<?php

namespace Drupal\search_api\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;

/**
 * Defines an "Item URL" property.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\AddURL
 */
class AddURLProperty extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'absolute' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state) {
    $configuration = $field->getConfiguration();

    $form['absolute'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate absolute URL'),
      '#description' => $this->t('Check this box to pass absolute URLs to the index. This can be useful when indexing multiple sites with a single search index.'),
      '#default_value' => $configuration['absolute'] ?? FALSE,
      '#return_value' => TRUE,
    ];

    return $form;
  }

}
