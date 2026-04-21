<?php

namespace Drupal\geolocation;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Search plugin manager.
 */
class GeocoderManager extends DefaultPluginManager {

  /**
   * Constructs an GeocoderManager object.
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
    parent::__construct('Plugin/geolocation/Geocoder', $namespaces, $module_handler, 'Drupal\geolocation\GeocoderInterface', 'Drupal\geolocation\Annotation\Geocoder');
    $this->alterInfo('geolocation_geocoder_info');
    $this->setCacheBackend($cache_backend, 'geolocation_geocoder');
  }

  /**
   * Return Geocoder by ID.
   *
   * @param string $id
   *   Geocoder ID.
   * @param array $configuration
   *   Configuration.
   *
   * @return \Drupal\geolocation\GeocoderInterface|false
   *   Geocoder instance.
   */
  public function getGeocoder($id, array $configuration = []) {
    if (!$this->hasDefinition($id)) {
      return FALSE;
    }
    try {
      /** @var \Drupal\geolocation\GeocoderInterface $instance */
      $instance = $this->createInstance($id, $configuration);
      if ($instance) {
        return $instance;
      }
    }
    catch (\Exception $e) {
      return FALSE;
    }
    return FALSE;
  }

  /**
   * Return settings array for geocoder after select change.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current From State.
   *
   * @return array|false
   *   Settings form.
   */
  public static function addGeocoderSettingsFormAjax(array $form, FormStateInterface $form_state) {
    $triggering_element_parents = $form_state->getTriggeringElement()['#array_parents'];

    $settings_element_parents = $triggering_element_parents;
    array_pop($settings_element_parents);
    $settings_element_parents[] = 'settings';

    return NestedArray::getValue($form, $settings_element_parents);
  }

}
