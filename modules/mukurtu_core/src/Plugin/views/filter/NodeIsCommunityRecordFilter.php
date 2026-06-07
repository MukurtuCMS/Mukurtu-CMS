<?php

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\Core\Database\Query\SelectInterface;

/**
 * Filters content nodes by whether they are a community record.
 *
 * A node is a community record when it has a value in
 * node__field_mukurtu_original_record (i.e. it was created as a community
 * record version of an original digital heritage item).
 *
 * @ViewsFilter("mukurtu_node_is_community_record")
 */
class NodeIsCommunityRecordFilter extends NodeBooleanExistsFilterBase {

  protected function getSubquery(): SelectInterface {
    return $this->database->select('node__field_mukurtu_original_record', 'ocr')
      ->fields('ocr', ['entity_id'])
      ->condition('ocr.deleted', 0);
  }

}
