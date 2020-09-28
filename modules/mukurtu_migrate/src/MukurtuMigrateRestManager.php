<?php

namespace Drupal\mukurtu_migrate;

use Exception;
use stdClass;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

class MukurtuMigrateRestManager {
  protected $sourceUrl;
  protected $sourceUser;
  protected $sourcePassword;

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
      ['entity_type' => 'node', 'bundle' => 'protocol'],
      ['entity_type' => 'node', 'bundle' => 'digital_heritage'],
    ];

    /** @var PrivateTempStoreFactory $private_tempstore */
    $private_tempstore = \Drupal::service('tempstore.private');
    $migrate_tempstore = $private_tempstore->get('mukurtu_migrate');
    try {
      $this->sourceUrl = $migrate_tempstore->get('migration_source_url');
      $this->sourceUser = $migrate_tempstore->get('migration_source_username');
      $this->sourcePassword = $migrate_tempstore->get('migration_source_password');
    } catch (Exception $e) {
      dpm($e->getMessage());
    }

    // Load previous old -> new table.
    $this->oldToNewLookupTable = \Drupal::state()->get('mukurtu_migrate_old_new_table', []);
  }

  public function setState($step = NULL, $entity_type = NULL, $bundle = NULL, $offset = 0) {
    $this->currentStep = $step ?? $this->migrationSteps[0];
    $this->currentEntityType = $entity_type ?? 'taxonomy_vocabulary';
    $this->currentBundle = $bundle ?? current(array_keys($this->importTable[$this->currentEntityType]));
    $this->currentOffset = $offset;
  }


  protected function setOldToNewMapping($entity_type, $oldId, $newId) {
    if (!is_null($oldId) && !is_null($newId)) {
      dpm("Mapping $entity_type $oldId => $newId");
      $this->oldToNewLookupTable[$entity_type][$oldId] = $newId;
      \Drupal::state()->set('mukurtu_migrate_old_new_table', $this->oldToNewLookupTable);
    }
  }

  /**
   * Map pre-defined Mukurtu 4 content to pre-defined Mukurtu 2 content.
   */
  protected function mapDefaultContent() {
    // New -> Old.
    $defaultTaxonomies = [
      'authors' => 'scald_authors',
      'category' => 'category',
      'contributor' => 'contributor',
      'creator' => 'creator',
      'format' => 'format',
      'interpersonal_relationship' => 'interpersonal_relationship',
      'keywords' => 'tags',
      'language' => 'language',
      'part_of_speech' => 'part_of_speech',
      'people' => 'people',
      'publisher' => 'publisher',
      'subject' => 'subject',
      //'tags' => '',
      'type' => 'dh_type',
    ];

    // Taxonomy vocabularies.
    foreach ($defaultTaxonomies as $newVocab => $oldVocab) {
      // Fetch the old remote vocab.
      $data = ['parameters[machine_name]' => $oldVocab];
      $response = $this->remoteCall('GET', '/tax-vocab', $data);
      if ($response['HTTP_CODE'] == 200) {
        $responseData = json_decode($response['body']);

        if (isset($responseData[0]->vid)) {
          // Add to the mapping table.
          $this->setOldToNewMapping('taxonomy_vocabulary', $responseData[0]->vid, $newVocab);
        }
      }
    }

    // "Default" category.
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
    if ($id_type == 'tid') {
      return $this->oldToNewLookupTable['taxonomy_term'][$target_id];
    }

    return NULL;
  }

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
              $new_value[$lang][] = ['target_id' => $this->translateTargetId($id, $target_id)];
              //$new_value[] = ['target_id' => $this->translateTargetId($id, $target_id)];
            }
          }
        }
      }
      dpm($new_value);
      return $new_value;
    }

    //dpm($value);
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


    if (method_exists($this, $field_type_ftn)) {
      $value = $this->{$field_type_ftn}($value);
    }

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
    $new_item = new stdClass();

    foreach ($item as $key => $value) {
      $fieldMapping = $this->mapField($entity_type, $bundle, $key);

      if ($fieldMapping) {
        // TODO: Do any required processing to the new field value.
        $new_item->{$fieldMapping['field_name']} = $this->migrateValue($entity_type, $bundle, $fieldMapping['field_name'], $value);
      }
    }

    if ($bundle != '') {
      $new_item->type = $bundle;
    }

    $old_id = $new_item->old_id[0] ?? NULL;
    if ($old_id) {
      unset($new_item->old_id);
      // Is this an existing (previously migrated) item?
      $existing_id = $this->oldToNewLookupTable[$entity_type][$old_id];
      if ($existing_id) {
        // It is an existing item, add the existing ID to the migrated item
        // so that we don't create a duplicate.
        $id_fieldname = $this->getIdFieldname($entity_type);
        if ($id_fieldname) {
          $new_item->{$id_fieldname} = $existing_id;
        }
      }
    }

    return ['old_id' => $old_id[0], 'json' => json_encode($new_item)];
  }

  protected function convertTextField($value) {
    $new_value = new stdClass();
    $new_value->value = $value[0]->und[0]->value;
    $new_value->format = $value[0]->und[0]->format;
    switch ($value[0]->und[0]->format) {
      case 'full_html':
      case 'ds_code':
        $new_value->format = 'full_html';
        break;

      case 'filtered_html':
        $new_value->format = 'restricted_html';
        break;

      default:
        $new_value->format = 'basic_html';
    }

    return [$new_value];
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
        if (method_exists($entity, 'validate')) {
          // Check if the entity validates.
          $violations = $entity->validate();
          if ($violations->count() > 0) {
            // Validation failed. Log and move on.
            dpm("Failed to import $uri");
            foreach ($violations as $violation) {
              $msg = $violations[0]->getMessage();
              dpm("Field Violation: " . $violation->getPropertyPath());
            }
          } else {
            // Validation succeeded, save the entity.
            $entity->save();
          }
        } else {
          //dpm($entity);
          $entity->save();
        }

        // Add old ID -> new ID lookup.
        if (isset($migratedItem['old_id']) && $migratedItem['old_id']) {
          $this->setOldToNewMapping($entity_type, $migratedItem['old_id'], $entity->id());
        }
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
      } elseif(isset($this->importTable[$step['entity_type']])) {
        $items += count($this->importTable[$step['entity_type']]);
      }
    }

    return $items;
    //return 10;
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

      $this->mapDefaultContent();
    }

    $entity_type = $context['entity_type'];
    $bundle = $context['bundle'];
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
    $raw = file_get_contents($path);
    $data = json_decode($raw);

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
    $response = $this->remoteCall("POST", "/user/login", $data);

    if ($response['HTTP_CODE'] == 200) {
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
        if ($data)
          $url = sprintf("%s?%s", $url, http_build_query($data));
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
