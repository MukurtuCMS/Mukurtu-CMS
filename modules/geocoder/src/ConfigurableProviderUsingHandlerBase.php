<?php

declare(strict_types=1);

namespace Drupal\geocoder;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\geocoder\Traits\ConfigurableProviderTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for configurable providers using handlers.
 */
abstract class ConfigurableProviderUsingHandlerBase extends ProviderUsingHandlerBase implements ConfigurableInterface, PluginFormInterface {

  use ConfigurableProviderTrait;

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfigManager;

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
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, CacheBackendInterface $cache_backend, LanguageManagerInterface $language_manager, TypedConfigManagerInterface $typed_config_manager) {
    try {
      // The typedConfigManager property needs to be set before the constructor,
      // to prevent its possible exception, and allow the
      // getConfigSchemaDefinition().
      $this->typedConfigManager = $typed_config_manager;
      parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory, $cache_backend, $language_manager);
    }
    catch (InvalidPluginDefinitionException $e) {
      $this->getLogger('geocoder')->error($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('cache.geocoder'),
      $container->get('language_manager'),
      $container->get('config.typed')
    );
  }

}
