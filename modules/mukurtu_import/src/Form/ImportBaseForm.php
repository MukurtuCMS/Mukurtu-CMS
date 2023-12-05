<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\file\FileInterface;
use Exception;
use League\Csv\Reader;
use Drupal\mukurtu_import\MukurtuImportStrategyInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a Mukurtu Import form.
 */
class ImportBaseForm extends FormBase {
  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $store;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfo;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;
  protected $metadataFileWeights;
  protected $metadataFilesImportConfig;

  /**
   * The Mukurtu import ID.
   *
   * @var string
   */
  protected $importId;

  protected $fieldDefinitions;

  /**
   * {@inheritdoc}
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_bundle_info) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->store = $this->tempStoreFactory->get('mukurtu_import');
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityBundleInfo = $entity_bundle_info;
    $import_id = $this->store->get('import_id');
    if (empty($import_id)) {
      $this->reset();
      $import_id = \Drupal::service('uuid')->generate();
      $this->store->set('import_id', $import_id);
    }
    $this->importId = $import_id;
    $this->refreshMetadataFilesImportConfig();
    $this->metadataFileWeights = $this->store->get('metadata_file_weights') ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_import_import_base';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Reset the import operation to a clean initial state.
   *
   * @return void
   */
  protected function reset() {
    // Delete all the temporary metadata files.
/*     foreach ($this->getMetadataFiles() as $fid) {
      if ($file = $this->entityTypeManager->getStorage('file')->load($fid)) {
        $file->delete();
      }
    } */

    $this->store->set('import_id', NULL);
    $this->metadataFilesImportConfig = [];
    $this->store->set('import_config', []);
    $this->store->set('batch_results_messages', []);
    $this->store->set('metadata_file_weights', []);
  }

  /**
   * Load the import config from the private store.
   *
   * This is a silly state management hack. Because of the ajax requests,
   * the local object variable metadataFilesImportConfig can become out of
   * sync with the "real" current value. The correct way to handle this is
   * probably to remove the object variable completely and have the getter and
   * setter only deal with the private store.
   */
  public function refreshMetadataFilesImportConfig() {
    $this->metadataFilesImportConfig = $this->store->get('import_config');
  }

  public function getMetadataFiles() {
    $query = $this->entityTypeManager->getStorage('file')->getQuery();
    return $query->condition('uri', $this->getMetadataUploadLocation(), 'STARTS_WITH')
      ->accessCheck(TRUE)
      ->execute();
  }

  public function getMetadataFileWeights() {
    $fids = $this->getMetadataFiles();
    $weights = $this->metadataFileWeights;
    // Remove any weights for files that no longer exist.
    foreach ($weights as $fid => $weight) {
      if (!in_array($fid, $fids)) {
        unset($weights[$fid]);
      }
    }
    asort($weights);
    return $weights;
  }

  public function setMetadataFileWeights($weights) {
    $this->metadataFileWeights = $weights;
    $this->store->set('metadata_file_weights', $weights);
  }

  public function getBinaryFiles() {
    $query = $this->entityTypeManager->getStorage('file')->getQuery();
    return $query->condition('uri', $this->getBinaryUploadLocation(), 'STARTS_WITH')
      ->accessCheck(TRUE)
      ->execute();
  }

  /**
   * Get the import ID.
   *
   * @return string
   */
  public function getImportId() {
    return $this->importId;
  }

  public function getBinaryUploadLocation() {
    return "private://{$this->getImportId()}/files/";
  }

  public function getMetadataUploadLocation() {
    return "private://{$this->getImportId()}/metadata/";
  }

  public function getImportRevisionMessage() {
    return $this->t("Imported by @username (Import ID: @import_id)", ['@import_id' => $this->getImportId(), '@username' => $this->currentUser()->getDisplayName()]);
  }

  protected function initializeProcess($fid) {
    $this->store->set('process_map', []);
  }

  // Bad?
  public function setFileProcess($fid, $mapping) {
    $processMap = $this->store->get('process_map') ?? [];
    $processMap[$fid]['mapping'] = $mapping;
    $this->store->set('process_map', $processMap);
  }

  // Bad?
  public function getFileProcess($fid) {
    $processMap = $this->store->get('process_map') ?? [];
    return $processMap[$fid]['mapping'] ?? [];
  }

  /**
   * Checks if a user has permission to create an entity of a specific type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID, e.g., 'node', 'taxonomy_term', etc.
   * @param string|null $bundle
   *   (optional) The bundle, e.g., 'article', 'page', etc. If omitted, checks access for the entity type generally.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user account for which to check access. Defaults to the current user.
   *
   * @return bool
   *   TRUE if the user has access, FALSE otherwise.
   */
  function userCanCreateEntity($entity_type_id, $bundle = NULL, AccountInterface $account = NULL) {
    // If no user account is provided, default to the current user.
    if (!$account) {
      $account = $this->currentUser();
    }

    // Use the access control handler to check create access.
    $access = $this->entityTypeManager->getAccessControlHandler($entity_type_id)->createAccess($bundle, $account);

    return $access;
  }

  /**
   * Checks if a user can create any bundle of a specific entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID, e.g., 'node', 'taxonomy_term', etc.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user account for which to check access. Defaults to the current user.
   *
   * @return bool
   *   TRUE if the user has access, FALSE otherwise.
   */
  function userCanCreateAnyBundleForEntityType($entity_type_id, AccountInterface $account = NULL) {
    if (!$account) {
      $account = $this->currentUser();
    }

    $bundleInfo = $this->entityBundleInfo->getAllBundleInfo();
    if (isset($bundleInfo[$entity_type_id]) && !empty($bundleInfo[$entity_type_id])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Set the import config for a specific file.
   *
   * @param int $fid
   *   The file id.
   * @param \Drupal\mukurtu_import\MukurtuImportStrategyInterface $config
   *   The import config.
   *
   * @return void
   */
  public function setImportConfig($fid, MukurtuImportStrategyInterface $config) {
    $config->setConfig('upload_location', $this->getBinaryUploadLocation());
    $this->refreshMetadataFilesImportConfig();
    $this->metadataFilesImportConfig[(int) $fid] = $config;
    $this->store->set('import_config', $this->metadataFilesImportConfig);
  }

  /**
   * Get the import config for a specific file.
   *
   * @param int $fid
   *   The file id.
   *
   * @return \Drupal\mukurtu_import\MukurtuImportStrategyInterface
   *   The import config.
   */
  public function getImportConfig($fid) {
    $this->refreshMetadataFilesImportConfig();
    $exitingConfigEntity = $this->metadataFilesImportConfig[(int) $fid] ?? NULL;
    if ($exitingConfigEntity) {
      return $exitingConfigEntity;
    }

    $newConfigEntity = MukurtuImportStrategy::create(['uid' => $this->currentUser()->id()]);
    $this->setImportConfig($fid, $newConfigEntity);
    return $newConfigEntity;
  }

  public function getMessages() {
    return $this->store->get('batch_results_messages') ?? [];
  }

  /**
   * Get the CSV headers from a file.
   */
  public function getCSVHeaders(FileInterface $file) {
    try {
      $csv = Reader::createFromPath($file->getFileUri(), 'r');
    } catch (Exception $e) {
      return [];
    }
    $csv->setHeaderOffset(0);
    return $csv->getHeader();
  }

  /**
   * Build the mapper target options for a single source column.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @return mixed
   *   The select form element.
   */
  protected function buildTargetOptions($entity_type_id, $bundle = NULL) {
    $entityDefinition = $this->entityTypeManager->getDefinition($entity_type_id);
    $entityKeys = $entityDefinition->getKeys();

    $options = [-1 => $this->t('Ignore - Do not import')];
    foreach ($this->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      if ($field_definition->getType() == 'cultural_protocol') {
        // Split our protocol field into the individual sharing
        // setting/protocols subfields.
        $options["{$field_name}/sharing_setting"] = $this->t('Sharing Setting');
        $options["{$field_name}/protocols"] = $this->t('Protocols');
        continue;
      }

      $options[$field_name] = $field_definition->getLabel();
    }

    // Very Mukurtu specific. We ship with a "Language" field which has
    // the exact same label as content entity langcodes. Here we disambiguate
    // the two.
    if (isset($options[$entityKeys['langcode']])) {
      $options[$entityKeys['langcode']] .= $this->t(' (langcode)');
    }

    return $options;
  }

  /**
   * Get the field definitions for an entity type/bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @return mixed
   *   The field definitions.
   */
  protected function getFieldDefinitions($entity_type_id, $bundle = NULL) {
    // Memoize the field defs.
    if (empty($this->fieldDefinitions[$entity_type_id][$bundle])) {
      $entityDefinition = $this->entityTypeManager->getDefinition($entity_type_id);
      $entityKeys = $entityDefinition->getKeys();
      $fieldDefs = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

      // Remove computed fields/fields that can't be targeted for import.
      foreach ($fieldDefs as $field_name => $fieldDef) {
        // Don't remove ID/UUID fields.
        if ($field_name == $entityKeys['id'] || $field_name == $entityKeys['uuid']) {
          continue;
        }

        // Remove the revision log message as a valid target. We are using
        // specific revision log messages to control import behavior.
        if ($field_name == 'revision_log') {
          unset($fieldDefs[$field_name]);
        }

        // Remove unwanted 'behavior_settings' paragraph base field.
        if ($entity_type_id == "paragraph" && $field_name == 'behavior_settings') {
          unset($fieldDefs[$field_name]);
        }

        // Remove computed and read-only fields.
        if ($fieldDef->isComputed() || $fieldDef->isReadOnly()) {
          unset($fieldDefs[$field_name]);
        }
      }
      $this->fieldDefinitions[$entity_type_id][$bundle] = $fieldDefs;
    }

    return $this->fieldDefinitions[$entity_type_id][$bundle];
  }

  /**
   * Get the filename.
   *
   * @param int $fid
   *  The fid of the file.
   *
   * @return string|null
   *   The filename or null if the file does not exist.
   */
  protected function getImportFilename($fid) {
    /** @var \Drupal\file\FileInterface $file */
    if ($file = \Drupal::entityTypeManager()->getStorage('file')->load($fid)) {
      return $file->getFilename();
    }
    return NULL;
  }

}
