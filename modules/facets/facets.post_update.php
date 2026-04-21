<?php

/**
 * @file
 * Post-update functions for the Facets module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\views\ViewEntityInterface;

/**
 * Add the hierarchy processor to facets that have the hierarchy enabled.
 */
function facets_post_update_8001_add_hierarchy_processor() {
  $config_factory = \Drupal::configFactory();

  // Find all facets that have the hierarchy enabled, but do not use the
  // hierarchy processor.
  foreach ($config_factory->listAll('facets.facet.') as $facet_config_name) {
    $facet = $config_factory->getEditable($facet_config_name);
    if ($facet->get('use_hierarchy')) {
      $processor_configs = $facet->get('processor_configs');
      if (!isset($processor_configs['hierarchy_processor'])) {
        // Enable the hierarchy processor.
        $processor_configs['hierarchy_processor'] = [
          'id' => 'hierarchy_processor',
          'weights' => [
            'build' => 100,
          ],
          'settings' => [],
        ];
        $facet->set('processor_configs', $processor_configs);
        $facet->save(TRUE);
      }
    }
  }
}

/**
 * Add missing "processor_id" to existing views with exposed facets.
 */
function facets_post_update_add_processor_id_to_views(&$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view): bool {
    $displays = $view->get('display');
    $changed = FALSE;
    /** @var \Drupal\views\Plugin\views\display\DisplayPluginInterface $display */
    foreach ($displays as &$display) {
      if (!empty($display['display_options']['filters'])) {
        foreach ($display['display_options']['filters'] as &$handler) {
          if (is_array($handler) && array_key_exists('facet', $handler)) {
            $changed = TRUE;
            foreach ($handler['facet']['processor_configs'] as $processor_id => &$processor_config) {
              $processor_config['processor_id'] = $processor_id;
            }
            unset($processor_config);
          }
        }
        unset($handler);
      }
    }

    if ($changed) {
      $view->set('display', $displays);
    }

    return $changed;
  });
}
