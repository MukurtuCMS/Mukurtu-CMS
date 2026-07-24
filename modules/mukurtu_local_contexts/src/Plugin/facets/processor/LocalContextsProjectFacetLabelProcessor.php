<?php

namespace Drupal\mukurtu_local_contexts\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Replaces LC project IDs with project titles in facet results.
 *
 * @FacetsProcessor(
 *   id = "local_contexts_project_facet_label_processor",
 *   label = @Translation("Local Contexts project facet label processor"),
 *   description = @Translation("Replaces the default label (project id) with the project title."),
 *   stages = {
 *     "build" = 50
 *   }
 * )
 */
class LocalContextsProjectFacetLabelProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    $projects = \Drupal::service('mukurtu_local_contexts.supported_project_manager')->getAllProjects();

    /** @var \Drupal\facets\Result\Result $result */
    foreach ($results as $result) {
      $projectId = $result->getRawValue();
      $result->setDisplayValue($projects[$projectId]['title'] ?? $this->t('Unknown Project'));
    }

    return $results;
  }

}
