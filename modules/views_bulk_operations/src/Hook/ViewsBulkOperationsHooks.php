<?php

declare(strict_types=1);

namespace Drupal\views_bulk_operations\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Contains module hook implementations.
 */
class ViewsBulkOperationsHooks {

  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    $data['views']['views_bulk_operations_bulk_form'] = [
      'title' => t('Views bulk operations'),
      'help' => t("Process entities returned by the view with Views Bulk Operations' actions."),
      'field' => [
        'id' => 'views_bulk_operations_bulk_form',
      ],
    ];
  }

  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name !== 'help.page.views_bulk_operations') {
      return NULL;
    }
    $filepath = \dirname(__FILE__) . '/README.md';
    if (!\file_exists($filepath)) {
      return NULL;
    }
    $readme = file_get_contents($filepath);
    return '<pre>' . $readme . '</pre>';
  }

  #[Hook('preprocess_views_view_table')]
  public function preprocessViewsViewTable(array &$variables): void {
    if (!\array_key_exists('views_bulk_operations_enabled', $variables['view']->style_plugin->options)) {
      return;
    }
    if ($variables['view']->style_plugin->options['views_bulk_operations_enabled'] === TRUE) {
      // Add module own class to improve resistance to theme overrides.
      $variables['attributes']['class'][] = 'vbo-table';
    }
  }

}
