<?php

namespace Drupal\mukurtu_import\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Mukurtu Import routes.
 */
class MukurtuImportController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
