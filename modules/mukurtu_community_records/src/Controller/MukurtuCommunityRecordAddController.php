<?php

namespace Drupal\mukurtu_community_records\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/* use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;

use Drupal\Core\Link;
use Drupal\Core\Url;
 */
class MukurtuCommunityRecordAddController extends ControllerBase {
  /**
   * Check access for adding new community records.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\node\NodeInterface $node
   *   The node that the user is trying to create
   *   a community record from.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, NodeInterface $node) {
    $config = $this->config('mukurtu_community_records.settings');
    $allowed_bundles = $config->get('allowed_community_record_bundles');

    // Node must support CRs.
    if (!mukurtu_community_records_entity_type_supports_records($node->getEntityTypeId(), $node->bundle())) {
      return AccessResult::forbidden();
    }

    // If the site has no allowed CR types, nobody can create CRs.
    if (empty($allowed_bundles)) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  public function content(NodeInterface $node) {
    $config = $this->config('mukurtu_community_records.settings');
    $allowed_bundles = $config->get('allowed_community_record_bundles');

    $build = [
      '#theme' => 'mukurtu_select_community_record_type',
      '#cache' => [
        'tags' => $this->entityTypeManager()->getDefinition('node_type')->getListCacheTags(),
      ],
    ];

    $types = [];
    foreach ($this->entityTypeManager()->getStorage('node_type')->loadMultiple($allowed_bundles) as $type) {
      if (mukurtu_community_records_entity_type_supports_records('node', $type->id())) {
        $types[$type->id()] = $type;
      }
    }

    $build['#node'] = $node;
    $build['#types'] = $types;

    return $build;
  }

}
