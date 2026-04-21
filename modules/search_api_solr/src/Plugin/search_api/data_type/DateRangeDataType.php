<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\DateDataType;

/**
 * Provides a date range data type.
 *
 * @SearchApiDataType(
 *   id = "solr_date_range",
 *   label = @Translation("Date range"),
 *   description = @Translation("Date field that contains date ranges."),
 *   fallback_type = "date",
 *   prefix = "dr"
 * )
 */
class DateRangeDataType extends DateDataType {
}
