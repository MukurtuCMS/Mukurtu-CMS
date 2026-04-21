<?php

namespace Drupal\facets\Plugin\facets\processor;

use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facets\Result\Result;

/**
 * Provides a processor that adds all siblings of the active item to the result.
 *
 * @FacetsProcessor(
 *   id = "show_siblings_processor",
 *   label = @Translation("Show siblings"),
 *   description = @Translation("Show all siblings of a hierarchical facet item. In 'Advanced settings' this processor should be executed early in the processor chain, for example before the URL handler and before ids get converted into titles. It is recommended to enable 'Use hierarchy' and 'Ensure that only one result can be displayed', too."),
 *   stages = {
 *     "build" = 11
 *   }
 * )
 */
class ShowSiblingsProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

  use UnchangingCacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    /** @var \Drupal\facets\Result\ResultInterface[] $results */
    if ($facet->getUseHierarchy()) {
      $rawValues = array_map(function ($result) {
        return $result->getRawValue();
      }, $results);
      $hierarchy = $facet->getHierarchyInstance();
      $facet->addCacheableDependency($hierarchy);
      foreach ($hierarchy->getSiblingIds($rawValues, $facet->getActiveItems(), $this->getConfiguration()['show_parent_siblings']) as $siblingId) {
        $results[] = new Result($facet, $siblingId, $siblingId, 0);
      }
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'show_parent_siblings' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $configuration = $this->getConfiguration();

    $build['show_parent_siblings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show parent siblings'),
      '#description' => $this->t('If selected the siblings of all (inactive) parents of an active item will be added be shown. Otherwise only the siblings of active items will be shown. (See "Keep hierarchy parents active".)'),
      '#default_value' => $configuration['show_parent_siblings'],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    return $facet->getFacetType() == 'facet_entity';
  }

}
