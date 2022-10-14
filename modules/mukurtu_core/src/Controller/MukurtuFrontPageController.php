<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Mukurtu Front Page.
 */
class MukurtuFrontPageController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => '',
    ];

    return $build;
  }

}
