<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the site's custom 404 (not found) page.
 */
class NotFoundPageController extends ControllerBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_core.not_found';

  /**
   * Title callback: returns the manager-configurable page title.
   */
  public function getTitle() {
    $title = $this->config(static::SETTINGS)->get('title');
    return $title ?: $this->t('Page Not Found');
  }

  /**
   * Builds the not found page content.
   */
  public function build() {
    $config = $this->config(static::SETTINGS);

    $message = $config->get('message') ?? [];
    $build = [];
    if (!empty($message['value'])) {
      $build['message'] = [
        '#type' => 'processed_text',
        '#text' => $message['value'],
        '#format' => $message['format'] ?? 'full_html',
      ];
    }

    $build['#cache']['tags'] = $config->getCacheTags();

    return $build;
  }

}
