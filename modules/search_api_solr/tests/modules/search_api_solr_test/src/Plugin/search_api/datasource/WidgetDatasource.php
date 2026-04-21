<?php

namespace Drupal\search_api_solr_test\Plugin\search_api\datasource;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Datasource\DatasourcePluginBase;

/**
 * Represents a datasource which exposes widgets.
 *
 * @SearchApiDatasource(
 *   id = "search_api_solr_test_widget",
 *   label = @Translation("Widgets"),
 *   description = @Translation("A test widget."),
 * )
 */
class WidgetDatasource extends DatasourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    return \Drupal::typedDataManager()->createDataDefinition('search_api_solr_test_widget')->getPropertyDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemId(ComplexDataInterface $item) {
    return 0;
  }

}
