<?php

namespace Drupal\search_api_solr\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;

/**
 * Defines a "dummy field" property.
 *
 * @see \Drupal\search_api_solr\Plugin\search_api\processor\DummyFields
 */
class DummyFieldProperty extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'dummy_value' => 'dummy',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state) {
    $configuration = $field->getConfiguration();

    $form['dummy_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dummy value'),
      '#description' => $this->t('The value to be set initially on the dummy field.'),
      '#default_value' => $configuration['dummy_value'],
      '#required' => TRUE,
    ];

    return $form;
  }

}
