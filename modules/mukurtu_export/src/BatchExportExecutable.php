<?php

namespace Drupal\mukurtu_export;

use ZipArchive;
use Exception;
use Drupal\file\Entity\File;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class BatchExportExecutable implements MukurtuExportExecutableInterface
{
  use StringTranslationTrait;

  /**
   * The export entity source.
   *
   * @var \Drupal\mukurtu_export\MukurtuExporterSourceInterface
   */
  protected $source;

  protected $exporter;

  public function __construct(MukurtuExporterSourceInterface $source, MukurtuExporterInterface $exporter)
  {
    $this->source = $source;
    $this->exporter = $exporter;
  }

  /**
   * {@inheritDoc}
   */
  public function export()
  {
    $entities = $this->source->getEntities();

    $operations = [
      [sprintf('%s::%s', $this->exporter::class, 'exportSetup'), [$entities, $this->exporter->getConfiguration()]],
      [sprintf('%s::%s', self::class, 'exportBatch'), [$this->exporter::class]],
      [sprintf('%s::%s', self::class, 'package'), []],
      [sprintf('%s::%s', $this->exporter::class, 'exportCompleted'), []],
    ];

    $batch = [
      'operations' => $operations,
      'title' => $this->t('Exporting'),
      'init_message' => $this->t('Initializing export'),
      'progress_message' => $this->t('Exporting...'),
      'error_message' => $this->t('Export failed with error.'),
      'finished' => self::class . '::batchFinishedExport',
    ];

    $_SESSION['mukurtu_export']['results'] = [];
    batch_set($batch);
  }

  /**
   * Run an export batch.
   *
   * @param string $exporter_class
   *   Class of the exporter that implements MukurtuExporterInterface.
   * @param mixed $context
   *   The batch context.
   *
   * @return void
   */
  public static function exportBatch($exporter_class, &$context)
  {
    call_user_func_array([$exporter_class, 'batchSetup'], [&$context]);
    call_user_func_array([$exporter_class, 'batchExport'], [&$context]);
    call_user_func_array([$exporter_class, 'batchCompleted'], [&$context]);
  }

  public static function package(&$context)
  {
    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $batchSize = 1;
    $zip = new ZipArchive();
    if (empty($context['sandbox']['deliverables'])) {
      $context['sandbox']['deliverables'] = array_merge($context['results']['deliverables']['metadata'], $context['results']['deliverables']['files']);
      $context['sandbox']['total'] = count($context['sandbox']['deliverables']);
      $context['sandbox']['packaged'] = 0;
      $zipName = 'export-' . date('m-d-Y_hia') . '.zip';
      $context['sandbox']['download'] = "private://exports/{$context['results']['uid']}/$zipName";
    }

    // Open the Zip archive.
    $zipPath = $fs->realpath($context['sandbox']['download']);
    $zip->open($zipPath, ZipArchive::CREATE);

    // Get the batch of files to zip and remove from the global list.
    $filesBatch = array_slice($context['sandbox']['deliverables'], 0, $batchSize);
    $context['sandbox']['deliverables'] = array_slice($context['sandbox']['deliverables'], $batchSize);

    // Zip the files.
    foreach ($filesBatch as $fileToZip) {
      $filePath = $fs->realpath($fileToZip['uri']);
      if ($filePath) {
        $zip->addFile($filePath, $fileToZip['entryname'] ?? basename($filePath));
      }
      $context['sandbox']['packaged']++;
    }

    $zip->close();

    if (!empty($context['sandbox']['deliverables'])) {
      $context['finished'] = $context['sandbox']['packaged'] / $context['sandbox']['total'];
    } else {
      $context['finished'] = 1;

      // Add the final zip as a managed file for the exporting user.
      $file = File::create([
        'uri' => $context['sandbox']['download'],
        'uid' => $context['results']['uid'],
      ]);
      try {
        $file->save();
        $context['results']['download_fid'] = $file->id();
      } catch (Exception $e) {

      }
    }
  }

  /**
   * Finished callback for export batches.
   *
   * @param bool $success
   *   A boolean indicating whether the batch has completed successfully.
   * @param array $results
   *   The value set in $context['results'] by callback_batch_operation().
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public static function batchFinishedExport(bool $success, array $results, array $operations): void
  {
    $_SESSION['mukurtu_export']['results'] = $results;
    $_SESSION['mukurtu_export']['download_fid'] = $results['download_fid'] ?? NULL;
  }

}
