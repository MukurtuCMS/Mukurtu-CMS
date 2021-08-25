<?php

namespace Drupal\mukurtu_roundtrip\Services;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\mukurtu_roundtrip\ImportProcessor\MukurtuCsvImportFileProcessor;
use Drupal\mukurtu_roundtrip\ImportProcessor\MukurtuImportFileProcessorResult;
use Drupal\mukurtu_roundtrip\Services\MukurtuImportFileProcessorManager;
use Exception;
use Symfony\Component\Serializer\SerializerInterface;
use ZipArchive;

class Importer {
  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $store;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entity_manager;

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $file_system;

  protected $serializer;

  protected $import_file_process_manager;

  protected $basepath;

  protected $processors;

  public function __construct(PrivateTempStoreFactory $temp_store_factory, AccountInterface $current_user, EntityTypeManagerInterface $entity_manager, FileSystemInterface $file_system, MukurtuImportFileProcessorManager $import_file_process_manager, SerializerInterface $serializer) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->currentUser = $current_user;
    $this->store = $this->tempStoreFactory->get('mukurtu_roundtrip_importer');
    $this->entity_manager = $entity_manager;
    $this->file_system = $file_system;
    $this->import_file_process_manager = $import_file_process_manager;
    $this->serializer = $serializer;
    $this->basepath = 'private://mukurtu_importer/' . $this->currentUser->id();
    $this->file_system->prepareDirectory($this->basepath);

    //$this->processors['text/csv'][] = new MukurtuCsvImportFileProcessor();
  }

  public function getInputFiles() {
    return $this->store->get('user_input_files');
  }

  public function setInputFiles($files) {
    $this->store->set('user_input_files', $files);
  }

  public function getImportFiles() {
    return $this->store->get('import_files');
  }

  public function setImportFiles($files) {
    $this->store->set('import_files', $files);
  }

  protected function getBatchChunks() {
    return $this->store->get('batch_chunks');
  }

  protected function setBatchChunks($chunks) {
    $this->store->set('batch_chunks', $chunks);
  }

  public function getValidationReport() {
    return $this->store->get('import_validation_report');
  }

  protected function setValidationReport($report) {
    $this->store->set('import_validation_report', $report);
  }

  public function resetValidationReport() {
    $this->setValidationReport([]);
  }

  protected function getContext() {
    return [];
  }

  protected function unzipImportFile(File $zipFile) {
    $zip = new ZipArchive;
    $zipFilePath = $this->file_system->realpath($zipFile->getFileUri());
    if ($zip->open($zipFilePath) === TRUE) {
      $zip->extractTo($this->basepath);
      $zip->close();
    }
  }

  /**
   * Return the installed import processors for a given file.
   */
  public function getAvailableProcessors($file) {
    return $this->import_file_process_manager->getProcessors($file);
  }

  protected function convertToManaged() {
    $files = [];
    $rawFiles = $this->file_system->scanDirectory($this->basepath, '/.*/', ['recurse' => FALSE]);

    foreach ($rawFiles as $uri => $rawFile) {
      // Only add files.
      if (is_file($this->file_system->realpath($uri))) {
        $file = File::create([
          'uri' => $uri,
          'uid' => $this->currentUser->id(),
          'status' => 0,
        ]);
        $file->save();

        // Don't add any files we don't have import processors for.
        $fileProcessors = $this->getAvailableProcessors($file);
        if (!empty($fileProcessors)) {
          $files[] = $file->id();
        }
      }
    }
    return $files;
  }

  /**
   * Purge any files that aren't the current input files from the import space.
   */
  protected function reset() {
    // Build a list of input URIs to keep.
    $inputFiles = $this->getInputFiles();
    $storage = $this->entity_manager->getStorage('file');
    $fileEntities = $storage->loadMultiple($inputFiles);
    $inputUri = [];
    foreach ($fileEntities as $file) {
      $inputUri[] = $file->getFileUri();
    }

    // Clear the violation list.
    $this->resetValidationReport();

    // Search our import space and delete anything not in that list.
    $rawFiles = $this->file_system->scanDirectory($this->basepath, '/.*/', ['recurse' => FALSE]);
    foreach ($rawFiles as $uri => $rawFile) {
      if (!in_array($inputUri, $uri)) {
        try {
          if (is_file($this->file_system->realpath($uri))) {
            $this->file_system->delete($uri);
          }
          elseif (is_dir($this->file_system->realpath($uri))) {
            $this->file_system->deleteRecursive($uri);
          }
        } catch (Exception $e) {
          dpm($e);
        }
      }
    }
  }

  /**
   * Take initial input files and unpack/copy as needed.
   */
  public function setup() {
    $this->reset();
    $inputFiles = $this->getInputFiles();
    if (!empty($inputFiles)) {
      $storage = $this->entity_manager->getStorage('file');
      $fileEntities = $storage->loadMultiple($inputFiles);
      foreach ($fileEntities as $entity) {
        // Unpack Zip files.
        if ($entity->get('filemime')->value == 'application/zip') {
          $this->unzipImportFile($entity);
        } else {
          // Copy single files.
          $this->file_system->copy($entity->getFileUri(), $this->basepath);
        }
      }
    }

    $setupFiles = $this->convertToManaged();
    $this->setImportFiles($setupFiles);
    return $setupFiles;
  }

/*   protected function reset() {
    // TODO: Delete temp files?

    // Reset our variables.
    $this->store->set('user_input_files', []);
  } */

  public function import($fid, $processor_id) {
    $import_processor = $this->import_file_process_manager->getProcessorById($processor_id);
    $import_files = $this->getImportFiles();

    // Only import if we have a processor and the fid is in the
    // list of files we processed earlier.
    if ($import_processor && in_array($fid, $import_files)) {
      // Load the import file entity.
      $storage = $this->entity_manager->getStorage('file');
      $importFile = $storage->load($fid);

      // Build the context.
      $context = $this->getContext();

      // Process the file.
      $processed_file = $import_processor->process($importFile, $context);

      // File is ready for deserializing.
      //dpm("import: {$processed_file->id()} via $processor_id");
    } else {
      dpm('add no import processor error handler');
    }
  }

  /**
   * Batch validation operation callback.
   *
   * @param int $fid
   *   The file ID of the file to validate.
   * @param string $processor_fn
   *   The name of the MukurtuImportFileProcessor process function.
   *
   * @return void
   */
  public static function batchValidation($fid, $processor_fn) {
    // The Importer object won't exist when working in the Batch API, so we
    // can't use dependency injection here.
    $storage = \Drupal::entityTypeManager()->getStorage('file');
    $serializer = \Drupal::service('serializer');
    $tempstore = \Drupal::service('tempstore.private');
    $store = $tempstore->get('mukurtu_roundtrip_importer');

    try {
      $file = $storage->load($fid);
      $report = $store->get('import_validation_report');
      $context = [];

      // Should this be array of class/content?
      $result = call_user_func($processor_fn, $file, $context);

      $entities = $serializer->deserialize($result->getData(), $result->getClass(), $result->getFormat(), $result->getContext());
      foreach ($entities as $entity) {
        $violations = $entity->validate();
        // If no violations, save entity to file for later actual import?
        if ($violations->count() == 0) {
          $report[$fid]['valid'][] = $entity;
        } else {
          $report[$fid]['invalid'][] = ['entity' => $entity, 'violations' => $violations];
          /* foreach ($violations as $violation) {
            //$report[$fid][][] = ['filename' => $file->getFileName(), 'message' => $violation->getMessage(), 'propertyPath' => $violation->getPropertyPath()];
            $report[$fid]['invalid'][] = $entity;
             //dpm($violation->getMessage() . " " . $violation->getPropertyPath());
          } */
        }
      }
      // Update saved violations.
      $store->set('import_validation_report', $report);
    } catch (Exception $e) {
      dpm($e);
    }
  }

  /**
   * Batch import operation callback.
   *
   * @param int $fid
   *   The file ID of the file to import.
   * @param string $processor_id
   *   The machine name of the MukurtuImportFileProcessor to use to process
   *   the file.
   *
   * @return void
   */
  public static function batchImport($fid, $processor_id) {
    dpm("batch import($fid, $processor_id)");
  }

  /**
   * Create a batch operations array for import file validation.
   *
   * @param array $inputs
   *   An array of ['id' => file id, 'processor' => import processor]. Should probably make this its own class.
   * @param int $size
   *   Number of items to process per batch.
   *
   * @return array
   *   Return the array of batch operations.
   */
  public function getValidationBatchOperations(array $inputs, $size) {
    $operations = [];
    $chunk_inputs = [];
    foreach ($inputs as $importFile) {
      if (isset($importFile['id']) && isset($importFile['processor'])) {
        $import_processor = $this->import_file_process_manager->getProcessorById($importFile['processor']);
        $storage = $this->entity_manager->getStorage('file');
        $file = $storage->load($importFile['id']);
        if ($import_processor && $file) {
          $chunks = $import_processor->chunkForBatch($file, $size);
          foreach ($chunks as $chunk) {
            $operations[] = [['Drupal\mukurtu_roundtrip\Services\Importer', 'batchValidation'], [$chunk, get_class($import_processor). "::process"]];
            $chunk_inputs[] = ['id' => $chunk, 'processor' => $importFile['processor']];
          }
        }
      }
    }

    // Save the chunks for later reference.
    $this->setBatchChunks($chunk_inputs);

    return $operations;
  }

  public function getImportBatchOperations() {
    $operations = [];
    $chunks = $this->getBatchChunks();
    foreach ($chunks as $chunk) {
      $operations[] = [
        ['Drupal\mukurtu_roundtrip\Services\Importer', 'batchImport'], [$chunk['id'], $chunk['processor']],
      ];
    }
    return $operations;
  }

  public function importMultiple(array $input) {
    foreach ($input as $importFile) {
      if (isset($importFile['id']) && isset($importFile['processor'])) {
        $this->import($importFile['id'], $importFile['processor']);
      }
    }
  }

}
