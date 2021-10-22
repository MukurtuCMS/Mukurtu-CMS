<?php

namespace Drupal\mukurtu_community_records\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\NodeType;

class MukurtuCommunityRecordAddNodeController extends ControllerBase {
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
  public function access(AccountInterface $account, NodeInterface $node, NodeType $node_type = NULL) {
    // Desired community record content type must have the CR fields.
    if (!mukurtu_community_records_entity_type_supports_records('node', $node_type->getOriginalId())) {
      return AccessResult::forbidden();
    }

    // CR content type must be on the allowed bundle list.
    $config = $this->config('mukurtu_community_records.settings');
    $allowed_bundles = $config->get('allowed_community_record_bundles');
    if (!in_array($node_type->getOriginalId(), $allowed_bundles)) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  public function content(NodeInterface $node, NodeType $node_type = NULL) {
    $bundle = $node_type->getOriginalId();

    // Find the original record.
    $original = $node->get(MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD)->referencedEntities();
    if (isset($original[0])) {
      $node = $original[0];
    }

    // Create a new node form for the given bundle.
    $new_record = Node::create([
      'type' => $bundle,
      MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD => $node->id(),
    ]);

    // Get the node form.
    $form = $this->entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($new_record);

    $form_title = $this->t('Original Record');
    $build[] = ['#type' => 'markup', '#markup' => "<h2>$form_title</h2>"];
    $build[] = $this->entityTypeManager()->getViewBuilder('node')->view($node, 'content_browser');
    $build[] = $this->formBuilder()->getForm($form, $args);

    // It doesn't make sense for community records to use protocol
    // inheritance, so don't show the field.
    $build[2][MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET]['#access'] = FALSE;

    // Don't show the original record field.
    $build[2][MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD]['#access'] = FALSE;

    // Hide the media assets field.
    $build[2]['field_media_assets']['#access'] = FALSE;

    return $build;
  }

}
