<?php

namespace Drupal\geolocation;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Search plugin manager.
 */
class DataProviderManager extends DefaultPluginManager {

  /**
   * Constructs an DataProviderManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/geolocation/DataProvider', $namespaces, $module_handler, 'Drupal\geolocation\DataProviderInterface', 'Drupal\geolocation\Annotation\DataProvider');
    $this->alterInfo('geolocation_dataprovider_info');
    $this->setCacheBackend($cache_backend, 'geolocation_dataprovider');
  }

  /**
   * Return DataProvider by field type.
   *
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase $viewField
   *   Map type.
   * @param array $configuration
   *   Configuration.
   *
   * @return \Drupal\geolocation\DataProviderInterface|false
   *   Data provider.
   */
  public function getDataProviderByViewsField(FieldPluginBase $viewField, array $configuration = []) {
    $definitions = $this->getDefinitions();
    try {
      foreach ($definitions as $dataProviderId => $dataProviderDefinition) {
        /** @var \Drupal\geolocation\DataProviderInterface $dataProvider */
        $dataProvider = $this->createInstance($dataProviderId, $configuration);

        if ($dataProvider->isViewsGeoOption($viewField)) {
          $dataProvider->setViewsField($viewField);
          return $dataProvider;
        }
      }
    }
    catch (\Exception $e) {
      return FALSE;
    }

    return FALSE;
  }

  /**
   * Return DataProvider by field type.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition.
   * @param array $configuration
   *   Configuration.
   *
   * @return \Drupal\geolocation\DataProviderInterface|false
   *   Data provider.
   */
  public function getDataProviderByFieldDefinition(FieldDefinitionInterface $fieldDefinition, array $configuration = []) {
    $definitions = $this->getDefinitions();
    try {
      foreach ($definitions as $dataProviderId => $dataProviderDefinition) {
        /** @var \Drupal\geolocation\DataProviderInterface $dataProvider */
        $dataProvider = $this->createInstance($dataProviderId, $configuration);

        if ($dataProvider->isFieldGeoOption($fieldDefinition)) {
          $dataProvider->setFieldDefinition($fieldDefinition);
          return $dataProvider;
        }
      }
    }
    catch (\Exception $e) {
      return FALSE;
    }

    return FALSE;
  }

  /**
   * Return settings array for data provider after select change.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current From State.
   *
   * @return array|false
   *   Settings form.
   */
  public static function addDataProviderSettingsFormAjax(array $form, FormStateInterface $form_state) {
    $triggering_element_parents = $form_state->getTriggeringElement()['#array_parents'];

    $settings_element_parents = $triggering_element_parents;
    array_pop($settings_element_parents);
    $settings_element_parents[] = 'data_provider_settings';

    return NestedArray::getValue($form, $settings_element_parents);
  }

}
