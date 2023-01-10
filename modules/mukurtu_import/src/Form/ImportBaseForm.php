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
/**
 * Provides a Mukurtu Import form.
 */
class ImportBaseForm extends FormBase {
  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $store;

  protected $entityTypeManager;
  protected $entityBundleInfo;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  protected $metadataFiles;
  protected $binaryFiles;
  protected $metadataFilesImportConfig;
  protected $importId;


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
      $import_id = \Drupal::service('uuid')->generate();
      $this->store->set('import_id', str_replace('-', '', $import_id));
    }
    $this->importId = $import_id;

    $this->metadataFiles = $this->store->get('metadata_files');
    $this->metadataFilesImportConfig = $this->store->get('import_config');
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
    foreach ($this->getMetadataFiles() as $fid) {
      if ($file = $this->entityTypeManager->getStorage('file')->load($fid)) {
        $file->delete();
      }
    }

    $this->store->set('import_id', NULL);
    $this->store->set('metadata_files', NULL);
    $this->metadataFilesImportConfig = [];
    $this->store->set('import_config', []);
  }

  public function setMetadataFiles($files) {
    $this->metadataFiles = $files;
    $this->store->set('metadata_files', $files);
  }

  public function getMetadataFiles() {
    return $this->metadataFiles;
  }

  public function getImportId() {
    return $this->importId;
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
    $this->metadataFilesImportConfig[$fid] = $config;
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
    return $this->metadataFilesImportConfig[$fid] ?? MukurtuImportStrategy::create(['uid' => $this->currentUser()->id()]);
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

}
