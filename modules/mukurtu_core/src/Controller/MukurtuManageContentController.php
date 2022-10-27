<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;

class MukurtuManageContentController extends ControllerBase {

  /**
   * Check access for adding new community records.
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    $account = \Drupal::currentUser();
    if (!$account->isAnonymous()) {
      return AccessResult::allowedIfHasPermission($account, 'administer nodes');
    }

    return AccessResult::forbidden();
  }

  public function addPage() {
    $build = [
      '#theme' => 'mukurtu_node_add_list',
      '#cache' => [
        'tags' => $this->entityTypeManager()->getDefinition('node_type')->getListCacheTags(),
      ],
    ];

    $content = [];

    // Only use node types the user has access to.
    foreach ($this->entityTypeManager()->getStorage('node_type')->loadMultiple() as $type) {
      $access = $this->entityTypeManager()->getAccessControlHandler('node')->createAccess($type->id(), NULL, [], TRUE);
      if ($access->isAllowed()) {
        $content[$type->id()] = $type;
      }
    }

    // Bypass the node/add listing if only one content type is available.
    if (count($content) == 1) {
      $type = array_shift($content);
      return $this->redirect('mukurtu_core.add', ['node_type' => $type->id()]);
    }

    $build['#content'] = $content;

    return $build;
  }
}
