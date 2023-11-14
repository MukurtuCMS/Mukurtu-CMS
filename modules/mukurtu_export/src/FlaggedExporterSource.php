<?php

namespace Drupal\mukurtu_export;

class FlaggedExporterSource implements MukurtuExporterSourceInterface {
    /**
     * {@inheritdoc}
     */
    public function getEntities() {
        // Build the list of flagged entities.
        $flagMapping = [
            'node' => 'export_content',
            'media' => 'export_media',
        ];

        $uid = \Drupal::currentUser()->id();
        $database = \Drupal::database();

        $entities = [];
        foreach ($flagMapping as $entity_type_id => $flag_id) {
            $query = $database->query("SELECT id, entity_type, entity_id FROM {flagging} WHERE uid = :uid AND flag_id = :flag_id", [
                ':uid' => $uid,
                ':flag_id' => $flag_id,
            ]);
            $result = $query->fetchAllAssoc('entity_id');
            $ids = empty($result) ? [] : array_keys($result);
            $entities[$entity_type_id] = empty($ids) ? [] : array_combine($ids, $ids);
        }

        return $entities;
    }

}
