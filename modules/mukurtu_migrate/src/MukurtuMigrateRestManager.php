<?php

namespace Drupal\mukurtu_migrate;

use Exception;
use stdClass;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Language\Language;

class MukurtuMigrateRestManager {
  protected $sourceUrl;
  protected $sourceUser;
  protected $sourcePassword;
  protected $sourceCookie;

  protected $taxonomyTable;
  protected $nodeTable;
  protected $mediaTable;
  protected $importTable;
  protected $oldToNewLookupTable;

  protected $fieldMappings;
  protected $vocabMappings;
  protected $fieldTransforms;
  protected $migrationSteps;

  protected $currentStep;
  protected $currentEntityType;
  protected $currentBundle;
  protected $currentOffset;
  protected $itemsPerBatch;
  protected $openProtocolDeleteTag;

  public function __construct() {
    // Default field mappings. Mukurtu v2 -> Mukurtu v4.
    $this->fieldMappings = [
      'default' => [
        'language' => 'langcode',
      ],
      'taxonomy_vocabulary' => [
        'default' => [
          'name' => 'name',
          'machine_name' => 'vid',
          'vid' => 'old_id',
          'description' => 'description',
        ],
      ],
      'taxonomy_term' => [
        'default' => [
          'tid' => 'old_id',
          'name' => 'name',
          'description' => 'description',
          'vocabulary_machine_name' => 'vid',
        ],
      ],
      'node' => [
        'default' => [
          'nid' => 'old_id',
          'title' => 'title',
          'status' => 'status',
          'type' => 'type',
          'field_identifier' => 'field_identifier',
          'field_tk_body' => 'field_traditional_knowledge',
          'field_category' => 'field_category',
          'field_description' => 'field_description',
        ],
        'digital_heritage' => [
          'body' => 'field_cultural_narrative',
          'field_tags' => 'field_keywords',
          'field_media_asset' => 'field_media_assets',
        ],
/*         'protocol' => [
          'og_group_ref' => 'field_mukurtu_community',
        ], */
        'cultural_protocol_group' => [
          'og_group_ref' => 'field_mukurtu_community',
        ],
      ],
      'media' => [
        'default' => [
          'sid' => 'old_id',
          'type' => 'bundle',
          'title' => 'name',
        ],
        'image' => [
          'base_id' => 'field_media_image',
        ],
      ],
    ];

    // Mukurtu 2 vs Mukurtu 4 vocabulary machine names.
    $this->vocabMappings = [
      'scald_authors' => 'authors',
      'tags' => 'keywords',
    ];

    $this->itemsPerBatch = 20;

    // Default Migration Steps.
    $this->migrationSteps = [
      ['entity_type' => 'taxonomy_vocabulary', 'bundle' => ''],
      ['entity_type' => 'taxonomy_term', 'bundle' => ''],
      ['entity_type' => 'node', 'bundle' => 'community'],
      [
        'entity_type' => 'node',
        'bundle' => 'protocol',
        'bundle_v2' => 'cultural_protocol_group',
      ],
      ['entity_type' => 'node', 'bundle' => 'digital_heritage'],
    ];

    /** @var PrivateTempStoreFactory $private_tempstore */
    $private_tempstore = \Drupal::service('tempstore.private');
    $migrate_tempstore = $private_tempstore->get('mukurtu_migrate');
    try {
      $this->sourceUrl = $migrate_tempstore->get('migration_source_url');
      $this->sourceUser = $migrate_tempstore->get('migration_source_username');
      $this->sourcePassword = $migrate_tempstore->get('migration_source_password');
      $this->sourceCookie = $migrate_tempstore->get('migration_source_cookie');
    } catch (Exception $e) {
      dpm($e->getMessage());
    }

    $this->openProtocolDeleteTag = "!!!mukurtu_migrate:open!!!";
  }

  /**
   * Return the local ID for existing content given entity type and remote ID.
   */
  protected function getPreviouslyImported($entity_type, $remote_id) {
    // Taxonomy Vocabulary.
    if ($entity_type == 'taxonomy_vocabulary') {
      if (isset($this->importTable['taxonomy_vocabulary'][$remote_id])) {
        // Get the remote vocab name from the manifest.
        $remote_machine_name = $this->importTable['taxonomy_vocabulary'][$remote_id]->machine_name;

        // If we've renamed that vocab for Mukurtu 4, use that name.
        $remote_machine_name = $this->vocabMappings[$remote_machine_name] ?? $remote_machine_name;

        // Try and load a local vocab with that name.
        $local_vocab = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($remote_machine_name);
        if ($local_vocab) {
          return $local_vocab->id();
        }
      }
      return NULL;
    }

    // Taxonomy Term.
    if ($entity_type == 'taxonomy_term') {
      if (isset($this->importTable['taxonomy_term'][$remote_id])) {
        // Get the taxonomy term name from the manifest. For migrate, we are treating names as unique.
        $term_name = $this->importTable['taxonomy_term'][$remote_id]->name;

        // Get the remote vocab ID from the manifest.
        $vid = $this->importTable['taxonomy_term'][$remote_id]->vid;

        if (isset($this->importTable['taxonomy_vocabulary'][$vid])) {
          // Lookup the remote vocab name from the manifest using that ID.
          $vocab_name = $this->importTable['taxonomy_vocabulary'][$vid]->machine_name;

          // If we've renamed that vocab for Mukurtu 4, use that name.
          $vocab_name = $this->vocabMappings[$vocab_name] ?? $vocab_name;

          // Try and load a local taxonomy term with that name/vocab.
          $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
            ->loadByProperties(['name' => $term_name, 'vid' => $vocab_name]);
          $term = reset($term);
          if ($term) {
            return $term->id();
          }
        }
      }
      return NULL;
    }

    // Media/Scald Atoms.
    if ($entity_type == 'scald_atom' || $entity_type == 'media') {
      // Try and load a local media item with that ID.
      $msg = $this->makeRevisionMessage('media', $remote_id);

      $query = \Drupal::entityQuery('media')
        ->latestRevision()
        ->condition('revision_log_message', $msg, '=');

      $ids = $query->execute();

      if (count($ids) == 1) {
        dpm("I found media with msg = $msg");
        dpm($ids);
        return reset($ids);
      }

      return NULL;
    }

    if ($entity_type == 'file') {
      $msg = $this->makeRevisionMessage($entity_type, $remote_id);

      $query = \Drupal::entityQuery($entity_type)
        ->condition('filename', $msg, 'CONTAINS');

      $ids = $query->execute();

      if (count($ids) == 1) {
        return reset($ids);
      }

      return NULL;
    }

    // Nodes.
    $msg = $this->makeRevisionMessage($entity_type, $remote_id);
    $query = \Drupal::entityQuery($entity_type)
      ->latestRevision()
      ->condition('revision_log', $msg, '=');

    $ids = $query->execute();

    if (empty($ids)) {
      return NULL;
    }

    if (count($ids) == 1) {
      return reset($ids);
    } else {
      dpm("getPreviouslyImported($entity_type, $remote_id) returned too many results");
      dpm($ids);
    }

    return NULL;
  }

  /**
   * Given entity type/bundle/field name, return the new field name.
   */
  protected function mapField($entity_type, $bundle, $field_name) {
    if (!isset($this->fieldMappings[$entity_type])) {
      return NULL;
    }

    // Check most specific case first, type/bundle/fieldname given.
    if (isset($this->fieldMappings[$entity_type][$bundle][$field_name])) {
     return ['field_name' => $this->fieldMappings[$entity_type][$bundle][$field_name]];
    }

    // Try the default for the given entity type.
    if (isset($this->fieldMappings[$entity_type]['default'][$field_name])) {
      return ['field_name' => $this->fieldMappings[$entity_type]['default'][$field_name]];
    }

    // Try the global default.
    if (isset($this->fieldMappings['default'][$field_name])) {
      return ['field_name' => $this->fieldMappings['default'][$field_name]];
    }

    return NULL;
  }

  protected function translateTargetId($id_type, $target_id) {
    $lookup = [
      'tid' => 'taxonomy_term',
      'vid' => 'taxonomy_vocabulary',
      'nid' => 'node',
      'sid' => 'scald_atom',
      'target_id' => 'node',
    ];

    if (isset($lookup[$id_type])) {
      $new_id = $this->getPreviouslyImported($lookup[$id_type], $target_id);
      if (is_null($new_id)) {
        // If this is a media reference that hasn't been imported, try and import it.
        // This is temporary until we make a REST index for Mukurtu 2 scald atoms.
        if ($id_type == 'sid') {
          $uri = "{$this->sourceUrl}/scald/sid/$target_id";
          $media_id = $this->importByURI('media', $uri);
          if ($media_id) {
            return $media_id;
          }
        }
        dpm("I didn't find an existing $id_type with ID $target_id");
      }
      return $new_id;
    }

    dpm("translateTargetId($id_type, $target_id) = NULL");
    return NULL;
  }

  /**
   * Return the ID fieldname for a given entity type.
   */
  protected function getIdFieldname($entity_type) {
    switch ($entity_type) {
      case 'taxonomy_term':
        return 'tid';

      case 'taxonomy_vocabulary':
        return 'vid';

      case 'node':
      default:
        return 'nid';

    }
  }

  protected function migrate_entity_reference($value) {
    if (is_object($value)) {
      $new_value = [];
      foreach ($value as $lang => $targets) {
        if (is_array($targets)) {
          foreach ($targets as $delta => $target) {
            foreach ($target as $id => $target_id) {
              $new_target = $this->translateTargetId($id, $target_id);
              if ($new_target) {
                $new_value[] = ['value' => $new_target];
              }
            }
          }
        }
      }

      return $new_value;
    }

    return $value;
  }

  protected function migrate_text_with_summary($value) {
    if (is_object($value)) {
      $new_value = [];
      foreach ($value as $lang => $text_values) {
        if (is_array($text_values)) {
          foreach ($text_values as $delta => $text_value) {
            if (isset($text_value->format)) {
              switch ($text_value->format) {
                case 'full_html':
                case 'ds_code':
                  $text_value->format = 'full_html';
                  break;

                case 'filtered_html':
                  $text_value->format = 'restricted_html';
                  break;

                default:
                  $text_value->format = 'basic_html';
              }
            }
            $new_value[] = $text_value;
          }
        }
      }

      return $new_value;
    }

    return $value;
  }

  protected function migrate_image($value) {
    $id = $this->getPreviouslyImported('file', $value);
    if ($id) {
      return [['target_id' => $id]];
    }

    // TESTING.
    return [['target_id' => 11]];//[['target_id' => 11]];
  }


  /**
   * Migrate a field value.
   *
   * This should be very specific Mukurtu 2 -> 4 behavior, general stuff
   * should be handled in the serializer/normalizer.
   */
  protected function migrateValue($entity_type, $bundle, $field_name, $value) {
    $field_type_ftn = '';
    if (isset($this->importTable['field_definitions'][$entity_type][$bundle][$field_name])) {
      $field_type_ftn = 'migrate_' . $this->importTable['field_definitions'][$entity_type][$bundle][$field_name]->getType();
    } elseif (isset($this->importTable['field_definitions'][$entity_type][$field_name])) {
      $field_type_ftn = 'migrate_' . $this->importTable['field_definitions'][$entity_type][$field_name]->getType();
    }

    // One off for protocol community handler.
    if ($entity_type == 'node' && $bundle == 'cultural_protocol_group' && $field_name == 'field_mukurtu_community') {
      $field_type_ftn = 'migrate_entity_reference';
    }

    // Check if we have a migration method for this field type.
    if (method_exists($this, $field_type_ftn)) {
      $value = $this->{$field_type_ftn}($value);
    }

    if ($field_name == 'field_media_assets') {
      dpm("$entity_type:$bundle:$field_name");
      dpm($value);
      // TODO: Placeholder, replace.
      $value = [['value'=> 4]];
    }

    // These are all special one off migration cases.
    if ($entity_type == 'taxonomy_vocabulary') {
      // Convert vocab names.
      if ($field_name == 'vid') {
        return $this->vocabMappings[$value] ?? $value;
      }

      // These can't be an array.
      if ($field_name == 'name' || $field_name == 'description') {
        return $value;
      }
    }

    if ($entity_type == 'taxonomy_term') {
      if ($field_name == 'vid') {
        return $this->vocabMappings[$value] ?? $value;
      }
    }

    if ($entity_type == 'media') {
      if ($field_name == 'bundle') {
        return $value;
      }
    }

    return is_array($value) ? $value : [$value];
  }

  /**
   * Take Mukurtu v2 JSON and convert to Mukurtu v4 JSON.
   */
  protected function migrateItem($entity_type, $json) {
    $item = json_decode($json);

    $bundle = ($entity_type == 'taxonomy_vocabulary' || $entity_type == 'taxonomy_term') ? '' : $item->type;

    // Migrate the values from the incoming JSON to v4 format/schema.
    $new_item = new stdClass();
    foreach ($item as $key => $value) {
      $fieldMapping = $this->mapField($entity_type, $bundle, $key);

      if ($fieldMapping) {
        // TODO: Do any required processing to the new field value.
        $new_item->{$fieldMapping['field_name']} = $this->migrateValue($entity_type, $bundle, $fieldMapping['field_name'], $value);
      }
    }

    // Handle item under Protocols.
    if ($entity_type == 'node' && isset($this->importTable['field_definitions'][$entity_type][$bundle]['field_mukurtu_protocol_r_scope'])) {
      // Set to most retrictive first.
      $new_item->field_mukurtu_protocol_r_scope = ['value' => 'personal'];
      $new_item->field_mukurtu_protocol_w_scope = ['value' => 'personal'];
      if (isset($item->og_group_ref) && !empty($item->og_group_ref->{Language::LANGCODE_NOT_SPECIFIED})) {
        $all_open = TRUE;
        $strict_protocols = [];
        foreach ($item->og_group_ref->{Language::LANGCODE_NOT_SPECIFIED} as $delta => $item_protocol) {
          $protocol_id = $this->getPreviouslyImported('node', $item_protocol->target_id);
          $protocol_entity = \Drupal::entityTypeManager()->getStorage('node')->load($protocol_id);
          if ($protocol_entity) {
            // Item has at least one strict protocol.
            if (stripos($protocol_entity->title->value, $this->openProtocolDeleteTag) === FALSE) {
              $all_open = FALSE;
              $strict_protocols[] = ['value' => $protocol_entity->id()];
            }
          }
        }

        // If all the item's protocols are open, set the migrated item's scope to public.
        if ($all_open) {
          $new_item->field_mukurtu_protocol_r_scope = ['value' => 'public'];
          $new_item->field_mukurtu_protocol_w_scope = ['value' => 'public'];
        } else {
          // Some or all of the protocols were strict, get any/all setting.
          if (count($strict_protocols) > 0 && isset($item->field_item_privacy_setting->{Language::LANGCODE_NOT_SPECIFIED}[0])) {
            $new_item->field_mukurtu_protocol_r_scope = $item->field_item_privacy_setting->{Language::LANGCODE_NOT_SPECIFIED}[0];
            $new_item->field_mukurtu_protocol_w_scope = $item->field_item_privacy_setting->{Language::LANGCODE_NOT_SPECIFIED}[0];
            $new_item->field_mukurtu_protocol_read = $strict_protocols;
            $new_item->field_mukurtu_protocol_write = $strict_protocols;
          }
        }
      }
    }

    // Swtich back from v2 -> v4 bundle names.
    if ($entity_type == 'node') {
      if ($bundle == 'cultural_protocol_group') {
        $bundle = 'protocol';
        $item->type = $bundle;

        // Mark open protocols so we can delete them later.
        if (isset($item->group_access->und[0]->value) && $item->group_access->und[0]->value == "0") {
          $item->title = $this->openProtocolDeleteTag . ' ' . $item->title;
        }
      }
    }

    // Set the type so the entity validation works.
    if ($bundle != '' && $entity_type != 'media') {
      $new_item->type = $bundle;
    }

    // Now we need to determine if we are creating a new entity or updating an
    // existing one.
    $old_id = $new_item->old_id[0] ?? NULL;

    if ($old_id) {
      // "old_id" is book-keeping that we don't want to save in the entity.
      unset($new_item->old_id);

      // Check for an existing local ID for this entity.
      $existing_id = $this->getPreviouslyImported($entity_type, $old_id);
      if ($existing_id) {
        // It is an existing item, add the existing ID to the migrated item
        // so that we don't create a duplicate.
        $id_fieldname = $this->getIdFieldname($entity_type);

        if ($id_fieldname) {
          $new_item->{$id_fieldname} = $this->migrateValue($entity_type, $bundle, $id_fieldname, $existing_id);
        }
      }
    }

    return ['old_id' => $old_id, 'local_id' => $existing_id, 'json' => json_encode($new_item)];
  }

  /**
   * Make a unique revision log message.
   */
  protected function makeRevisionMessage($entity_type, $id) {
    $url = strtolower($this->sourceUrl);
    $url = str_replace('http://', '', $url);
    $url = str_replace('https://', '', $url);
    return "mukurtu_migrate:{$url}:{$entity_type}:{$id}";
  }

  /**
   * Import a single item by fetching its JSON record.
   */
  protected function importByURI($entity_type, $uri) {
    $response = $this->remoteCall('GET', $uri);

    if ($response['HTTP_CODE'] == 200) {
      // Map and process fields.
      $migratedItem = $this->migrateItem($entity_type, $response['body']);

      // Determine entity class for the serializer.
      $entityClass = \Drupal\node\Entity\Node::class;
      if ($entity_type == 'taxonomy_vocabulary') {
        $entityClass = \Drupal\taxonomy\Entity\Vocabulary::class;
      }
      if ($entity_type == 'taxonomy_term') {
        $entityClass = \Drupal\taxonomy\Entity\Term::class;
      }
      if ($entity_type == 'media' || $entity_type == 'scald_atom') {
        $entityClass = \Drupal\media\Entity\Media::class;
      }

      // Run the resulting JSON through the deserializer.
      $serializer = \Drupal::service('serializer');

      try {
        $entity = $serializer->deserialize($migratedItem['json'], $entityClass, 'json');

        // Mark it as fromImport, this stops automatic protocol creation, etc.
        $entity->fromImport = TRUE;

        // The item already exists and we should do an update.
        if (isset($migratedItem['local_id']) && !is_null($migratedItem['local_id'])) {
          // Load the local entity.
          $existing_entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($migratedItem['local_id']);

          // Copy the remote field values onto the existing entity.
          foreach ($entity as $fieldname => $value) {
            $existing_entity->{$fieldname} = $value;
          }
          $entity = $existing_entity;
        }

        // Mark the entity revision with the old ID so we can find it on later runs.
        if (method_exists($entity, 'setRevisionLogMessage') && isset($migratedItem['old_id']) && $migratedItem['old_id']) {
          $entity->setRevisionLogMessage($this->makeRevisionMessage($entity_type, $migratedItem['old_id']));
        }

        // Validate the entity, if possible.
        if (method_exists($entity, 'validate')) {
          // Check if the entity validates.
          $violations = $entity->validate();
          if ($violations->count() > 0) {
            // Validation failed. Log and move on.
            dpm("Failed to import $uri");
            foreach ($violations as $violation) {
              //$msg = $violations[0]->getMessage();
              $badField = $violation->getPropertyPath();
              dpm("Field Violation: $badField");
              dpm($entity->get($badField)->value);
            }
          } else {
            // Validation succeeded, save the entity.
            $entity->save();
            return $entity->id();
          }
        } else {
          // Not all types support validiation, role the dice and hope it saves.
          $entity->save();
          return $entity->id();
        }
      } catch (Exception $e) {
        dpm("Failed to import $uri");
        dpm($e->getMessage());
      }
    } else {
      dpm("Need to log error");
      dpm($response);
    }

    return NULL;
  }


  /**
   * Return the total number of items to process.
   */
  public function getMigrationStepCount() {
    $items = 0;
    foreach ($this->migrationSteps as $step) {
      if (isset($this->importTable[$step['entity_type']][$step['bundle']])) {
        $items += count($this->importTable[$step['entity_type']][$step['bundle']]);
      } elseif (isset($this->importTable[$step['entity_type']])) {
        $items += count($this->importTable[$step['entity_type']]);
      }
    }

    return $items;
  }

  /**
   * Run a batch of imports.
   */
  public function processBatch($context) {
    // Fresh run.
    if (!isset($context['step'])) {
      $context['step'] = 0;
      $context['entity_type'] = $this->migrationSteps[$context['step']]['entity_type'];
      $context['bundle'] = $this->migrationSteps[$context['step']]['bundle'];
      $context['total_items_processed'] = 0;
      $context['offset'] = 0;
    }

    $entity_type = $context['entity_type'];
    $bundle = $context['bundle'];

    // Convert v4 -> v2 bundles.
    if (isset($this->migrationSteps[$context['step']]['bundle_v2'])) {
      $bundle = $this->migrationSteps[$context['step']]['bundle_v2'];
    }

    $offset = $context['offset'] ?? 0;
    $items_per_batch = $context['items_per_batch'] ?? $this->itemsPerBatch;

    // Break off the amount of items we are processing this batch.
    if ($entity_type == 'taxonomy_vocabulary' || $entity_type == 'taxonomy_term') {
      $batch = array_slice($this->importTable[$entity_type], $offset, $items_per_batch);
    } else {
      $import_table = $this->importTable[$entity_type][$bundle] ?? [];
      $batch = array_slice($import_table, $offset, $items_per_batch);
    }

    if (is_null($batch)) {
      $batch = [];
    }

    // Import them by fetching their individual JSON records.
    foreach ($batch as $item) {
      $this->importByURI($entity_type, $item->uri);
    }

    // Update based on how many items we processed.
    $processed = count($batch);
    $context['total_items_processed'] += $processed;
    $context['offset'] += $processed;

    // Move on to the next step when we exhaust the current step.
    if ($processed < $items_per_batch) {
      // Is this the last step?
      if (!isset($this->migrationSteps[$context['step'] + 1])) {
        // It was the last step, we're done.
        $context['done'] = TRUE;
      } else {
        // There are more steps, set the context up for the next step.
        $context['done'] = FALSE;
        $context['step'] += 1;
        $context['entity_type'] = $this->migrationSteps[$context['step']]['entity_type'];
        $context['bundle'] = $this->migrationSteps[$context['step']]['bundle'];
        $context['offset'] = 0;
      }
    }

    return $context;
  }

  protected function loadJsonFile($path) {
    if (file_exists($path)) {
      $raw = file_get_contents($path);
      $data = json_decode($raw);
    }

    if (!$data) {
      return [];
    }
    return $data;
  }

  public function buildVocabularyTable($vocabFile, $termsFile) {
    $vocabs = $this->loadJsonFile($vocabFile);
    $terms = $this->loadJsonFile($termsFile);

    $table = [];
    $entityFieldManager = \Drupal::service('entity_field.manager');

    // Index all the vocabularies.
    foreach ($vocabs as $vocab) {
      $table[$vocab->vid] = $vocab;
      $this->importTable['field_definitions']['taxonomy_term'][$vocab->machine_name] = $entityFieldManager->getFieldDefinitions('taxonomy_term', $vocab->machine_name);
    }

    // Index all terms and attach to the vocabulary table.
    foreach ($terms as $term) {
      // We are harvesting the vocab that this term exists in.
      if (isset($term->vid) && isset($table[$term->vid])) {
        if (!isset($table[$term->vid]->terms)) {
          $table[$term->vid]->terms = [];
        }
        $table[$term->vid]->terms[$term->tid] = $term;
        $this->importTable['taxonomy_term'][$term->tid] = $term;
      }
    }

    $this->importTable['taxonomy_vocabulary'] = $table;

    return $table;
  }

  /**
   * Build the table of nodes to import.
   */
  public function buildNodeTable($nodeFile) {
    $nodes = $this->loadJsonFile($nodeFile);

    $table = [];
    $entityFieldManager = \Drupal::service('entity_field.manager');

    // Index all nodes by type, then nid.
    foreach ($nodes as $node) {
      if (!isset($table[$node->type])) {
        $table[$node->type] = [];
        $this->importTable['field_definitions']['node'][$node->type] = $entityFieldManager->getFieldDefinitions('node', $node->type);
      }
      $table[$node->type][$node->nid] = $node;
    }

    // Load the media entity field definitions as well.
    foreach (['image', 'audio', 'document', 'remote_video', 'video'] as $media_type) {
      $this->importTable['field_definitions']['media'][$media_type] = $entityFieldManager->getFieldDefinitions('media', $media_type);
    }

    $this->importTable['node'] = $table;
    return $table;
  }

  public function buildMediaTable($mediaFile) {
    $table = [];
    $this->importTable['media'] = $table;
    return $table;
  }

  public function summarizeImportData($manifest) {
    $taxTable = (isset($manifest['vocab']) && isset($manifest['terms'])) ? $this->buildVocabularyTable($manifest['vocab'], $manifest['terms']) : [];
    $nodeTable = isset($manifest['nodes']) ? $this->buildNodeTable($manifest['nodes']) : [];
    $summary = "";

    if (!empty($taxTable)) {
      $summary .= "<h3>Taxonomy Vocabularies and Terms</h3><ul>";
      foreach ($taxTable as $vocab) {
        $terms_in_vocab = isset($vocab->terms) ? count($vocab->terms) : 0;
        $summary .= "<li>{$vocab->name} ({$vocab->machine_name}): $terms_in_vocab terms</li>";
      }
      $summary .= "</ul>";
    }

    if (!empty($nodeTable)) {
      $summary .= "<h3>Nodes</h3><ul>";
      foreach ($nodeTable as $type => $nodes) {
        $typeCount = count($nodes);
        $summary .= "<li>$typeCount nodes of type $type</li>";
      }
      $summary .= "</ul>";
    }

    return $summary;
  }

  /**
   * Fetch results from the given endpoint, starting at the given offset.
   */
  public function fetchBatch($endpoint, $offset = 0, $number = 10, $itemsPerPage = 20) {
    $batch = [];
    $page = 0;
    $data = [];
    if ($offset >= $itemsPerPage) {
      $page = intdiv($offset, $itemsPerPage);
      $data = ['page' => $page];
    }

    $pageOffset = $offset - ($page * $itemsPerPage);
    $response = $this->remoteCall('GET', $endpoint, $data);
    if ($response['HTTP_CODE'] == 200) {
      $responseData = json_decode($response['body']);

      if (empty($responseData) || count($responseData) == 0) {
        return $batch;
      }
      $batch = array_slice($responseData, $pageOffset, $number);
      $size = count($batch);

      if ($size > 0 && $number - $size > 0) {
        return array_merge($batch, $this->fetchBatch($endpoint, $offset + $size, $number - $size, $itemsPerPage));
      }
    }

    return $batch;
  }

  /**
   * Authenticate to the remote site using the given credentials.
   */
  public function login($url, $user, $password) {
    $this->sourceUrl = $url;
    $this->sourceUser = $user;
    $this->sourcePassword = $password;

/*     $tempstore = \Drupal::service('tempstore.private')->get('mukurtu_migrate');
    $tempstore->set('source_url', $url);
    $tempstore->set('source_username', $user);
    $tempstore->set('source_password', $password); */

    return $this->authenticate();
  }

  /**
   * Authenticate to the remote site using the stored credentials.
   */
  public function authenticate() {
    $data = [
      'username' => $this->sourceUser,
      'password' => $this->sourcePassword,
    ];
    $response = $this->remoteCall("POST", "/user/login", $data, FALSE);

    if ($response['HTTP_CODE'] == 200) {
      $body = json_decode($response['body']);
      if (isset($body->session_name)) {
        $this->sourceCookie = $body->session_name . '=' . $body->sessid;
        $tempstore = \Drupal::service('tempstore.private')->get('mukurtu_migrate');
        $tempstore->set('migration_source_cookie', $this->sourceCookie);
      }

      return TRUE;
    }
    return FALSE;
  }

  /**
   * Make a REST API call to the Mukurtu Mobile interface of the remote site.
   */
  private function remoteCall($method, $endpoint, $data = FALSE, $retry = TRUE) {
    if (stripos($endpoint, $this->sourceUrl) === 0) {
      $url = $endpoint;
    } else {
      $url = $this->sourceUrl . $endpoint;
    }
    $http_header = [];

    $curl = curl_init();

    switch ($method) {
      case "DELETE":
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        break;

      case "PATCH":
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');

        if ($data) {
          $http_header[] = 'Content-Type: application/json';
          curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        break;

      case "POST":
        curl_setopt($curl, CURLOPT_POST, 1);

        if ($data) {
          $http_header[] = 'Content-Type: application/json';
          curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        break;

      case "PUT":
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
          $http_header[] = 'Content-Type: application/json';
          curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        break;

      default:
        if ($data) {
          $http_header[] = 'Content-Type: application/json';
          $url = sprintf("%s?%s", $url, http_build_query($data));
        }
    }

    if ($endpoint != '/user/login') {
      $http_header[] = 'Cookie: ' . $this->sourceCookie;
    }
    curl_setopt($curl, CURLOPT_HTTPHEADER, $http_header);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    // Send the request.
    $result['body'] = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $result['HTTP_CODE'] = $http_code;
    curl_close($curl);

    // If request was forbidden and auth was selected, try re-authenticating and repeat the request.
    if (($http_code == 403 || $http_code == 401) && $retry) {
      $this->authenticate();
      return $this->remoteCall($method, $data, FALSE);
    }

    return $result;
  }
}
