<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\file\FileInterface;
use Exception;
use League\Csv\Reader;
use Drupal\mukurtu_import\MukurtuImportStrategyInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginManager;

/**
 * Provides a Mukurtu Import form.
 */
class ImportBaseForm extends FormBase {
  use ImportFormTrait;

  /**
   * The private temporary storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $store;

  /**
   * The Mukurtu import ID.
   *
   * @var ?string
   */
  protected ?string $importId = NULL;

  /**
   * The metadata file weights.
   *
   * @var array
   */
  protected array $metadataFileWeights;

  /**
   * The metadata files import config.
   *
   * @var array
   */
  protected array $metadataFilesImportConfig;

  /**
   * Constructs an ImportBaseForm object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private temporary storage factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityBundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   * @param \Drupal\mukurtu_import\MukurtuImportFieldProcessPluginManager $fieldProcessPluginManager
   *   The field process plugin manager.
   */
  public function __construct(
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
    protected UuidInterface $uuid,
    protected MukurtuImportFieldProcessPluginManager $fieldProcessPluginManager,
  ) {
    $this->store = $tempStoreFactory->get('mukurtu_import');
    $this->importId = $this->store->get('import_id');
    if (empty($this->importId)) {
      $this->reset();
      $this->importId = $uuid->generate();
      $this->store->set('import_id', $this->importId);
    }
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
      $container->get('uuid'),
      $container->get('plugin.manager.mukurtu_import_field_process'),
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
   * This is a silly state management hack. Because of the AJAX requests,
   * the local object variable metadataFilesImportConfig can become out of
   * sync with the "real" current value. The correct way to handle this is
   * probably to remove the object variable completely and have the getter and
   * setter only deal with the private store.
   */
  public function refreshMetadataFilesImportConfig(): void {
    $this->metadataFilesImportConfig = $this->store->get('import_config');
  }

  /**
   * Get all metadata files for the current import.
   *
   * Retrieves file entity IDs for all files stored in the metadata upload
   * location for this import session.
   *
   * @return int[]
   *   An array of file entity IDs (fid) for metadata files.
   */
  public function getMetadataFiles(): array {
    $query = $this->entityTypeManager->getStorage('file')->getQuery();
    return $query->condition('uri', $this->getMetadataUploadLocation(), 'STARTS_WITH')
      ->accessCheck()
      ->execute();
  }

  /**
   * Get the metadata file weights.
   *
   * Retrieves the weights assigned to metadata files, removing any weights
   * for files that no longer exist, and returns them sorted by weight.
   *
   * @return array
   *   An associative array of file IDs (fid) as keys and their weights as
   *   values, sorted in ascending order by weight.
   */
  public function getMetadataFileWeights(): array {
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

  /**
   * Set the metadata file weights.
   *
   * @param array $weights
   *   An associative array of file IDs (fid) as keys and their weights as
   *   values.
   */
  public function setMetadataFileWeights(array $weights): void {
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

  /**
   * Get the metadata upload location for the current import.
   *
   * @return string
   *   The metadata upload location.
   */
  public function getMetadataUploadLocation(): string {
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
  public function getImportConfig($fid): MukurtuImportStrategyInterface {
    $this->refreshMetadataFilesImportConfig();
    $exiting_config_entity = $this->metadataFilesImportConfig[(int) $fid] ?? NULL;
    if ($exiting_config_entity) {
      return $exiting_config_entity;
    }

    $new_config_entity = MukurtuImportStrategy::create(['uid' => $this->currentUser()->id()]);
    $this->setImportConfig($fid, $new_config_entity);
    return $new_config_entity;
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
