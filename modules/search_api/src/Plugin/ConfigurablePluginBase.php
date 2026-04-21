<?php

namespace Drupal\search_api\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for all configurable Search API plugins.
 */
abstract class ConfigurablePluginBase extends HideablePluginBase implements ConfigurablePluginInterface {

  // Normally, we'd just need \Drupal\Core\Entity\DependencyTrait here for
  // plugins. However, in a few cases, plugins use plugins themselves, and then
  // the additional calculatePluginDependencies() method from this trait is
  // useful. Since PHP 5 complains when adding this trait along with its
  // "parent" trait to the same class, we just add it here in case a child class
  // does need it.
  use PluginDependencyTrait {
    getPluginDependencies as traitGetPluginDependencies;
    calculatePluginDependencies as traitCalculatePluginDependencies;
    moduleHandler as traitModuleHandler;
    themeHandler as traitThemeHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    $configuration += $this->defaultConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = new static($configuration, $plugin_id, $plugin_definition);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $container->get('string_translation');
    $plugin->setStringTranslation($translation);

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    // By default, we're not reacting to anything and so we should leave
    // everything as it was.
    return FALSE;
  }

  /**
   * Calculates and returns dependencies of a specific plugin instance.
   *
   * Dependencies are added for the module that provides the plugin, as well
   * as any dependencies declared by the instance's calculateDependencies()
   * method, if it implements
   * \Drupal\Component\Plugin\DependentPluginInterface.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $instance
   *   The plugin instance.
   *
   * @return array
   *   An array of dependencies keyed by the type of dependency.
   *
   * @deprecated in search_api:8.x-1.16 and is removed from search_api:2.0.0.
   *   Add \Drupal\Core\Plugin\PluginDependencyTrait manually for your class if
   *   you need it.
   *
   * @see https://www.drupal.org/node/3099004
   */
  protected function getPluginDependencies(PluginInspectionInterface $instance) {
    @trigger_error('The use of \Drupal\Core\Plugin\PluginDependencyTrait via \Drupal\search_api\Plugin\ConfigurablePluginBase is deprecated in search_api:8.x-1.16 and is removed from search_api:2.0.0. Add \Drupal\Core\Plugin\PluginDependencyTrait manually for your class if you need it. See https://www.drupal.org/node/3099004', E_USER_DEPRECATED);
    return $this->traitGetPluginDependencies($instance);
  }

  /**
   * Calculates and adds dependencies of a specific plugin instance.
   *
   * Dependencies are added for the module that provides the plugin, as well
   * as any dependencies declared by the instance's calculateDependencies()
   * method, if it implements
   * \Drupal\Component\Plugin\DependentPluginInterface.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $instance
   *   The plugin instance.
   *
   * @deprecated in search_api:8.x-1.16 and is removed from search_api:2.0.0.
   *   Add \Drupal\Core\Plugin\PluginDependencyTrait manually for your class if
   *   you need it.
   *
   * @see https://www.drupal.org/node/3099004
   */
  protected function calculatePluginDependencies(PluginInspectionInterface $instance) {
    @trigger_error('The use of \Drupal\Core\Plugin\PluginDependencyTrait via \Drupal\search_api\Plugin\ConfigurablePluginBase is deprecated in search_api:8.x-1.16 and is removed from search_api:2.0.0. Add \Drupal\Core\Plugin\PluginDependencyTrait manually for your class if you need it. See https://www.drupal.org/node/3099004', E_USER_DEPRECATED);
    $this->traitCalculatePluginDependencies($instance);
  }

  /**
   * Wraps the module handler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   *
   * @deprecated in search_api:8.x-1.16 and is removed from search_api:2.0.0.
   *   Add \Drupal\Core\Plugin\PluginDependencyTrait manually for your class if
   *   you need it.
   *
   * @see https://www.drupal.org/node/3099004
   */
  protected function moduleHandler() {
    @trigger_error('The use of \Drupal\Core\Plugin\PluginDependencyTrait via \Drupal\search_api\Plugin\ConfigurablePluginBase is deprecated in search_api:8.x-1.16 and is removed from search_api:2.0.0. Add \Drupal\Core\Plugin\PluginDependencyTrait manually for your class if you need it. See https://www.drupal.org/node/3099004', E_USER_DEPRECATED);
    return $this->traitModuleHandler();
  }

  /**
   * Wraps the theme handler.
   *
   * @return \Drupal\Core\Extension\ThemeHandlerInterface
   *   The theme handler.
   *
   * @deprecated in search_api:8.x-1.16 and is removed from search_api:2.0.0.
   *   Add \Drupal\Core\Plugin\PluginDependencyTrait manually for your class if
   *   you need it.
   *
   * @see https://www.drupal.org/node/3099004
   */
  protected function themeHandler() {
    @trigger_error('The use of \Drupal\Core\Plugin\PluginDependencyTrait via \Drupal\search_api\Plugin\ConfigurablePluginBase is deprecated in search_api:8.x-1.16 and is removed from search_api:2.0.0. Add \Drupal\Core\Plugin\PluginDependencyTrait manually for your class if you need it. See https://www.drupal.org/node/3099004', E_USER_DEPRECATED);
    return $this->traitThemeHandler();
  }

}
