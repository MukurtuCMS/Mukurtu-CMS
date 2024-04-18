<?php

namespace Drupal\mukurtu_dictionary\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Provides a processor for CulturalProtocolFacetLabelProcessor.
 *
 * @FacetsProcessor(
 *   id = "cultural_protocol_facet_label_processor",
 *   label = @Translation("Cultural protocol facet label processor"),
 *   description = @Translation("Replaces the default label (protocol id) with the protocol name."),
 *   stages = {
 *     "build" = 50
 *   }
 * )
 */
class CulturalProtocolFacetLabelProcessor extends ProcessorPluginBase implements BuildProcessorInterface
{
  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results)
  {
    /* @var \Drupal\facets\Result\Result $result */
    foreach ($results as $result) {
      $protocolId = trim($result->getDisplayValue(), '|');
      $protocolEntity = \Drupal::entityTypeManager()->getStorage('protocol')->load($protocolId);
      $result->setDisplayValue($protocolEntity->getName());
    }

    return $results;
  }

}
