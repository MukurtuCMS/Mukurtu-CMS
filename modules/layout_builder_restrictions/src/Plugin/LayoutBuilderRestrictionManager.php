<?php

namespace Drupal\layout_builder_restrictions\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the Layout builder restriction plugin plugin manager.
 */
class LayoutBuilderRestrictionManager extends DefaultPluginManager {
  /**
   * The config factory.
   *
   * Subclasses should use the self::config() method, which may be overridden to
   * address specific needs when loading config, rather than this property
   * directly. See \Drupal\Core\Form\ConfigFormBase::config() for an example of
   * this.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new LayoutBuilderRestrictionManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory to load plugin configuration.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {
    parent::__construct('Plugin/LayoutBuilderRestriction', $namespaces, $module_handler, 'Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestrictionInterface', 'Drupal\layout_builder_restrictions\Annotation\LayoutBuilderRestriction');
    $this->configFactory = $config_factory;
    $this->alterInfo('layout_builder_restrictions_layout_builder_restriction_info');
    $this->setCacheBackend($cache_backend, 'layout_builder_restriction_plugins');
  }

  /**
   * Helper function to get all restriction plugins.
   *
   * Will get configuration for all plugins, regardless of being enabled or not,
   * sorted by weight.
   * For *only* enabled plugins, use getSortedEnabledPlugins().
   *
   * @param bool $get_disabled
   *   Pass TRUE if the plugin data should also include plugins that
   *   are disabled.
   *
   * @return array
   *   Returns the plugin information, with 'weight' and 'enabled' information.
   *   The array is sorted by the configured weight.
   */
  public function getSortedPlugins(bool $get_disabled = FALSE) {
    $plugin_config = $this->configFactory->get('layout_builder_restrictions.plugins')->get('plugin_config');
    // Build a list of plugins, using saved weight & enabled status if defined.
    $plugin_list = [];
    foreach ($this->getDefinitions() as $restriction_definition) {
      $id = $restriction_definition['id'];
      // Handle plugins that are in existing config.
      if ($plugin_config && !empty($plugin_config[$id])) {
        $config = $plugin_config[$id];
        if ($config['enabled'] == FALSE && $get_disabled) {
          $plugin_list[$id] = [
            'weight' => (int) $config['weight'],
            'enabled' => (bool) $config['enabled'],
            'title' => $restriction_definition['title'],
            'description' => $restriction_definition['description'],
          ];
        }
        elseif ($config['enabled'] == TRUE) {
          $plugin_list[$id] = [
            'weight' => (int) $config['weight'],
            'enabled' => (bool) $config['enabled'],
            'title' => $restriction_definition['title'],
            'description' => $restriction_definition['description'],
          ];
        }
      }
      else {
        // Plugin not in existing config, default to enabled & default weight.
        $plugin_list[$id] = [
          'weight' => 1,
          'enabled' => TRUE,
          'title' => $restriction_definition['title'],
          'description' => $restriction_definition['description'],
        ];
      }
    }

    // Sort the plugin list by weight.
    uasort($plugin_list, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
    return $plugin_list;
  }

}
