<?php

namespace Drupal\mukurtu_community_records\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

/**
 * Controller to manage Community Record creation/management.
 */
class CommunityRecordController extends ControllerBase {

  /**
   * Check access for adding new community records.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that the user is trying to create
   *   a community record from.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node, AccountInterface $account) {
    // Check if CRs are enabled site wide for this bundle.
    $crConfig = $this->config('mukurtu_community_records.settings');
    $allowedBundles = $crConfig->get('allowed_community_record_bundles');
    if (!in_array($node->bundle(), $allowedBundles)) {
      // Not enabled for community records.
      return AccessResult::forbidden();
    }

    // Node might be a CR, find the OR.
    $originalRecord = $this->getOriginalRecord($node);

    // Original record must support protocols in order to have community
    // records.
    if (!($originalRecord instanceof CulturalProtocolControlledInterface)) {
      return AccessResult::forbidden();
    }

    // Fail if the account can't view the original record.
    if (!$originalRecord->access('view', $account)) {
      return AccessResult::forbidden();
    }

    // Check for the community record admin permission in one of the
    // owning protocols.
    if ($protocols = $originalRecord->getProtocolEntities()) {
      foreach ($protocols as $protocol) {
        $membership = $protocol->getMembership($account);
        if ($membership && $membership->hasPermission('administer community records')) {
          return AccessResult::allowed();
        }
      }
    }

    return AccessResult::forbidden();
  }

  /**
   * Return the original record for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The content to find the original record for.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The original record node.
   */
  protected function getOriginalRecord(NodeInterface $node) {
    if (!$node->hasField('field_mukurtu_original_record')) {
      return NULL;
    }

    // If node is a CR, find the OR.
    $original = $node->get('field_mukurtu_original_record')->referencedEntities();
    if (isset($original[0])) {
      return $original[0];
    }

    // Here node is not a CR, so it is the assumed OR.
    return $node;
  }

  /**
   * Check if the user can create any valid CR content.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that the user is trying to create
   *   a community record from.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return bool
   *   The access result.
   */
  protected function checkCreateRecordTypes(NodeInterface $node, AccountInterface $account) {
    $accessHandler = $this->entityTypeManager()->getAccessControlHandler($node->getEntityTypeId());
    // @todo This needs to pull from the configured list of allowed CR types.
    // Right now it's assuming we want to use the same bundle as the original
    // record.
    if ($accessHandler->createAccess($node->bundle(), $account)) {
      return TRUE;
    }
    return FALSE;
  }

  public function createCommunityRecord(NodeInterface $node) {
    $originalRecord = $this->getOriginalRecord($node);
    return $this->createCommunityRecordofBundle($originalRecord, $originalRecord->bundle());
  }

  public function createCommunityRecordofBundle(NodeInterface $node, $bundle) {
    $originalRecord = $this->getOriginalRecord($node);

    // Create a new node form for the given bundle.
    $communityRecord = Node::create([
      'type' => $bundle,
      MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD => $originalRecord->id(),
    ]);

    // Get the node form.
    $form = $this->entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($communityRecord);

    $form_title = $this->t('Original Record');
    $build[] = ['#type' => 'markup', '#markup' => "<h2>$form_title</h2>"];
    $build[] = $this->entityTypeManager()->getViewBuilder('node')->view($originalRecord, 'browse');
    $build[] = $this->formBuilder()->getForm($form);


    // Don't show the original record field.
    $build[2][MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD]['#access'] = FALSE;

    // Hide the media assets field.
    $build[2]['field_media_assets']['#access'] = FALSE;

    return $build;
  }

}
