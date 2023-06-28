<?php

namespace Drupal\mukurtu_export\Plugin\MukurtuExporter;

use Drupal\mukurtu_export\Plugin\ExporterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\Core\Url;
use Drupal\Core\Link;
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
      'id' => $this->getPluginId(),
      'settings' => ['settings_id' => NULL],
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

  protected function addSiteWideConfigOptions(&$element) {
    $storage = \Drupal::entityTypeManager()->getStorage('csv_exporter');
    $query = $storage->getQuery();
    $result = $query->condition('site_wide', TRUE)->sort('label')->execute();
    if (!empty($result)) {
      $entities = $storage->loadMultiple($result);
      foreach ($entities as $id => $entity) {
        $element['#options'][$id] = $entity->label();
        $element[$id]['#description'] = $entity->getDescription();
      }
    }
  }

  protected function addUserConfigOptions(&$element) {
    $uid = \Drupal::currentUser()->id();
    $storage = \Drupal::entityTypeManager()->getStorage('csv_exporter');
    $query = $storage->getQuery();
    $result = $query->condition('uid', $uid)->sort('label')->execute();
    $options = [];
    if (!empty($result)) {
      $entities = $storage->loadMultiple($result);
      foreach($entities as $id => $entity) {
        $element['#options'][$id] = $entity->label();
        if ($entity->access('update')) {
          $element['#options'][$id] .= " (" . Link::createFromRoute($this->t('Edit'), 'entity.csv_exporter.edit_form', ['csv_exporter' => $id], [])->toString() . ")";
        }
        $element[$id]['#description'] = $entity->getDescription();
      }
    }
  }

  public function settingsForm(array $form, FormStateInterface $form_state, $settings = [])
  {
    $form['export_settings'] = [
      '#type' => 'radios',
      '#title' => $this->t('Export Settings'),
      '#options' => [],
    ];
    $this->addSiteWideConfigOptions($form['export_settings']);
    $this->addUserConfigOptions($form['export_settings']);

    $option_keys = array_keys($form['export_settings']['#options']);
    $form['export_settings']['#default_value'] = $settings['settings_id'] ?? reset($option_keys);

    $form['new_setting'] = [
      '#type' => 'link',
      '#title' => $this->t('New CSV export setting'),
      '#url' => Url::fromRoute('entity.csv_exporter.add_form'),
    ];

    $form['manage_settings'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage saved CSV export settings'),
      '#url' => Url::fromRoute('entity.csv_exporter.collection'),
    ];
    return $form;
  }

  public function getSettings(array &$form, FormStateInterface $form_state)
  {
    $settings = [];
    $settings['settings_id'] = $form_state->getValue('export_settings');
    return $settings;
  }

  public function duplicateSettings(array &$form, FormStateInterface $form_state) {
    $id = $form_state->getValue('export_settings');

    if (!$id) {
      return NULL;
    }

    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $config */
    if ($config = \Drupal::entityTypeManager()->getStorage('csv_exporter')->load($id)) {
      $dupeConfig = $config->createDuplicate();
      $uuid = $dupeConfig->uuid();
      $uuid = str_replace('-', '_', $uuid);
      $dupeConfig->set('id', $uuid);
      $dupeConfig->set('label', $this->t('Copy of ') . $config->get('label'));
      // Force to user only.
      $dupeConfig->set('site_wide', FALSE);

      // Switch owner to duping user.
      $dupeConfig->setOwnerId(\Drupal::currentUser()->id());
      $dupeConfig->save();
      $form_state->setValue('export_settings', $dupeConfig->id());
      return $dupeConfig->id();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function exportSetup($entities, $options, &$context)
  {
    // Load the settings config entity.
    $id = $options['settings']['settings_id'];
    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $config */
    $config = \Drupal::entityTypeManager()->getStorage('csv_exporter')->load($id);
    // @todo error handling.
    if (!$config) {
      throw new Exception('Failed to load CSV exporter settings.');
    }

    // CSV settings. These won't change from batch to batch.
    $context['results']['csv']['separator'] = $config->getSeparator();
    $context['results']['csv']['enclosure'] = $config->getEnclosure();
    $context['results']['csv']['escape'] = $config->getEscape();
    $context['results']['csv']['eol'] = $config->getEol();

    $context['results']['config_id'] = $options['settings']['settings_id'];
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

    // Load the config entity.
    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $config */
    $context['sandbox']['config'] = \Drupal::entityTypeManager()->getStorage('csv_exporter')->load($context['results']['config_id']);

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
    $context['results']['deliverables']['metadata'][] = ['uri' => $filepath, 'entryname' => basename($filepath)];

    // Check if we've written headers for this file.
    if ($output && !isset($context['results']['headers_written'][$entity_type_id][$bundle])) {

      $headers = $context['sandbox']['config']->getHeaders($entity_type_id, $bundle);

      fputcsv($output, $headers, $context['results']['csv']['separator'], $context['results']['csv']['enclosure'], $context['results']['csv']['escape']);
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

        // Regardless of the result, remove entity from the list.
        unset($context['results']['entities'][$entity_type_id][$id]);

        if (!$entity) {
          // @todo what do we do on this failure?
          continue;
        }

        // Confirm user has access to view this entity.
        if (!$entity->access('view')) {
          // @todo should we inform the user of this failure?
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
        fputcsv($output, $result, $context['results']['csv']['separator'], $context['results']['csv']['enclosure'], $context['results']['csv']['escape']);

        // Record export of this entity.
        $context['results']['exported_entities'][$entity_type_id][$id] = $id;
        $context['results']['exported_entities_count']++;
      }

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
    $event_dispatcher = \Drupal::service('event_dispatcher');

    // Get mapping to determine what fields to export.
    $multiValueDelimiter = $context['sandbox']['config']->getMultivalueDelimiter();

    $export = [];
    foreach($context['sandbox']['config']->getExportFields($entity->getEntityTypeId(), $entity->bundle()) as $field_name) {
      $event = new EntityFieldExportEvent('csv', $entity, $field_name, $context);
      $event_dispatcher->dispatch($event, EntityFieldExportEvent::EVENT_NAME);
      $exportValue = $event->getValue();

      $export[] = implode($multiValueDelimiter, $exportValue);
    }

    return $export;
  }

}
