<?php

namespace Drupal\mukurtu_content_warnings\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/* use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;

use Drupal\Core\Link;
use Drupal\Core\Url;
 */
class MukurtuManageContentWarningsController extends ControllerBase {

  public function access(AccountInterface $account, NodeInterface $node) {
    return AccessResult::forbidden();
/*     if ($node->bundle() != 'community') {
      return AccessResult::forbidden();
    }

    if ($node->access('update', $account)) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden(); */
  }

  public function content(NodeInterface $node) {
    $build = [];
    return $build;
  }

}
