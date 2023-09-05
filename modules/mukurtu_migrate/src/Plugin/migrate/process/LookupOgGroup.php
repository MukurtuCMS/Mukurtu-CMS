<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use \Drupal\Core\Database\Database;


/**
 * Lookup an entity's OG group.
 *
 * @MigrateProcessPlugin(
 *   id = "mukurtu_migrate_lookup_og_group"
 * )
 */
class LookupOgGroup extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $field_name = $this->configuration['group_field_name'] ?? 'og_group_ref';
    $group_type = $this->configuration['group_type'] ?? 'node';
    $key = $row->getSource()['key'] ?? NULL;
    if ($key) {
      if ($db = Database::getConnection('default', $key)) {
        $result = $db->query("SELECT gid FROM {og_membership} WHERE etid = :id AND group_type = :group_type AND type = 'og_membership_type_default' AND state = 1 AND field_name = :field_name",[
          ':id' => $value,
          ':field_name' => $field_name,
          ':group_type' => $group_type,
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
