<?php

namespace Drupal\mukurtu_export\Plugin\MukurtuExporter;

use Drupal\mukurtu_export\Plugin\ExporterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Exception;

/**
 * Plugin implementation of MukurtuExporter for CSV.
 *
 * @MukurtuExporter(
 *   id = "csv",
 *   label = @Translation("CSV"),
 *   description = @Translation("Export to CSV."),
 * )
 */
class CSV extends ExporterBase
{
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration()
  {
    return [
      'settings' => [
        'binary_files' => 'metadata_and_binary',
        'field_mapping' => [
          'node' => [
              'digital_heritage' => [
                'title' => 'Title',
                'field_description' => "Description",
                'field_media_assets' => "Media Assets",
                'nid' => 'ID',
                'uuid' => 'UUID',
              ],
          ],
          'media' => [
            'image' => [
              'mid' => 'ID',
              'uuid' => 'UUID',
              'name' => 'Name',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration()
  {
    return [
      'id' => $this->getPluginId(),
      'settings' => $this->settings,
    ];
  }
  public function settingsForm(array $form, FormStateInterface $form_state)
  {
    $settings = (array) $this->getConfiguration()['settings'] + $this->defaultConfiguration()['settings'];

    $form['binary_files'] = [
      '#type' => 'radios',
      '#title' => $this->t('Files to Export'),
      '#default_value' => $settings['binary_files'],
      '#options' => [
        'metadata' => $this->t('Export metadata only'),
        'metadata_and_binary' => $this->t('Include media and other files'),
      ]
    ];

    return $form;
  }

  public function getSettings(array &$form, FormStateInterface $form_state)
  {
    $settings = [];
    $settings['binary_files'] = $form_state->getValue('binary_files');
    // temp.
    $settings['field_mapping'] = $this->defaultConfiguration()['settings']['field_mapping'];
    return $settings;
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
    $context['results']['settings'] = $options['settings'] ?? [];

    // Track entities that have been exported.
    $context['results']['exported_entities'] = [];

    // Count how many entities we are exporting.
    $context['results']['entities_count'] = array_reduce($entities, function ($accum, $entity_type_array) {
      $accum += count($entity_type_array);
      return $accum;
    });
    $context['results']['exported_entities_count'] = 0;

    // Track if we've written headers for a given file yet.
    $context['results']['headers_written'] = [];
    //$context['results']['headers'] = [];
    $context['results']['headers'] = $context['results']['settings']['field_mapping'];
    //$context['results']['headers']['media'] = $context['results']['settings']['field_mapping']['media'];

    // Files paths for metadata to include in the package.
    $context['results']['deliverables']['metadata'] = [];

    // Files paths for binary files to include in the package.
    $context['results']['deliverables']['files'] = [];

    // Create a directory for the output.
    $fs = \Drupal::service('file_system');
    $session_id = session_id();
    $context['results']['basepath'] = "private://exports/{$context['results']['uid']}/$session_id";

    try {
      $existingFiles = $fs->scanDirectory($context['results']['basepath'], '/.*/');
      if (!empty($existingFiles)) {
        $fs->deleteRecursive($context['results']['basepath']);
      }
    } catch (Exception $e) {

    }

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
    try {
      $fs->deleteRecursive($context['results']['basepath']);
    } catch (Exception $e) {

    }
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
  }

  public static function getOutputFile($entity_type_id, $bundle, &$context) {
    $existing = $context['sandbox']['batch']['output_files'][$entity_type_id][$bundle] ?? FALSE;
    if ($existing) {
      return $existing;
    }

    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type_id);
    $entityType = \Drupal::service('entity_type.manager')->getDefinition($entity_type_id);
    $entityLabel = $entityType->getLabel();
    $bundleLabel = $bundles[$bundle]['label'] ?? $bundle;
    $filename = sprintf("%s - %s.csv", $entityLabel, $bundleLabel);
    $filepath = "{$context['results']['basepath']}/$filename";

    // @todo handle error.
    $output = fopen($filepath, 'a');
    $context['sandbox']['batch']['output_files'][$entity_type_id][$bundle] = $output;

    // Add file as deliverable.
    $context['results']['deliverables']['metadata'][] = $filepath;

    // Check if we've written headers for this file.
    if ($output && !isset($context['results']['headers_written'][$entity_type_id][$bundle])) {
      fputcsv($output, $context['results']['headers'][$entity_type_id][$bundle]);
      $context['results']['headers_written'][$entity_type_id][$bundle] = TRUE;
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public static function batchExport(&$context)
  {
    $entity_type_id = $context['sandbox']['batch']['entity_type_id'];
    $entities = $context['sandbox']['batch']['entities'];
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);

    foreach ($entities as $id) {
      $alreadyExported = $context['results']['exported_entities'][$entity_type_id][$id] ?? FALSE;
      if (!$alreadyExported) {
        $entity = $storage->load($id);
        if (!$entity) {
          // @todo what do we do on this failure?
          continue;
        }

        // Get output file for bundle.
        $output = static::class::getOutputFile($entity_type_id, $entity->bundle(), $context);
        if (!$output) {
          // @todo what do we do on this failure?
          continue;
        }

        // Export the entity.
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
    foreach ($context['sandbox']['batch']['output_files'] as $entity_type_id => $bundleFiles) {
      foreach ($bundleFiles as $bundle => $outputfile) {
        if ($outputfile) {
          fclose($outputfile);
          unset($context['sandbox']['batch']['output_files'][$entity_type_id][$bundle]);
        }
      }
    }
    //fclose($context['sandbox']['batch']['output_file']);

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
    //$moduleHandler = \Drupal::moduleHandler();
    $event_dispatcher = \Drupal::service('event_dispatcher');

    // Get mapping to determine what fields to export.
    $multiValueDelimiter = '||';
    $field_mapping = $context['results']['settings']['field_mapping'];
    $export = [];
    foreach ($field_mapping[$entity->getEntityTypeId()][$entity->bundle()] as $field_name => $label) {
      $event = new EntityFieldExportEvent('csv', $entity, $field_name, $context);
      $event_dispatcher->dispatch($event, EntityFieldExportEvent::EVENT_NAME);
      $exportValue = $event->getValue();

      //$moduleHandler->alter("exporter_csv_field_{$fieldType}", $exportValue, $entity, $field_name);
      $export[] = implode($multiValueDelimiter, $exportValue);
    }

    return $export;
  }

}
