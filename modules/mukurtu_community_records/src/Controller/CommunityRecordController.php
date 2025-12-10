<?php

namespace Drupal\mukurtu_community_records\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
  public function access(NodeInterface $node, AccountInterface $account): AccessResultInterface {
    // Check if CRs are enabled site wide for this bundle.
    $cr_config = $this->config('mukurtu_community_records.settings');
    $allowed_bundles = $cr_config->get('allowed_community_record_bundles');
    if (!in_array($node->bundle(), $allowed_bundles)) {
      // Not enabled for community records.
      return AccessResult::forbidden();
    }

    // Node might be a CR, find the OR.
    $original_record = $this->getOriginalRecord($node);

    // Original record must support protocols in order to have community
    // records.
    if (!($original_record instanceof CulturalProtocolControlledInterface)) {
      return AccessResult::forbidden();
    }

    // Fail if the account can't view the original record.
    if (!$original_record->access('view', $account)) {
      return AccessResult::forbidden();
    }

    // Check for the community record admin permission in one of the
    // owning protocols.
    if ($protocols = $original_record->getProtocolEntities()) {
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
   * Return a render array for creating a community record of the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to create a community record for.
   *
   * @return array
   *   Render array for the community record creation form.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the original record cannot be found.
   */
  public function createCommunityRecord(NodeInterface $node): array {
    $original_record = $this->getOriginalRecord($node);
    if (!$original_record) {
      throw new NotFoundHttpException();
    }
    return $this->createCommunityRecordOfBundle($original_record, $original_record->bundle());
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
  protected function getOriginalRecord(NodeInterface $node): ?NodeInterface {
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
   * Creates a new community record node of specified bundle type.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node from which to create the community record.
   * @param string $bundle
   *   The bundle type for the new community record.
   *
   * @return array
   *   Render array containing:
   *   - Heading for original record section.
   *   - Browse view of original record.
   *   - Community record creation form, eg. the given $node's form.
   */
  protected function createCommunityRecordOfBundle(NodeInterface $node, string $bundle): array {
    $original_record = $this->getOriginalRecord($node);

    // Create a new node form for the given bundle.
    $communityRecord = Node::create([
      'type' => $bundle,
      MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD => $original_record->id(),
    ]);

    // Get the node form.
    $form = $this->entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($communityRecord);

    $heading = [
      '#type' => 'inline_template',
      '#template' => '<h2>{% trans %}Original Record{% endtrans %}</h2>',
    ];
    $original_record_browse = $this->entityTypeManager()->getViewBuilder('node')->view($original_record, 'browse');

    // Build the form. Note that the form alter takes care of hiding the
    // original record field and media assets field.
    // @see mukurtu_community_records_form_node_form_alter().
    $form_build = $this->formBuilder()->getForm($form);

    return [
      $heading,
      $original_record_browse,
      $form_build,
    ];
  }

}
