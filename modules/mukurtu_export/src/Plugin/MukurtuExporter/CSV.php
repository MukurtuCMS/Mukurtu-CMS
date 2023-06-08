<?php

namespace Drupal\mukurtu_export\Plugin\MukurtuExporter;

use Drupal\mukurtu_export\Plugin\ExporterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Plugin implementation of MukurtuExporter for CSV.
 *
 * @MukurtuExporter(
 *   id = "csv",
 *   label = @Translation("CSV"),
 *   description = @Translation("Export to CSV.")
 * )
 */
class CSV extends ExporterBase
{

  public function settingsForm(array $form, FormStateInterface $form_state)
  {
    $form['binary_files'] = [
      '#type' => 'radios',
      '#title' => $this->t('Files to Export'),
      '#default_value' => 'metadata',
      '#options' => [
        'metadata' => $this->t('Export metadata only'),
        'metadata_and_binary' => $this->t('Include media and other files'),
      ]
    ];
    return $form;
  }

  public function getConfig(array &$form, FormStateInterface $form_state)
  {
    $config = [];
    $config['binary_files'] = $form_state->getValue('binary_files');
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function exportSetup($entities, $options, &$context)
  {
    $context['results']['uid'] = \Drupal::currentUser()->id();

    // List of entities to export. We will "consume" these as we export. Empty means done.
    $context['results']['entities'] = $entities;

    // Export configuration options.
    $context['results']['options'] = $options;

    // Track entities that have been exported.
    $context['results']['exported_entities'] = [];

    // Count how many entities we are exporting.
    $context['results']['entities_count'] = array_reduce($entities, function ($accum, $entity_type_array) {
      $accum += count($entity_type_array);
      return $accum;
    });
    $context['results']['exported_entities_count'] = 0;

    // Files IDs for metadata.
    $context['results']['deliverables']['metadata'] = [];

    // Files IDs for binary files to include in the package.
    $context['results']['deliverables']['files'] = [];

    // Create a directory for the output.
    $fs = \Drupal::service('file_system');
    $session_id = session_id();
    $context['results']['basepath'] = "private://exports/{$context['results']['uid']}/$session_id";

    // Delete any existing files.
    $fs->deleteRecursive($context['results']['basepath']);

    // @todo error handling here?
    $fs->prepareDirectory($context['results']['basepath'], FileSystemInterface::CREATE_DIRECTORY);

    $context['message'] = t('Setting up the export.');
  }

  /**
   * {@inheritdoc}
   */
  public static function exportCompleted(&$context)
  {
    // Clean-up.
    $fs = \Drupal::service('file_system');
    $fs->deleteRecursive($context['results']['basepath']);
  }

  /**
   * {@inheritdoc}
   */
  public static function batchSetup(&$context)
  {
    $size = 10;

    // Identify the next batch of entities to export. We only get entities of a single type per batch.
    $entity_types = array_keys($context['results']['entities']);
    $entity_type_id = reset($entity_types);
    $context['sandbox']['batch']['entity_type_id'] = $entity_type_id;
    $context['sandbox']['batch']['entities'] = array_slice($context['results']['entities'][$entity_type_id], 0, $size);

    // Open the output file.
    $entityType = \Drupal::service('entity_type.manager')->getDefinition($entity_type_id);
    $entityLabel = $entityType->getLabel();
    $filePath = "{$context['results']['basepath']}/{$entityLabel}.csv";

    // @todo handle error.
    $context['sandbox']['batch']['output_file'] = fopen($filePath, 'a');
    if (!isset($context['results']['deliverables']['metadata'][$entity_type_id])) {
      $context['results']['deliverables']['metadata'][$entity_type_id] = $filePath;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function batchExport(&$context)
  {
    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $entity_type_id = $context['sandbox']['batch']['entity_type_id'];
    $entities = $context['sandbox']['batch']['entities'];
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
    $output = $context['sandbox']['batch']['output_file'];


    foreach ($entities as $id) {
      $alreadyExported = $context['results']['exported_entities'][$entity_type_id][$id] ?? FALSE;
      if (!$alreadyExported) {
        $entity = $storage->load($id);
        $result = static::class::export($entity, $context);

        // Write out.
        fputcsv($output, $result);

        // Record export of this entity.
        $context['results']['exported_entities'][$entity_type_id][$id] = $id;
        $context['results']['exported_entities_count']++;
      }

      // Remove entity from the list.
      unset($context['results']['entities'][$entity_type_id][$id]);

      // @todo check for memory limits and exit early if getting close?
    }



  }

  /**
   * {@inheritdoc}
   */
  public static function batchCompleted(&$context)
  {
    fclose($context['sandbox']['batch']['output_file']);

    // Remove any empty entity types from the export list.
    foreach ($context['results']['entities'] as $entity_type_id => $entities) {
      if (empty($entities)) {
        unset($context['results']['entities'][$entity_type_id]);
      }
    }

    // We are done exporting if there are no more entities in the export list.
    if (!empty($context['results']['entities'])) {
      $context['finished'] = 0;
    }
  }

  /**
   * Export a single entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return mixed
   *   The exported result.
   */
  public static function export(EntityInterface $entity, &$context)
  {
    return [$entity->id(), $entity->uuid()];
  }

}
