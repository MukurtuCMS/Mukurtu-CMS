<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use \Drupal\Core\Database\Database;


/**
 * Lookup an entity's OG groups.
 *
 * @MigrateProcessPlugin(
 *   id = "mukurtu_migrate_lookup_og_group"
 * )
 */
class LookupOgGroup extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // This is serious cheating. We need to know which database source to
    // lookup Mukurtu CMS v3 group memberships from. I don't really want to
    // write the whole chain of custom plugins just for this, a one off
    // migration. Here we create a dummy migration based on one of our known
    // migrations and get the database from its source. We fall back to standard
    // 'key' behavior if it doesn't have a connection.
    $mid = 'mukurtu_cms_v3_digital_heritage';
    $pmm = \Drupal::service('plugin.manager.migration');
    $migrations = $pmm->createInstances([$mid]);
    $key = $row->getSource()['key'] ?? 'migrate';
    if ($v3Migration = $migrations[$mid]) {
      $db = $v3Migration->getSourcePlugin()->getDatabase();
    } else {
      $db = Database::getConnection('default', $key);
    }

    $field_name = $this->configuration['group_field_name'] ?? 'og_group_ref';
    $group_type = $this->configuration['group_type'] ?? 'node';

    if ($db) {
      $result = $db->query("SELECT gid FROM {og_membership} WHERE etid = :id AND group_type = :group_type AND type = 'og_membership_type_default' AND state = 1 AND field_name = :field_name",[
        ':id' => $value,
        ':field_name' => $field_name,
        ':group_type' => $group_type,
      ]);
      $gids = $result->fetchAllAssoc('gid');
      if (!empty($gids)) {
        $multiple_gids = array_map(fn ($g) => ['target_id' => $g], array_keys($gids));
        return $multiple_gids;
      }
    }

    return [];
  }

}
