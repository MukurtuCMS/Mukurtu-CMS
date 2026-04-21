<?php

namespace Drupal\geocoder_field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for the Preprocessor plugin.
 */
abstract class PreprocessorBase extends PluginBase implements PreprocessorInterface, ContainerFactoryPluginInterface {

  /**
   * The field that needs to be preprocessed.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface
   */
  protected $field;

  /**
   * The country manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * PreprocessorBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The Country Manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CountryManagerInterface $country_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->countryManager = $country_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function setField(FieldItemListInterface $field) {
    $this->field = $field;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('country_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess() {
    if (!isset($this->field)) {
      throw new \RuntimeException('A field (\Drupal\Core\Field\FieldItemListInterface) must be set with ::setField() before preprocessing.');
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreparedReverseGeocodeValues(array $values = []) {
    return array_map(function ($value) {
      return array_combine(['lat', 'lon'], array_map(
        'trim',
        explode(',', trim($value['value']), 2)
      ));
    }, $values);
  }

}
