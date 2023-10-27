<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use \Drupal\Core\Database\Database;

/**
 * Given a v3 TK label, transform it into a v4 TK label.
 *
 * @MigrateProcessPlugin(
 *   id = "transform_tk_label",
 *   handle_multiples = TRUE
 * )
 */
class TransformTkLabel extends ProcessPluginBase
{
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property)
  {
    // Cheat to get a db connection in here.
    $mid = 'mukurtu_cms_v3_legacy_tk_default_labels';
    $pmm = \Drupal::service('plugin.manager.migration');
    $migrations = $pmm->createInstances([$mid]);
    $key = $row->getSource()['key'] ?? 'migrate';
    if ($v3Migration = $migrations[$mid]) {
      $db = $v3Migration->getSourcePlugin()->getDatabase();
    } else {
      $db = Database::getConnection('default', $key);
    }

    if ($db) {
      $result = $db->query("SELECT value FROM {variable} WHERE name = :name", [
        ':name' => "mukurtu_use_sitewide_custom_tk_label_text",
      ]);
      $result = $result->fetchAllAssoc('value');

      // This is cheeky since it will only run once. I just don't know how to
      // unpackage this value.
      foreach ($result as $k => $v) {
        $unserializedVal = unserialize($k);
      }

      // Sitewide
      if ($unserializedVal) {
        $newValue = [];

        foreach ($value as $key => $val) {
          foreach ($val as $k => $labelValue) {
            $trimmed = str_replace('http://localcontexts.org/tk/', "", $labelValue);
            $toks = explode('/', $trimmed);
            $labelInitials = $toks[0];

            // Query for sitewide label with these label initials.
            $result = $db->query("SELECT value FROM {variable} WHERE name LIKE :name", [
              ':name' => 'mukurtu_customized_TK_%_(TK_' . strtoupper($labelInitials) . ')',
            ]);
            $result = $result->fetchAllAssoc('value');
            if ($result) {
              // There is a custom sitewide label for the migrated label, use that.
              $project_id = "sitewide_tk";
              $label_id = "sitewide_tk_" . $labelInitials;
            }
            else {
              // Use the default label and project.
              $project_id = "default_tk";
              $label_id = "default_tk_" . $labelInitials;
            }

            $labelOption = [
              'value' => $project_id . ':' . $label_id . ':' . 'label'
            ];

            array_push($newValue, $labelOption);
          }
        }

        return $newValue;
      }

      // Community
      else {
        // Get the community id from the row's field_community_ref field:
        // we only care about the first one haha.
        $communityId = $row->getSourceProperty('field_community_ref')[0]['nid'];
        $newValue = [];

        foreach ($value as $key => $val) {
          foreach ($val as $k => $labelValue) {
            $trimmed = str_replace('http://localcontexts.org/tk/', "", $labelValue);
            $toks = explode('/', $trimmed);
            $labelInitials = $toks[0];

            // Query for sitewide label with these label initials.
            $result = $db->query("SELECT value FROM {variable} WHERE name LIKE :name", [
              ':name' => 'mukurtu_comm_custom_TK_%_(TK_' . strtoupper($labelInitials) . ')',
            ]);
            $result = $result->fetchAllAssoc('value');
            if ($result) {
              // There is a custom community label for the migrated label, use that.
              $project_id = "comm_" . $communityId . "_tk";
              $label_id = $project_id . '_' . $labelInitials;
            } else {
              // Use the default label and project.
              $project_id = "default_tk";
              $label_id = "default_tk_" . $labelInitials;
            }

            $labelOption = [
              'value' => $project_id . ':' . $label_id . ':' . 'label'
            ];

            array_push($newValue, $labelOption);
          }
        }
        return $newValue;
      }
    }

    return [];
  }
}
