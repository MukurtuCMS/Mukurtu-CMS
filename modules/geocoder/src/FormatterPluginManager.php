<?php

namespace Drupal\geocoder;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\geocoder\Annotation\GeocoderFormatter;
use Drupal\geocoder\Plugin\Geocoder\Formatter\FormatterInterface;

/**
 * Provides a plugin manager for geocoder formatters.
 */
class FormatterPluginManager extends GeocoderPluginManagerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Geocoder/Formatter', $namespaces, $module_handler, FormatterInterface::class, GeocoderFormatter::class);
    $this->alterInfo('geocoder_formatter_info');
    $this->setCacheBackend($cache_backend, 'geocoder_formatter_plugins');
  }

}
