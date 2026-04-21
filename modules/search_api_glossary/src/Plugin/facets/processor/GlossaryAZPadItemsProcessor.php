<?php

namespace Drupal\search_api_glossary\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facets\Result\Result;

/**
 * Provides a processor to rewrite facet results to pad out missing alpha.
 *
 * @FacetsProcessor(
 *   id = "glossaryaz_pad_items_processor",
 *   label = @Translation("Add missing items to Glossary AZ"),
 *   description = @Translation("Rewrite facet results to pad out missing Glossary AZ"),
 *   stages = {
 *     "build" = 10
 *   }
 * )
 */
class GlossaryAZPadItemsProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    // All good?
    // Load up the search index and processor.
    $glossary_processor = $facet->getFacetSource()->getIndex()->getProcessor('glossary');
    $glossary_processor_config_fields = $glossary_processor->getConfig();

    // Resolve fields.
    $glossary_field_id = $facet->getFieldIdentifier();
    $parent_field_id = $glossary_processor->getFieldName($glossary_field_id);

    // Finally get group values.
    $glossary_az_grouping = array_values($glossary_processor_config_fields[$parent_field_id]['grouping']);

    $glossary_array = [];

    // TODO Dependency Inject.
    $glossary_helper = \Drupal::service('search_api_glossary.helper');
    $group_prefix = $glossary_helper->glossaryGetGroupNamePrefix();

    // If Alpha grouping is not set, pad alpha.
    if (!in_array('grouping_az', $glossary_az_grouping, TRUE)) {
      // TODO Figure out how to get AZ equivalent in native language.
      $glossary_array = array_merge($glossary_array, range('A', 'Z'));
    }
    else {
      $glossary_array[] = $group_prefix['alpha'];
    }

    // If Numeric grouping is not set, pad alpha.
    if (!in_array('grouping_09', $glossary_az_grouping, TRUE)) {
      // TODO Figure out how to get 09 equivalent in native language.
      $glossary_array = array_merge($glossary_array, array_map('strval', range('0', '9')));
    }
    else {
      $glossary_array[] = $group_prefix['numeric'];
    }

    // Do we have Non Alpha Numeric grouping?
    if (in_array('grouping_other', $glossary_az_grouping, TRUE)) {
      // To get # equivalent in native language, change the settings YAML.
      $glossary_array[] = $group_prefix['special'];
    }

    // Generate keys from values.
    $glossary_missing = array_combine($glossary_array, $glossary_array);

    /** @var \Drupal\facets\Result\ResultInterface $result */
    foreach ($results as $result) {
      $result_glossary = $result->getDisplayValue();

      // Items that exist in result, remove them from sample array.
      if (in_array($result_glossary, $glossary_missing)) {
        unset($glossary_missing[$result_glossary]);
      }
    }

    // Loop over the missing items and add them.
    foreach ($glossary_missing as $glossary) {
      $results[] = new Result($facet, $glossary, $glossary, 0);
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    // Check if
    // 1) The correct widget is chosen for the facet
    // 2) If the glossary processor is enabled in Search API index.
    $widget = $facet->getWidget()['type'];
    $search_processors = $facet->getFacetSource()->getIndex()->getProcessors();

    if ($widget == 'glossaryaz' && array_key_exists('glossary', $search_processors)) {
      // Glossary processor is enabled.
      return TRUE;
    }

    return FALSE;
  }

}
