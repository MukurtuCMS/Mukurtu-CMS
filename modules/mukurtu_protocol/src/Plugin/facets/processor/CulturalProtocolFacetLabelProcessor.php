<?php

namespace Drupal\mukurtu_protocol\Plugin\facets\processor;

use Drupal\Core\Language\LanguageInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Replaces protocol IDs with protocol names in facet results.
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
class CulturalProtocolFacetLabelProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    /** @var \Drupal\facets\Result\Result $result */
    foreach ($results as $result) {
      $protocolId = trim($result->getDisplayValue(), '|');
      $protocolEntity = \Drupal::service('mukurtu_core.entity_translation_resolver')->loadTranslated('protocol', $protocolId);
      if ($protocolEntity) {
        $result->setDisplayValue($protocolEntity->getName());
      }
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   *
   * Overrides the inherited UncacheableDependencyTrait (which declares this
   * processor uncacheable, so it currently has no effect on the facet
   * block's cache metadata) so that once this processor becomes cacheable,
   * the display value's dependency on the active content language is
   * actually varied on. See Facet::calculateCacheDependencies(), which
   * merges every attached processor's cache contexts into the block.
   */
  public function getCacheContexts() {
    return ['languages:' . LanguageInterface::TYPE_CONTENT];
  }

}
