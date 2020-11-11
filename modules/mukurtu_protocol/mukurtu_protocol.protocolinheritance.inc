<?php

function mukurtu_protocol_batch_copy_protocol_inheritance($options, &$context) {
  $protocol_manager = \Drupal::service('mukurtu_protocol.protocol_manager');
  $ids = $options['ids'];
  $entity = $options['entity'];

  // Start of batch run.
  if (empty($context['sandbox'])) {
    $context['sandbox']['entity_types'] = array_keys($ids);
    $context['sandbox']['entity_type_index'] = 0;
    $context['sandbox']['offset'] = 0;

    $context['sandbox']['processed'] = 0;
    $context['sandbox']['max'] = 0;
    foreach ($ids as $entity_type => $entity_ids) {
      $context['sandbox']['max'] += count($entity_ids);
    }
  }

  // Current entity type of the batch run.
  $entity_type = $context['sandbox']['entity_types'][$context['sandbox']['entity_type_index']];

  // Get a batch of ids.
  $ids_batch = array_slice($ids[$entity_type], $context['sandbox']['offset'], 10);

  if (!empty($ids_batch)) {
    // Load all the entities.
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $entities = $entity_storage->loadMultiple($ids_batch);

    // Copy the protocol field for each.
    foreach ($entities as $entity_to_update) {
      $protocol_manager->copyProtocolFields($entity, $entity_to_update);
    }

    $context['sandbox']['offset'] += count($ids_batch);
    $context['sandbox']['processed'] += count($ids_batch);
  } else {
    // Empty batch, do we have more entity types to check?
    $context['sandbox']['entity_type_index'] += 1;
    if (isset($context['sandbox']['entity_types'][$context['sandbox']['entity_type_index']])) {
      // We do have more entity types, reset the offset.
      $context['sandbox']['offset'] = 0;
    } else {
      // We don't have anything else to process.
      $context['sandbox']['processed'] = $context['sandbox']['max'];
    }
  }

  // Status message.
  $message = t("Checking protocol inheritance for @count of @total items", ['@count' => $context['sandbox']['processed'], '@total' => $context['sandbox']['max']]);
  $context['message'] = $message;

  // Progress bar.
  if ($context['sandbox']['max'] > 0) {
    $context['finished'] = $context['sandbox']['processed'] / $context['sandbox']['max'];
  }
}
