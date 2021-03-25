<?php

/**
 * @param array $options
 * @param $context
 */
function mukurtu_roundtrip_importbatch($options, &$context) {
  $save = $options['save'] ?? FALSE;
  $input = $options['input'] ?? [];
  $form_id = $options['form_id'] ?? 'import';

  // Start of batch run.
  if (empty($context['sandbox'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current_id'] = 0;
    $context['sandbox']['violations'] = [];
    $context['sandbox']['valid_entities'] = 0;
    $context['sandbox']['max'] = count($input) - 1;
  }

  if (empty($context['results'])) {
    $context['results']['save'] = $save;
    $context['results']['valid_entities'] = 0;
    $context['results']['violations'] = [];
    $context['results']['form_id'] = $form_id;
  }
  $limit = 5;

  /* Process this batch. */

  // Break off a chunk of the input data.
  $this_batch_input = [];
  $this_batch_input = array_slice($input, $context['sandbox']['progress'] + 1, $limit);
  $batch_temp = tmpfile();

  // Write headers.
  fputcsv($batch_temp, $input[0]);

  // Write data.
  foreach ($this_batch_input as $line) {
    if (!empty($line)) {
      // Skip completely empty lines.
      if ((count($line) == 1) && trim($line) == "") {
        continue;
      }
      fputcsv($batch_temp, $line);
    }
  }

  // Get the raw data to hand to the serializer.
  $raw_batch_input = file_get_contents(stream_get_meta_data($batch_temp)['uri']);
  fclose($batch_temp);

  // Run this chunk of the import file through the deserializer.
  $serializer = \Drupal::service('serializer');
  try {
    $entities = $serializer->deserialize($raw_batch_input, 'Drupal\node\Entity\Node', 'csv', []);

    // Validate the resultant entities.
    foreach ($entities as $entity) {
      $violations = $entity->validate();
      if ($violations->count() > 0) {
        foreach ($violations as $violation) {
          $context['results']['violations'][] = $violation->getMessage();
        }
      } else {
        $context['results']['valid_entities']++;
        if ($save) {
          $entity->save();
        }
      }
    }
  } catch (Exception $e) {
    $trace = $e->getTrace();
    $lineNumber = NULL;
    if (isset($trace[0]['args'][0])) {
      $badLine = implode('||', $trace[0]['args'][0]);

      foreach ($this_batch_input as $delta => $inputLine) {
        $thisLine = implode('||', $inputLine);
        if ($badLine == $thisLine) {
          $lineNumber = $delta + $context['sandbox']['progress'] + 1;
        }
      }
    }

    if ($lineNumber) {
      $context['results']['violations'][$lineNumber] = $e->getMessage();
    } else {
      $context['results']['violations'][] = $e->getMessage();
    }
  }

  // Status message.
  $current = $context['sandbox']['progress'] + 1;
  if ($save) {
    $message = t("Importing @current of @max", ['@current' => $current, '@max' => $context['sandbox']['max']]);
  } else {
    $message = t("Validating import data @current of @max", ['@current' => $current, '@max' => $context['sandbox']['max']]);
  }
  $context['message'] = $message;

  // Progress bar.
  $context['sandbox']['progress'] += $limit;
  if ($context['sandbox']['progress'] < $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
}

/**
 * Callback when the batch processing is complete.
 * @param $success
 * @param $results
 * @param $operations
 */
function mukurtu_roundtrip_import_complete_callback($success, $results, $operations) {
  $save = $results['save'] ?? FALSE;
  $valid = $results['valid_entities'] ?? 0;
  $form_id = $results['form_id'] ?? 'import';
  $violations = $results['violations'];

  if ($success && count($violations) == 0) {
    if ($save) {
      $message = t('Successfully imported @valid entities.', ['@valid' => $valid]);
    } else {
      $message = t('Import file is valid, you can now import.');
      $_SESSION['mukurtu_roundtrip'][$form_id]['valid'] = TRUE;
    }
    \Drupal::messenger()->addMessage($message, 'status');
  } else {
    $message = t('There were some errors.');
    $_SESSION['mukurtu_roundtrip'][$form_id]['valid'] = FALSE;
    $_SESSION['mukurtu_roundtrip'][$form_id]['violations'] = $violations;
    foreach ($violations as $delta => $violation) {
      \Drupal::messenger()->addMessage("Row $delta: " . $violation, 'error');
    }
  }

}
