<?php

namespace Drupal\mukurtu_local_contexts\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Replaces LC label/notice compound IDs with display names in facet results.
 *
 * @FacetsProcessor(
 *   id = "local_contexts_label_facet_label_processor",
 *   label = @Translation("Local Contexts label facet label processor"),
 *   description = @Translation("Replaces the default label (compound project:id:type value) with the label/notice name."),
 *   stages = {
 *     "build" = 50
 *   }
 * )
 */
class LocalContextsLabelFacetLabelProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    $manager = \Drupal::service('mukurtu_local_contexts.supported_project_manager');

    $names = [];
    foreach ($manager->getAllLabels() as $label) {
      $key = $label['project_id'] . ':' . $label['id'] . ':' . $label['display'];
      $names[$key] = $label['name'] ?: $this->t('Unknown Label');
    }
    foreach ($manager->getAllNotices() as $notice) {
      $key = $notice['project_id'] . ':' . $notice['type'] . ':' . $notice['display'];
      $names[$key] = $notice['name'] ?: $this->t('Unknown Notice');
    }

    /** @var \Drupal\facets\Result\Result $result */
    foreach ($results as $result) {
      $rawValue = $result->getRawValue();
      $result->setDisplayValue($names[$rawValue] ?? $this->t('Unknown Label'));
    }

    return $results;
  }

}
