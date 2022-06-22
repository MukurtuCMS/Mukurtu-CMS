<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

class ContentFoundInController extends ControllerBase {

  /**
   * Check access for viewing the "found in" entity report.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node) {
    return $node->access('update', $this->currentUser(), TRUE);
  }

  /**
   * Build the "found in" report.
   */
  public function content(NodeInterface $node) {
    $build = [];
    $build[] = ['#markup' => $node->id()];
    return $build;
  }

}
