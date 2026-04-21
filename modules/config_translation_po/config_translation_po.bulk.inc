<?php

/**
 * @file
 * Mass import-export and batch import functionality for Gettext .po files.
 */

/**
 * Builds a locale batch to refresh configuration.
 *
 * @param array $options
 *   An array with options that can have the following elements:
 *   - 'finish_feedback': (optional) Whether or not to give feedback to the user
 *     when the batch is finished. Defaults to TRUE.
 * @param array $langcodes
 *   (optional) Array of language codes. Defaults to all translatable languages.
 * @param array $components
 *   (optional) Array of component lists indexed by type. If not present or it
 *   is an empty array, it will update all components.
 *
 * @return array
 *   The batch definition.
 */
function config_translation_po_config_batch_update_components(array $options, array $langcodes = [], array $components = []) {
  $langcodes = $langcodes ?: array_keys(\Drupal::languageManager()->getLanguages());
  if ($langcodes &&
    $names = \Drupal::service('ctp.config_manager')->getComponentNames($components)) {
    return config_translation_po_config_batch_build($names, $langcodes, $options);
  }
}

/**
 * Creates a locale batch to refresh specific configuration.
 *
 * @param array $names
 *   List of configuration object names (which are strings) to update.
 * @param array $langcodes
 *   List of language codes to refresh.
 * @param array $options
 *   (optional) An array with options that can have the following elements:
 *   - 'finish_feedback': Whether or not to give feedback to the user when the
 *     batch is finished. Defaults to TRUE.
 *
 * @return array
 *   The batch definition.
 *
 * @see config_translation_po_config_batch_refresh_name()
 */
function config_translation_po_config_batch_build(array $names, array $langcodes, array $options = []) {
  $options += ['finish_feedback' => TRUE];
  $i = 0;
  $batch_names = [];
  $operations = [];
  foreach ($names as $name) {
    $batch_names[] = $name;
    $i++;
    // During installation the caching of configuration objects is disabled so
    // it is very expensive to initialize the \Drupal::config() object on each
    // request. We batch a small number of configuration object upgrades
    // together to improve the overall performance of the process.
    if ($i % 20 == 0) {
      $operations[] = [
        'config_translation_po_config_batch_refresh_name',
        [$batch_names, $langcodes],
      ];
      $batch_names = [];
    }
  }
  if (!empty($batch_names)) {
    $operations[] = [
      'config_translation_po_config_batch_refresh_name',
      [$batch_names, $langcodes],
    ];
  }
  $batch = [
    'operations'    => $operations,
    'title'         => t('Updating configuration translations'),
    'init_message'  => t('Starting configuration update'),
    'error_message' => t('Error updating configuration translations'),
    'file'          => \Drupal::service('extension.list.module')->getPath('config_translation_po') . '/config_translation_po.bulk.inc',
  ];
  if (!empty($options['finish_feedback'])) {
    $batch['completed'] = 'config_translation_po_config_batch_finished';
  }
  return $batch;
}

/**
 * Implements callback_batch_operation().
 *
 * Performs configuration translation refresh.
 *
 * @param array $names
 *   An array of names of configuration objects to update.
 * @param array $langcodes
 *   (optional) Array of language codes to update. Defaults to all languages.
 * @param array|\ArrayAccess $context
 *   Contains a list of files imported.
 *
 * @see config_translation_po_config_batch_build()
 */
function config_translation_po_config_batch_refresh_name(array $names, array $langcodes, &$context) {
  if (!isset($context['result']['stats']['config'])) {
    $context['result']['stats']['config'] = 0;
  }
  $context['result']['stats']['config'] += \Drupal::service('ctp.config_manager')
    ->updateConfigTranslations($names, $langcodes);
  foreach ($names as $name) {
    $context['result']['names'][] = $name;
  }
  $context['result']['langcodes'] = $langcodes;
  $context['finished'] = 1;
}

/**
 * Implements callback_batch_finished().
 *
 * Finishes callback of system page locale import batch.
 *
 * @param bool $success
 *   Information about the success of the batch import.
 * @param array $results
 *   Information about the results of the batch import.
 *
 * @see config_translation_po_config_batch_build()
 */
function config_translation_po_config_batch_finished($success, array $results) {
  if ($success) {
    $configuration = $results['stats']['config'] ?? 0;
    if ($configuration) {
      \Drupal::messenger()->addStatus(t('The configuration was successfully updated. There are %number configuration objects updated.', ['%number' => $configuration]));
      \Drupal::logger('locale')->notice('The configuration was successfully updated. %number configuration objects updated.', ['%number' => $configuration]);
    }
    else {
      \Drupal::messenger()->addStatus(t('No configuration objects have been updated.'));
      \Drupal::logger('locale')->warning('No configuration objects have been updated.');
    }
  }
}
