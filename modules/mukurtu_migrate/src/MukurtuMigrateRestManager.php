<?php

namespace Drupal\mukurtu_migrate;

use Exception;
use stdClass;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

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

  public function __construct() {
    // Default field mappings.
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
          //'field_media_asset' => 'field_media_assets',
        ],
      ],
      'scald_atom' => [],
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
          dpm("getPreviouslyImported($entity_type, $remote_id): found existing vocab");
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
            dpm("getPreviouslyImported($entity_type, $remote_id): found existing term");
            return $term->id();
          }
        }
      }
      return NULL;
    }

    // Nodes.
    $msg = $this->makeRevisionMessage($entity_type, $remote_id);
    $query = \Drupal::entityQuery($entity_type)
      ->latestRevision()
      ->condition('revision_log', $msg , '=');

    $ids = $query->execute();

    if (empty($ids)) {
      return NULL;
    }

    if (count($ids) == 1) {
      dpm("getPreviouslyImported found one!");
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
    ];

    //$new_id = $this->getOldToNewMapping($lookup[$id_type], $target_id);
    $new_id = $this->getPreviouslyImported($lookup[$id_type], $target_id);
    if (is_null($new_id)) {
      dpm("I didn't find an existing $id_type with ID $target_id");
    }
    return $new_id;
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

    // Check if we have a migration method for this field type.
    if (method_exists($this, $field_type_ftn)) {
      $value = $this->{$field_type_ftn}($value);
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

    return is_array($value) ? $value : [$value];
  }

  /**
   * Take Mukurtu v2 JSON and convert to Mukurtu v4 JSON.
   */
  protected function migrateItem($entity_type, $json) {
    $item = json_decode($json);

    $bundle = ($entity_type == 'taxonomy_vocabulary' || $entity_type == 'taxonomy_term') ? '' : $item->type;

    // Swtich back from v2 -> v4 bundle names.
    if ($entity_type == 'node') {
      if ($bundle == 'cultural_protocol_group') {
        $bundle = 'protocol';
        $item->type = $bundle;
      }
    }

    // Migrate the values from the incoming JSON to v4 format/schema.
    $new_item = new stdClass();
    foreach ($item as $key => $value) {
      $fieldMapping = $this->mapField($entity_type, $bundle, $key);

      if ($fieldMapping) {
        // TODO: Do any required processing to the new field value.
        $new_item->{$fieldMapping['field_name']} = $this->migrateValue($entity_type, $bundle, $fieldMapping['field_name'], $value);
      }
    }

    // Set the type so the entity validation works.
    if ($bundle != '') {
      $new_item->type = $bundle;
    }

    // Now we need to determine if we are creating a new entity or updating an
    // existing one.
    $old_id = $new_item->old_id[0] ?? NULL;

    if ($old_id) {
      // "old_id" is book-keeping that we don't want to save in the entity.
      unset($new_item->old_id);

      // Check for an existing local ID for this entity.
      //$existing_id = $this->getOldToNewMapping($entity_type, $old_id);
      $existing_id = $this->getPreviouslyImported($entity_type, $old_id);
      if ($existing_id) {
        // It is an existing item, add the existing ID to the migrated item
        // so that we don't create a duplicate.
        $id_fieldname = $this->getIdFieldname($entity_type);
        dpm("this already exists, $entity_type: $id_fieldname=> $existing_id");
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
      //dpm("I would run JSON deserializer on this:");
      //dpm($migratedItem);

      // Determine entity class for the serializer.
      $entityClass = \Drupal\node\Entity\Node::class;
      if ($entity_type == 'taxonomy_vocabulary') {
        $entityClass = \Drupal\taxonomy\Entity\Vocabulary::class;
      }
      if ($entity_type == 'taxonomy_term') {
        $entityClass = \Drupal\taxonomy\Entity\Term::class;
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
            //dpm("I'm copying $fieldname onto existing");
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
          }
        } else {
          // Not all types support validiation, role the dice and hope it saves.
          //dpm($entity);
          $entity->save();
        }

        // Add old ID -> new ID lookup.
  /*       if (isset($migratedItem['old_id']) && $migratedItem['old_id']) {
          $this->setOldToNewMapping($entity_type, $migratedItem['old_id'], $entity->id());
        } */
      } catch (Exception $e) {
        dpm("Failed to import $uri");
        dpm($e->getMessage());
      }
    } else {
      dpm("Need to log error");
      dpm($response);
    }
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
