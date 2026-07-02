<?php

namespace Drupal\mukurtu_workflows\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Renders the /my-content page with two sections: in-progress and published.
 */
class MyContentController extends ControllerBase {

  public function view(): array {
    return [
      'in_progress' => [
        '#type' => 'container',
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('In progress'),
        ],
        'view' => [
          '#type' => 'view',
          '#name' => 'mukurtu_workflow_overview',
          '#display_id' => 'my_content_in_progress',
          '#embed' => TRUE,
        ],
      ],
      'published' => [
        '#type' => 'container',
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Published'),
        ],
        'view' => [
          '#type' => 'view',
          '#name' => 'mukurtu_workflow_overview',
          '#display_id' => 'my_content_published',
          '#embed' => TRUE,
        ],
      ],
    ];
  }

}
