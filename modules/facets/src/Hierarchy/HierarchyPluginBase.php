<?php

namespace Drupal\facets\Hierarchy;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\UncacheableDependencyTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base class for plugins that implements most of the boilerplate.
 *
 * By default all plugins that will extend this class will disable facets
 * caching mechanism. It is strongly recommended to turn it on by implementing
 * own methods for the CacheableDependencyInterface interface.
 */
abstract class HierarchyPluginBase extends ProcessorPluginBase implements HierarchyInterface, ContainerFactoryPluginInterface, CacheableDependencyInterface {

  use UncacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $request_stack = $container->get('request_stack');
    $request = $request_stack->getMainRequest();

    return new static($configuration, $plugin_id, $plugin_definition, $request);
  }

  /**
   * Provide a default implementation for backward compatibility.
   *
   * {@inheritdoc}
   */
  public function getSiblingIds(array $ids, array $activeIds = [], bool $parentSiblings = TRUE) {
    return [];
  }

  /**
   * Set the default values for the configuration form.
   *
   * @param array $form
   *   The configuration form.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet entity.
   */
  protected function setConfigurationFormDefaultValues(array &$form, FacetInterface $facet) {
    if ($this->getPluginId() === $facet->getHierarchy()['type']) {
      foreach ($form as $key => $form_item) {
        if (isset($facet->getHierarchy()['config'][$key])) {
          $form[$key]['#default_value'] = $facet->getHierarchy()['config'][$key];
        }
        elseif (isset($this->defaultConfiguration()[$key])) {
          $form[$key]['#default_value'] = $this->defaultConfiguration()[$key];
        }
      }
    }
  }

}
