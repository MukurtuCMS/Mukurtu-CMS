<?php

namespace Drupal\mukurtu_community_records\Controller;

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
    // Can't create community records if the node lacks the fields.
    if (!($node->hasField(MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_COMMUNITY_RECORDS) && $node->hasField(MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD))) {
      return AccessResult::forbidden();
    }

    // If the original record field is set, this is already a community record.
    // Do the rest of the checks using the parent record.
    $original = $node->get(MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD)->referencedEntities();
    if (isset($original[0])) {
      $node = $original[0];
    }

    // TODO: All of this needs to be cut out and put somewhere reusable.
    $protocol_manager = \Drupal::service('mukurtu_protocol.protocol_manager');
    if ($node->access('update', $account)) {
      // These are the protocols the user has create access for.
      $user_protocols = $protocol_manager->getValidProtocols($node->getEntityTypeId(), $node->bundle(), $account);

      // These are the owning communities of those protocols.
      $community_ids = [];
      foreach ($user_protocols as $user_protocol) {
        $community_ids[] = $user_protocol->get(MUKURTU_PROTOCOL_FIELD_NAME_COMMUNITY)->target_id;
      }
      $community_ids = array_unique($community_ids);

      // Remove the existing node's communities from the community list.
      $communities = $node->get(MUKURTU_COMMUNITY_FIELD_NAME_COMMUNITY)->referencedEntities();
      foreach ($communities as $community) {
        $key = array_search($community->id(), $community_ids);
        if ($key !== FALSE) {
          unset($community_ids[$key]);
        }
      }

      // Remove all protocols that already have community records.
      $existing_community_records = $node->get(MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_COMMUNITY_RECORDS)->referencedEntities();
      if (!empty($existing_community_records)) {
        foreach ($existing_community_records as $cr) {
          $communities = $cr->get(MUKURTU_COMMUNITY_FIELD_NAME_COMMUNITY)->referencedEntities();
          foreach ($communities as $community) {
            $key = array_search($community->id(), $community_ids);
            if ($key !== FALSE) {
              unset($community_ids[$key]);
            }
          }
        }
      }

      if (!empty($community_ids)) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::forbidden();
  }

  public function content(NodeInterface $node) {
    $original = $node->get(MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD)->referencedEntities();
    if (isset($original[0])) {
      $node = $original[0];
    }

    // Create a new node form for the given bundle.
    $new_record = Node::create([
      'type' => $node->bundle(),
    ]);

    // Get the node form.
    $form = \Drupal::service('entity.manager')
        ->getFormObject('node', 'default')
        ->setEntity($new_record);

    // Pass a custom submit handler and the collection ID to
    // mukurtu_collection_form_alter. There might be a cleaner way to do this...
    $args = [
      'mukurtu_community_records' => [
        'submit' => ['mukurtu_community_records_add_community_record_form_submit'],
        'target' => $node->id(),
      ],
    ];
    $build[] = \Drupal::formBuilder()->getForm($form, $args);

    return $build;
  }

}
