<?php

namespace Drupal\search_api\Processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Item\FieldInterface;

/**
 * Provides a base class for configurable processor-defined properties.
 */
abstract class ConfigurablePropertyBase extends ProcessorProperty implements ConfigurablePropertyInterface {

  /**
   * The active configuration for this property, if known.
   *
   * This usually means that the property object was created for one specific
   * field, in which case $this->configuration will reflect that field's
   * configuration.
   *
   * The methods defined on ProcessorPropertyInterface all receive a $field
   * object. For those, the configuration should be taken from that object
   * instead. However, if the configuration is needed in other methods, then
   * this property can be used as an (unreliable) fallback.
   *
   * @var array|null
   */
  protected $configuration;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(FieldInterface $field, array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(FieldInterface $field, array &$form, FormStateInterface $form_state) {
    $values = array_intersect_key($form_state->getValues(), $this->defaultConfiguration());
    $field->setConfiguration($values);
    $this->configuration = $field->getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDescription(FieldInterface $field) {
    return $this->getDescription();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(?array $configuration): ConfigurablePropertyInterface {
    $this->configuration = $configuration;
    return $this;
  }

}
