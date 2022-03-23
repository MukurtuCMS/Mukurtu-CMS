<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Core\Controller\ControllerBase;

class MyPersonalCollectionsController extends ControllerBase {

  public function content() {
    // Render the browse view block.
    $browse_view_block = [
      '#type' => 'view',
      '#name' => 'my_personal_collections',
      '#display_id' => 'my_personal_collections_block',
      '#embed' => TRUE,
    ];

    return [
      '#theme' => 'mukurtu_my_personal_collections',
      '#results' => $browse_view_block,
    ];
  }
}
