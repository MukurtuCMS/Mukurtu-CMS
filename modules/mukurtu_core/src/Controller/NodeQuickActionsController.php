<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles quick publish/unpublish actions on individual nodes.
 */
class NodeQuickActionsController extends ControllerBase {

  public function publish(NodeInterface $node, Request $request): RedirectResponse {
    $node->setPublished()->save();
    $this->messenger()->addStatus($this->t('%title has been published.', ['%title' => $node->label()]));
    return $this->redirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
  }

  public function unpublish(NodeInterface $node, Request $request): RedirectResponse {
    $node->setUnpublished()->save();
    $this->messenger()->addStatus($this->t('%title has been unpublished.', ['%title' => $node->label()]));
    return $this->redirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
  }

}
