<?php

namespace Drupal\search_api_solr_test\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Defines the "Widget" data type.
 *
 * @DataType(
 *  id = "search_api_solr_test_widget",
 *  label = @Translation("Widget"),
 *  description = @Translation("A test widget."),
 *  definition_class = "\Drupal\search_api_solr_test\TypedData\WidgetDefinition"
 * )
 */
class Widget extends Map {}
