<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use \Drupal\Core\Database\Database;


/**
 * Lookup a node's OG group.
 *
 * @MigrateProcessPlugin(
 *   id = "mukurtu_migrate_lookup_node_og_group"
 * )
 */
class LookupNodeOgGroup extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $key = $row->getSource()['key'] ?? NULL;
    if ($key) {
      if ($db = Database::getConnection('default', $key)) {
        $result = $db->query("SELECT gid FROM {og_membership} WHERE etid = :nid AND group_type = 'node' AND type = 'og_membership_type_default' AND state = 1 AND field_name = 'og_group_ref'",[
          ':nid' => $value,
        ]);
        $gids = $result->fetchAllAssoc('gid');
        if (!empty($gids)) {
          return array_keys($gids);
        }
      }
      return [];
    }
    return [];
  }
}
