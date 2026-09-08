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
    $names = $manager->getLabelAndNoticeNames();

    /** @var \Drupal\facets\Result\Result $result */
    foreach ($results as $result) {
      $rawValue = $result->getRawValue();
      // Indexed values are already resolved display names (see
      // LocalContextsEffectiveLabelsProcessor), so the lookup above is
      // mostly a no-op for current content. Fall back to the raw value
      // itself, rather than an "unknown" placeholder, since it is already
      // human-readable text.
      $result->setDisplayValue($names[$rawValue] ?? $rawValue);
    }

    return $results;
  }

}
