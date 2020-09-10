<?php

/**
 * @param array $options
 * @param $context
 */
function mukurtu_migrate_import_from_remote($options, &$context) {
  $migrationManager = $options['migration_manager'];
  $manifest = $options['manifest'] ?? [];
  $form_id = $options['form_id'] ?? 'import';

  // Start of batch run.
  if (empty($context['sandbox'])) {
    $context['sandbox']['file'] = 0;
    $context['sandbox']['offset'] = 0;
    $context['sandbox']['max'] = count($manifest);
  }

  $current_file = $manifest[$context['sandbox']['file']];

  // Done with the current file, move to the next.
  $context['sandbox']['file'] += 1;
  dpm($current_file);

  // Progress bar.
  if ($context['sandbox']['file'] < $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['file'] / $context['sandbox']['max'];
  }
}

/**
 * Callback when the batch processing is complete.
 * @param $success
 * @param $results
 * @param $operations
 */
function mukurtu_migrate_migration_complete_callback($success, $results, $operations) {
/*   $save = $results['save'] ?? FALSE;
  $valid = $results['valid_entities'] ?? 0;
  $form_id = $results['form_id'] ?? 'import';
  $violations = $results['violations'];

  if ($success) {
    if ($save) {
      $message = t('Successfully imported @valid entities.', ['@valid' => $valid]);
    } else {
      $message = t('Import file is valid, you can now import.');
      $_SESSION['mukurtu_roundtrip'][$form_id]['valid'] = TRUE;
    }
  } else {
    $message = t('There were some errors.');
    $_SESSION['mukurtu_roundtrip'][$form_id]['valid'] = FALSE;
    dpm($violations);
  }
  drupal_set_message($message); */
}

/**
 * @param array $options
 * @param $context
 */
function mukurtu_migrate_import_summary($options, &$context) {
  $directory = 'private://mukurtu_migrate/';
  $migrationManager = $options['migration_manager'];
  $form_id = $options['form_id'] ?? 'import';
  $items_per_batch = 20;

  // Endpoints to slurp up data from.
  $steps = [
    ['endpoint' => '/tax-vocab', 'type' => 'Taxonomy Vocabularies', 'filename' => 'vocab.json'],
    ['endpoint' => '/tax-term', 'type' => 'Taxonomy Terms', 'filename' => 'terms.json'],
    ['endpoint' => '/scald', 'type' => 'Media', 'filename' => 'scald.json'],
    ['endpoint' => '/node', 'type' => 'Nodes', 'filename' => 'nodes.json'],
  ];

  // Start of batch run.
  if (empty($context['sandbox'])) {
    $context['sandbox']['step'] = 0;
    $context['sandbox']['offset'] = 0;
    $context['sandbox']['max'] = count($steps);
    $context['sandbox']['files'] = [];
    $context['sandbox']['manifest'] = [];

    // Create directory to write json files to.
    file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
  }

  // Batch run is complete.
  if (!isset($steps[$context['sandbox']['step']])) {
    $context['finished'] = 1;
    return;
  }

  $current_step = $steps[$context['sandbox']['step']];

  // Run batch.
  $batch = $migrationManager->fetchBatch($current_step['endpoint'], $context['sandbox']['offset'], $items_per_batch);
  $size_of_this_batch = count($batch);

  // Ran out of data for this step.
  if ($size_of_this_batch == 0) {
    // Go to the next step.
    $context['sandbox']['step'] += 1;
    $context['sandbox']['offset'] = 0;
  } else {
    $context['sandbox']['offset'] = $context['sandbox']['offset'] + $size_of_this_batch;

    // Write chunk to file.
    if (!isset($context['sandbox']['files'][$context['sandbox']['step']])) {
      // File doesn't exist yet, create a new one.
      $file = file_save_data(json_encode($batch), $directory . $current_step['filename'], FILE_EXISTS_REPLACE);
      $context['sandbox']['files'][$context['sandbox']['step']] = $file;

      // Add to manifest.
      $context['sandbox']['manifest'][] = $directory . $current_step['filename'];
      file_save_data(json_encode($context['sandbox']['manifest']), $directory . 'manifest.json', FILE_EXISTS_REPLACE);
    } else {
      // File exists, append the batch data.
      file_put_contents(drupal_realpath($directory . $current_step['filename']), json_encode($batch), FILE_APPEND);
    }
  }

  // Status message.
  $message = t("Fetching all @type, @count fetched", ['@type' => $current_step['type'], '@count' => $context['sandbox']['offset']]);
  $context['message'] = $message;

  // Progress bar.
  if ($context['sandbox']['step'] < $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['step'] / $context['sandbox']['max'];
  }
}

/**
 * Callback when the batch processing is complete.
 *
 * @param $success
 * @param $results
 * @param $operations
 */
function mukurtu_migrate_summary_complete_callback($success, $results, $operations) {
}
