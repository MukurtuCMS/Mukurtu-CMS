<?php

namespace Drupal\mukurtu_import\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\mukurtu_import\MukurtuImportStrategyInterface;
use Drupal\user\UserInterface;
use Drupal\file\FileInterface;
use League\Csv\Reader;
use Exception;

/**
 * Defines the mukurtu_import_strategy entity type.
 *
 * @ConfigEntityType(
 *   id = "mukurtu_import_strategy",
 *   label = @Translation("Import Configuration Templates"),
 *   label_collection = @Translation("Import Configuration Templates"),
 *   label_singular = @Translation("Import Configuration Template"),
 *   label_plural = @Translation("Import Configuration Templates"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Import Configuration Template",
 *     plural = "@count Import Configuration Templates",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\mukurtu_import\MukurtuImportStrategyListBuilder",
 *     "form" = {
 *       "add" = "Drupal\mukurtu_import\Form\MukurtuImportStrategyForm",
 *       "edit" = "Drupal\mukurtu_import\Form\MukurtuImportStrategyForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "mukurtu_import_strategy",
 *   admin_permission = "administer mukurtu_import_strategy",
 *   links = {
 *     "collection" = "/admin/structure/mukurtu-import-strategy",
 *     "add-form" = "/admin/structure/mukurtu-import-strategy/add",
 *     "edit-form" = "/admin/structure/mukurtu-import-strategy/{mukurtu_import_strategy}",
 *     "delete-form" = "/admin/structure/mukurtu-import-strategy/{mukurtu_import_strategy}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "uid",
 *     "label",
 *     "description",
 *     "target_entity_type_id",
 *     "target_bundle",
 *     "mapping",
 *   }
 * )
 */
class MukurtuImportStrategy extends ConfigEntityBase implements MukurtuImportStrategyInterface {

  /**
   * The mukurtu_import_strategy ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The mukurtu_import_strategy label.
   *
   * @var string
   */
  protected $label;

  /**
   * The mukurtu_import_strategy status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The mukurtu_import_strategy description.
   *
   * @var string
   */
  protected $description;

  /**
   * The target entity type id.
   *
   * @var string
   */
  protected $target_entity_type_id;

  /**
   * The target bundle.
   *
   * @var string
   */
  protected $target_bundle;
  protected $uid;

  /**
   * [0] => Array
   *  (
   *     [target] => nid
   *     [source] => id
   *  )
   */
  protected $mapping;
  protected $configuration;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  public function setTargetEntityTypeId($entity_type_id) {
    $this->target_entity_type_id = $entity_type_id;
  }

  public function getTargetEntityTypeId() {
    return $this->target_entity_type_id ?? 'node';
  }

  public function setTargetBundle($bundle) {
    $this->target_bundle = $bundle;
  }

  public function getTargetBundle() {
    return $this->target_bundle ?? NULL;
  }

  /*
  Example:
   0 => array:2 [▼
    "target" => "uuid"
    "source" => "UUID"
  ]
  1 => array:2 [▼
    "target" => "title"
    "source" => "Title"
  ]
   */
  public function getMapping() {
    return $this->mapping ?? [];
  }

  public function setMapping($mapping) {
    $this->mapping = $mapping;
  }

  public function setConfig($key, $value) {
    $this->configuration[$key] = $value;
  }

  public function getConfig($key) {
    return $this->configuration[$key] ?? NULL;
  }

  public function getLabel() {
    return $this->label;
  }

  public function setLabel($label) {
    $this->label = $label;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    if (!$this->id()) {
      $this->id = $this->uuidGenerator()->generate();
    }

    if (!$this->uid) {
      $this->uid = 1;
    }
    parent::save();
  }

  public function getOwner() {
    return $this->entityTypeManager()->getStorage('user')->load($this->getOwnerId());
  }

  public function setOwner(UserInterface $account) {
    $this->uid = $account->id();
    return $this;
  }

  public function getOwnerId() {
    return $this->uid ?: 1;
  }

  public function setOwnerId($uid) {
    $this->uid = $uid;
    return $this;
  }

  public function applies(FileInterface $file) {
    $headers = $this->getCSVHeaders($file);
    $mapping = $this->getMapping();
    $diff = array_diff(array_column($mapping, 'source'), $headers);
    if (empty($diff)) {
      return TRUE;
    }
    return FALSE;
  }

  protected function getCSVHeaders(FileInterface $file) {
    try {
      $csv = Reader::createFromPath($file->getFileUri(), 'r');
    } catch (Exception $e) {
      return [];
    }
    $csv->setHeaderOffset(0);
    return $csv->getHeader();
  }

  protected function getDefinitionId(FileInterface $file) {
    return $this->getOwnerId() . "__" . $file->id() . "__" . $this->getTargetEntityTypeId() . "__" . $this->getTargetBundle();
  }

  protected function getDefinitionLabel(FileInterface $file) {
    return sprintf('%s - %s', $this->label(), $file->getFilename());
  }

  protected function getFieldDefinitions($entity_type_id, $bundle = NULL) {
    if ($bundle) {
      return \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);
    }
    return \Drupal::service('entity_field.manager')->getBaseFieldDefinitions($entity_type_id);
  }

  protected function getProcess() {
    $entity_type_id = $this->getTargetEntityTypeId();
    $bundle = $this->getTargetBundle();
    $mapping = $this->getMapping();

    // Get the field definitions for the target.
    $fieldDefs = $this->getFieldDefinitions($entity_type_id, $bundle);

    /** @var \Drupal\mukurtu_import\MukurtuImportFieldProcessPluginManager $manager */
    $manager = \Drupal::service('plugin.manager.mukurtu_import_field_process');

    // This will cause collisions if there are dupes. Do we care?
    $importProcess = array_combine(array_column($mapping, 'target'), array_column($mapping, 'source'));

    // Remove ignored mappings. This depends on the above dupe collision behavior.
    if (isset($importProcess['-1'])) {
      unset($importProcess['-1']);
    }

    // @todo Add process plugins as appropriate for the target field type.
    foreach ($importProcess as $target_option => $source) {
      $targets = explode('/', $target_option, 2);
      $target = $targets[0];
      $subtarget = NULL;
      if (count($targets) > 1) {
        [$target, $subtarget] = $targets;
      }


      /** @var \Drupal\field\FieldConfigInterface $fieldDef */
      $fieldDef = $fieldDefs[$target] ?? NULL;
      if (!$fieldDef) {
        continue;
      }

      /** @var \Drupal\mukurtu_import\MukurtuImportFieldProcessInterface $processPlugin */
      if ($processPlugin = $manager->getInstance(['field_definition' => $fieldDef])) {
        $context = [];
        $context['multivalue_delimiter'] = $this->getConfig('multivalue_delimiter') ?? ';';
        $context['upload_location'] = $this->getConfig('upload_location') ?? NULL;
        if ($subtarget) {
          $context['subfield'] = $subtarget;
        }
        $importProcess[$target_option] = $processPlugin->getProcess($fieldDef, $source, $context);
      }
    }

    return $importProcess;
  }

  /**
   * Get the fields that are allowed to be altered for existing entities.
   *
   * @return mixed
   */
  protected function getOverwriteProperties() {
    $entity_type_id = $this->getTargetEntityTypeId();
    $bundle = $this->getTargetBundle();
    $mapping = $this->getMapping();
    $rawTargets = array_column($mapping, 'target');

    // For subfield processes, we only want the fieldname.
    $targets = array_map(fn($t) => explode('/', $t, 2)[0], $rawTargets);

    // Get the field definitions for the target.
    $fieldDefs = $this->getFieldDefinitions($entity_type_id, $bundle);

    $writableFields = [];
    foreach ($fieldDefs as $fieldName => $fieldDef) {
      if (in_array($fieldName,['default_langcode'])) {
        continue;
      }

      // Sometimes there are fields that are marked as writable but the migrate
      // system will fail to import if they are specified as overwritable. Here
      // we get around this by only specifying what is absolutely necessary for
      // the given import input.
      if (!$fieldDef->isReadOnly() && in_array($fieldName, $targets)) {
        $writableFields[] = $fieldName;
      }
    }

    return $writableFields;
  }

  /**
   * Generate a Migrate API definition for a given file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The import input file.
   * @return array
   *   The migration definition array
   */
  public function toDefinition(FileInterface $file): array {
    $mapping = $this->getMapping();
    $entity_type_id = $this->getTargetEntityTypeId();
    $bundle = $this->getTargetBundle();
    $id_key = $this->entityTypeManager()->getDefinition($entity_type_id)->getKey('id');
    $uuid_key = $this->entityTypeManager()->getDefinition($entity_type_id)->getKey('uuid');
    $process = $this->getProcess();

    $ids = [];
    // Entity ID has priority.
    if (!empty($process[$id_key])) {
      $ids = array_filter(array_map(fn($v) => $v['target'] == $id_key ? $v['source'] : NULL, $mapping));
    }

    // UUID has next priority.
    if (empty($ids) && !empty($process[$uuid_key])) {
      $ids = array_filter(array_map(fn ($v) => $v['target'] == $uuid_key ? $v['source'] : NULL, $mapping));
    }

    // If we have no ID or UUID, use all input fields as the collective ID.
    // This will effectively make each row a unique item.
    // @todo This has problems as not all field names are suitable for this
    //   context.
    if (empty($ids)) {
      $ids = array_map(fn ($v) => $v['source'], $mapping);
    }

    return [
      'id' => $this->getDefinitionId($file),
      'label' => $this->getDefinitionLabel($file),
      'source' => [
        'plugin' => 'csv',
        'path' => $file->getFileUri(),
        'ids' => $ids,
        'delimiter' => $this->getConfig('delimiter') ?? ',',
        'enclosure' => $this->getConfig('enclosure') ?? '"',
        'escape' => $this->getConfig('escape') ?? '\\',
        'track_changes' => TRUE,
      ],
      'process' => $process,
      'destination' => [
        'plugin' => "entity:$entity_type_id",
        'default_bundle' => $bundle,
        'overwrite_properties' => $this->getOverwriteProperties(),
        'validate' => TRUE,
      ],
    ];
  }

  public function mappedFieldsCount(FileInterface $file) {
    $fileHeaders = $this->getCSVHeaders($file);
    $process = $this->getMapping();
    // Compare the import config to the headers.
    $mappingHeaders = array_column($process, 'source');
    $diff = array_diff($fileHeaders, $mappingHeaders);
    $mappedCount = count($fileHeaders) - count($diff);
    return $mappedCount;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappedTarget(string $source): ?string {
    $mapping = $this->getMapping();
    foreach ($mapping as $target) {
      if ($target['source'] === $source) {
        return $target['target'];
      }
    }
    return NULL;
  }

}
