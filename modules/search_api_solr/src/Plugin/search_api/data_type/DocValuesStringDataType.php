<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\StringDataType;

/**
 * Provides a storage-only string data type.
 *
 * @SearchApiDataType(
 *   id = "solr_string_docvalues",
 *   label = @Translation("docValues-only"),
 *   description = @Translation("A docValues-only field. You can store any string and retrieve it from the index but you can't search through it. In oposite to storage-only, docValues will be stored, so the field is compatible to the export handler (and probably facets)."),
 *   fallback_type = "string",
 *   prefix = "zdv"
 * )
 */
class DocValuesStringDataType extends StringDataType {}
