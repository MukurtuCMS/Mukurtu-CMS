<?php

namespace Drupal\mukurtu_collection\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\Views;

/**
 * Argument handler to scope a Content view to the items in a collection.
 *
 * Accepts the node ID of a Collection and filters the base Content query to
 * only the nodes referenced by that collection's field_items_in_collection,
 * ordered to match the collection's curated order.
 */
#[ViewsArgument(
  id: 'mukurtu_collection_items',
)]
class CollectionItemsArgument extends ArgumentPluginBase {

  public function query($group_by = FALSE): void {
    $this->ensureMyTable();

    $query = $this->query;
    if (!$query instanceof Sql) {
      return;
    }

    $configuration = [
      'table' => 'node__field_items_in_collection',
      'field' => 'field_items_in_collection_target_id',
      'left_table' => $this->tableAlias,
      'left_field' => 'nid',
      'operator' => '=',
      'extra' => [
        ['field' => 'deleted', 'value' => 0],
      ],
    ];
    $join = Views::pluginManager('join')->createInstance('standard', $configuration);
    $alias = $query->addTable('node__field_items_in_collection', $this->relationship, $join);

    $query->addWhere(0, "$alias.entity_id", $this->argument, '=');
    $query->addOrderBy($alias, 'delta', 'ASC');
  }

}
