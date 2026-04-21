<?php

namespace Drupal\facets\Processor;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\UncacheableDependencyTrait;
use Drupal\Core\Entity\EntityAccessControlHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Plugin\PluginBase;
use Drupal\facets\FacetInterface;

/**
 * A base class for plugins that implements most of the boilerplate.
 *
 * By default all plugins that will extend this class will disable facets
 * caching mechanism. It is strongly recommended to turn it on by implementing
 * own methods for the CacheableDependencyInterface interface.
 */
class ProcessorPluginBase extends PluginBase implements ProcessorInterface, CacheableDependencyInterface {

  use UncacheableDependencyTrait;
  use DependencyTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    // By default, there should be no config form.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function supportsStage($stage_identifier) {
    $plugin_definition = $this->getPluginDefinition();
    return isset($plugin_definition['stages'][$stage_identifier]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultWeight($stage) {
    $plugin_definition = $this->getPluginDefinition();
    return isset($plugin_definition['stages'][$stage]) ? (int) $plugin_definition['stages'][$stage] : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked() {
    return !empty($this->pluginDefinition['locked']);
  }

  /**
   * {@inheritdoc}
   */
  public function isHidden() {
    return !empty($this->pluginDefinition['hidden']);
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
    unset($this->configuration['facet']);
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->addDependency('module', $this->getPluginDefinition()['provider']);
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return NULL;
  }

  /**
   * Checks access for the given entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The entities for which to check access, passed by reference. Entities to
   *   which the current user does not have access will be removed.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet for which the entities were loaded.
   */
  protected function checkEntitiesAccess(
    array &$entities,
    FacetInterface $facet,
    EntityAccessControlHandlerInterface $access,
  ): void {
    foreach ($entities as $id => $entity) {
      $access_result = $access->access($entity, 'view', return_as_object: TRUE);
      $facet->addCacheableDependency($access_result);
      if (!$access_result->isAllowed()) {
        unset($entities[$id]);
      }
    }
  }

}
