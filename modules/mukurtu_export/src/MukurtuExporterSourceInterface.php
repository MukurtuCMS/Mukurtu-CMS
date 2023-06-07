<?php

namespace Drupal\mukurtu_export;

interface MukurtuExporterSourceInterface {
    /**
     * Get entities to export.
     * 
     * @return mixed
     *  An associative array of entities to export. Structured by entity_type_id then entity ID for key and value,
     *  e.g., ['node' => [4 => 4, 6 => 6]].
     */
    public function getEntities();
}