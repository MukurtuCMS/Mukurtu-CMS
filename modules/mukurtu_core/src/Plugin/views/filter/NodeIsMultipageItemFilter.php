<?php

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\Core\Database\Query\SelectInterface;

/**
 * Filters content nodes by whether they are a page in a multipage item.
 *
 * @ViewsFilter("mukurtu_node_is_multipage_item")
 */
class NodeIsMultipageItemFilter extends NodeBooleanExistsFilterBase {

  protected function getSubquery(): SelectInterface {
    return $this->database->select('multipage_item__field_pages', 'mip')
      ->fields('mip', ['field_pages_target_id'])
      ->condition('mip.deleted', 0);
  }

}
