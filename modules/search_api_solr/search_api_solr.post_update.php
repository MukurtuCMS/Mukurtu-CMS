<?php

/**
 * @file
 * Post updates.
 */

/**
 * Delete Solr 4 and 5 field types.
 */
function search_api_solr_post_update_8204_replace_solr_4_field_types() {
  try {
    $storage = \Drupal::entityTypeManager()->getStorage('solr_field_type');
    $storage->delete($storage->loadMultiple([
      'm_text_und_5_2_0',
      'text_und_4_5_0',
      'm_text_de_5_2_0',
      'm_text_en_5_2_0',
      'm_text_nl_5_2_0',
      'text_cs_5_0_0',
      'text_de_4_5_0',
      'text_de_5_0_0',
      'text_de_scientific_5_0_0',
      'text_el_4_5_0',
      'text_en_4_5_0',
      'text_es_4_5_0',
      'text_fi_4_5_0',
      'text_fr_4_5_0',
      'text_it_4_5_0',
      'text_nl_4_5_0',
      'text_ru_4_5_0',
      'text_uk_4_5_0',
    ]));
  }
  catch (\Exception $e) {
    // Don't break the upgrade, ignore the error because it is just nice to have
    // cleanup.
  }
}

/**
 * Install new Solr Field Types and uninstall search_api_solr_multilingual.
 */
function search_api_solr_post_update_8319() {
  $module_handler = \Drupal::moduleHandler();
  if ($module_handler->moduleExists('search_api_solr_multilingual')) {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = \Drupal::service('module_installer');
    $module_installer->uninstall(['search_api_solr_multilingual']);
  }
  // module_load_include is required in case that no update_hooks were run
  // before.
  $module_handler->loadInclude('search_api_solr', 'install');
  search_api_solr_update_helper_install_configs();
}

/**
 * Install new Search API Solr Autocomplete.
 */
function search_api_solr_post_update_8320(): void {
  if (\Drupal::moduleHandler()->moduleExists('search_api_autocomplete')) {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = \Drupal::service('module_installer');
    $module_installer->install(['search_api_solr_autocomplete']);
  }
}
