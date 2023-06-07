<?php

namespace Drupal\mukurtu_export;

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
            [sprintf('%s::%s', $this->exporter::class, 'exportSetup'), [$entities, []]],
            [sprintf('%s::%s', self::class, 'exportBatch'), ['node', $this->exporter::class]],
            [sprintf('%s::%s', $this->exporter::class, 'exportCompleted'), []],
        ];

        $batch = [
            'operations' => $operations,
            'title' => $this->t('Exporting'),
            'init_message' => $this->t('Initializing export'),
            'progress_message' => $this->t('Exporting...'),
            'error_message' => $this->t('Export failed with error.'),
            'finished' => '\Drupal\migrate_export\MigrateBatchExecutable::batchFinishedImport',
        ];

        batch_set($batch);
    }

    public static function exportBatch($entity_type_id, $exporter_class, &$context)
    {
        call_user_func_array([$exporter_class, 'batchSetup'], [&$context]);
        call_user_func_array([$exporter_class, 'batchExport'], [&$context]);
        call_user_func_array([$exporter_class, 'batchCompleted'], [&$context]);
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
    }

}