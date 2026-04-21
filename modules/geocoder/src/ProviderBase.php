<?php

namespace Drupal\geocoder;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Geocoder\Query\Query;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for providers using handlers.
 */
abstract class ProviderBase extends PluginBase implements ProviderInterface, ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache backend used to cache geocoding data.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The configurable language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManager
   */
  protected $languageManager;

  /**
   * Constructs a geocoder provider plugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend used to cache geocoding data.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The Drupal language manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, CacheBackendInterface $cache_backend, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->cacheBackend = $cache_backend;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('cache.geocoder'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function geocode($source) {
    return $this->process(__FUNCTION__, \func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function reverse($latitude, $longitude) {
    return $this->process(__FUNCTION__, \func_get_args());
  }

  /**
   * Provides a helper callback for geocode() and reverse().
   *
   * @param string $method
   *   The method: 'geocode' or 'reverse'.
   * @param array $data
   *   An array with data to be processed. When geocoding, it contains only one
   *   item with the string. When reversing, contains 2 items: the latitude and
   *   the longitude.
   *
   * @return \Geocoder\Collection|\Geometry|null
   *   The address collection, or the geometry, or NULL.
   */
  protected function process($method, array $data) {
    $value = $this->getCache($method, $data);
    if (is_null($value)) {
      $processor = $method == 'geocode' ? 'doGeocode' : 'doReverse';
      $value = \call_user_func_array([$this, $processor], $data);
      $this->setCache($method, $data, $value);
    }

    return $value;
  }

  /**
   * Retrieve result from the cache if it is enabled.
   *
   * @param string $method
   *   The method: 'geocode' or 'reverse'.
   * @param array|\Geocoder\Query\Query $data
   *   An array with data to be processed. When geocoding, it contains only one
   *   item with the string. When reversing, contains 2 items: the latitude and
   *   the longitude.
   *
   * @return mixed
   *   The cached value. Presently address collection, or the geometry, or NULL.
   */
  protected function getCache(string $method, array|Query $data): mixed {
    if ($this->configFactory->get('geocoder.settings')->get('cache')) {
      // Try to retrieve from cache first.
      $cid = $this->getCacheId($method, $data);
      if ($cache = $this->cacheBackend->get($cid)) {
        return $cache->data;
      }
    }
    return NULL;
  }

  /**
   * Set result in the cache if it is enabled.
   *
   * @param string $method
   *   The method: 'geocode' or 'reverse'.
   * @param array|\Geocoder\Query\Query $data
   *   An array with data to be processed. When geocoding, it contains only one
   *   item with the string. When reversing, contains 2 items: the latitude and
   *   the longitude.
   * @param mixed $value
   *   The value to cache. Presently address collection, or the geometry,
   *   or NULL.
   */
  protected function setCache($method, array|Query $data, mixed $value): void {
    if ($this->configFactory->get('geocoder.settings')->get('cache')) {
      $cid = $this->getCacheId($method, $data);
      $this->cacheBackend->set($cid, $value);
    }
  }

  /**
   * Performs the geocoding.
   *
   * @param string $source
   *   The data to be geocoded.
   *
   * @return \Geocoder\Collection|\Geometry|null
   *   The address collection, or the geometry, or NULL.
   */
  abstract protected function doGeocode($source);

  /**
   * Performs the reverse geocode.
   *
   * @param float $latitude
   *   The latitude.
   * @param float $longitude
   *   The longitude.
   *
   * @return \Geocoder\Collection|null
   *   The AddressCollection, NULL otherwise.
   */
  abstract protected function doReverse($latitude, $longitude);

  /**
   * Builds a cached id.
   *
   * @param string $method
   *   The method: 'geocode' or 'reverse'.
   * @param array|\Geocoder\Query\Query $data
   *   An array with data to be processed. When geocoding, it contains only one
   *   item with the string. When reversing, contains 2 items: the latitude and
   *   the longitude.
   *
   * @return string
   *   A unique cache id.
   */
  protected function getCacheId($method, array|Query $data): string {
    // Set cache id also on the basis of the locale/language param (#3406296).
    $locale = $this->getLocale();
    $cid = [$method, $this->getPluginId()];
    $cid[] = sha1(serialize($this->configuration) . serialize($data) . $locale);

    return implode(':', $cid);
  }

  /**
   * Set the Locale/language parameter for Geocoding/Reverse-Geocoding ops.
   *
   * Define it on the basis of the geocoder additional option,
   * or falling back to the current Interface language code/id.
   *
   * @return string
   *   The locale id.
   */
  protected function getLocale(): string {
    return !empty($this->configuration['geocoder']['locale']) ? $this->configuration['geocoder']['locale'] : $this->languageManager->getCurrentLanguage()->getId();
  }

}
