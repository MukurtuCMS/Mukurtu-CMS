<?php

namespace Drupal\mukurtu_migrate;

use stdClass;

class MukurtuMigrateRestManager {
  protected $sourceUrl;
  protected $sourceUser;
  protected $sourcePassword;

  protected $taxonomyTable;
  protected $nodeTable;
  protected $mediaTable;

  protected $fieldMappings;
  protected $migrationSteps;

  protected $currentImportPosition;
  protected $itemsPerBatch;

  public function __construct() {
    // Default field mappings.
    $this->fieldMappings = [
      'default' => [
        'language' => 'langcode',
      ],
      'taxonomy_term' => [],
      'node' => [
        'default' => [
          'title' => 'title',
          'status' => 'status',
          'type' => 'type',
          'field_identifier' => 'field_identifier',
          'field_tk_body' => 'field_traditional_knowledge',
          'field_description' => 'field_description',
        ],
        'digital_heritage' => [
          'body' => 'field_cultural_narrative',
        ],
      ],
      'scald_atom' => [],
    ];

    $this->itemsPerBatch = 5;
    $this->currentImportPosition = ['node', 0];
    // Default Migration Steps.

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

  /**
   * Take Mukurtu v2 JSON and convert to Mukurtu v4 JSON.
   */
  protected function migrateItem($json) {
    $item = json_decode($json);
    $entity_type = 'node';
    $bundle = $item->type;
    $new_item = new stdClass();

    foreach ($item as $key => $value) {
      $fieldMapping = $this->mapField($entity_type, $bundle, $key);

      if ($fieldMapping) {
        // TODO: Do any required processing to the new field value.
        $new_item->{$fieldMapping['field_name']} = is_array($value) ? $value : [$value];
      }
    }


    // Testing only.
    $new_item->field_category = [1];
    $new_item->field_description = $this->convertTextField($new_item->field_description);
    $new_item->field_traditional_knowledge = $this->convertTextField($new_item->field_traditional_knowledge);

    $new_item->type = $bundle;
    return json_encode($new_item);
  }

  protected function convertTextField($value) {
    $new_value = new stdClass();
    $new_value->value = $value[0]->und[0]->value;
    $new_value->format = $value[0]->und[0]->format;
    switch($value[0]->und[0]->format) {
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
  protected function importByURI($uri) {
    $response = $this->remoteCall('GET', $uri);
    if ($response['HTTP_CODE'] == 200) {
      // Map and process fields.
      $migratedItem = $this->migrateItem($response['body']);
      dpm("I would run JSON deserializer on this:");
      dpm($migratedItem);

      // Run the resulting JSON through the deserializer.
      $serializer = \Drupal::service('serializer');
      $entity = $serializer->deserialize($migratedItem, \Drupal\node\Entity\Node::class, 'json');

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
        //$entity->save();
      }
    } else {
      dpm("Need to log error");
      dpm($response);
    }
  }

  public function processBatch() {
    $bundle = 'digital_heritage';

    $item = $this->nodeTable[$bundle][37684];
    $this->importByURI($item->uri);
    return ['current' => 1];
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
    // Index all the vocabularies.
    foreach ($vocabs as $vocab) {
      $table[$vocab->vid] = $vocab;
    }

    // Index all terms and attach to the vocabulary table.
    foreach ($terms as $term) {
      // We are harvesting the vocab that this term exists in.
      if (isset($term->vid) && isset($table[$term->vid])) {
        if (!isset($table[$term->vid]->terms)) {
          $table[$term->vid]->terms = [];
        }
        $table[$term->vid]->terms[$term->tid] = $term;
      }
    }

    $this->taxonomyTable = $table;
    return $table;
  }

  public function buildNodeTable($nodeFile) {
    $nodes = $this->loadJsonFile($nodeFile);

    $table = [];

    // Index all nodes by type, then nid.
    foreach ($nodes as $node) {
      if (!isset($table[$node->type])) {
        $table[$node->type] = [];
      }
      $table[$node->type][$node->nid] = $node;
    }

    $this->nodeTable = $table;
    return $table;
  }

  public function buildMediaTable($mediaFile) {
    $table = [];
    $this->mediaTable = $table;
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
   * Fetch a number results from the given endpoint, starting at the given offset.
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
    if (stripos($url, $this->sourceUrl) == 0) {
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

    // If request was forbidden and auth was selected, try re-authenticated and repeat the request.
    if (($http_code == 403 || $http_code == 401) && $retry) {
      return $this->remoteCall($method, $data, FALSE);
    }

    return $result;
  }
}
