<?php

namespace Drupal\mukurtu_export\Controller;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_export\Form\ExportListAddItemForm;
use Drupal\node\NodeInterface;

/**
 * "Add to export list" tab on a node's full view page.
 *
 * Forwards to the same picker form (ExportListAddItemForm) used by the
 * browse-card quick action, so both surfaces share one dialog experience.
 * A dedicated {node} route (rather than reusing the entity_type/entity_id
 * route directly as a local task) matches the pattern already used by the
 * "Add to Collection" and "Add to Word List" tabs.
 */
class ExportListAddNodeController extends ControllerBase {

  /**
   * Access callback for the route.
   *
   * The route's _permission only confirms export-manager status, not that
   * the user may view $node - without this, an arbitrary node ID (including
   * one behind a protocol the user isn't a member of) would be reachable.
   */
  public function access(AccountInterface $account, NodeInterface $node): AccessResultInterface {
    return $node->access('view', $account, TRUE);
  }

  /**
   * Add node to export list page.
   */
  public function content(NodeInterface $node) {
    return $this->formBuilder()->getForm(ExportListAddItemForm::class, 'node', (string) $node->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(NodeInterface $node) {
    return $this->t('Add %node to Export List', ['%node' => $node->getTitle()]);
  }

}
