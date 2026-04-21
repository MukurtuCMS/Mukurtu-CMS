<?php

namespace Drupal\facets\Plugin\facets\widget;

use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;
use Drupal\facets\Widget\WidgetPluginBase;

/**
 * A simple widget class that returns a simple array of the facet results.
 *
 * @FacetsWidget(
 *   id = "array",
 *   label = @Translation("Array with raw results"),
 *   description = @Translation("A widget that builds an array with results. This widget is not supposed to display any results, but it is needed for rest integration."),
 * )
 */
class ArrayWidget extends WidgetPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $results = $facet->getResults();

    $configuration = $facet->getWidget();
    $this->showNumbers = !empty($configuration['show_numbers']);

    return [
      $facet->getFieldIdentifier() => $this->buildOneLevel($results),
    ];
  }

  /**
   * Builds one level from results.
   *
   * @param \Drupal\facets\Result\ResultInterface[] $results
   *   A list of results.
   *
   * @return array
   *   Generated build.
   */
  protected function buildOneLevel(array $results): array {
    $items = [];

    foreach ($results as $result) {
      if (is_null($result->getUrl())) {
        $items[] = $this->generateValues($result);
      }
      else {
        $item = $this->prepare($result);
        if ($children = $result->getChildren()) {
          // @todo This is a useless nesting.
          $item['children'][] = $this->buildOneLevel($children);
        }
        $items[] = $item;
      }
    }

    return $items;
  }

  /**
   * Prepares the URL and values for the facet.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   A result item.
   *
   * @return array
   *   The results.
   */
  protected function prepare(ResultInterface $result) {
    return [
      'url' => $result->getUrl()->setAbsolute()->toString(),
      'raw_value' => $result->getRawValue(),
      'values' => $this->generateValues($result),
    ];
  }

  /**
   * Generates the value and the url.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   The result to extract the values.
   *
   * @return array
   *   The values.
   */
  protected function generateValues(ResultInterface $result) {
    $values['value'] = $result->getDisplayValue();

    if ($this->getConfiguration()['show_numbers'] && $result->getCount() !== FALSE) {
      $values['count'] = $result->getCount();
    }

    if ($result->isActive()) {
      $values['active'] = 'true';
    }

    return $values;
  }

}
