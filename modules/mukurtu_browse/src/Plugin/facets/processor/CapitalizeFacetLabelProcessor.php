<?php

namespace Drupal\mukurtu_browse\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Provides a processor for CapitalizeFacetLabelProcessor. Capitalizes the first
 * character of facet titles.
 *
 * @FacetsProcessor(
 *   id = "capitalize_facet_label_processor",
 *   label = @Translation("Capitalize facet label processor"),
 *   description = @Translation("Capitalizes the first character of the facet title."),
 *   stages = {
 *     "build" = 50
 *   }
 * )
 */
class CapitalizeFacetLabelProcessor extends ProcessorPluginBase implements BuildProcessorInterface
{
  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results)
  {
    /* @var \Drupal\facets\Result\Result $result */
    foreach ($results as $result) {
      $facetTitle = $result->getDisplayValue();
      $result->setDisplayValue(ucfirst($facetTitle));
    }

    return $results;
  }
}
