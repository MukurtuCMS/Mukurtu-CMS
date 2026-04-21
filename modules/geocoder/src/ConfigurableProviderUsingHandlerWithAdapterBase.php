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
use Drupal\Core\Utility\Token;
use Drupal\geocoder\Traits\ConfigurableProviderTrait;
use Geocoder\Collection;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for providers using handlers with HTTP adapter.
 */
abstract class ConfigurableProviderUsingHandlerWithAdapterBase extends ProviderUsingHandlerWithAdapterBase implements ConfigurableInterface, PluginFormInterface {

  use ConfigurableProviderTrait;

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfigManager;

  /**
   * The throttle service.
   *
   * @var \Drupal\geocoder\GeocoderThrottleInterface
   */
  protected $throttle;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs a new configurable geocoder provider using handlers.
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
   *   The typed config manager.
   * @param \Psr\Http\Client\ClientInterface $http_adapter
   *   The HTTP adapter.
   * @param \Drupal\geocoder\GeocoderThrottleInterface $throttle
   *   The Geocoder Throttle service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, CacheBackendInterface $cache_backend, LanguageManagerInterface $language_manager, TypedConfigManagerInterface $typed_config_manager, ClientInterface $http_adapter, GeocoderThrottleInterface $throttle, Token $token) {
    try {
      // The typedConfigManager property needs to be set before the constructor,
      // to prevent its possible exception, and allow the
      // getConfigSchemaDefinition().
      $this->typedConfigManager = $typed_config_manager;
      parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory, $cache_backend, $language_manager, $http_adapter);
      $this->setConfiguration($configuration);
      $this->throttle = $throttle;
      $this->token = $token;
    }
    catch (InvalidPluginDefinitionException $e) {
      $this->getLogger('geocoder')->error($e->getMessage());
    }
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
      $container->get('language_manager'),
      $container->get('config.typed'),
      $container->get('geocoder.http_adapter'),
      $container->get('geocoder.throttle'),
      $container->get('token'),
    );
  }

  /**
   * Set Token Replacements data.
   *
   * @return array
   *   The Token Replacements array data.
   */
  protected function getTokensData(): array {
    return [
      'geocoder' => [
        'site_uuid' => $this->configFactory->get('system.site')->get('uuid'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Apply token replacements to arguments of Configurable Providers.
   */
  protected function getArguments(): array {
    $arguments = parent::getArguments();
    $token_data = $this->getTokensData();

    foreach ($arguments as $key => $argument) {
      if (isset($argument) && is_string($argument) && $argument !== '') {
        // Use 'clear' to avoid passing unresolved token strings to external
        // APIs when a token context is not available.
        $arguments[$key] = $this->token->replace($argument, $token_data, ['clear' => TRUE]);
      }
    }
    return $arguments;
  }

  /**
   * {@inheritdoc}
   */
  protected function getLocale(): string {
    $locale = parent::getLocale();
    $token_data = $this->getTokensData();
    return $this->token->replace($locale, $token_data, ['clear' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  protected function doGeocode($source) {
    $this->throttle->waitForAvailability($this->pluginId, $this->configuration['throttle'] ?? []);
    return parent::doGeocode($source);
  }

  /**
   * {@inheritdoc}
   */
  protected function doReverse($latitude, $longitude) {
    $this->throttle->waitForAvailability($this->pluginId, $this->configuration['throttle'] ?? []);
    return parent::doReverse($latitude, $longitude);
  }

  /**
   * {@inheritdoc}
   */
  protected function doGeocodeQuery(GeocodeQuery $query): Collection {
    $this->throttle->waitForAvailability($this->pluginId, $this->configuration['throttle'] ?? []);
    return parent::doGeocodeQuery($query);
  }

  /**
   * {@inheritdoc}
   */
  protected function doReverseQuery(ReverseQuery $query): Collection {
    $this->throttle->waitForAvailability($this->pluginId, $this->configuration['throttle'] ?? []);
    return parent::doReverseQuery($query);
  }

}
