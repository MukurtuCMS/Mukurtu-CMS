<?php

namespace Drupal\search_api_glossary\Plugin\facets\processor;

use Drupal\facets\Processor\SortProcessorPluginBase;
use Drupal\facets\Processor\SortProcessorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\Result;

/**
 * A processor that orders the results by display value.
 *
 * @FacetsProcessor(
 *   id = "glossaryaz_widget_order",
 *   label = @Translation("Sort by Glossary AZ"),
 *   description = @Translation("Sort order for Glossary AZ items."),
 *   stages = {
 *     "sort" = 100
 *   }
 * )
 */
class GlossaryAZWidgetOrderProcessor extends SortProcessorPluginBase implements SortProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function sortResults(Result $a, Result $b) {
    $group_a = $this->getResultGroup($a);
    $group_b = $this->getResultGroup($b);
    if ($group_a == $group_b) {
      // Apply natural sorting within single group.
      return strnatcasecmp($a->getRawValue(), $b->getRawValue());
    }
    else {
      // Get the custom sort order from config.
      $sort_options_by_weight = $this->sortConfigurationWeight($this->getConfiguration()['sort']);
      return $sort_options_by_weight[$group_a] < $sort_options_by_weight[$group_b] ? -1 : 1;
    }
  }

  /**
   * Returns glossary result group.
   */
  private function getResultGroup(Result $result) {
    // Is it a number? or maybe grouped number eg 0-9 (technically a string).
    if ($result->getRawValue() == '0-9' || ctype_digit($result->getRawValue()) || is_int($result->getRawValue())) {
      $group = 'glossaryaz_sort_09';
    }
    elseif ($result->getRawValue() == 'All') {
      $group = 'glossaryaz_sort_all';
    }
    // Is it alpha?
    elseif (ctype_alpha($result->getRawValue())) {
      $group = 'glossaryaz_sort_az';
    }
    // Non alpha numeric.
    else {
      $group = 'glossaryaz_sort_other';
    }

    return $group;
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    // Get the custom sort order from config.
    $sort_options_by_weight = $this->sortConfigurationWeight($this->getConfiguration()['sort']);

    // Initialise an empty array and populate
    // it with options in the same order as the sort
    // order defined in the config.
    $glossary_results = [];

    foreach ($sort_options_by_weight as $sort_option_by_weight_id => $sort_option_by_weight_weight) {
      $glossary_results[$sort_option_by_weight_id] = [];
    }

    // Since our new array is already in
    // the sort order defined in the config
    // lets step through the results and populate
    // results into respective containers.
    foreach ($results as $result) {
      // Is it a number? or maybe grouped number eg 0-9 (technically a string).
      if ($result->getRawValue() == '0-9' || ctype_digit($result->getRawValue()) || is_int($result->getRawValue())) {
        $glossary_results['glossaryaz_sort_09'][$result->getRawValue()] = $result;
      }
      // TODO Only add the all option if ALL processor is enabled.
      elseif ($result->getRawValue() == 'All') {
        $glossary_results['glossaryaz_sort_all'][$result->getRawValue()] = $result;
      }
      // Is it alpha?
      elseif (ctype_alpha($result->getRawValue())) {
        $glossary_results['glossaryaz_sort_az'][$result->getRawValue()] = $result;
      }
      // Non alpha numeric.
      else {
        $glossary_results['glossaryaz_sort_other'][$result->getRawValue()] = $result;
      }
    }

    ksort($glossary_results['glossaryaz_sort_az']);
    ksort($glossary_results['glossaryaz_sort_09']);
    ksort($glossary_results['glossaryaz_sort_other']);

    // Flatten the array to same structure as $results.
    $glossary_results_sorted = [];
    foreach ($glossary_results as $glossary_result) {
      if ($glossary_result) {
        $glossary_results_sorted = array_merge($glossary_results_sorted, $glossary_result);
      }
    }

    // And its done.
    return $glossary_results_sorted;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $processors = $facet->getProcessors();
    $config = isset($processors[$this->getPluginId()]) ? $processors[$this->getPluginId()] : NULL;

    // Get the weight options.
    $sort_options = !is_null($config) ? $config->getConfiguration()['sort'] : $this->defaultConfiguration();
    $sort_options_by_weight = $this->sortConfigurationWeight($sort_options);

    // TODO Only add the all option if ALL processor is enabled.
    // Resolve the issue when the ALL processor is enabled AFTER the
    // Sort options have been saved. In such case, the all option will not be
    // presented despite the processor being enabled because its not in the
    // $config array.
    // Build the form.
    $build['sort'] = [
      '#tree' => TRUE,
      '#type' => 'table',
      '#attributes' => [
        'id' => 'glossaryaz-sort-widget',
      ],
      '#header' => [
        $this->t('Sort By'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'glossaryaz-sort-weight',
        ],
      ],
    ];

    foreach ($sort_options_by_weight as $sort_option_key => $sort_option_weight) {
      $build['sort'][$sort_option_key]['#attributes']['class'][] = 'draggable';
      $build['sort'][$sort_option_key]['#attributes']['class'][] = 'glossaryaz-sort-weight--' . $sort_option_key;
      $build['sort'][$sort_option_key]['#weight'] = $sort_option_weight;
      $build['sort'][$sort_option_key]['sort_by']['#plain_text'] = $this->defaultConfiguration()[$sort_option_key]['name'];

      $build['sort'][$sort_option_key]['weight'] = [
        '#type' => 'weight',
        '#delta' => count($this->defaultConfiguration()),
        '#default_value' => $sort_option_weight,
        '#attributes' => [
          'class' => [
            'glossaryaz-sort-weight',
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $sort_options_deafult = [
      'glossaryaz_sort_az' => [
        'weight' => 1,
        'name' => $this->t('Alpha (A-Z)'),
      ],
      'glossaryaz_sort_09' => [
        'weight' => 2,
        'name' => $this->t('Numeric (0-9)'),
      ],
      'glossaryaz_sort_other' => [
        'weight' => 3,
        'name' => $this->t('Other (#)'),
      ],
      // TODO Only add the all option if ALL processor is enabled.
      'glossaryaz_sort_all' => [
        'weight' => -1,
        'name' => $this->t('All'),
      ],
    ];

    return $sort_options_deafult;
  }

  /**
   * {@inheritdoc}
   */
  public function sortConfigurationWeight($sort_options) {
    foreach ($sort_options as $sort_option_id => $sort_option) {
      $sort_options_by_weight[$sort_option_id] = $sort_option['weight'];
    }

    // Sort by weight options.
    asort($sort_options_by_weight);
    return $sort_options_by_weight;
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
